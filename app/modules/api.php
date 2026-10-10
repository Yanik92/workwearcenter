<?php
declare(strict_types=1);

/*
 * 10.8: WorkwearCenter API v1 – eine Schnittstelle für Auftragszentrale, Fan-Bande und Partner.
 *
 * Anmeldung: Authorization: Bearer wwc_<48 Hexzeichen>. Schlüssel werden unter Admin → System → API-Zugänge
 * angelegt, nur als Hash gespeichert und tragen Rechte (Scopes):
 *   orders:read, orders:write, customers:read, products:read
 * Schutz: HTTPS-Pflicht, Drosselung je Schlüssel (600 Anfragen / 10 Minuten), Sperre nach Fehlversuchen,
 * optionale IP-Bindung je Schlüssel, Protokoll der letzten Nutzung.
 * Dokumentation: API-DOKUMENTATION.md und openapi.yaml.
 */

const YW_API_SCOPES=['orders:read'=>'Bestellungen lesen','orders:write'=>'Produktionsstand und Quittungen schreiben','customers:read'=>'Kunden lesen','products:read'=>'Produkte lesen'];
const YW_API_RATE_LIMIT=600;      // Anfragen …
const YW_API_RATE_WINDOW=600;     // … je 10 Minuten

/** Aktuell authentifizierter Schlüssel (Zeile aus yw_api_keys) oder null. Wird einmal je Anfrage ermittelt. */
function yw_api_key_current(): ?array {
    static $resolved=false,$key=null;
    if($resolved)return $key;
    $resolved=true;
    $header=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
    if(!preg_match('/^Bearer\s+(wwc_[a-f0-9]{48})$/',$header,$m))return null;
    if(login_throttled('api-key',20))yw_api_json(429,['error'=>'too_many_requests','message'=>'Zu viele fehlgeschlagene Anmeldungen. Bitte 15 Minuten warten.']);
    $hash=hash('sha256',$m[1]);
    try{$q=db()->prepare('SELECT * FROM yw_api_keys WHERE key_hash=? AND active=1 LIMIT 1');$q->execute([$hash]);$row=$q->fetch();}catch(Throwable $e){$row=false;}
    if(!$row){record_login_failure('api-key');yw_api_json(401,['error'=>'unauthorized','message'=>'API-Schlüssel ungültig oder gesperrt.']);}
    $ip=(string)($_SERVER['REMOTE_ADDR']??'');
    $allowed=array_values(array_filter(array_map('trim',explode(',',(string)($row['ip_allowlist']??'')))));
    if($allowed && !in_array($ip,$allowed,true)){audit_log('api.ip_rejected','api_key',(int)$row['id'],['ip'=>$ip]);yw_api_json(403,['error'=>'ip_not_allowed','message'=>'Dieser Schlüssel ist auf andere IP-Adressen beschränkt.']);}
    yw_api_rate_check((int)$row['id']);
    try{db()->prepare('UPDATE yw_api_keys SET last_used_at=NOW(),last_ip=? WHERE id=?')->execute([mb_substr($ip,0,45),(int)$row['id']]);}catch(Throwable $e){}
    $key=$row;
    return $key;
}

/** Firma, auf die der aktuelle Schlüssel beschränkt ist, oder null (alle Firmen). */
function yw_api_company_scope(): ?int { $key=yw_api_key_current();$id=(int)($key['company_id']??0);return $id>0?$id:null; }
/** 404, wenn eine Bestellung nicht zur Firma des Schlüssels gehört. */
function yw_api_assert_order_scope(int $orderId): void {
    $scope=yw_api_company_scope();if($scope===null)return;
    $q=db()->prepare('SELECT company_id FROM orders WHERE id=?');$q->execute([$orderId]);
    if((int)$q->fetchColumn()!==$scope)yw_api_json(404,['error'=>'not_found']);
}

function yw_api_scopes(array $key): array { return array_values(array_filter(array_map('trim',explode(',',(string)($key['scopes']??''))))); }

/** Bricht mit 401/403 ab, wenn kein Schlüssel mit diesem Recht vorliegt. */
function yw_api_require_scope(string $scope): array {
    if(!yw_api_https_ok())yw_api_json(400,['error'=>'https_required','message'=>'Die API ist nur über HTTPS erreichbar.']);
    $key=yw_api_key_current();
    if($key===null){header('WWW-Authenticate: Bearer');yw_api_json(401,['error'=>'unauthorized','message'=>'API-Schlüssel erforderlich (Authorization: Bearer wwc_…).']);}
    if(!in_array($scope,yw_api_scopes($key),true))yw_api_json(403,['error'=>'forbidden','message'=>'Dem Schlüssel fehlt das Recht '.$scope.'.']);
    return $key;
}

function yw_api_https_ok(): bool {
    if(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')return true;
    if(strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO']??''))==='https')return true;
    return in_array((string)($_SERVER['REMOTE_ADDR']??''),['127.0.0.1','::1'],true); // lokale Tests
}

/** Einfache Drosselung je Schlüssel über ein Zeitfenster. */
function yw_api_rate_check(int $keyId): void {
    try{
        $window=date('Y-m-d H:i:s',intdiv(time(),YW_API_RATE_WINDOW)*YW_API_RATE_WINDOW);
        db()->prepare('INSERT INTO yw_api_rate(key_id,window_start,requests) VALUES(?,?,1) ON DUPLICATE KEY UPDATE requests=requests+1')->execute([$keyId,$window]);
        $q=db()->prepare('SELECT requests FROM yw_api_rate WHERE key_id=? AND window_start=?');$q->execute([$keyId,$window]);
        if((int)$q->fetchColumn()>YW_API_RATE_LIMIT){header('Retry-After: '.YW_API_RATE_WINDOW);yw_api_json(429,['error'=>'rate_limited','message'=>'Höchstens '.YW_API_RATE_LIMIT.' Anfragen je '.(int)(YW_API_RATE_WINDOW/60).' Minuten.']);}
        if(random_int(1,50)===1)db()->exec("DELETE FROM yw_api_rate WHERE window_start<DATE_SUB(NOW(),INTERVAL 1 DAY)");
    }catch(Throwable $e){}
}

/** Neuen Schlüssel erzeugen; gibt [Klartext, id] zurück. Der Klartext wird nur einmal angezeigt. */
function yw_api_key_create(string $name,string $system,array $scopes,string $ipAllowlist='',int $companyId=0): array {
    $name=trim($name);$system=trim($system);
    if($name===''||mb_strlen($name)>120)throw new RuntimeException('Bitte einen Namen für den Schlüssel angeben (max. 120 Zeichen).');
    if(!in_array($system,['auftragszentrale','fan-bande','partner','test'],true))throw new RuntimeException('Unbekanntes System.');
    $scopes=array_values(array_intersect($scopes,array_keys(YW_API_SCOPES)));
    if(!$scopes)throw new RuntimeException('Mindestens ein Recht auswählen.');
    $ips=[];foreach(array_filter(array_map('trim',explode(',',$ipAllowlist))) as $ip){if(filter_var($ip,FILTER_VALIDATE_IP)===false)throw new RuntimeException('Ungültige IP-Adresse: '.$ip);$ips[]=$ip;}
    if($companyId>0){$q=db()->prepare('SELECT id FROM companies WHERE id=?');$q->execute([$companyId]);if(!$q->fetchColumn())throw new RuntimeException('Firma nicht gefunden.');}
    if($system==='auftragszentrale'&&$companyId>0)throw new RuntimeException('Die Auftragszentrale braucht Zugriff auf alle Firmen.');
    $plain='wwc_'.bin2hex(random_bytes(24));
    db()->prepare('INSERT INTO yw_api_keys(name,`system`,key_hash,key_prefix,scopes,ip_allowlist,company_id,active,created_by) VALUES(?,?,?,?,?,?,?,1,?)')
        ->execute([$name,$system,hash('sha256',$plain),substr($plain,0,12),implode(',',$scopes),$ips?implode(',',$ips):null,$companyId>0?$companyId:null,(int)($_SESSION['admin_id']??0)]);
    $id=(int)db()->lastInsertId();
    audit_log('api.key_created','api_key',$id,['name'=>$name,'system'=>$system,'scopes'=>$scopes]);
    return [$plain,$id];
}

/* ---------- Datenabbildungen ---------- */

function yw_api_company(array $c): array {
    return [
        'id'=>(int)$c['id'],'slug'=>(string)$c['slug'],'legalName'=>(string)$c['legal_name'],'shopName'=>(string)$c['shop_name'],
        'active'=>(int)($c['active']??1)===1,'shopUrl'=>workwearcenter_url((string)$c['slug']),
        'delivery'=>['mode'=>(string)($c['delivery_mode']??'company'),'address'=>(string)($c['delivery_address']??'')],
        'financing'=>['mode'=>(string)($c['financing_mode']??'company'),'employeeSharePercent'=>(float)($c['employee_share_percent']??0)],
        'approvalRequired'=>(int)($c['approval_required']??1)===1,
        'createdAt'=>(new DateTimeImmutable((string)$c['created_at']))->format(DATE_ATOM),
    ];
}

function yw_api_product(array $p): array {
    $views=yw_product_views($p);$images=[];foreach($views as $view=>$path)$images[$view]=workwearcenter_url($path);
    return [
        'id'=>(int)$p['id'],'sku'=>(string)($p['sku']??''),'name'=>(string)$p['name'],'brand'=>(string)($p['brand']??''),'supplier'=>(string)($p['supplier_name']??''),
        'category'=>product_category_name((int)($p['category_id']??0),(string)($p['category']??'')),'color'=>(string)($p['color']??''),
        'sizes'=>json_decode((string)$p['sizes'],true)?:[],'netPriceCents'=>(int)$p['price_cents'],'vatRate'=>(float)($p['vat_rate']??19),
        'decorationType'=>(string)($p['decoration_type']??'none'),'active'=>(int)$p['active']===1,'images'=>$images,
    ];
}

/** Bestellung im Format der Übergabe (gleiche Felder wie das Ereignis order.approved) plus Produktions- und Versandstand. */
function yw_api_order(int $orderId): array {
    $payload=yw_handover_payload($orderId,'order.snapshot','');
    $q=db()->prepare('SELECT status,fulfillment_status,tracking_carrier,tracking_number,tracking_url,external_reference,production_updated_at FROM orders WHERE id=?');$q->execute([$orderId]);$o=$q->fetch();
    $payload['order']['approvalStatus']=(string)$o['status'];
    $payload['order']['productionStatus']=(string)($o['fulfillment_status']??'new');
    $payload['order']['productionUpdatedAt']=$o['production_updated_at']?(new DateTimeImmutable((string)$o['production_updated_at']))->format(DATE_ATOM):null;
    $payload['order']['tracking']=$o['tracking_number']?['carrier'=>(string)$o['tracking_carrier'],'number'=>(string)$o['tracking_number'],'url'=>yw_tracking_link((string)$o['tracking_carrier'],(string)$o['tracking_number'],(string)($o['tracking_url']??''))]:null;
    $payload['order']['externalReference']=$o['external_reference']?:null;
    return $payload;
}

/* ---------- Routing ---------- */

function yw_api_v1_route(string $path): bool {
    $method=(string)($_SERVER['REQUEST_METHOD']??'GET');
    $get=static fn(string $k,string $d=''):string=>is_scalar($_GET[$k]??null)?(string)$_GET[$k]:$d;

    if($path==='/api/v1/ping'){
        $key=yw_api_key_current();
        yw_api_json(200,['ok'=>true,'system'=>'WorkwearCenter','version'=>$key?app_version():null,'api'=>'v1','time'=>date(DATE_ATOM),'key'=>$key?['name'=>$key['name'],'system'=>$key['system'],'scopes'=>yw_api_scopes($key)]:null]);
    }
    if($path==='/api/v1/kunden'&&$method==='GET'){
        yw_api_require_scope('customers:read');
        $limit=max(1,min(200,(int)$get('limit','100')));$since=$get('seit');$where='1=1';$params=[];
        if($since!==''){$ts=strtotime($since);if($ts===false)yw_api_json(400,['error'=>'invalid_since','message'=>'seit muss ein Datum sein (ISO 8601).']);$where='created_at>=?';$params[]=date('Y-m-d H:i:s',$ts);}
        if($get('aktiv')==='1'){$where.=' AND active=1';}
        if(($scope=yw_api_company_scope())!==null){$where.=' AND id=?';$params[]=$scope;}
        $q=db()->prepare("SELECT * FROM companies WHERE $where ORDER BY id LIMIT $limit");$q->execute($params);
        yw_api_json(200,['count'=>$q->rowCount(),'data'=>array_map('yw_api_company',$q->fetchAll())]);
    }
    if(preg_match('#^/api/v1/kunden/(\d{1,10})$#',$path,$m)&&$method==='GET'){
        yw_api_require_scope('customers:read');
        $q=db()->prepare('SELECT * FROM companies WHERE id=?');$q->execute([(int)$m[1]]);$c=$q->fetch();
        if(!$c||(($scope=yw_api_company_scope())!==null&&(int)$c['id']!==$scope))yw_api_json(404,['error'=>'not_found']);
        $data=yw_api_company($c);
        $e=db()->prepare("SELECT COUNT(*) FROM employee_accounts WHERE company_id=? AND active=1 AND registration_status='approved' AND anonymized_at IS NULL");$e->execute([(int)$c['id']]);$data['employeeCount']=(int)$e->fetchColumn();
        $p=db()->prepare('SELECT p.* FROM company_products cp JOIN products p ON p.id=cp.product_id WHERE cp.company_id=? AND p.active=1 ORDER BY p.name');$p->execute([(int)$c['id']]);$data['collection']=array_map('yw_api_product',$p->fetchAll());
        yw_api_json(200,$data);
    }
    if($path==='/api/v1/produkte'&&$method==='GET'){
        yw_api_require_scope('products:read');
        $limit=max(1,min(500,(int)$get('limit','200')));$where=$get('aktiv')==='1'?'active=1':'1=1';
        $q=db()->query("SELECT * FROM products WHERE $where ORDER BY name LIMIT $limit");
        yw_api_json(200,['count'=>$q->rowCount(),'data'=>array_map('yw_api_product',$q->fetchAll())]);
    }
    if(preg_match('#^/api/v1/produkte/(\d{1,10})$#',$path,$m)&&$method==='GET'){
        yw_api_require_scope('products:read');
        $q=db()->prepare('SELECT * FROM products WHERE id=?');$q->execute([(int)$m[1]]);$p=$q->fetch();
        if(!$p)yw_api_json(404,['error'=>'not_found']);
        yw_api_json(200,yw_api_product($p));
    }
    if($path==='/api/v1/bestellungen'&&$method==='GET'){
        yw_api_require_scope('orders:read');
        $limit=max(1,min(100,(int)$get('limit','50')));$where=[];$params=[];
        $approval=$get('freigabe','approved');if(!in_array($approval,['approved','pending','rejected','cancelled','alle'],true))yw_api_json(400,['error'=>'invalid_filter','message'=>'freigabe muss approved, pending, rejected, cancelled oder alle sein.']);
        if($approval!=='alle'){$where[]='o.status=?';$params[]=$approval;}
        $prod=$get('produktion');if($prod!==''){if(!array_key_exists($prod,yw_production_statuses()))yw_api_json(400,['error'=>'invalid_filter','message'=>'produktion ungültig.']);$where[]='o.fulfillment_status=?';$params[]=$prod;}
        if(($kunde=(int)$get('kunde'))>0){$where[]='o.company_id=?';$params[]=$kunde;}
        if(($scope=yw_api_company_scope())!==null){$where[]='o.company_id=?';$params[]=$scope;}
        if(($since=$get('seit'))!==''){$ts=strtotime($since);if($ts===false)yw_api_json(400,['error'=>'invalid_since']);$where[]='GREATEST(o.created_at,COALESCE(o.decided_at,o.created_at),COALESCE(o.production_updated_at,o.created_at))>=?';$params[]=date('Y-m-d H:i:s',$ts);}
        if(($after=(int)$get('nach'))>0){$where[]='o.id>?';$params[]=$after;}
        $sql='SELECT o.id FROM orders o'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY o.id LIMIT '.$limit;
        $q=db()->prepare($sql);$q->execute($params);$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        $data=[];foreach($ids as $id)$data[]=yw_api_order($id);
        yw_api_json(200,['count'=>count($data),'next'=>$ids?('/api/v1/bestellungen?nach='.end($ids).'&limit='.$limit.($approval!=='approved'?'&freigabe='.$approval:'')):null,'data'=>$data]);
    }
    if(preg_match('#^/api/v1/bestellungen/(?:WWC-?)?0*(\d{1,10})$#i',$path,$m)&&$method==='GET'){
        yw_api_require_scope('orders:read');
        $q=db()->prepare('SELECT id FROM orders WHERE id=?');$q->execute([(int)$m[1]]);if(!$q->fetchColumn())yw_api_json(404,['error'=>'not_found']);
        yw_api_assert_order_scope((int)$m[1]);
        yw_api_json(200,yw_api_order((int)$m[1]));
    }
    if(preg_match('#^/api/v1/bestellungen/(?:WWC-?)?0*(\d{1,10})/status$#i',$path,$m)&&$method==='POST'){
        $key=yw_api_require_scope('orders:write');yw_api_assert_order_scope((int)$m[1]);
        $body=(string)file_get_contents('php://input',false,null,0,65537);
        $data=json_decode($body,true);if(!is_array($data))yw_api_json(400,['error'=>'invalid_json']);
        yw_api_json(200,yw_status_apply((int)$m[1],$data,'api:'.$key['system']));
    }
    if(preg_match('#^/api/v1/bestellungen/(?:WWC-?)?0*(\d{1,10})/quittung$#i',$path,$m)&&$method==='POST'){
        $key=yw_api_require_scope('orders:write');yw_api_assert_order_scope((int)$m[1]);
        $raw=(string)file_get_contents('php://input',false,null,0,10000);$body=$raw!==''?json_decode($raw,true):[];
        if(!is_array($body))yw_api_json(400,['error'=>'invalid_json']);
        $reference=isset($body['reference'])&&is_scalar($body['reference'])?mb_substr(trim((string)$body['reference']),0,120):null;if($reference==='')$reference=null;
        $q=db()->prepare("SELECT id FROM orders WHERE id=? AND status='approved'");$q->execute([(int)$m[1]]);if(!$q->fetchColumn())yw_api_json(404,['error'=>'not_found','message'=>'Keine freigegebene Bestellung mit dieser Nummer.']);
        db()->prepare("UPDATE yw_handover SET status='acknowledged',acknowledged_at=COALESCE(acknowledged_at,NOW()),external_reference=COALESCE(?,external_reference),last_error=NULL WHERE order_id=? AND event='order.approved'")->execute([$reference,(int)$m[1]]);
        if($reference!==null)db()->prepare('UPDATE orders SET external_reference=? WHERE id=?')->execute([$reference,(int)$m[1]]);
        audit_log('handover.acknowledged','order',(int)$m[1],['reference'=>$reference,'via'=>'api:'.$key['system']]);
        yw_api_json(200,['ok'=>true,'order'=>yw_order_number((int)$m[1]),'externalReference'=>$reference]);
    }
    if(str_starts_with($path,'/api/v1/')&&!in_array($path,['/api/v1/auftraege'],true)&&!preg_match('#^/api/v1/auftraege/#',$path)){
        yw_api_json(404,['error'=>'not_found','message'=>'Unbekannter Endpunkt. Dokumentation: API-DOKUMENTATION.md']);
    }
    return false;
}

/* ---------- Admin: API-Zugänge ---------- */

function yw_admin_api_keys_action(string $action): void {
    if($action==='api_key_create'){
        $scopes=array_map('strval',(array)($_POST['scopes']??[]));
        [$plain,$id]=yw_api_key_create((string)($_POST['name']??''),(string)($_POST['system']??''),$scopes,(string)($_POST['ip_allowlist']??''),(int)($_POST['company_id']??0));
        $_SESSION['api_key_once']=$plain;
        flash('success','API-Schlüssel wurde angelegt. Bitte jetzt kopieren – er wird nur einmal angezeigt.');redirect('/admin?tab=api');
    }
    if($action==='api_key_toggle'){
        $id=(int)($_POST['key_id']??0);$stmt=db()->prepare('UPDATE yw_api_keys SET active=IF(active=1,0,1) WHERE id=?');$stmt->execute([$id]);
        if(!$stmt->rowCount())throw new RuntimeException('Schlüssel nicht gefunden.');
        audit_log('api.key_toggled','api_key',$id);flash('success','Schlüssel wurde geändert.');redirect('/admin?tab=api');
    }
    if($action==='api_key_delete'){
        $id=(int)($_POST['key_id']??0);db()->prepare('DELETE FROM yw_api_keys WHERE id=?')->execute([$id]);
        audit_log('api.key_deleted','api_key',$id);flash('success','Schlüssel wurde gelöscht.');redirect('/admin?tab=api');
    }
}

function yw_admin_api_page(): void {
    $once=(string)($_SESSION['api_key_once']??'');unset($_SESSION['api_key_once']);
    if($once!=='')echo '<section class="card api-key-once"><h2>Neuer API-Schlüssel</h2><p>Diesen Schlüssel jetzt in das andere System eintragen. Er wird <strong>nicht noch einmal angezeigt</strong>.</p><div class="copy">'.e($once).'</div><button type="button" class="btn small" data-copy="'.e($once).'">Schlüssel kopieren</button></section>';
    $keys=db()->query('SELECT k.*,a.name admin_name,c.shop_name company_name FROM yw_api_keys k LEFT JOIN admins a ON a.id=k.created_by LEFT JOIN companies c ON c.id=k.company_id ORDER BY k.id DESC')->fetchAll();
    echo '<section class="card"><div class="title-row"><div><h2>API-Zugänge</h2><p class="muted">Jedes angebundene System bekommt einen eigenen Schlüssel mit genau den Rechten, die es braucht. Adresse: <code>'.e(workwearcenter_url('/api/v1/')).'</code> · Beschreibung: API-DOKUMENTATION.md im Paket.</p></div></div>';
    if(!$keys)echo '<div class="empty compact">Noch kein API-Schlüssel angelegt.</div>';
    else{
        echo '<div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>System</th><th>Schlüssel</th><th>Rechte</th><th>Zuletzt genutzt</th><th>Status</th><th></th></tr></thead><tbody>';
        foreach($keys as $k){
            echo '<tr><td><strong>'.e($k['name']).'</strong><br><span class="muted">angelegt '.e(date('d.m.Y',strtotime((string)$k['created_at']))).($k['admin_name']?' von '.e($k['admin_name']):'').'</span></td><td>'.e($k['system']).'</td><td><code>'.e($k['key_prefix']).'…</code>'.($k['ip_allowlist']?'<br><span class="muted">nur '.e($k['ip_allowlist']).'</span>':'').'</td><td>'.e(str_replace(',',', ',(string)$k['scopes'])).'<br><span class="muted">'.($k['company_name']?'nur '.e((string)$k['company_name']):'alle Firmen').'</span></td><td>'.($k['last_used_at']?e(date('d.m.Y H:i',strtotime((string)$k['last_used_at']))).'<br><span class="muted">'.e((string)$k['last_ip']).'</span>':'<span class="muted">nie</span>').'</td><td><span class="badge '.((int)$k['active']?'approved':'rejected').'">'.((int)$k['active']?'aktiv':'gesperrt').'</span></td>';
            echo '<td class="actions"><form method="post" class="inline-form">'.csrf_field().'<input type="hidden" name="action" value="api_key_toggle"><input type="hidden" name="key_id" value="'.(int)$k['id'].'"><button class="btn small">'.((int)$k['active']?'Sperren':'Freigeben').'</button></form> <form method="post" class="inline-form" data-confirm="Schlüssel „'.e($k['name']).'“ endgültig löschen? Das angebundene System verliert sofort den Zugriff.">'.csrf_field().'<input type="hidden" name="action" value="api_key_delete"><input type="hidden" name="key_id" value="'.(int)$k['id'].'"><button class="btn small danger">Löschen</button></form></td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';
    echo '<section class="card"><h2>Neuen Schlüssel anlegen</h2><form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="api_key_create">';
    echo '<div class="grid2"><label>Name<input name="name" maxlength="120" required placeholder="z. B. Auftragszentrale Produktion"></label><label>System<select name="system"><option value="auftragszentrale">Auftragszentrale</option><option value="fan-bande">Fan-Bande</option><option value="partner">Partner / Sonstiges</option><option value="test">Test</option></select></label></div>';
    echo '<fieldset><legend>Rechte</legend><div class="checkboxes">';
    foreach(YW_API_SCOPES as $scope=>$label)echo '<label class="check"><input type="checkbox" name="scopes[]" value="'.e($scope).'" '.(in_array($scope,['orders:read','orders:write'],true)?'checked':'').'><span><strong>'.e($scope).'</strong> – '.e($label).'</span></label>';
    echo '</div></fieldset>';
    $companies=db()->query('SELECT id,shop_name FROM companies ORDER BY shop_name')->fetchAll();
    echo '<label>Zugriff auf <small>Partner und Fan-Bande bitte immer auf eine Firma beschränken</small><select name="company_id"><option value="0">alle Firmen (nur Auftragszentrale)</option>';
    foreach($companies as $c)echo '<option value="'.(int)$c['id'].'">nur '.e((string)$c['shop_name']).'</option>';
    echo '</select></label>';
    echo '<label>Erlaubte IP-Adressen <small>optional, durch Komma getrennt – leer = alle</small><input name="ip_allowlist" placeholder="z. B. 203.0.113.10"></label>';
    echo '<button class="btn primary">Schlüssel erzeugen</button></form></section>';
    echo '<section class="card"><h2>Ausgehende Übergabe (Webhook)</h2><p class="muted">Freigegebene Bestellungen werden weiterhin aktiv an die Auftragszentrale gesendet (Admin → System → Übergabe). Die API hier ist der Weg für Abfragen und Rückmeldungen.</p></section>';
}
