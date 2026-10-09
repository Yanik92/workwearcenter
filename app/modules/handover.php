<?php
declare(strict_types=1);

/*
 * Übergabe freigegebener Bestellungen an die Auftragsverwaltung.
 *
 * Grundsatz: Jede freigegebene Bestellung erzeugt genau einen unveränderlichen
 * Übergabedatensatz (yw_handover). Der Datensatz entsteht immer – auch wenn die
 * Schnittstelle noch ausgeschaltet ist –, damit nach dem Einschalten nichts fehlt.
 *
 * Zwei Wege, einzeln oder gemeinsam nutzbar (config.php → handover_mode):
 *  - push: WorkwearCenter sendet den Datensatz per HTTPS-POST an handover_url,
 *          signiert mit HMAC-SHA256 (handover_secret), mit automatischen Wiederholungen.
 *  - pull: Die Auftragsverwaltung ruft /api/v1/auftraege ab (Bearer-Token
 *          handover_api_token) und quittiert jeden Auftrag nach Übernahme.
 * Details und Beispielempfänger: SCHNITTSTELLE-AUFTRAGSVERWALTUNG.md
 */

const YW_HANDOVER_SCHEMA = 'workwearcenter.order.v1';
const YW_HANDOVER_MAX_ATTEMPTS = 10;

function yw_order_number(int $orderId): string {
    return 'WWC-'.str_pad((string)$orderId,6,'0',STR_PAD_LEFT);
}

function yw_handover_config(): array {
    $mode=strtolower(trim((string)(cfg('handover_mode')?:'off')));
    if(!in_array($mode,['off','push','pull','both'],true))$mode='off';
    return [
        'mode'=>$mode,
        'url'=>trim((string)(cfg('handover_url')?:'')),
        'secret'=>(string)(cfg('handover_secret')?:''),
        'api_token'=>(string)(cfg('handover_api_token')?:''),
        // 'workwearcenter' = eigenes Schema v1 (X-WWC-*), 'auftragszentrale' = Protokoll der
        // Yanik Auftragszentrale (channel.php, X-Yanik-*, Ereignis order.upsert)
        'format'=>strtolower(trim((string)(cfg('handover_format')?:'workwearcenter')))==='auftragszentrale'?'auftragszentrale':'workwearcenter',
    ];
}

function yw_handover_push_enabled(): bool { return in_array(yw_handover_config()['mode'],['push','both'],true); }
function yw_handover_pull_enabled(): bool { return in_array(yw_handover_config()['mode'],['pull','both'],true); }

/** Liefert Konfigurationsprobleme als lesbare Liste (ohne Geheimnisse preiszugeben). */
function yw_handover_config_issues(): array {
    $c=yw_handover_config();$issues=[];
    if($c['mode']==='off')return [];
    if(in_array($c['mode'],['push','both'],true)){
        $parts=parse_url($c['url'])?:[];
        $scheme=strtolower((string)($parts['scheme']??''));$host=strtolower((string)($parts['host']??''));
        $local=in_array($host,['localhost','127.0.0.1','::1'],true);
        if($c['url']===''||!filter_var($c['url'],FILTER_VALIDATE_URL))$issues[]='handover_url fehlt oder ist keine gültige Adresse';
        elseif($scheme!=='https' && !($scheme==='http' && $local))$issues[]='handover_url muss mit https:// beginnen (http nur für localhost)';
        if(strlen($c['secret'])<32)$issues[]='handover_secret muss mindestens 32 Zeichen lang sein';
    }
    if(in_array($c['mode'],['pull','both'],true) && strlen($c['api_token'])<32)$issues[]='handover_api_token muss mindestens 32 Zeichen lang sein';
    return $issues;
}

/** Baut den vollständigen, eigenständigen Auftragsdatensatz (Schema v1). */
function yw_handover_payload(int $orderId,string $event='order.approved',string $deliveryId=''): array {
    $q=db()->prepare("SELECT o.*,c.slug,c.legal_name,c.shop_name,c.delivery_mode company_delivery_mode,c.delivery_address company_delivery_address
        FROM orders o JOIN companies c ON c.id=o.company_id WHERE o.id=? LIMIT 1");
    $q->execute([$orderId]);$order=$q->fetch();
    if(!$order)throw new RuntimeException('Bestellung '.$orderId.' nicht gefunden.');

    $employee=null;
    if(!empty($order['employee_account_id'])){
        $q=db()->prepare('SELECT id,name,email,department,cost_center,location,employee_number FROM employee_accounts WHERE id=? LIMIT 1');
        $q->execute([(int)$order['employee_account_id']]);$employee=$q->fetch()?:null;
    }
    $approver=null;
    if(!empty($order['decided_by_account_id'])){
        $q=db()->prepare('SELECT id,name,email FROM employee_accounts WHERE id=? LIMIT 1');
        $q->execute([(int)$order['decided_by_account_id']]);$approver=$q->fetch()?:null;
    }
    $delivery=json_decode((string)($order['delivery_snapshot_json']??''),true)?:[];
    $deliveryMode=(string)($delivery['mode']??$order['company_delivery_mode']??'company');
    $address=(string)($delivery['address']??$order['company_delivery_address']??'');
    $addressLines=array_values(array_filter(array_map('trim',preg_split('/\R/u',$address)?:[]),fn($l)=>$l!==''));

    $motifs=[];
    try{$m=db()->prepare('SELECT id,name,method,file_path FROM yw_decoration_motifs WHERE company_id=?');$m->execute([(int)$order['company_id']]);foreach($m as $row)$motifs[(int)$row['id']]=$row;}catch(Throwable $ignored){}

    $items=[];$line=0;
    foreach(order_items($orderId) as $item){
        $line++;
        $snap=json_decode((string)($item['decoration_snapshot_json']??''),true)?:[];
        $decorations=[];
        foreach((is_array($snap['decorations']??null)?$snap['decorations']:[]) as $d){
            if(!is_array($d))continue;
            $motifId=(int)($d['motif_id']??0);
            $decorations[]=[
                'position'=>(string)($d['position']??''),
                'position_label'=>decoration_position_label((string)($d['position']??'')),
                'method'=>(string)($d['method']??''),
                'method_label'=>decoration_method_label((string)($d['method']??'')),
                'text'=>(string)($d['text']??''),
                'motif_id'=>$motifId?:null,
                'motif_name'=>$motifId&&isset($motifs[$motifId])?(string)$motifs[$motifId]['name']:null,
                // 10.6 (nur ergänzt): Logo-Datei und Größenstufe
                'motif_file'=>$motifId&&isset($motifs[$motifId])&&yw_motif_file_path($motifs[$motifId])!==''?workwearcenter_url(yw_motif_file_path($motifs[$motifId])):null,
                'size'=>(string)($d['size']??'m'),
            ];
        }
        $unitNet=(int)($item['unit_net_cents']??0);if($unitNet<=0)$unitNet=(int)$item['unit_price_cents'];
        $unitGross=(int)($item['unit_gross_cents']??0);
        $qty=(int)$item['quantity'];
        $items[]=[
            'line'=>$line,
            'product_id'=>(int)$item['product_id'],
            'sku'=>isset($snap['sku'])&&$snap['sku']!==''?(string)$snap['sku']:null,
            'name'=>(string)$item['product_name'],
            'description'=>(string)($snap['description']??''),
            'color'=>(string)($snap['color']??''),
            'size'=>(string)$item['size'],
            'quantity'=>$qty,
            'vat_rate'=>(float)($item['vat_rate']??19),
            'unit_net_cents'=>$unitNet,
            'unit_gross_cents'=>$unitGross,
            'line_net_cents'=>$unitNet*$qty,
            'line_gross_cents'=>$unitGross*$qty,
            'employee_share_cents'=>(int)($item['employee_share_cents']??0)*$qty,
            'company_share_cents'=>(int)($item['company_share_cents']??0)*$qty,
            'decorations'=>$decorations,
            'base_decoration'=>['type'=>(string)($snap['base_decoration_type']??''),'details'=>(string)($snap['base_decoration_details']??'')],
        ];
    }

    $iso=static fn($value)=>$value?(new DateTimeImmutable((string)$value))->format(DATE_ATOM):null;
    return [
        'schema'=>YW_HANDOVER_SCHEMA,
        'event'=>$event,
        'delivery_id'=>$deliveryId,
        'generated_at'=>(new DateTimeImmutable())->format(DATE_ATOM),
        'source'=>['system'=>'WorkwearCenter','version'=>app_version(),'url'=>workwearcenter_url()],
        'order'=>[
            'id'=>$orderId,
            'number'=>yw_order_number($orderId),
            'status'=>(string)$order['status'],
            'created_at'=>$iso($order['created_at']),
            'approved_at'=>$iso($order['decided_at']?:$order['created_at']),
            'approved_by'=>$approver?['id'=>(int)$approver['id'],'name'=>(string)$approver['name'],'email'=>(string)$approver['email']]:null,
            'approval_type'=>$approver?'manager':'automatic',
            'note'=>(string)($order['note']??''),
            'currency'=>'EUR',
            'totals'=>[
                'net_cents'=>(int)($order['net_total_cents']??$order['total_cents']),
                'gross_cents'=>(int)($order['gross_total_cents']??0),
                'employee_share_cents'=>(int)($order['employee_total_cents']??0),
                'company_share_cents'=>(int)($order['company_total_cents']??0),
                'shipping'=>[
                    'net_cents'=>(int)($order['shipping_net_cents']??0),
                    'gross_cents'=>(int)($order['shipping_gross_cents']??0),
                    'employee_cents'=>(int)($order['shipping_employee_cents']??0),
                    'company_cents'=>(int)($order['shipping_company_cents']??0),
                ],
            ],
        ],
        'customer'=>[
            'company_id'=>(int)$order['company_id'],
            'slug'=>(string)$order['slug'],
            'legal_name'=>(string)$order['legal_name'],
            'shop_name'=>(string)$order['shop_name'],
        ],
        'employee'=>$employee?[
            'id'=>(int)$employee['id'],
            'name'=>(string)$employee['name'],
            'email'=>(string)$employee['email'],
            'department'=>(string)($employee['department']??''),
            'cost_center'=>(string)($employee['cost_center']??''),
            'location'=>(string)($employee['location']??''),
            'employee_number'=>(string)($employee['employee_number']??''),
        ]:['id'=>null,'name'=>(string)($delivery['employee']??''),'email'=>null,'department'=>(string)($delivery['department']??''),'cost_center'=>(string)($delivery['cost_center']??''),'location'=>'','employee_number'=>''],
        'delivery'=>[
            'mode'=>$deliveryMode==='pickup'?'pickup':'delivery',
            'company'=>(string)($delivery['company']??$order['legal_name']),
            'address_lines'=>$deliveryMode==='pickup'?[]:$addressLines,
        ],
        'items'=>$items,
    ];
}

/**
 * Legt den Übergabedatensatz an. Darf den Bestellablauf nie abbrechen:
 * Fehler werden protokolliert; der Systemstatus zeigt fehlende Übergaben an.
 */
function yw_handover_enqueue(int $orderId): void {
    try{
        $deliveryId=bin2hex(random_bytes(16));
        $payload=yw_handover_payload($orderId,'order.approved',$deliveryId);
        $json=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $q=db()->prepare("INSERT IGNORE INTO yw_handover(order_id,event,delivery_id,payload_json,status) VALUES(?,?,?,?,'pending')");
        $q->execute([$orderId,'order.approved',$deliveryId,$json]);
    }catch(Throwable $e){
        error_log('WorkwearCenter handover enqueue #'.$orderId.': '.$e->getMessage());
    }
}

/** Freigegebene Bestellungen ohne Übergabedatensatz (z. B. aus der Zeit vor Version 10). */
function yw_handover_missing_count(): int {
    try{return (int)db()->query("SELECT COUNT(*) FROM orders o LEFT JOIN yw_handover h ON h.order_id=o.id AND h.event='order.approved' WHERE o.status='approved' AND h.id IS NULL")->fetchColumn();}
    catch(Throwable $e){return 0;}
}

function yw_handover_signature(string $secret,string $timestamp,string $body): string {
    return 'sha256='.hash_hmac('sha256',$timestamp.'.'.$body,$secret);
}

/** @return array{0:int,1:string,2:string} HTTP-Status, Antworttext (gekürzt), Fehlertext */
function yw_handover_http_post(string $url,string $body,array $headers,int $timeout=10): array {
    if(function_exists('curl_init')){
        $ch=curl_init($url);
        curl_setopt_array($ch,[
            CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>$timeout,CURLOPT_CONNECTTIMEOUT=>5,
            CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS|CURLPROTO_HTTP,
        ]);
        $response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=$response===false?curl_error($ch):'';
        curl_close($ch);
        return [$status,is_string($response)?mb_substr($response,0,4000):'',$error];
    }
    $context=stream_context_create(['http'=>['method'=>'POST','header'=>implode("\r\n",$headers),'content'=>$body,'timeout'=>$timeout,'ignore_errors'=>true,'follow_location'=>0],'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
    $response=@file_get_contents($url,false,$context);
    $status=0;
    foreach(($http_response_header??[]) as $h)if(preg_match('#^HTTP/\S+\s+(\d{3})#',$h,$m))$status=(int)$m[1];
    return [$status,is_string($response)?mb_substr($response,0,4000):'',$response===false?'Verbindung fehlgeschlagen':''];
}

/**
 * Übersetzt den WorkwearCenter-Datensatz (Schema v1) in das Bestellformat der
 * Yanik Auftragszentrale (channel.php, Ereignis order.upsert).
 */
function yw_handover_to_auftragszentrale(array $v1): array {
    $order=$v1['order']??[];$customer=$v1['customer']??[];$employee=$v1['employee']??[];$delivery=$v1['delivery']??[];
    $lines=[];
    foreach(($v1['items']??[]) as $item){
        $decorations=[];
        foreach(($item['decorations']??[]) as $d){
            $position=(string)($d['position_label']??$d['position']??'');
            $text=trim((string)($d['text']??''));$motif=trim((string)($d['motif_name']??''));
            $decorations[]=['method'=>(string)($d['method_label']??$d['method']??'Veredelung'),'position'=>$position.($text!==''?': '.$text:'').($motif!==''?' (Motiv: '.$motif.')':'')];
        }
        if(!$decorations && ($item['base_decoration']['type']??'')!=='' && ($item['base_decoration']['type']??'')!=='none'){
            $decorations[]=['method'=>'Veredelung','position'=>trim((string)($item['base_decoration']['details']??'laut Produkt'))?:'laut Produkt'];
        }
        $lines[]=[
            'article'=>(string)$item['name'].(!empty($item['sku'])?' ('.$item['sku'].')':''),
            'sku'=>$item['sku']??null,
            'quantity'=>(int)$item['quantity'],
            'color'=>(string)($item['color']??''),
            'size'=>(string)($item['size']??''),
            'unitNet'=>(int)($item['unit_net_cents']??0),
            'unitGross'=>(int)($item['unit_gross_cents']??0),
            'vatRate'=>(float)($item['vat_rate']??19),
            'decorations'=>$decorations,
        ];
    }
    $money=static fn($cents)=>number_format(((int)$cents)/100,2,',','.').' €';
    $totals=$order['totals']??[];
    $notes=[];
    $notes[]='WorkwearCenter-Bestellung '.($order['number']??'').' · '.($customer['shop_name']??'');
    $notes[]='Mitarbeiter: '.($employee['name']??'').(!empty($employee['employee_number'])?' (Pers.-Nr. '.$employee['employee_number'].')':'');
    if(!empty($employee['department']))$notes[]='Abteilung: '.$employee['department'];
    if(!empty($employee['cost_center']))$notes[]='Kostenstelle: '.$employee['cost_center'];
    if(!empty($employee['location']))$notes[]='Standort: '.$employee['location'];
    $notes[]='Freigabe: '.(!empty($order['approved_by']['name'])?$order['approved_by']['name']:'automatisch').(!empty($order['approved_at'])?' am '.date('d.m.Y H:i',strtotime((string)$order['approved_at'])):'');
    $notes[]=($delivery['mode']??'delivery')==='pickup'?'Lieferung: Abholung bei Yanik Textildruck':'Lieferadresse: '.implode(', ',$delivery['address_lines']??[]);
    $notes[]='Summe brutto '.$money($totals['gross_cents']??0).' · Firma '.$money($totals['company_share_cents']??0).' · Mitarbeiter-Eigenanteil '.$money($totals['employee_share_cents']??0).(((int)($totals['shipping']['gross_cents']??0))>0?' · inkl. Versand '.$money($totals['shipping']['gross_cents']):'');
    if(trim((string)($order['note']??''))!=='')$notes[]='Hinweis des Mitarbeiters: '.trim((string)$order['note']);
    return [
        'event'=>'order.upsert',
        'order'=>[
            'id'=>(string)($order['number']??''),
            'revision'=>'1',
            'state'=>'approved',
            'title'=>mb_substr('WorkwearCenter · '.($customer['legal_name']??'').' · '.($employee['name']??''),0,200),
            'customer'=>['name'=>(string)($customer['legal_name']??''),'email'=>filter_var($employee['email']??'',FILTER_VALIDATE_EMAIL)?(string)$employee['email']:''],
            'source'=>['approvalStatus'=>'approved','companyName'=>(string)($customer['legal_name']??''),'employeeName'=>(string)($employee['name']??''),'shopId'=>(string)($customer['slug']??''),'shopName'=>(string)($customer['shop_name']??''),'costCenter'=>(string)($employee['cost_center']??''),'department'=>(string)($employee['department']??'')],
            'payment'=>['status'=>'invoice'],
            'totals'=>['net'=>(int)($totals['net_cents']??0),'gross'=>(int)($totals['gross_cents']??0),'companyShare'=>(int)($totals['company_share_cents']??0),'employeeShare'=>(int)($totals['employee_share_cents']??0)],
            'delivery'=>$delivery,
            'notes'=>implode("\n",$notes),
            'lines'=>$lines,
            'workwearcenter'=>$v1,
        ],
    ];
}

/** Sendet einen JSON-Text signiert an die Auftragsverwaltung. */
function yw_handover_send(string $event,string $deliveryId,string $json): array {
    $c=yw_handover_config();
    if($issues=yw_handover_config_issues())return [0,'','Konfiguration: '.$issues[0]];
    if($c['format']==='auftragszentrale'){
        $body=$event==='test'
            ? json_encode(['event'=>'ping'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
            : json_encode(yw_handover_to_auftragszentrale(json_decode($json,true)?:[]),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $parts=parse_url($c['url']);
        $target=(string)($parts['path']??'/').(isset($parts['query'])&&$parts['query']!==''?'?'.$parts['query']:'');
        $time=(string)(int)floor(microtime(true)*1000);
        $nonce=$deliveryId.'.'.bin2hex(random_bytes(8));
        $signature=hash_hmac('sha256',$time."\n".$nonce."\nPOST\n".$target."\n".$body,$c['secret']);
        return yw_handover_http_post($c['url'],(string)$body,[
            'Content-Type: application/json; charset=utf-8','Accept: application/json','User-Agent: WorkwearCenter/'.app_version(),
            'X-Yanik-Channel: workwearcenter','X-Yanik-Time: '.$time,'X-Yanik-Nonce: '.$nonce,'X-Yanik-Signature: '.$signature,
        ]);
    }
    $timestamp=(string)time();
    $headers=[
        'Content-Type: application/json; charset=utf-8',
        'Accept: application/json',
        'User-Agent: WorkwearCenter/'.app_version(),
        'X-WWC-Event: '.$event,
        'X-WWC-Delivery: '.$deliveryId,
        'X-WWC-Timestamp: '.$timestamp,
        'X-WWC-Signature: '.yw_handover_signature($c['secret'],$timestamp,$json),
    ];
    return yw_handover_http_post($c['url'],$json,$headers);
}

function yw_handover_deliver(int $id): bool {
    $claim=db()->prepare("UPDATE yw_handover SET status='sending',available_at=DATE_ADD(NOW(),INTERVAL 10 MINUTE) WHERE id=? AND status IN ('pending','failed') AND available_at<=NOW()");
    $claim->execute([$id]);
    if(!$claim->rowCount())return false;
    $q=db()->prepare('SELECT * FROM yw_handover WHERE id=?');$q->execute([$id]);$row=$q->fetch();
    if(!$row)return false;
    [$status,$response,$error]=yw_handover_send((string)$row['event'],(string)$row['delivery_id'],(string)$row['payload_json']);
    if($status>=200 && $status<300){
        $reference=null;
        $decoded=json_decode($response,true);
        if(is_array($decoded)){
            $candidate=$decoded['reference']??$decoded['orderNo']??$decoded['auftragsnummer']??$decoded['id']??null;
            if(is_scalar($candidate) && ($candidate=trim((string)$candidate))!=='')$reference=mb_substr($candidate,0,120);
        }
        db()->prepare("UPDATE yw_handover SET status='delivered',attempts=attempts+1,delivered_at=NOW(),last_http_status=?,last_error=NULL,external_reference=COALESCE(?,external_reference) WHERE id=?")->execute([$status,$reference,$id]);
        if($reference!==null)db()->prepare('UPDATE orders SET external_reference=? WHERE id=?')->execute([$reference,(int)$row['order_id']]);
        return true;
    }
    $attempts=(int)$row['attempts']+1;
    $delays=[1,5,15,30,60,120,240,480,720,1440];
    $finalFailure=$attempts>=YW_HANDOVER_MAX_ATTEMPTS;
    $next=(new DateTimeImmutable())->modify('+'.$delays[min(count($delays)-1,$attempts-1)].' minutes')->format('Y-m-d H:i:s');
    $message=$error!==''?$error:('HTTP '.$status.($response!==''?': '.preg_replace('/\s+/',' ',strip_tags($response)):''));
    db()->prepare('UPDATE yw_handover SET status=?,attempts=?,available_at=?,last_http_status=?,last_error=? WHERE id=?')
        ->execute([$finalFailure?'failed':'pending',$attempts,$next,$status?:null,mb_substr($message,0,500),$id]);
    if($finalFailure)yw_handover_alert_admins((int)$row['order_id'],$message);
    return false;
}

function yw_handover_alert_admins(int $orderId,string $message): void {
    try{
        foreach(db()->query('SELECT email FROM admins WHERE active=1') as $admin){
            send_system_mail((string)$admin['email'],'Übergabe fehlgeschlagen: '.yw_order_number($orderId),"Die Bestellung ".yw_order_number($orderId)." konnte nach ".YW_HANDOVER_MAX_ATTEMPTS." Versuchen nicht an die Auftragsverwaltung übergeben werden.\n\nLetzter Fehler: ".$message."\n\nIm Admin unter „Übergabe“ kannst du den Auftrag nach der Fehlerbehebung erneut senden.\n\nÜbergabe öffnen: ".workwearcenter_url('admin?tab=handover'));
        }
    }catch(Throwable $e){error_log('Handover alert: '.$e->getMessage());}
}

function yw_process_handover_queue(int $limit=5): int {
    if(!yw_handover_push_enabled() || yw_handover_config_issues())return 0;
    $limit=max(1,min(100,$limit));
    $locked=(int)db()->query("SELECT GET_LOCK('yanik_workwear_handover_worker',0)")->fetchColumn()===1;
    if(!$locked)return 0;
    $sent=0;
    try{
        db()->exec("UPDATE yw_handover SET status='pending' WHERE status='sending' AND available_at<=NOW()");
        $ids=db()->query("SELECT id FROM yw_handover WHERE status='pending' AND available_at<=NOW() ORDER BY id LIMIT ".$limit)->fetchAll(PDO::FETCH_COLUMN);
        foreach($ids as $id)if(yw_handover_deliver((int)$id))$sent++;
    }finally{
        try{db()->query("SELECT RELEASE_LOCK('yanik_workwear_handover_worker')");}catch(Throwable $ignored){}
    }
    return $sent;
}

/* --------------------------------------------------------------------------
   Pull-API für die Auftragsverwaltung
   -------------------------------------------------------------------------- */

function yw_api_json(int $status,array $data): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Robots-Tag: noindex');
    echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
    exit;
}

function yw_api_authenticate(): void {
    // Ab 10.8: API-Schlüssel (Admin → System → API-Zugänge) gelten zusätzlich zum alten handover_api_token.
    if(yw_api_key_current()!==null){yw_api_require_scope('orders:write');return;}
    $c=yw_handover_config();
    if(!yw_handover_pull_enabled() || strlen($c['api_token'])<32)yw_api_json(404,['error'=>'not_enabled','message'=>'Die Abruf-Schnittstelle ist nicht aktiviert.']);
    if(login_throttled('api-auth',20))yw_api_json(429,['error'=>'too_many_requests','message'=>'Zu viele fehlgeschlagene Anmeldungen. Bitte 15 Minuten warten.']);
    $header=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
    if(!preg_match('/^Bearer\s+(\S+)$/',$header,$m) || !hash_equals($c['api_token'],$m[1])){
        record_login_failure('api-auth');
        header('WWW-Authenticate: Bearer');
        yw_api_json(401,['error'=>'unauthorized','message'=>'Gültiger API-Schlüssel erforderlich (Authorization: Bearer …).']);
    }
}

function yw_api_row(array $row,bool $withPayload=true): array {
    $data=[
        'order_id'=>(int)$row['order_id'],
        'order_number'=>yw_order_number((int)$row['order_id']),
        'delivery_id'=>(string)$row['delivery_id'],
        'event'=>(string)$row['event'],
        'status'=>(string)$row['status'],
        'created_at'=>(new DateTimeImmutable((string)$row['created_at']))->format(DATE_ATOM),
        'acknowledged_at'=>$row['acknowledged_at']?(new DateTimeImmutable((string)$row['acknowledged_at']))->format(DATE_ATOM):null,
        'external_reference'=>$row['external_reference'],
    ];
    if($withPayload)$data['payload']=json_decode((string)$row['payload_json'],true);
    return $data;
}

function yw_api_route(string $path): never {
    header('Cache-Control: no-store');
    if($path==='/api/v1/status')yw_status_route();
    if(yw_api_v1_route($path))exit;
    yw_api_authenticate();
    if($path==='/api/v1/auftraege' && ($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
        $status=(string)($_GET['status']??'offen');
        $limit=max(1,min(200,(int)($_GET['limit']??50)));
        $where=match($status){
            'offen'=>"acknowledged_at IS NULL AND status<>'delivered'",
            'alle'=>'1=1',
            'quittiert'=>'acknowledged_at IS NOT NULL',
            default=>null,
        };
        if($where===null)yw_api_json(400,['error'=>'invalid_status','message'=>'status muss offen, quittiert oder alle sein.']);
        $rows=db()->query("SELECT * FROM yw_handover WHERE $where ORDER BY id LIMIT ".$limit)->fetchAll();
        yw_api_json(200,['schema'=>YW_HANDOVER_SCHEMA,'count'=>count($rows),'data'=>array_map('yw_api_row',$rows)]);
    }
    if(preg_match('#^/api/v1/auftraege/(\d{1,10})$#',$path,$m) && ($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
        $q=db()->prepare("SELECT * FROM yw_handover WHERE order_id=? AND event='order.approved' LIMIT 1");$q->execute([(int)$m[1]]);$row=$q->fetch();
        if(!$row)yw_api_json(404,['error'=>'not_found','message'=>'Für diese Bestellung liegt kein freigegebener Auftrag vor.']);
        yw_api_json(200,yw_api_row($row));
    }
    if(preg_match('#^/api/v1/auftraege/(\d{1,10})/quittung$#',$path,$m) && is_post()){
        $raw=(string)file_get_contents('php://input',false,null,0,10000);
        $body=$raw!==''?json_decode($raw,true):[];
        if(!is_array($body))yw_api_json(400,['error'=>'invalid_json','message'=>'Der Anfrageinhalt ist kein gültiges JSON.']);
        $reference=isset($body['reference'])&&is_scalar($body['reference'])?mb_substr(trim((string)$body['reference']),0,120):null;
        if($reference==='')$reference=null;
        $q=db()->prepare("SELECT * FROM yw_handover WHERE order_id=? AND event='order.approved' LIMIT 1");$q->execute([(int)$m[1]]);$row=$q->fetch();
        if(!$row)yw_api_json(404,['error'=>'not_found','message'=>'Auftrag nicht gefunden.']);
        db()->prepare("UPDATE yw_handover SET status='acknowledged',acknowledged_at=COALESCE(acknowledged_at,NOW()),external_reference=COALESCE(?,external_reference),last_error=NULL WHERE id=?")->execute([$reference,(int)$row['id']]);
        if($reference!==null)db()->prepare('UPDATE orders SET external_reference=? WHERE id=?')->execute([$reference,(int)$row['order_id']]);
        audit_log('handover.acknowledged','order',(int)$row['order_id'],['reference'=>$reference]);
        $q=db()->prepare('SELECT * FROM yw_handover WHERE id=?');$q->execute([(int)$row['id']]);
        yw_api_json(200,yw_api_row($q->fetch(),false));
    }
    yw_api_json(404,['error'=>'not_found','message'=>'Unbekannter Endpunkt.']);
}

/* --------------------------------------------------------------------------
   Admin-Oberfläche
   -------------------------------------------------------------------------- */

function yw_handover_status_label(string $status): array {
    return match($status){
        'pending'=>['Wartet auf Übergabe','pending'],
        'sending'=>['Wird gesendet','pending'],
        'delivered'=>['Übergeben','approved'],
        'acknowledged'=>['Übernommen','approved'],
        'failed'=>['Fehlgeschlagen','rejected'],
        default=>[$status,''],
    };
}

function yw_handover_status_map(array $orderIds): array {
    $ids=array_values(array_unique(array_filter(array_map('intval',$orderIds))));
    if(!$ids)return [];
    $map=[];
    try{
        foreach(array_chunk($ids,500) as $chunk){
            $q=db()->prepare("SELECT order_id,status,external_reference FROM yw_handover WHERE event='order.approved' AND order_id IN (".implode(',',array_fill(0,count($chunk),'?')).")");
            $q->execute($chunk);
            foreach($q as $row)$map[(int)$row['order_id']]=$row;
        }
    }catch(Throwable $ignored){}
    return $map;
}

function yw_admin_handover_action(string $action): never {
    try{
        if($action==='handover_retry'){
            $id=(int)($_POST['handover_id']??0);
            $q=db()->prepare("UPDATE yw_handover SET status='pending',available_at=NOW(),attempts=IF(status='failed',0,attempts) WHERE id=? AND status IN ('pending','failed','delivered')");
            $q->execute([$id]);
            if(!$q->rowCount())throw new RuntimeException('Übergabe wurde nicht gefunden oder bereits quittiert.');
            $sent=yw_handover_push_enabled()?yw_handover_deliver($id):false;
            audit_log('handover.retry','handover',$id);
            flash($sent||!yw_handover_push_enabled()?'success':'error',yw_handover_push_enabled()?($sent?'Auftrag wurde erfolgreich übergeben.':'Übergabe erneut fehlgeschlagen – Fehlerdetails in der Liste. Es folgen automatische Wiederholungen.'):'Auftrag steht wieder zum Abruf bereit.');
        }elseif($action==='handover_retry_failed'){
            $n=db()->exec("UPDATE yw_handover SET status='pending',attempts=0,available_at=NOW() WHERE status='failed'");
            audit_log('handover.retry_failed','handover',null,['count'=>$n]);
            $sent=yw_process_handover_queue(20);
            flash('success',$n.' fehlgeschlagene Übergaben neu eingeplant'.(yw_handover_push_enabled()?', davon '.$sent.' sofort übergeben.':'.'));
        }elseif($action==='handover_backfill'){
            $ids=db()->query("SELECT o.id FROM orders o LEFT JOIN yw_handover h ON h.order_id=o.id AND h.event='order.approved' WHERE o.status='approved' AND h.id IS NULL ORDER BY o.id LIMIT 500")->fetchAll(PDO::FETCH_COLUMN);
            $onlyOpen=!empty($_POST['only_open']);
            $count=0;
            foreach($ids as $id){
                if($onlyOpen){$q=db()->prepare("SELECT fulfillment_status FROM orders WHERE id=?");$q->execute([(int)$id]);if(in_array((string)$q->fetchColumn(),['shipped','picked_up','completed'],true))continue;}
                yw_handover_enqueue((int)$id);$count++;
            }
            audit_log('handover.backfill','handover',null,['count'=>$count]);
            flash('success',$count.' ältere freigegebene Aufträge wurden für die Übergabe vorbereitet.');
        }elseif($action==='handover_test'){
            if(!yw_handover_push_enabled())throw new RuntimeException('Der Testversand ist nur im Modus „push“ oder „both“ möglich.');
            $payload=['schema'=>YW_HANDOVER_SCHEMA,'event'=>'test','delivery_id'=>'test-'.bin2hex(random_bytes(8)),'generated_at'=>(new DateTimeImmutable())->format(DATE_ATOM),'source'=>['system'=>'WorkwearCenter','version'=>app_version()],'message'=>'Testübertragung – bitte nicht als Auftrag anlegen.'];
            $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            [$status,$response,$error]=yw_handover_send('test',$payload['delivery_id'],$json);
            audit_log('handover.test','handover',null,['http_status'=>$status]);
            if($status>=200&&$status<300)flash('success','Testübertragung erfolgreich (HTTP '.$status.'). Die Auftragsverwaltung ist erreichbar und hat geantwortet.');
            else flash('error','Testübertragung fehlgeschlagen: '.($error!==''?$error:'HTTP '.$status.' '.mb_substr(strip_tags($response),0,200)));
        }else throw new RuntimeException('Aktion unbekannt.');
    }catch(Throwable $e){
        if(!$e instanceof RuntimeException)error_log('Handover admin: '.$e->getMessage());
        flash('error',$e instanceof RuntimeException?$e->getMessage():'Aktion fehlgeschlagen.');
    }
    redirect('/admin?tab=handover');
}

function yw_admin_handover(): void {
    $c=yw_handover_config();$issues=yw_handover_config_issues();
    $counts=['pending'=>0,'sending'=>0,'delivered'=>0,'acknowledged'=>0,'failed'=>0];
    try{foreach(db()->query('SELECT status,COUNT(*) n FROM yw_handover GROUP BY status') as $r)$counts[(string)$r['status']]=(int)$r['n'];}catch(Throwable $e){}
    $missing=yw_handover_missing_count();
    $modeLabels=['off'=>'Ausgeschaltet – Aufträge werden gesammelt','push'=>'Senden (Push)','pull'=>'Abholen (Pull-API)','both'=>'Senden und Abholen'];
    $lastOk=null;try{$lastOk=db()->query("SELECT MAX(COALESCE(acknowledged_at,delivered_at)) FROM yw_handover")->fetchColumn();}catch(Throwable $e){}

    echo '<section class="card"><div class="title-row"><div><h2>Verbindung zur Auftragsverwaltung</h2><p class="muted">Jeder freigegebene Auftrag wird automatisch als vollständiger Datensatz an deine Auftragsverwaltung übergeben.</p></div><span class="badge '.($c['mode']==='off'?'pending':($issues?'rejected':'approved')).'">'.($c['mode']==='off'?'Nicht verbunden':($issues?'Konfiguration prüfen':'Aktiv')).'</span></div>';
    echo '<div class="release-checks">';
    echo '<div class="release-check '.($c['mode']==='off'?'warn':'ok').'"><strong>Modus</strong><span>'.e($modeLabels[$c['mode']]).'</span></div>';
    if(yw_handover_push_enabled()){$host=(string)(parse_url($c['url'],PHP_URL_HOST)?:'–');echo '<div class="release-check '.($issues?'warn':'ok').'"><strong>Ziel</strong><span>'.e($host).' · Signatur '.(strlen($c['secret'])>=32?'eingerichtet':'fehlt').'</span></div>';}
    if(yw_handover_pull_enabled())echo '<div class="release-check '.(strlen($c['api_token'])>=32?'ok':'warn').'"><strong>Abruf-Adresse</strong><span>'.e(workwearcenter_url('api/v1/auftraege')).' · Schlüssel '.(strlen($c['api_token'])>=32?'eingerichtet':'fehlt').'</span></div>';
    echo '<div class="release-check '.($lastOk?'ok':'warn').'"><strong>Letzte erfolgreiche Übergabe</strong><span>'.($lastOk?e(date('d.m.Y H:i',strtotime((string)$lastOk))).' Uhr':'Noch keine').'</span></div>';
    echo '</div>';
    foreach($issues as $issue)echo '<div class="flash error space-top">'.e($issue).'. Einstellung in der config.php anpassen.</div>';
    if($c['mode']==='off')echo '<div class="flash info space-top"><strong>So wird die Verbindung eingeschaltet:</strong> In der <code>config.php</code> <code>handover_mode</code> sowie Adresse bzw. Schlüssel eintragen. Die Anleitung steht in <code>SCHNITTSTELLE-AUFTRAGSVERWALTUNG.md</code>. Bis dahin werden alle freigegebenen Aufträge gesammelt und nach dem Einschalten übergeben.</div>';
    echo '<div class="company-kpi-grid">';
    foreach([['pending','Warten'],['delivered','Übergeben'],['acknowledged','Übernommen'],['failed','Fehlgeschlagen']] as [$key,$label])echo '<div class="company-kpi"><strong>'.(int)$counts[$key].'</strong><span>'.$label.'</span></div>';
    echo '</div><div class="actions">';
    if(yw_handover_push_enabled())echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="handover_test"><input type="hidden" name="return_tab" value="handover"><button class="btn">Verbindung testen</button></form>';
    if($counts['failed'])echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="handover_retry_failed"><input type="hidden" name="return_tab" value="handover"><button class="btn primary">Alle fehlgeschlagenen erneut senden</button></form>';
    echo '</div>';
    if($missing>0)echo '<div class="flash info space-top"><strong>'.$missing.' ältere freigegebene Aufträge</strong> (aus der Zeit vor Version 10) haben noch keinen Übergabedatensatz.<form method="post" class="space-top actions" data-confirm="Ältere Aufträge für die Übergabe vorbereiten?">'.csrf_field().'<input type="hidden" name="action" value="handover_backfill"><input type="hidden" name="return_tab" value="handover"><label class="check"><input type="checkbox" name="only_open" value="1" checked><span>Nur noch nicht ausgelieferte Aufträge</span></label><button class="btn small">Für Übergabe vorbereiten</button></form></div>';
    echo '</section>';

    $rows=[];try{$rows=db()->query("SELECT h.*,c.legal_name FROM yw_handover h JOIN orders o ON o.id=h.order_id JOIN companies c ON c.id=o.company_id ORDER BY FIELD(h.status,'failed','pending','sending','delivered','acknowledged'),h.id DESC LIMIT 100")->fetchAll();}catch(Throwable $e){}
    echo '<section class="card"><div class="title-row"><div><h2>Übergabeprotokoll</h2><p class="muted">Die letzten 100 Übergaben, Probleme zuerst.</p></div></div>';
    if(!$rows){echo '<div class="empty">Noch keine freigegebenen Aufträge.</div></section>';return;}
    echo '<div class="table-wrap"><table class="table"><thead><tr><th>Auftrag</th><th>Firma</th><th>Status</th><th>Versuche</th><th>Referenz / Hinweis</th><th></th></tr></thead><tbody>';
    foreach($rows as $r){
        [$label,$class]=yw_handover_status_label((string)$r['status']);
        $when=$r['acknowledged_at']?:($r['delivered_at']?:$r['created_at']);
        echo '<tr><td><strong>'.e(yw_order_number((int)$r['order_id'])).'</strong><br><span class="muted">'.e(date('d.m.Y H:i',strtotime((string)$when))).'</span></td><td>'.e($r['legal_name']).'</td><td><span class="badge '.e($class).'">'.e($label).'</span></td><td>'.(int)$r['attempts'].'</td><td>';
        if($r['external_reference'])echo 'Ref. <strong>'.e($r['external_reference']).'</strong>';
        elseif($r['last_error'])echo '<span class="muted">'.e(mb_substr((string)$r['last_error'],0,160)).'</span>';
        elseif($r['status']==='pending')echo '<span class="muted">'.(yw_handover_push_enabled()?'Nächster Versuch '.e(date('H:i',strtotime((string)$r['available_at']))).' Uhr':'Bereit zum Abruf').'</span>';
        else echo '–';
        echo '<details class="details"><summary>Daten ansehen</summary><pre class="json-view">'.e(json_encode(json_decode((string)$r['payload_json'],true),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)).'</pre></details></td><td>';
        if(in_array((string)$r['status'],['failed','pending','delivered'],true))echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="handover_retry"><input type="hidden" name="return_tab" value="handover"><input type="hidden" name="handover_id" value="'.(int)$r['id'].'"><button class="btn small">'.((string)$r['status']==='delivered'?'Nochmals senden':'Jetzt senden').'</button></form>';
        echo '</td></tr>';
    }
    echo '</tbody></table></div></section>';
}

/* --------------------------------------------------------------------------
   Statusrückmeldung aus der Auftragszentrale (Version 10.3)
   POST /api/v1/status  ·  gleiche Signatur wie die Übergabe (X-Yanik-*),
   Kanal "auftragszentrale", Schlüssel = handover_secret.
   -------------------------------------------------------------------------- */

const YW_STATUS_EVENTS = ['accepted','goods_ordered','goods_received','production','quality_check','ready','shipped','picked_up','completed'];

function yw_tracking_link(?string $carrier,?string $number,?string $url=null): ?string {
    $number=trim((string)$number);
    if($url!==null && $url!=='' && filter_var($url,FILTER_VALIDATE_URL) && str_starts_with(strtolower($url),'https://'))return $url;
    if($number==='')return null;
    return match(strtolower(trim((string)$carrier))){
        'dhl'=>'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode='.rawurlencode($number),
        'dpd'=>'https://tracking.dpd.de/status/de_DE/parcel/'.rawurlencode($number),
        'ups'=>'https://www.ups.com/track?loc=de_DE&tracknum='.rawurlencode($number),
        'gls'=>'https://gls-group.com/DE/de/paketverfolgung?match='.rawurlencode($number),
        'hermes'=>'https://www.myhermes.de/empfangen/sendungsverfolgung/sendungsinformation/#'.rawurlencode($number),
        default=>null,
    };
}

function yw_status_authenticate(string $body): void {
    $c=yw_handover_config();
    if(strlen($c['secret'])<32)yw_api_json(404,['error'=>'not_enabled','message'=>'Statusrückmeldung ist nicht eingerichtet.']);
    if(login_throttled('status-auth',30))yw_api_json(429,['error'=>'too_many_requests']);
    $channel=(string)($_SERVER['HTTP_X_YANIK_CHANNEL']??'');$time=(string)($_SERVER['HTTP_X_YANIK_TIME']??'');
    $nonce=(string)($_SERVER['HTTP_X_YANIK_NONCE']??'');$signature=strtolower((string)($_SERVER['HTTP_X_YANIK_SIGNATURE']??''));
    $fail=static function(string $msg): never {record_login_failure('status-auth');yw_api_json(401,['error'=>'invalid_signature','message'=>$msg]);};
    if($channel!=='auftragszentrale'||!preg_match('/^\d{10,13}$/',$time)||!preg_match('/^[A-Za-z0-9._:-]{16,120}$/',$nonce)||!preg_match('/^[a-f0-9]{64}$/',$signature))$fail('Signatur-Header fehlen oder sind ungültig.');
    $seconds=strlen($time)===13?intdiv((int)$time,1000):(int)$time;
    if(abs(time()-$seconds)>300)$fail('Zeitfenster abgelaufen.');
    $path=(string)(parse_url((string)($_SERVER['REQUEST_URI']??'/api/v1/status'),PHP_URL_PATH)?:'/api/v1/status');
    $query=(string)($_SERVER['QUERY_STRING']??'');
    $expected=hash_hmac('sha256',$time."\n".$nonce."\nPOST\n".$path.($query!==''?'?'.$query:'')."\n".$body,$c['secret']);
    if(!hash_equals($expected,$signature))$fail('Signatur stimmt nicht.');
    db()->exec("DELETE FROM yw_api_nonces WHERE expires_at<NOW()");
    try{db()->prepare('INSERT INTO yw_api_nonces(id,expires_at) VALUES(?,DATE_ADD(NOW(),INTERVAL 15 MINUTE))')->execute(['status:'.$nonce]);}
    catch(PDOException $e){yw_api_json(409,['error'=>'replay','message'=>'Diese Anfrage wurde bereits verarbeitet.']);}
}

function yw_status_route(): never {
    header('Cache-Control: no-store');
    if(!is_post())yw_api_json(405,['error'=>'method_not_allowed']);
    $body=(string)file_get_contents('php://input',false,null,0,65537);
    if(strlen($body)>65536)yw_api_json(413,['error'=>'too_large']);
    // Entweder signierte Anfrage (Auftragszentrale, X-Yanik-*) oder API-Schlüssel mit Recht orders:write (ab 10.8).
    if(isset($_SERVER['HTTP_X_YANIK_SIGNATURE']))yw_status_authenticate($body);
    else yw_api_require_scope('orders:write');
    $data=json_decode($body,true);
    if(!is_array($data))yw_api_json(400,['error'=>'invalid_json']);
    if(($data['event']??'')==='ping')yw_api_json(200,['ok'=>true,'system'=>'WorkwearCenter','version'=>app_version()]);
    if(($data['event']??'')!=='order.status'||!is_array($data['order']??null))yw_api_json(400,['error'=>'unknown_event','message'=>'Erwartet wird event "order.status".']);
    $o=$data['order'];
    if(!preg_match('/^WWC-(\d{1,10})$/',(string)($o['id']??''),$m))yw_api_json(400,['error'=>'invalid_order_id','message'=>'order.id muss die WorkwearCenter-Nummer sein, z. B. WWC-000123.']);
    yw_api_json(200,yw_status_apply((int)$m[1],$o,'auftragszentrale'));
}

/** Produktionsstand und Sendungsdaten übernehmen (gemeinsam für signierte Rückmeldung und API v1). Antwortet bei Fehlern direkt. */
function yw_status_apply(int $orderId,array $o,string $actor): array {
    $status=(string)($o['status']??'');
    if(!in_array($status,YW_STATUS_EVENTS,true))yw_api_json(400,['error'=>'invalid_status','allowed'=>YW_STATUS_EVENTS]);
    $tracking=is_array($o['tracking']??null)?$o['tracking']:[];
    $carrier=mb_substr(trim((string)($tracking['carrier']??'')),0,40);$number=mb_substr(preg_replace('/\s+/','',(string)($tracking['number']??'')),0,80);
    $url=trim((string)($tracking['url']??''));$url=$url!==''&&filter_var($url,FILTER_VALIDATE_URL)&&str_starts_with(strtolower($url),'https://')?mb_substr($url,0,500):null;
    if($number!==''&&!preg_match('/^[A-Za-z0-9-]{4,80}$/',$number))yw_api_json(400,['error'=>'invalid_tracking_number']);
    $reference=mb_substr(trim((string)($o['externalReference']??'')),0,120);

    $q=db()->prepare("SELECT o.id,o.status,o.fulfillment_status,o.tracking_number,c.slug FROM orders o JOIN companies c ON c.id=o.company_id WHERE o.id=? LIMIT 1");
    $q->execute([$orderId]);$order=$q->fetch();
    if(!$order)yw_api_json(404,['error'=>'not_found','message'=>'Bestellung unbekannt.']);
    if($order['status']!=='approved')yw_api_json(409,['error'=>'not_approved','message'=>'Nur freigegebene Bestellungen können einen Produktionsstatus erhalten.']);
    $statusChanged=(string)$order['fulfillment_status']!==$status;
    $trackingChanged=$number!=='' && $number!==(string)($order['tracking_number']??'');
    db()->prepare("UPDATE orders SET fulfillment_status=?,production_updated_at=NOW(),status_updated_by=?,tracking_carrier=COALESCE(NULLIF(?,''),tracking_carrier),tracking_number=COALESCE(NULLIF(?,''),tracking_number),tracking_url=COALESCE(?,tracking_url),external_reference=COALESCE(NULLIF(?,''),external_reference) WHERE id=?")
        ->execute([$status,mb_substr($actor,0,40),$carrier,$number,$url,$reference,$orderId]);
    if($statusChanged||$trackingChanged){
        audit_log($actor==='auftragszentrale'?'order.status_from_auftragszentrale':'order.status_from_api','order',$orderId,['status'=>$status,'tracking'=>$number!==''?$carrier.' '.$number:null]);
        $text='Der Stand deiner Bestellung '.yw_order_number($orderId).' hat sich geändert: '.company_portal_fulfillment_status($status).'.';
        $link=yw_tracking_link($carrier,$number,$url);
        if($number!=='')$text.="\n\nVersand mit ".($carrier!==''?$carrier:'Paketdienst')."\nSendungsnummer: ".$number;
        if($link)$text.="\nSendung verfolgen: ".$link;
        $text.="\n\nBestellstatus ansehen: ".workwearcenter_url((string)$order['slug'].'/bestellungen');
        notify_order_employee($orderId,'Neuer Stand deiner Bestellung '.yw_order_number($orderId),$text);
    }
    return ['ok'=>true,'order'=>yw_order_number($orderId),'status'=>$status,'changed'=>$statusChanged||$trackingChanged];
}
