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
require_once __DIR__.'/katalog_daiber.php';
require_once __DIR__.'/content.php';
require_once __DIR__.'/mockup.php';
require_once __DIR__.'/admin_customer.php';
require_once __DIR__.'/admin_tools.php';
require_once __DIR__.'/order_schedule.php';
require_once __DIR__.'/order_mail_actions.php';
require_once __DIR__.'/magic_login.php';
require_once __DIR__.'/exchange.php';
require_once __DIR__.'/pricing.php';
require_once __DIR__.'/lexware.php';
require_once __DIR__.'/payroll.php';

function yw_locked_company(int $id): array {
    $q=db()->prepare('SELECT * FROM companies WHERE id=? FOR UPDATE'); $q->execute([$id]);
    $company=$q->fetch();
    if(!$company) throw new DomainException('Firma nicht gefunden.');
    return $company;
}

/**
 * Ist die Datenbank auf dem Stand dieser Programmdateien? Volle Prüfung (~40 Abfragen) nur, wenn sich die
 * Prüfliste geändert hat (neue Programmdateien) oder $full verlangt wird (update.php, Systemstatus); sonst
 * genügt der Vergleich mit der Marke in site_settings aus yw_schema_state() – dieselbe eine Abfrage, die auch
 * den roten Hinweis „Datenbank-Update fehlt“ speist. Nur ein bestandener Lauf setzt die Marke, ein fehlgeschlagener
 * verwirft sie; solange etwas fehlt, wird bei jedem Aufruf neu geprüft, damit „Update erforderlich“ direkt nach
 * update.php verschwindet.
 */
function yw_schema_ready(bool $full=false): bool {
    static $ready=false;
    if($ready&&!$full)return true; // pro Anfrage nur einmal prüfen
    $signature=yw_schema_signature();
    if(!$full && hash_equals($signature,yw_schema_state()['signature']))return $ready=true;
    $current='Versionsmarke 10.12.0';
    try {
        if(!db()->query("SELECT 1 FROM yw_schema_versions WHERE version='10.12.0'")->fetchColumn()){$GLOBALS['yw_schema_problem']='Datenbankstand 10.12.0 fehlt – bitte update.php ausführen';yw_schema_forget();return $ready=false;}
        foreach(yw_schema_probes() as $current) db()->query($current);
        // Marke liegt in derselben Datenbank: eine zurückgespielte ältere Sicherung bringt ihre alte Marke mit und wird neu geprüft.
        if(!hash_equals($signature,yw_schema_state()['signature']))try{db()->prepare("REPLACE INTO site_settings(setting_key,setting_value) VALUES('schema_checked',?)")->execute([$signature]);yw_schema_state(true);}catch(Throwable $ignored){}
        return $ready=true;
    }
    catch(Throwable $e) { $GLOBALS['yw_schema_problem']=$e->getMessage().' – Prüfung: '.$current; yw_schema_forget(); return $ready=false; }
}

/** Kennung der Prüfliste samt verlangtem Datenbankstand; ändert sich mit jedem Programmpaket, das neue Spalten verlangt. */
function yw_schema_signature(): string { return sha1(YW_SCHEMA_REQUIRED.'|10.12.0|'.implode('|',yw_schema_probes())); }

/** Prüfabfragen: Spalten und Tabellen, die diese Programmdateien voraussetzen (neue Versionen hier ergänzen). */
function yw_schema_probes(): array {
    return [
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
            // 10.11: Umtausch & Retouren, Anmeldelink, Freigabe per E-Mail, Jahreswechsel-Mail, Sammelbestellung
            'SELECT exchange_window_days,budget_year_mail,budget_year_mail_period,batch_mode,batch_day,batch_anchor FROM companies LIMIT 0',
            'SELECT budget_mail_period FROM employee_accounts LIMIT 0',
            'SELECT id,order_item_id,type,status,unit_company_cents,budget_delta_cents FROM yw_exchange_requests LIMIT 0',
            'SELECT id,token_hash,expires_at,used_at FROM yw_login_links LIMIT 0',
            'SELECT id,nonce_hash,expires_at,used_at FROM yw_decision_tokens LIMIT 0',
            // 10.11: Probeshop
            'SELECT id,token,logo_path,garment_tone,expires_at,view_count FROM yw_probeshops LIMIT 0',
            // 10.11: Positionsrundung wie Lexware, Lexware-Anbindung
            'SELECT line_net_cents,line_gross_cents,line_employee_cents,line_company_cents FROM order_items LIMIT 0',
            'SELECT shipping_vat_rate FROM orders LIMIT 0',
            'SELECT lexware_contact_id,lexware_customer_number FROM companies LIMIT 0',
            'SELECT id,dedupe_key,status,lexware_id FROM yw_lexware_documents LIMIT 0',
            'SELECT order_id,document_id FROM yw_lexware_document_orders LIMIT 0',
            // 10.12: Mehrsprachiger Firmenshop
            'SELECT language FROM employee_accounts LIMIT 0',
            'SELECT shop_languages FROM companies LIMIT 0',
            'SELECT language FROM yw_mail_queue LIMIT 0',
        ];
}

/** Technischer Grund, warum die Datenbank als nicht bereit gilt (nur für Admins anzeigen). */
function yw_schema_problem(): string { return (string)($GLOBALS['yw_schema_problem']??''); }

/** Einrichtungsschritte eines Firmen-Shops für die Fortschrittsanzeige im Admin. */
function yw_company_setup_steps(array $company): array {
    $id=(int)$company['id'];
    $count=static function(string $sql,array $params): int {try{$q=db()->prepare($sql);$q->execute($params);return (int)$q->fetchColumn();}catch(Throwable $e){return 0;}};
    // Zähler kommen in der Verwaltung schon gruppiert mit der Firmenliste (yw_admin_companies), sonst einzeln.
    $products=isset($company['setup_products'])?(int)$company['setup_products']:$count('SELECT COUNT(*) FROM company_products WHERE company_id=?',[$id]);
    $managers=isset($company['setup_managers'])?(int)$company['setup_managers']:$count("SELECT COUNT(*) FROM employee_accounts WHERE company_id=? AND role='manager' AND active=1 AND registration_status='approved'",[$id]);
    $employees=isset($company['setup_employees'])?(int)$company['setup_employees']:$count("SELECT COUNT(*) FROM employee_accounts WHERE company_id=? AND active=1 AND registration_status='approved'",[$id]);
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
    if(!is_array($qtyInput) || !is_array($sizeInput)) throw new DomainException(t('err.selection_invalid'));
    $selection=[];
    foreach($qtyInput as $rawId=>$rawQty){
        if(is_int($rawId)) $productId=$rawId;
        elseif(is_string($rawId) && preg_match('/^[1-9]\d*$/D',$rawId) && (string)(int)$rawId===$rawId) $productId=(int)$rawId;
        else throw new DomainException(t('err.item_invalid'));
        if($productId<1 || isset($selection[$productId])) throw new DomainException(t('err.item_invalid'));
        if(!is_scalar($rawQty) || !preg_match('/^\d{1,2}$/D',(string)$rawQty)) throw new DomainException(t('err.qty_invalid'));
        $qty=(int)$rawQty;
        if($qty===0) continue;
        if($qty>10) throw new DomainException(t('err.qty_max'));
        $size=$sizeInput[$rawId]??null;
        if(!is_string($size)) throw new DomainException(t('err.size_invalid'));
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
    return '<input type="hidden" name="submission_token" value="'.e($token).'"><p class="muted">'.e(t('cart.delivery',['address'=>($company['delivery_mode']??'company')==='pickup'?t('cart.pickup'):implode(', ',array_filter(array_map('trim',preg_split('/\R/u',(string)($company['delivery_address']??''))?:[])))])).'</p>';
}

function yw_shop_rules_summary(array $company,array $account): void {
    $limit=yw_budget_limit($company,$account);
    $remaining=employee_budget_remaining($company,$account);
    [$start,$end]=yw_period((int)$company['budget_reset_month']);
    $from=yw_date($start);
    $to=yw_date((new DateTimeImmutable($end))->modify('-1 day'));
    $profile=yw_account_equipment_profile($account);$profileRules=yw_account_profile_rules($account);
    echo '<section class="cp-budget-card"'.($remaining===null?'':' data-budget-remaining="'.(int)$remaining.'"').'>';
    echo '<div class="cp-budget-main"><span class="cp-budget-label">'.e($remaining===null?t('budget.company'):t('budget.available_company')).'</span>';
    if($remaining===null){
        echo '<strong>'.e(t('budget.none')).'</strong>';
    }else{
        $percent=$limit>0?(int)round(max(0,min(100,$remaining/$limit*100))):0;
        echo '<strong>'.money($remaining).'<small> '.e(t('budget.of',['limit'=>money((int)$limit)])).'</small></strong><div class="cp-budget-bar" role="progressbar" aria-label="'.e(t('budget.remaining_aria')).'" aria-valuemin="0" aria-valuemax="100" aria-valuenow="'.$percent.'"><span style="width:'.$percent.'%"></span></div>';
    }
    echo '<small class="cp-budget-period">'.e(t('budget.year_range',['from'=>$from,'to'=>$to])).'</small></div>';
    echo '<ul class="cp-budget-facts"><li>'.e(financing_label($company)).'</li><li>'.e(shipping_rule_label($company)).'</li><li>'.e(((int)($company['approval_required']??1)===1)?t('rules.internal_approval'):t('rules.no_approval')).'</li>';
    if($profile)echo '<li>'.e(t('rules.profile',['profile'=>$profile['name']])).'</li>';
    if($batchDate=yw_company_batch_date($company))echo '<li>'.e(t('batch.next',['date'=>yw_date($batchDate)])).'</li>';
    echo '</ul>';
    $limits=[];
    $q=db()->prepare('SELECT p.name,cp.custom_name,cp.product_id,cp.yearly_limit_qty FROM company_products cp JOIN products p ON p.id=cp.product_id WHERE cp.company_id=?');
    $q->execute([$company['id']]);
    foreach($q as $p){$id=(int)$p['product_id'];if($profileRules!==null && !isset($profileRules[$id]))continue;$max=yw_effective_product_limit($p,$profileRules[$id]??null);if($max!==null)$limits[]=e($p['custom_name']?:$p['name']).': '.t('rules.limit_left',['left'=>'<b>'.max(0,$max-yw_quantity_used($company,$account,$id)).'</b>','max'=>$max]);}
    if($limits)echo '<div class="cp-budget-limits"><span>'.e(t('rules.limits_title')).'</span>'.implode('',array_map(fn($l)=>'<em>'.$l.'</em>',$limits)).'</div>';
    echo '<p class="cp-budget-note">'.e(t('rules.note')).'</p>';
    echo '</section>';
}

function place_account_order(array $company,array $account): never {
    verify_csrf();
    $token=is_string($_POST['submission_token']??null)?$_POST['submission_token']:'';
    $binding=$_SESSION['yw_order_tokens'][$token]??[];
    if(!preg_match('/^[a-f0-9]{64}$/D',$token) || ($binding['account']??0)!==(int)$account['id'] || ($binding['company']??0)!==(int)$company['id'] || ($binding['time']??0)<time()-7200) {
        flash('error',t('err.form_expired')); redirect('/'.$company['slug'].'/produkte');
    }
    try {
        db()->beginTransaction();
        // One company lock is shared by checkout and all rule/collection edits.
        $q=db()->prepare('SELECT * FROM companies WHERE id=? AND active=1 FOR UPDATE');
        $q->execute([$company['id']]); $company=$q->fetch();
        if(!$company) throw new DomainException(t('err.shop_unavailable'));
        $q=db()->prepare("SELECT * FROM employee_accounts WHERE id=? AND company_id=? AND active=1 AND registration_status='approved' AND (employment_start IS NULL OR employment_start<=CURDATE()) AND (employment_end IS NULL OR employment_end>=CURDATE()) FOR UPDATE");
        $q->execute([$account['id'],$company['id']]); $account=$q->fetch();
        if(!$account) throw new DomainException(t('err.access_not_approved'));
        $q=db()->prepare('SELECT id FROM orders WHERE submission_token=? AND company_id=? AND employee_account_id=?');
        $q->execute([$token,$company['id'],$account['id']]);
        if($existing=$q->fetchColumn()) {
            db()->rollBack(); flash('success',t('flash.order_already_saved',['id'=>$existing])); redirect('/'.$company['slug'].'/bestellungen');
        }
        $q=db()->prepare('SELECT p.*,cp.custom_name,cp.custom_description,cp.price_override_cents,cp.preview_front_path,cp.preview_back_path,cp.decorations_json,cp.yearly_limit_qty FROM products p JOIN company_products cp ON cp.product_id=p.id WHERE cp.company_id=? AND p.active=1 FOR UPDATE');
        $q->execute([$company['id']]); $products=[];
        $profileRules=yw_account_profile_rules($account);
        foreach($q as $p){$productId=(int)$p['id'];if($profileRules!==null && !isset($profileRules[$productId]))continue;$p['profile_rule']=$profileRules[$productId]??null;$products[$productId]=$p;}
        $selection=yw_normalize_cart_selection($_POST['qty']??null,$_POST['size']??null);
        $items=[]; $calcLines=[];
        foreach($selection as $productId=>$selected){
            $qty=(int)$selected['quantity'];
            if(!isset($products[$productId])) throw new DomainException(t('err.item_not_allowed'));
            $p=$products[$productId];
            $size=(string)$selected['size'];
            if(!is_string($size) || !in_array($size,json_decode($p['sizes'],true)?:[],true)) throw new DomainException(t('err.size_not_allowed'));
            $limit=yw_effective_product_limit($p,$p['profile_rule']);
            yw_check_limits(null,0,0,$limit,yw_quantity_used($company,$account,$productId),$qty);
            $price=product_price_breakdown($p,$company);
            $calcLines[]=['net_unit'=>$price['net'],'qty'=>$qty,'vat'=>$price['vat']];
            $snapshot=['version'=>1,'name'=>company_product_display_name($p),'description'=>company_product_display_description($p),'color'=>$p['color'],'sku'=>$p['sku']??null,'decorations'=>company_product_decorations($p),'base_decoration_type'=>$p['decoration_type']??'','base_decoration_details'=>$p['decoration_details']??''];
            $items[]=[$p,$size,$qty,$price,json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)];
        }
        if(!$items) throw new DomainException(t('err.no_items'));
        // 10.11: Rundung je Position und Steuer je Steuersatz wie in der Lexware-Rechnung (pricing.php).
        $calc=yw_calc_items($calcLines,company_employee_share_percent($company));
        $shipping=shipping_breakdown($company,$calc['gross'],$calc['net_by_rate']);
        if(($company['delivery_mode']??'company')==='pickup') $shipping=['net'=>0,'vat'=>null,'gross'=>0,'employee'=>0,'company'=>0];
        $net=$calc['net']+$shipping['net']; $gross=$calc['gross']+$shipping['gross']; $employee=$calc['employee']+$shipping['employee']; $employer=$calc['company']+$shipping['company'];
        yw_check_limits(yw_budget_limit($company,$account),yw_budget_used($company,$account),$employer);
        $delivery=['mode'=>$company['delivery_mode'],'address'=>trim((string)$company['delivery_address']),'company'=>$company['legal_name'],'employee'=>$account['name'],'department'=>$account['department'],'cost_center'=>$account['cost_center']];
        if($delivery['mode']==='company' && $delivery['address']==='') throw new DomainException(t('err.no_delivery_address'));
        $inviteId=ensure_account_invite($company,$account);
        $status=(int)$company['approval_required']===1?'pending':'approved';
        $q=db()->prepare('INSERT INTO orders(company_id,invite_id,employee_account_id,total_cents,net_total_cents,gross_total_cents,employee_total_cents,company_total_cents,shipping_net_cents,shipping_gross_cents,shipping_employee_cents,shipping_company_cents,status,note,submission_token,delivery_snapshot_json,shipping_vat_rate) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $q->execute([$company['id'],$inviteId,$account['id'],$net,$net,$gross,$employee,$employer,$shipping['net'],$shipping['gross'],$shipping['employee'],$shipping['company'],$status,mb_substr(is_string($_POST['note']??null)?trim($_POST['note']):'',0,500),$token,json_encode($delivery,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$shipping['net']>0?$shipping['vat']:null]);
        $orderId=(int)db()->lastInsertId();
        // Stückwerte (unit_*) bleiben zur Anzeige erhalten; maßgeblich sind die Positionswerte (line_*).
        $q=db()->prepare('INSERT INTO order_items(order_id,product_id,product_name,size,quantity,unit_price_cents,unit_net_cents,vat_rate,unit_gross_cents,employee_share_cents,company_share_cents,decoration_snapshot_json,line_net_cents,line_gross_cents,line_employee_cents,line_company_cents) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach($items as $index=>[$p,$size,$qty,$price,$snapshot]){$line=$calc['lines'][$index];$q->execute([$orderId,$p['id'],company_product_display_name($p),$size,$qty,$price['net'],$price['net'],$price['vat'],$price['gross'],$price['employee'],$price['employer'],$snapshot,$line['net'],$line['gross'],$line['employee'],$line['company']]);}
        db()->commit();
    } catch(Throwable $e) {
        if(db()->inTransaction()) db()->rollBack();
        if(!$e instanceof DomainException) error_log('Yanik checkout: '.$e->getMessage());
        flash('error',$e instanceof DomainException?$e->getMessage():t('err.order_failed'));
        redirect('/'.($company['slug']??'').'/produkte');
    }
    audit_log('order.created','order',$orderId,['company_id'=>(int)$company['id'],'status'=>$status]);
    if($status==='approved')yw_handover_enqueue($orderId);
    yw_notify_managers_new_order($company,$orderId,(int)$account['id'],'',$status); // 10.11: mit Freigabe-Links je Verantwortlichem (10.12: je Sprache)
    // 10.12: Bestätigung in der Sprache der Person (Übersicht wird darin neu erstellt)
    $statusUrl=workwearcenter_url((string)$company['slug'].'/bestellungen');
    notify_order_employee($orderId,fn(array $p)=>[t('mail.confirm.subject',['id'=>$orderId]),t('mail.hello',['name'=>$p['name']])."\n\n".t('mail.confirm.body')."\n\n".yw_order_mail_summary($orderId)."\n\n".t('mail.view_status').': '.$statusUrl]);
    flash('success',t('flash.order_saved',['id'=>$orderId]).' '.($status==='pending'?t('flash.order_saved_pending'):t('flash.order_saved_approved')));
    redirect('/'.$company['slug'].'/bestellungen');
}
