<?php
declare(strict_types=1);

/*
 * 10.11: Bestellfreigabe direkt aus der E-Mail.
 *
 * Die Freigabe-Anforderung an Verantwortliche enthält „Freigeben“ und „Ablehnen“. Beide führen
 * auf eine Bestätigungsseite: GET zeigt nur an (Link-Scanner in Mailprogrammen lösen so nichts
 * aus), erst ein POST mit CSRF-Prüfung entscheidet. Das Token ist je Bestellung und
 * verantwortlicher Person einmalig, 7 Tage gültig und mit einem Serverschlüssel signiert
 * (Nonce + HMAC); in der Datenbank liegt nur der Hash der Nonce.
 * Die Entscheidungsregeln (inkl. Vier-Augen-Prinzip) teilen sich Shop und Mail: yw_order_decide().
 */

const YW_DECISION_TOKEN_DAYS = 7;

/**
 * Entscheidung über eine offene Bestellung – gemeinsame Regeln für Shop und E-Mail-Link.
 * @return array{0:string,1:string} [flash-Typ, Meldung]
 */
function yw_order_decide(array $company,array $account,int $orderId,string $decision,string $decisionNote,string $via='shop'): array {
    $slug=(string)$company['slug'];
    $decisionNote=trim($decisionNote);
    if(!in_array($decision,['approved','rejected'],true))return ['error',t('err.decision_invalid')];
    if(mb_strlen($decisionNote)>1000 || ($decision==='rejected' && $decisionNote===''))
        return ['error',$decision==='rejected'?t('err.reason_required'):t('err.note_too_long')];

    // Vier-Augen-Prinzip: Eigene Bestellungen gibt eine andere verantwortliche Person frei.
    // Nur wenn es keine weitere aktuell zugangsberechtigte verantwortliche Person gibt, ist das erlaubt (eigener Protokolleintrag).
    $ownerQuery=db()->prepare('SELECT employee_account_id FROM orders WHERE id=? AND company_id=? LIMIT 1');
    $ownerQuery->execute([$orderId,(int)$company['id']]);
    $selfDecision=(int)$ownerQuery->fetchColumn()===(int)$account['id'];
    $selfApproval=$selfDecision && $decision==='approved';
    if($selfApproval && yw_other_manager_count((int)$company['id'],(int)$account['id'])>0)
        return ['error',t('err.four_eyes_order')];

    $stmt=db()->prepare("UPDATE orders SET status=?,decision_note=?,decided_by_account_id=?,decided_at=NOW() WHERE id=? AND company_id=? AND status='pending'");
    $stmt->execute([$decision,$decisionNote!==''?$decisionNote:null,(int)$account['id'],$orderId,(int)$company['id']]);
    $changed=$stmt->rowCount()>0;
    if($changed){
        $meta=['company_id'=>(int)$company['id'],'decision'=>$decision]+($selfDecision?['self_decision'=>true]:[]);
        if($via!=='shop')$meta+=['via'=>$via,'decided_by'=>(int)$account['id']];
        audit_log('order.decision','order',$orderId,$meta);
        if($selfApproval)audit_log('order.four_eyes_fallback','order',$orderId,['company_id'=>(int)$company['id'],'decided_by'=>(int)$account['id'],'via'=>$via,'reason'=>'einzige zugangsberechtigte verantwortliche Person']); // 10.15
        if($decision==='approved')yw_handover_enqueue($orderId);
        // 10.12: Mail in der Sprache der bestellenden Person
        $statusUrl=workwearcenter_url($slug.'/bestellungen');
        notify_order_employee($orderId,function(array $p) use($orderId,$decision,$decisionNote,$statusUrl){
            $text=t('mail.hello',['name'=>$p['name']])."\n\n".($decision==='approved'?t('mail.decision.approved'):t('mail.decision.rejected'));
            if($decisionNote!=='')$text.="\n".t('mail.note').': '.$decisionNote;
            return [t('mail.decision.subject',['id'=>$orderId]),$text."\n\n".yw_order_mail_summary($orderId)."\n\n".t('mail.view_status').': '.$statusUrl];
        });
        // Noch offene Mail-Links dieser Bestellung sind damit erledigt.
        try{db()->prepare('UPDATE yw_decision_tokens SET used_at=COALESCE(used_at,NOW()) WHERE order_id=?')->execute([$orderId]);}catch(Throwable $ignored){}
    }
    return $changed
        ? ['success',$decision==='approved'?t('flash.order_approved'):t('flash.order_rejected')]
        : ['error',t('err.order_already_decided')];
}

/**
 * Anzahl weiterer Verantwortlicher, die heute freigeben können (Grundlage des Vier-Augen-Prinzips für Shop,
 * Mail-Link und Umtausch). 10.15: gleiche Bedingungen wie Anmeldung und Freigabe-Link (freigegeben, Ein-/Austritt),
 * damit ein gesperrtes oder ausgeschiedenes Konto die einzige verantwortliche Person nicht aussperrt.
 */
function yw_other_manager_count(int $companyId,int $accountId): int {
    $q=db()->prepare("SELECT COUNT(*) FROM employee_accounts WHERE company_id=? AND role='manager' AND active=1 AND registration_status='approved' AND anonymized_at IS NULL AND (employment_start IS NULL OR employment_start<=CURDATE()) AND (employment_end IS NULL OR employment_end>=CURDATE()) AND id<>?");
    $q->execute([$companyId,$accountId]);
    return (int)$q->fetchColumn();
}

/* ---------- Token ---------- */

/** Serverschlüssel für signierte Mail-Links (optional config 'app_secret' + zufälliger Systemschlüssel). */
function yw_mail_action_key(): string {
    static $key=null;
    if($key!==null)return $key;
    $stored=current_site_setting('mail_action_key','');
    if(!preg_match('/^[a-f0-9]{64}$/D',$stored)){
        try{db()->prepare('INSERT IGNORE INTO site_settings(setting_key,setting_value) VALUES(?,?)')->execute(['mail_action_key',bin2hex(random_bytes(32))]);}catch(Throwable $ignored){}
        $stored=current_site_setting('mail_action_key','');
    }
    if(!preg_match('/^[a-f0-9]{64}$/D',$stored))throw new RuntimeException('Schlüssel für Mail-Links fehlt.');
    return $key=hash_hmac('sha256',$stored,'wwc-mail-action|'.(string)(cfg('app_secret')??''));
}

/** Signatur über Nonce und Bindung (Bestellung, Person, Firma, Ablauf). */
function yw_decision_signature(string $nonce,int $orderId,int $accountId,int $companyId,string $expiresAt,string $key): string {
    return hash_hmac('sha256',implode('|',['order-decision',$nonce,$orderId,$accountId,$companyId,$expiresAt]),$key);
}

/** Zerlegt ein Link-Token (32 Hex Nonce + 64 Hex Signatur) oder null. */
function yw_decision_token_parts(string $token): ?array {
    if(!preg_match('/^([a-f0-9]{32})([a-f0-9]{64})$/D',$token,$m))return null;
    return [$m[1],$m[2]];
}

function yw_create_decision_token(int $orderId,int $accountId,int $companyId): string {
    $nonce=bin2hex(random_bytes(16));
    $expires=(new DateTimeImmutable('+'.YW_DECISION_TOKEN_DAYS.' days'))->format('Y-m-d H:i:s');
    db()->prepare('INSERT INTO yw_decision_tokens(order_id,account_id,company_id,nonce_hash,expires_at) VALUES(?,?,?,?,?)')
        ->execute([$orderId,$accountId,$companyId,hash('sha256',$nonce),$expires]);
    return $nonce.yw_decision_signature($nonce,$orderId,$accountId,$companyId,$expires,yw_mail_action_key());
}

/**
 * Prüft ein Token gegen Firma (Kurzname), Signatur, Ablauf, Einmaligkeit, offene Bestellung und
 * aktive verantwortliche Person. Rückgabe: [Tokenzeile, Konto] oder [null, Grund].
 */
function yw_resolve_decision_token(array $company,string $token,bool $lock=false): array {
    $parts=yw_decision_token_parts($token);
    if(!$parts)return [null,'invalid'];
    [$nonce,$signature]=$parts;
    $q=db()->prepare('SELECT * FROM yw_decision_tokens WHERE nonce_hash=? LIMIT 1'.($lock?' FOR UPDATE':''));
    $q->execute([hash('sha256',$nonce)]);$row=$q->fetch();
    if(!$row)return [null,'invalid'];
    $expected=yw_decision_signature($nonce,(int)$row['order_id'],(int)$row['account_id'],(int)$row['company_id'],(string)$row['expires_at'],yw_mail_action_key());
    if(!hash_equals($expected,$signature))return [null,'invalid'];
    if((int)$row['company_id']!==(int)$company['id'])return [null,'invalid'];
    if($row['used_at']!==null)return [null,'used'];
    if(strtotime((string)$row['expires_at'])<=time())return [null,'expired'];
    $q=db()->prepare("SELECT * FROM employee_accounts WHERE id=? AND company_id=? AND role='manager' AND active=1 AND registration_status='approved' AND anonymized_at IS NULL AND (employment_start IS NULL OR employment_start<=CURDATE()) AND (employment_end IS NULL OR employment_end>=CURDATE()) LIMIT 1");
    $q->execute([(int)$row['account_id'],(int)$company['id']]);$account=$q->fetch();
    if(!$account)return [null,'invalid'];
    $q=db()->prepare('SELECT status FROM orders WHERE id=? AND company_id=? LIMIT 1');$q->execute([(int)$row['order_id'],(int)$company['id']]);
    if((string)$q->fetchColumn()!=='pending')return [null,'decided'];
    return [$row,$account];
}

/* ---------- Mail an Verantwortliche ---------- */

/**
 * Benachrichtigt die Verantwortlichen über eine neue Bestellung. Bei offener Freigabe erhält jede
 * Person eigene Links „Freigeben“/„Ablehnen“ (außer bei der eigenen Bestellung, wenn eine weitere
 * verantwortliche Person freigeben kann).
 */
function yw_notify_managers_new_order(array $company,int $orderId,int $ownerAccountId,string $summary,string $status): void {
    // 10.12: Übersicht und Texte je verantwortlicher Person in deren Sprache ($summary bleibt nur für ältere Aufrufer).
    $slug=(string)$company['slug'];
    $reviewUrl=workwearcenter_url($slug.'/freigaben');
    $plain=function(array $m) use($orderId,$reviewUrl): array {
        return [t('mail.new_order.subject',['id'=>$orderId]),t('mail.new_order.intro')."\n\n".yw_order_mail_summary($orderId)."\n\n".t('mail.review_order').': '.$reviewUrl];
    };
    if($status!=='pending' || !yw_orders_schema_ready()){notify_company_managers((int)$company['id'],$plain);return;}
    try{
        $q=db()->prepare("SELECT ea.id,ea.name,ea.email,ea.language,c.shop_languages FROM employee_accounts ea JOIN companies c ON c.id=ea.company_id WHERE ea.company_id=? AND ea.role='manager' AND ea.active=1 AND ea.registration_status='approved' AND ea.anonymized_at IS NULL AND (ea.employment_start IS NULL OR ea.employment_start<=CURDATE()) AND (ea.employment_end IS NULL OR ea.employment_end>=CURDATE())");
        $q->execute([(int)$company['id']]);
        foreach($q->fetchAll() as $manager){
            $own=(int)$manager['id']===$ownerAccountId;
            if($own && yw_other_manager_count((int)$company['id'],(int)$manager['id'])>0){
                yw_mail_to_person($manager,fn(array $m)=>[t('mail.own_order.subject',['id'=>$orderId]),t('mail.hello',['name'=>$m['name']])."\n\n".t('mail.own_order.body')."\n\n".yw_order_mail_summary($orderId)]);
                continue;
            }
            $url=workwearcenter_url($slug.'/mail-freigabe/'.yw_create_decision_token($orderId,(int)$manager['id'],(int)$company['id']));
            yw_mail_to_person($manager,fn(array $m)=>[t('mail.approval.subject',['id'=>$orderId]),
                t('mail.hello',['name'=>$m['name']])."\n\n".t('mail.new_order.intro')."\n\n".yw_order_mail_summary($orderId)."\n\n".t('mail.approval.body',['days'=>YW_DECISION_TOKEN_DAYS])
                ."\n\n".t('mail.approval.approve').': '.$url.'?aktion=freigeben'
                ."\n".t('mail.approval.reject').': '.$url.'?aktion=ablehnen'
                ."\n\n".t('mail.review_order').': '.$reviewUrl]);
        }
    }catch(Throwable $e){
        error_log('WorkwearCenter decision mail: '.$e->getMessage());
        notify_company_managers((int)$company['id'],$plain);
    }
}

/* ---------- Bestätigungsseite ---------- */

function yw_mail_decision_page(string $slug,string $token): void {
    $q=db()->prepare('SELECT * FROM companies WHERE slug=? AND active=1 LIMIT 1');$q->execute([$slug]);$company=$q->fetch();
    if(!$company || !yw_orders_schema_ready()){http_response_code(404);simple_message('Link ungültig','Dieser Freigabe-Link ist nicht gültig.');return;}
    yw_shop_lang_init($company); // Fehlerseiten nach Browser/Cookie; mit gültigem Link gilt die Sprache der verantwortlichen Person
    $throttleKey='mail-decision-'.(int)$company['id'];
    if(login_throttled($throttleKey,30)){http_response_code(429);simple_message(t('err.too_many_title'),t('err.too_many_link'));return;}
    [$row,$account]=yw_resolve_decision_token($company,$token);
    if($row)yw_set_lang(yw_account_lang($account,$company)); // gültiger Link: Sprache der verantwortlichen Person
    $messages=['invalid'=>[t('decide.invalid_title'),t('decide.invalid_text')],
        'used'=>[t('decide.used_title'),t('decide.used_text')],
        'expired'=>[t('decide.expired_title'),t('decide.expired_text',['days'=>YW_DECISION_TOKEN_DAYS])],
        'decided'=>[t('decide.decided_title'),t('err.order_already_decided')]];
    if(!$row){
        if($account==='invalid')record_login_failure($throttleKey);
        http_response_code($account==='invalid'?404:410);
        [$title,$text]=$messages[$account]??$messages['invalid'];
        simple_message($title,$text);return;
    }
    $orderId=(int)$row['order_id'];
    $error='';
    if(is_post()){
        verify_csrf();
        $decision=(string)($_POST['decision']??'');
        $note=is_string($_POST['decision_note']??null)?$_POST['decision_note']:'';
        try{
            db()->beginTransaction();
            [$locked,$lockedAccount]=yw_resolve_decision_token($company,$token,true);
            if(!$locked){db()->rollBack();[$title,$text]=$messages[$lockedAccount]??$messages['invalid'];http_response_code(410);simple_message($title,$text);return;}
            [$type,$message]=yw_order_decide($company,$lockedAccount,$orderId,$decision,$note,'mail');
            if($type==='success')db()->prepare('UPDATE yw_decision_tokens SET used_at=NOW() WHERE id=?')->execute([(int)$locked['id']]);
            db()->commit();
        }catch(Throwable $e){
            if(db()->inTransaction())db()->rollBack();
            error_log('WorkwearCenter mail decision: '.$e->getMessage());
            $type='error';$message=t('err.decision_failed');
        }
        if($type==='success'){simple_message($decision==='approved'?t('decide.done_approved'):t('decide.done_rejected'),$message.' '.t('decide.employee_informed'));return;}
        $error=$message;
    }
    $preset=(string)($_GET['aktion']??'')==='ablehnen'?'ablehnen':'freigeben';
    $q=db()->prepare("SELECT o.*,COALESCE(ea.name,i.employee_name,'') employee_name FROM orders o LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id LEFT JOIN invites i ON i.id=o.invite_id WHERE o.id=? AND o.company_id=? LIMIT 1");
    $q->execute([$orderId,(int)$company['id']]);$order=$q->fetch();
    $own=(int)$order['employee_account_id']===(int)$account['id'];
    $selfBlocked=$own && yw_other_manager_count((int)$company['id'],(int)$account['id'])>0;

    render_head(t('decide.page_title',['id'=>$orderId,'shop'=>$company['shop_name']]),$company);
    echo company_shop_style($company);
    echo '<style>body{background:#f4f6f9}.yw-md{max-width:720px;margin:0 auto;padding:32px 16px 48px}.yw-md-card{background:#fff;border:1px solid #dce2e7;border-radius:6px;padding:26px}.yw-md-card h1{font-size:1.6rem;margin:0 0 6px}.yw-md-logo{display:flex;align-items:center;min-height:56px;margin-bottom:18px}.yw-md-logo img{max-height:56px;max-width:240px;object-fit:contain}.yw-md-items{list-style:none;margin:16px 0;padding:0;display:grid;gap:7px}.yw-md-items li{display:flex;justify-content:space-between;gap:14px;padding:9px 11px;background:#f8fafc;border-radius:4px;font-size:.92rem}.yw-md-sum{display:flex;flex-wrap:wrap;gap:8px 22px;margin:0 0 18px;color:#475467}.yw-md-actions{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:8px}.yw-md-actions form{display:grid;gap:10px;align-content:start;padding:16px;border:1px solid #e3e8ef;border-radius:5px}.yw-md-actions form.active{border-color:var(--shop-accent,#ff6b00);box-shadow:0 0 0 2px color-mix(in srgb,var(--shop-accent,#ff6b00) 20%,#fff)}.yw-md-actions textarea{min-height:80px;border:1px solid #d8dee8;border-radius:4px;padding:9px;font:inherit}.yw-md-actions label{display:grid;gap:6px;font-weight:700;font-size:.88rem}.yw-md-btn{min-height:44px;border-radius:4px;font-weight:850;cursor:pointer;border:1px solid var(--shop-accent,#ff6b00);background:var(--shop-accent,#ff6b00);color:#fff}.yw-md-btn.reject{background:#fff;color:#b42318;border-color:#f4c7c2}.yw-md small.muted{color:#667085}@media(max-width:640px){.yw-md-actions{grid-template-columns:1fr}}</style>';
    echo '<main class="yw-md"><div class="yw-md-logo">';
    $logo=yw_company_logo_path($company);
    echo $logo!==''?'<img src="/'.e($logo).'" alt="'.e($company['legal_name']).'">':'<strong>'.e($company['legal_name']).'</strong>';
    echo '</div><section class="yw-md-card"><h1>'.e(t('decide.heading',['id'=>$orderId])).'</h1><p>'.e($order['employee_name']!==''?$order['employee_name']:t('common.unknown')).' · '.e(yw_date((string)$order['created_at'],'datetime')).' · '.t('decide.as',['name'=>'<strong>'.e($account['name']).'</strong>']).'</p>';
    if($error!=='')echo '<div class="flash error">'.e($error).'</div>';
    echo '<ul class="yw-md-items">';
    foreach(order_items($orderId) as $item)echo '<li><span>'.(int)$item['quantity'].'× '.e($item['product_name']).' · '.e(t('common.size_value',['size'=>$item['size']])).'</span><strong>'.money(yw_order_item_amounts($item)['gross']).'</strong></li>';
    if((int)($order['shipping_gross_cents']??0)>0)echo '<li><span>'.e(t('mail.sum.shipping')).'</span><strong>'.money((int)$order['shipping_gross_cents']).'</strong></li>';
    echo '</ul>';
    $gross=(int)($order['gross_total_cents']??0);if($gross<=0)$gross=(int)$order['total_cents'];
    echo '<div class="yw-md-sum"><span>'.e(t('cart.total')).' <strong>'.money($gross).'</strong></span><span>'.e(t('approvals.company')).' <strong>'.money((int)$order['company_total_cents']).'</strong></span><span>'.e(t('approvals.employee')).' <strong>'.money((int)$order['employee_total_cents']).'</strong></span></div>';
    if(trim((string)($order['note']??''))!=='')echo '<p><strong>'.e(t('decide.employee_note')).':</strong> '.e((string)$order['note']).'</p>';
    if($own && !$selfBlocked)echo '<div class="flash info">'.e(t('approvals.own_order_single')).'</div>'; // 10.15: Rückfall bei nur einer verantwortlichen Person (protokolliert)
    echo '<div class="yw-md-actions">';
    if($selfBlocked)echo '<div class="flash info">'.e(t('decide.own_order')).'</div>';
    else echo '<form method="post" class="'.($preset==='freigeben'?'active':'').'">'.csrf_field().'<input type="hidden" name="decision" value="approved"><label>'.e(t('approvals.note')).' <small class="muted">'.e(t('common.optional')).'</small><textarea name="decision_note" maxlength="1000"></textarea></label><button class="yw-md-btn" type="submit">'.e(t('decide.approve_button')).'</button></form>';
    echo '<form method="post" class="'.($preset==='ablehnen'?'active':'').'">'.csrf_field().'<input type="hidden" name="decision" value="rejected"><label>'.e(t('decide.reason')).' <small class="muted">'.e(t('common.required')).'</small><textarea name="decision_note" maxlength="1000" required placeholder="'.e(t('approvals.note_placeholder')).'"></textarea></label><button class="yw-md-btn reject" type="submit">'.e(t('decide.reject_button')).'</button></form>';
    echo '</div><p class="muted" style="margin-top:18px"><small class="muted">'.t('decide.footer_note',['link'=>'<a href="/'.e($slug).'/freigaben">'.e(t('nav.approvals')).'</a>']).'</small></p></section></main>';
    company_shop_footer($company);
    render_foot();
}

/* ---------- 10.12: Statusmails an Mitarbeitende (Verwaltung, Schnittstelle) ---------- */

/** „Neuer Stand deiner Bestellung“ in der Sprache der Person; optional mit Sendungsverfolgung. */
function yw_notify_order_status(int $orderId,string $status,string $slug,string $carrier='',string $number='',?string $trackingLink=null): void {
    $statusUrl=workwearcenter_url($slug.'/bestellungen');
    notify_order_employee($orderId,function(array $p) use($orderId,$status,$carrier,$number,$trackingLink,$statusUrl): array {
        $text=t('mail.hello',['name'=>$p['name']])."\n\n".t('mail.status.body',['order'=>yw_order_number($orderId),'status'=>company_portal_fulfillment_status($status)]);
        if($number!=='')$text.="\n\n".t('mail.status.shipped_with',['carrier'=>$carrier!==''?$carrier:t('mail.status.carrier')])."\n".t('progress.tracking_number').': '.$number;
        if($trackingLink)$text.="\n".t('mail.track').': '.$trackingLink;
        return [t('mail.status.subject',['order'=>yw_order_number($orderId)]),$text."\n\n".t('mail.view_status').': '.$statusUrl];
    });
}

/** Storno durch die Verwaltung: Mail an die Person und (bei freigegebenen Aufträgen) an die Verantwortlichen. */
function yw_notify_order_cancelled_by_admin(int $orderId,int $companyId,string $slug,string $reason,bool $managers): void {
    $url=workwearcenter_url($slug.'/bestellungen');
    notify_order_employee($orderId,fn(array $p)=>[t('mail.admin_cancel.subject',['order'=>yw_order_number($orderId)]),t('mail.hello',['name'=>$p['name']])."\n\n".t('mail.admin_cancel.body',['order'=>yw_order_number($orderId)])."\n\n".t('mail.admin_cancel.reason').': '.$reason."\n\n".t('mail.admin_cancel.budget_free')."\n".t('x.mail.view_orders').': '.$url]);
    if($managers)notify_company_managers($companyId,fn(array $m)=>[t('mail.admin_cancel.subject',['order'=>yw_order_number($orderId)]),t('mail.admin_cancel.body_manager',['order'=>yw_order_number($orderId)])."\n\n".t('mail.admin_cancel.reason').': '.$reason]);
}
