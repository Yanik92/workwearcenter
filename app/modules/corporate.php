<?php
declare(strict_types=1);
require_once __DIR__.'/rules.php';
require_once __DIR__.'/equipment.php';
require_once __DIR__.'/corporate_admin.php';
require_once __DIR__.'/password_reset.php';
require_once __DIR__.'/customer_admin.php';
require_once __DIR__.'/registration.php';
require_once __DIR__.'/handover.php';
require_once __DIR__.'/api.php';
require_once __DIR__.'/katalog.php';
require_once __DIR__.'/content.php';
require_once __DIR__.'/mockup.php';
require_once __DIR__.'/admin_customer.php';
require_once __DIR__.'/admin_tools.php';
require_once __DIR__.'/order_schedule.php';
require_once __DIR__.'/order_mail_actions.php';
require_once __DIR__.'/magic_login.php';
require_once __DIR__.'/exchange.php';

function yw_locked_company(int $id): array {
    $q=db()->prepare('SELECT * FROM companies WHERE id=? FOR UPDATE'); $q->execute([$id]);
    $company=$q->fetch();
    if(!$company) throw new DomainException('Firma nicht gefunden.');
    return $company;
}

function yw_schema_ready(): bool {
    static $ready=false;
    if($ready)return true; // pro Anfrage nur einmal prüfen
    $current='Versionsmarke 10.8.0';
    try {
        if(!db()->query("SELECT 1 FROM yw_schema_versions WHERE version='10.8.0'")->fetchColumn()){$GLOBALS['yw_schema_problem']='Datenbankstand 10.8.0 fehlt';return false;}
        foreach([
            'SELECT tracking_number,tracking_carrier,tracking_url FROM orders LIMIT 0',
            'SELECT image_back_path,image_sleeve_path,print_zones_json FROM products LIMIT 0',
            'SELECT file_path FROM yw_decoration_motifs LIMIT 0',
            'SELECT internal_note FROM companies LIMIT 0',
            'SELECT id,key_hash,scopes FROM yw_api_keys LIMIT 0',
            'SELECT key_id FROM yw_api_rate LIMIT 0',
            'SELECT reminder_sent_at,internal_note FROM orders LIMIT 0',
            'SELECT id FROM yw_api_nonces LIMIT 0',
            'SELECT id,order_id,status,delivery_id FROM yw_handover LIMIT 0',
            'SELECT active,totp_enabled,totp_secret,recovery_codes_json FROM admins LIMIT 0',
            'SELECT anonymized_at FROM employee_accounts LIMIT 0',
            'SELECT decision_note,decided_by_account_id,decided_at,production_note,submission_token,delivery_snapshot_json,gross_total_cents,company_total_cents FROM orders LIMIT 0',
            'SELECT decoration_snapshot_json,unit_gross_cents,company_share_cents FROM order_items LIMIT 0',
            'SELECT auth_version,equipment_profile_id,registration_status,budget_override_cents,employment_start,employment_end FROM employee_accounts LIMIT 0',
            'SELECT budget_enabled,annual_budget_company_cents,budget_reset_month,registration_mode,delivery_mode FROM companies LIMIT 0',
            'SELECT decorations_json,yearly_limit_qty,preview_front_path,preview_back_path FROM company_products LIMIT 0',
            'SELECT auth_version FROM admins LIMIT 0',
            'SELECT id,reply_to FROM yw_mail_queue LIMIT 0',
            'SELECT id FROM yw_password_resets LIMIT 0',
            'SELECT id FROM yw_admin_password_resets LIMIT 0',
            'SELECT id,company_id FROM yw_equipment_profiles LIMIT 0',
            'SELECT profile_id,product_id FROM yw_equipment_profile_products LIMIT 0',
            'SELECT id,company_id,method,track_stock,stock_qty,reorder_level FROM yw_decoration_motifs LIMIT 0',
        ] as $current) db()->query($current);
        return $ready=true;
    }
    catch(Throwable $e) { $GLOBALS['yw_schema_problem']=$e->getMessage().' – Prüfung: '.$current; return false; }
}

/** Technischer Grund, warum die Datenbank als nicht bereit gilt (nur für Admins anzeigen). */
function yw_schema_problem(): string { return (string)($GLOBALS['yw_schema_problem']??''); }

/** Einrichtungsschritte eines Firmen-Shops für die Fortschrittsanzeige im Admin. */
function yw_company_setup_steps(array $company): array {
    $id=(int)$company['id'];
    $count=static function(string $sql,array $params): int {try{$q=db()->prepare($sql);$q->execute($params);return (int)$q->fetchColumn();}catch(Throwable $e){return 0;}};
    $products=$count('SELECT COUNT(*) FROM company_products WHERE company_id=?',[$id]);
    $managers=$count("SELECT COUNT(*) FROM employee_accounts WHERE company_id=? AND role='manager' AND active=1 AND registration_status='approved'",[$id]);
    $employees=$count("SELECT COUNT(*) FROM employee_accounts WHERE company_id=? AND active=1 AND registration_status='approved'",[$id]);
    $deliveryOk=($company['delivery_mode']??'company')==='pickup' || trim((string)($company['delivery_address']??''))!=='';
    $registrationSafe=($company['registration_mode']??'approval')!=='open' || (int)($company['approval_required']??1)===1;
    $base='/admin?tab=companies&company='.$id;
    return [
        ['Logo & Farben','Firmenlogo hochladen, damit Mitarbeitende ihren Shop wiedererkennen.',yw_company_logo_path($company)!=='',$base.'#design'],
        ['Kollektion','Mindestens ein Produkt für diese Firma freigeben.',$products>0,$base.'#kollektion'],
        ['Lieferung','Lieferadresse hinterlegen oder Abholung wählen.',$deliveryOk,'/admin?tab=corporate&company_id='.$id],
        ['Verantwortlicher','Eine Person, die Registrierungen und Bestellungen freigibt.',$managers>0,$base.'#mitarbeiter'],
        ['Zugang abgesichert','Registrierung mit Freigabe/PIN oder Bestellfreigabe aktiv.',$registrationSafe,'/admin?tab=corporate&company_id='.$id],
        ['Erste Mitarbeitende','Mitarbeitende einladen oder den Registrierungslink weitergeben.',$employees>$managers,$base.'#links'],
        ['Shop aktiv','Der Shop ist erreichbar.',(int)($company['active']??1)===1,$base],
    ];
}

/** Konfigurationen, die im Betrieb zu Fehlern oder Missbrauch führen können (HTML, bereits maskiert). */
function yw_company_setup_warnings(array $company): array {
    $warnings=[];
    if(($company['delivery_mode']??'company')==='company' && trim((string)($company['delivery_address']??''))==='')
        $warnings[]='<strong>Lieferadresse fehlt:</strong> Solange keine Firmenlieferadresse hinterlegt ist, schlägt jede Bestellung fehl. <a href="/admin?tab=corporate&amp;company_id='.(int)$company['id'].'">Jetzt ergänzen</a>';
    if(($company['registration_mode']??'approval')==='open' && (int)($company['approval_required']??1)!==1)
        $warnings[]='<strong>Sicherheitshinweis:</strong> Registrierung ist offen <em>und</em> Bestellungen brauchen keine Freigabe. Wer den Shop-Link kennt, kann sich registrieren und sofort auf Firmenkosten bestellen. Empfehlung: Registrierung mit Freigabe oder Firmen-PIN.';
    try{
        $q=db()->prepare("SELECT COUNT(*) FROM employee_accounts WHERE company_id=? AND role='manager' AND active=1 AND registration_status='approved'");$q->execute([(int)$company['id']]);
        if(!(int)$q->fetchColumn())$warnings[]='<strong>Noch kein Verantwortlicher:</strong> Ohne Verantwortlichen kann niemand Registrierungen oder Bestellungen freigeben. Lege unter „Mitarbeiter & Rollen“ die erste Person an.';
    }catch(Throwable $ignored){}
    return $warnings;
}

function yw_normalize_cart_selection(mixed $qtyInput,mixed $sizeInput): array {
    if(!is_array($qtyInput) || !is_array($sizeInput)) throw new DomainException('Bestellauswahl ungültig.');
    $selection=[];
    foreach($qtyInput as $rawId=>$rawQty){
        if(is_int($rawId)) $productId=$rawId;
        elseif(is_string($rawId) && preg_match('/^[1-9]\d*$/D',$rawId) && (string)(int)$rawId===$rawId) $productId=(int)$rawId;
        else throw new DomainException('Artikelkennung ungültig.');
        if($productId<1 || isset($selection[$productId])) throw new DomainException('Artikelkennung doppelt oder ungültig.');
        if(!is_scalar($rawQty) || !preg_match('/^\d{1,2}$/D',(string)$rawQty)) throw new DomainException('Menge ungültig.');
        $qty=(int)$rawQty;
        if($qty===0) continue;
        if($qty>10) throw new DomainException('Pro Artikel sind höchstens 10 Stück je Bestellung möglich.');
        $size=$sizeInput[$rawId]??null;
        if(!is_string($size)) throw new DomainException('Größenauswahl ungültig.');
        $selection[$productId]=['quantity'=>$qty,'size'=>$size];
    }
    return $selection;
}

function yw_budget_used(array $company,array $account): int {
    [$start,$end]=yw_period((int)$company['budget_reset_month']);
    $q=db()->prepare("SELECT COALESCE(SUM(company_total_cents),0) FROM orders WHERE company_id=? AND (employee_account_id=? OR (employee_account_id IS NULL AND invite_id=?)) AND status IN ('pending','approved') AND created_at>=? AND created_at<?");
    $q->execute([$company['id'],$account['id'],$account['invite_id']??0,$start,$end]);
    // 10.11: Rückgaben (Gutschrift) und Preisdifferenzen beim Größentausch
    return (int)$q->fetchColumn()+yw_exchange_budget_adjustment($company,$account,$start,$end);
}

function yw_quantity_used(array $company,array $account,int $productId): int {
    [$start,$end]=yw_period((int)$company['budget_reset_month']);
    $q=db()->prepare("SELECT COALESCE(SUM(i.quantity),0) FROM order_items i JOIN orders o ON o.id=i.order_id WHERE o.company_id=? AND (o.employee_account_id=? OR (o.employee_account_id IS NULL AND o.invite_id=?)) AND o.status IN ('pending','approved') AND o.created_at>=? AND o.created_at<? AND i.product_id=?");
    $q->execute([$company['id'],$account['id'],$account['invite_id']??0,$start,$end,$productId]);
    return max(0,(int)$q->fetchColumn()-yw_exchange_returned_qty($company,$account,$productId,$start,$end)); // 10.11: zurückgegebene Stücke zählen nicht
}

function yw_checkout_fields(array $company): string {
    $token=bin2hex(random_bytes(32));
    $tokens=$_SESSION['yw_order_tokens']??[];
    foreach($tokens as $k=>$v) if(($v['time']??0)<time()-7200) unset($tokens[$k]);
    if(count($tokens)>30) $tokens=array_slice($tokens,-30,null,true);
    $tokens[$token]=['time'=>time(),'account'=>(int)($_SESSION['employee_account_id']??0),'company'=>(int)$company['id']];
    $_SESSION['yw_order_tokens']=$tokens;
    return '<input type="hidden" name="submission_token" value="'.e($token).'"><p class="muted">Lieferung: '.e(($company['delivery_mode']??'company')==='pickup'?'Abholung bei Yanik Textildruck':implode(', ',array_filter(array_map('trim',preg_split('/\R/u',(string)($company['delivery_address']??''))?:[])))).'. Rechnungen und Lieferscheine werden separat in Lexware Office bearbeitet.</p>';
}

function yw_shop_rules_summary(array $company,array $account): void {
    $limit=yw_budget_limit($company,$account);
    $remaining=employee_budget_remaining($company,$account);
    [$start,$end]=yw_period((int)$company['budget_reset_month']);
    $from=(new DateTimeImmutable($start))->format('d.m.Y');
    $to=(new DateTimeImmutable($end))->modify('-1 day')->format('d.m.Y');
    $profile=yw_account_equipment_profile($account);$profileRules=yw_account_profile_rules($account);
    echo '<section class="cp-budget-card"'.($remaining===null?'':' data-budget-remaining="'.(int)$remaining.'"').'>';
    echo '<div class="cp-budget-main"><span class="cp-budget-label">'.($remaining===null?'Firmenbudget':'Verfügbares Firmenbudget').'</span>';
    if($remaining===null){
        echo '<strong>Kein Jahresbudget festgelegt</strong>';
    }else{
        $percent=$limit>0?(int)round(max(0,min(100,$remaining/$limit*100))):0;
        echo '<strong>'.money($remaining).'<small> von '.money((int)$limit).'</small></strong><div class="cp-budget-bar" role="progressbar" aria-label="Verbleibendes Budget" aria-valuemin="0" aria-valuemax="100" aria-valuenow="'.$percent.'"><span style="width:'.$percent.'%"></span></div>';
    }
    echo '<small class="cp-budget-period">Budgetjahr '.e($from).' – '.e($to).'</small></div>';
    echo '<ul class="cp-budget-facts"><li>'.e(financing_label($company)).'</li><li>'.e(shipping_rule_label($company)).'</li><li>'.(((int)($company['approval_required']??1)===1)?'Bestellungen werden intern freigegeben':'Keine interne Freigabe nötig').'</li>';
    if($profile)echo '<li>Ausstattungsprofil: '.e($profile['name']).'</li>';
    if($batchDate=yw_company_batch_date($company))echo '<li>Nächster Sammeltermin: '.e($batchDate->format('d.m.Y')).'</li>';
    echo '</ul>';
    $limits=[];
    $q=db()->prepare('SELECT p.name,cp.custom_name,cp.product_id,cp.yearly_limit_qty FROM company_products cp JOIN products p ON p.id=cp.product_id WHERE cp.company_id=?');
    $q->execute([$company['id']]);
    foreach($q as $p){$id=(int)$p['product_id'];if($profileRules!==null && !isset($profileRules[$id]))continue;$max=yw_effective_product_limit($p,$profileRules[$id]??null);if($max!==null)$limits[]=e($p['custom_name']?:$p['name']).': noch <b>'.max(0,$max-yw_quantity_used($company,$account,$id)).'</b> von '.$max;}
    if($limits)echo '<div class="cp-budget-limits"><span>Stückzahlen im Budgetjahr</span>'.implode('',array_map(fn($l)=>'<em>'.$l.'</em>',$limits)).'</div>';
    echo '<p class="cp-budget-note">Offene Bestellungen reservieren Budget und Stückzahlen, abgelehnte zählen nicht. Ein Eigenanteil wird nur ausgewiesen, nicht automatisch eingezogen.</p>';
    echo '</section>';
}

function place_account_order(array $company,array $account): never {
    verify_csrf();
    $token=is_string($_POST['submission_token']??null)?$_POST['submission_token']:'';
    $binding=$_SESSION['yw_order_tokens'][$token]??[];
    if(!preg_match('/^[a-f0-9]{64}$/D',$token) || ($binding['account']??0)!==(int)$account['id'] || ($binding['company']??0)!==(int)$company['id'] || ($binding['time']??0)<time()-7200) {
        flash('error','Bestellformular abgelaufen. Bitte neu öffnen.'); redirect('/'.$company['slug'].'/produkte');
    }
    try {
        db()->beginTransaction();
        // One company lock is shared by checkout and all rule/collection edits.
        $q=db()->prepare('SELECT * FROM companies WHERE id=? AND active=1 FOR UPDATE');
        $q->execute([$company['id']]); $company=$q->fetch();
        if(!$company) throw new DomainException('Firmenshop nicht verfügbar.');
        $q=db()->prepare("SELECT * FROM employee_accounts WHERE id=? AND company_id=? AND active=1 AND registration_status='approved' AND (employment_start IS NULL OR employment_start<=CURDATE()) AND (employment_end IS NULL OR employment_end>=CURDATE()) FOR UPDATE");
        $q->execute([$account['id'],$company['id']]); $account=$q->fetch();
        if(!$account) throw new DomainException('Zugang nicht freigegeben.');
        $q=db()->prepare('SELECT id FROM orders WHERE submission_token=? AND company_id=? AND employee_account_id=?');
        $q->execute([$token,$company['id'],$account['id']]);
        if($existing=$q->fetchColumn()) {
            db()->rollBack(); flash('success','Bestellung #'.$existing.' wurde bereits gespeichert.'); redirect('/'.$company['slug'].'/bestellungen');
        }
        $q=db()->prepare('SELECT p.*,cp.custom_name,cp.custom_description,cp.price_override_cents,cp.preview_front_path,cp.preview_back_path,cp.decorations_json,cp.yearly_limit_qty FROM products p JOIN company_products cp ON cp.product_id=p.id WHERE cp.company_id=? AND p.active=1 FOR UPDATE');
        $q->execute([$company['id']]); $products=[];
        $profileRules=yw_account_profile_rules($account);
        foreach($q as $p){$productId=(int)$p['id'];if($profileRules!==null && !isset($profileRules[$productId]))continue;$p['profile_rule']=$profileRules[$productId]??null;$products[$productId]=$p;}
        $selection=yw_normalize_cart_selection($_POST['qty']??null,$_POST['size']??null);
        $items=[]; $net=0; $gross=0; $employee=0; $employer=0;
        foreach($selection as $productId=>$selected){
            $qty=(int)$selected['quantity'];
            if(!isset($products[$productId])) throw new DomainException('Artikel oder Menge nicht freigegeben.');
            $p=$products[$productId];
            $size=(string)$selected['size'];
            if(!is_string($size) || !in_array($size,json_decode($p['sizes'],true)?:[],true)) throw new DomainException('Bitte eine zugelassene Größe auswählen.');
            $limit=yw_effective_product_limit($p,$p['profile_rule']);
            yw_check_limits(null,0,0,$limit,yw_quantity_used($company,$account,$productId),$qty);
            $price=product_price_breakdown($p,$company);
            $net+=$price['net']*$qty; $gross+=$price['gross']*$qty;
            $employee+=$price['employee']*$qty; $employer+=$price['employer']*$qty;
            $snapshot=['version'=>1,'name'=>company_product_display_name($p),'description'=>company_product_display_description($p),'color'=>$p['color'],'sku'=>$p['sku']??null,'decorations'=>company_product_decorations($p),'base_decoration_type'=>$p['decoration_type']??'','base_decoration_details'=>$p['decoration_details']??''];
            $items[]=[$p,$size,$qty,$price,json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)];
        }
        if(!$items) throw new DomainException('Bitte mindestens einen Artikel auswählen.');
        $shipping=shipping_breakdown($company,$gross);
        if(($company['delivery_mode']??'company')==='pickup') $shipping=['net'=>0,'gross'=>0,'employee'=>0,'company'=>0];
        $net+=$shipping['net']; $gross+=$shipping['gross']; $employee+=$shipping['employee']; $employer+=$shipping['company'];
        yw_check_limits(yw_budget_limit($company,$account),yw_budget_used($company,$account),$employer);
        $delivery=['mode'=>$company['delivery_mode'],'address'=>trim((string)$company['delivery_address']),'company'=>$company['legal_name'],'employee'=>$account['name'],'department'=>$account['department'],'cost_center'=>$account['cost_center']];
        if($delivery['mode']==='company' && $delivery['address']==='') throw new DomainException('Die Firmenlieferadresse fehlt. Bitte den Verantwortlichen kontaktieren.');
        $inviteId=ensure_account_invite($company,$account);
        $status=(int)$company['approval_required']===1?'pending':'approved';
        $q=db()->prepare('INSERT INTO orders(company_id,invite_id,employee_account_id,total_cents,net_total_cents,gross_total_cents,employee_total_cents,company_total_cents,shipping_net_cents,shipping_gross_cents,shipping_employee_cents,shipping_company_cents,status,note,submission_token,delivery_snapshot_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$company['id'],$inviteId,$account['id'],$net,$net,$gross,$employee,$employer,$shipping['net'],$shipping['gross'],$shipping['employee'],$shipping['company'],$status,mb_substr(is_string($_POST['note']??null)?trim($_POST['note']):'',0,500),$token,json_encode($delivery,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
        $orderId=(int)db()->lastInsertId();
        $q=db()->prepare('INSERT INTO order_items(order_id,product_id,product_name,size,quantity,unit_price_cents,unit_net_cents,vat_rate,unit_gross_cents,employee_share_cents,company_share_cents,decoration_snapshot_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach($items as [$p,$size,$qty,$price,$snapshot]) $q->execute([$orderId,$p['id'],company_product_display_name($p),$size,$qty,$price['net'],$price['net'],$price['vat'],$price['gross'],$price['employee'],$price['employer'],$snapshot]);
        db()->commit();
    } catch(Throwable $e) {
        if(db()->inTransaction()) db()->rollBack();
        if(!$e instanceof DomainException) error_log('Yanik checkout: '.$e->getMessage());
        flash('error',$e instanceof DomainException?$e->getMessage():'Bestellung konnte nicht gespeichert werden. Bitte erneut versuchen.');
        redirect('/'.($company['slug']??'').'/produkte');
    }
    audit_log('order.created','order',$orderId,['company_id'=>(int)$company['id'],'status'=>$status]);
    if($status==='approved')yw_handover_enqueue($orderId);
    $summary=yw_order_mail_summary($orderId);
    yw_notify_managers_new_order($company,$orderId,(int)$account['id'],$summary,$status); // 10.11: mit Freigabe-Links je Verantwortlichem
    notify_order_employee($orderId,'Bestellbestätigung #'.$orderId,"Hallo ".$account['name'].",\n\ndeine Bestellung wurde erfolgreich gespeichert.\n\n".$summary."\n\nBestellstatus ansehen: ".workwearcenter_url((string)$company['slug'].'/bestellungen'));
    flash('success','Bestellung #'.$orderId.' gespeichert. '.($status==='pending'?'Dein Budget ist bis zur Entscheidung reserviert.':'Die Bestellung ist freigegeben.'));
    redirect('/'.$company['slug'].'/bestellungen');
}
