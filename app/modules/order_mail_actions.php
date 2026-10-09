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
    if(!in_array($decision,['approved','rejected'],true))return ['error','Ungültige Entscheidung.'];
    if(mb_strlen($decisionNote)>1000 || ($decision==='rejected' && $decisionNote===''))
        return ['error',$decision==='rejected'?'Bitte einen kurzen Ablehnungsgrund angeben.':'Der Hinweis darf höchstens 1.000 Zeichen enthalten.'];

    // Vier-Augen-Prinzip: Eigene Bestellungen gibt eine andere verantwortliche Person frei.
    // Nur wenn es keine weitere aktive verantwortliche Person gibt, ist das erlaubt (wird protokolliert).
    $ownerQuery=db()->prepare('SELECT employee_account_id FROM orders WHERE id=? AND company_id=? LIMIT 1');
    $ownerQuery->execute([$orderId,(int)$company['id']]);
    $selfDecision=(int)$ownerQuery->fetchColumn()===(int)$account['id'];
    if($selfDecision && $decision==='approved' && yw_other_manager_count((int)$company['id'],(int)$account['id'])>0)
        return ['error','Eigene Bestellungen gibt eine andere verantwortliche Person frei (Vier-Augen-Prinzip).'];

    $stmt=db()->prepare("UPDATE orders SET status=?,decision_note=?,decided_by_account_id=?,decided_at=NOW() WHERE id=? AND company_id=? AND status='pending'");
    $stmt->execute([$decision,$decisionNote!==''?$decisionNote:null,(int)$account['id'],$orderId,(int)$company['id']]);
    $changed=$stmt->rowCount()>0;
    if($changed){
        $meta=['company_id'=>(int)$company['id'],'decision'=>$decision]+($selfDecision?['self_decision'=>true]:[]);
        if($via!=='shop')$meta+=['via'=>$via,'decided_by'=>(int)$account['id']];
        audit_log('order.decision','order',$orderId,$meta);
        if($decision==='approved')yw_handover_enqueue($orderId);
        $decisionText=$decision==='approved'?'Deine Bestellung wurde freigegeben und wird nun bearbeitet.':'Deine Bestellung wurde abgelehnt.';
        if($decisionNote!=='')$decisionText.="\nHinweis: ".$decisionNote;
        notify_order_employee($orderId,'Entscheidung zu Bestellung #'.$orderId,$decisionText."\n\n".yw_order_mail_summary($orderId)."\n\nBestellstatus ansehen: ".workwearcenter_url($slug.'/bestellungen'));
        // Noch offene Mail-Links dieser Bestellung sind damit erledigt.
        try{db()->prepare('UPDATE yw_decision_tokens SET used_at=COALESCE(used_at,NOW()) WHERE order_id=?')->execute([$orderId]);}catch(Throwable $ignored){}
    }
    return $changed
        ? ['success',$decision==='approved'?'Bestellung wurde freigegeben.':'Bestellung wurde abgelehnt.']
        : ['error','Die Bestellung wurde bereits bearbeitet oder vom Mitarbeiter zurückgezogen.'];
}

/** Anzahl weiterer aktiver Verantwortlicher (wie im Shop: Grundlage des Vier-Augen-Prinzips). */
function yw_other_manager_count(int $companyId,int $accountId): int {
    $q=db()->prepare("SELECT COUNT(*) FROM employee_accounts WHERE company_id=? AND role='manager' AND active=1 AND anonymized_at IS NULL AND id<>?");
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
    $slug=(string)$company['slug'];
    $intro="Eine neue Bestellung wurde im Firmenshop eingereicht.\n\n".$summary;
    $reviewLink="\n\nBestellung prüfen: ".workwearcenter_url($slug.'/freigaben');
    if($status!=='pending' || !yw_orders_schema_ready()){notify_company_managers((int)$company['id'],'Neue Bestellung #'.$orderId,$intro.$reviewLink);return;}
    try{
        $q=db()->prepare("SELECT id,name,email FROM employee_accounts WHERE company_id=? AND role='manager' AND active=1 AND registration_status='approved' AND anonymized_at IS NULL AND (employment_start IS NULL OR employment_start<=CURDATE()) AND (employment_end IS NULL OR employment_end>=CURDATE())");
        $q->execute([(int)$company['id']]);
        foreach($q->fetchAll() as $manager){
            $own=(int)$manager['id']===$ownerAccountId;
            if($own && yw_other_manager_count((int)$company['id'],(int)$manager['id'])>0){
                send_system_mail((string)$manager['email'],'Deine Bestellung #'.$orderId.' wartet auf Freigabe',"Hallo ".$manager['name'].",\n\ndeine Bestellung wurde gespeichert. Nach dem Vier-Augen-Prinzip gibt eine andere verantwortliche Person sie frei.\n\n".$summary);
                continue;
            }
            $url=workwearcenter_url($slug.'/mail-freigabe/'.yw_create_decision_token($orderId,(int)$manager['id'],(int)$company['id']));
            send_system_mail((string)$manager['email'],'Neue Bestellung #'.$orderId.' – Freigabe erforderlich',
                "Hallo ".$manager['name'].",\n\n".$intro."\n\nDu kannst direkt aus dieser E-Mail entscheiden. Der Link öffnet eine Bestätigungsseite, ist ".YW_DECISION_TOKEN_DAYS." Tage gültig und nur für dich bestimmt."
                ."\n\nFreigeben: ".$url.'?aktion=freigeben'
                ."\nAblehnen: ".$url.'?aktion=ablehnen'
                .$reviewLink);
        }
    }catch(Throwable $e){
        error_log('WorkwearCenter decision mail: '.$e->getMessage());
        notify_company_managers((int)$company['id'],'Neue Bestellung #'.$orderId,$intro.$reviewLink);
    }
}

/* ---------- Bestätigungsseite ---------- */

function yw_mail_decision_page(string $slug,string $token): void {
    $q=db()->prepare('SELECT * FROM companies WHERE slug=? AND active=1 LIMIT 1');$q->execute([$slug]);$company=$q->fetch();
    if(!$company || !yw_orders_schema_ready()){http_response_code(404);simple_message('Link ungültig','Dieser Freigabe-Link ist nicht gültig.');return;}
    $throttleKey='mail-decision-'.(int)$company['id'];
    if(login_throttled($throttleKey,30)){http_response_code(429);simple_message('Zu viele Versuche','Bitte 15 Minuten warten und den Link dann erneut öffnen.');return;}
    $messages=['invalid'=>['Link ungültig','Dieser Freigabe-Link ist nicht gültig. Bitte im Firmenshop unter „Freigaben“ entscheiden.'],
        'used'=>['Link bereits verwendet','Über diesen Link wurde bereits entschieden. Den aktuellen Stand siehst du im Firmenshop unter „Freigaben“.'],
        'expired'=>['Link abgelaufen','Der Link war '.YW_DECISION_TOKEN_DAYS.' Tage gültig. Bitte im Firmenshop unter „Freigaben“ entscheiden.'],
        'decided'=>['Bereits entschieden','Diese Bestellung wurde bereits bearbeitet oder vom Mitarbeiter zurückgezogen.']];
    [$row,$account]=yw_resolve_decision_token($company,$token);
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
            $type='error';$message='Die Entscheidung konnte nicht gespeichert werden. Bitte erneut versuchen.';
        }
        if($type==='success'){simple_message($decision==='approved'?'Bestellung freigegeben':'Bestellung abgelehnt',$message.' Der Mitarbeiter wird per E-Mail informiert.');return;}
        $error=$message;
    }
    $preset=(string)($_GET['aktion']??'')==='ablehnen'?'ablehnen':'freigeben';
    $q=db()->prepare("SELECT o.*,COALESCE(ea.name,i.employee_name,'Unbekannt') employee_name FROM orders o LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id LEFT JOIN invites i ON i.id=o.invite_id WHERE o.id=? AND o.company_id=? LIMIT 1");
    $q->execute([$orderId,(int)$company['id']]);$order=$q->fetch();
    $selfBlocked=(int)$order['employee_account_id']===(int)$account['id'] && yw_other_manager_count((int)$company['id'],(int)$account['id'])>0;

    render_head('Bestellung #'.$orderId.' entscheiden – '.$company['shop_name'],$company);
    echo company_shop_style($company);
    echo '<style>body{background:#f4f6f9}.yw-md{max-width:720px;margin:0 auto;padding:32px 16px 48px}.yw-md-card{background:#fff;border:1px solid #dce2e7;border-radius:6px;padding:26px}.yw-md-card h1{font-size:1.6rem;margin:0 0 6px}.yw-md-logo{display:flex;align-items:center;min-height:56px;margin-bottom:18px}.yw-md-logo img{max-height:56px;max-width:240px;object-fit:contain}.yw-md-items{list-style:none;margin:16px 0;padding:0;display:grid;gap:7px}.yw-md-items li{display:flex;justify-content:space-between;gap:14px;padding:9px 11px;background:#f8fafc;border-radius:4px;font-size:.92rem}.yw-md-sum{display:flex;flex-wrap:wrap;gap:8px 22px;margin:0 0 18px;color:#475467}.yw-md-actions{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:8px}.yw-md-actions form{display:grid;gap:10px;align-content:start;padding:16px;border:1px solid #e3e8ef;border-radius:5px}.yw-md-actions form.active{border-color:var(--shop-accent,#ff6b00);box-shadow:0 0 0 2px color-mix(in srgb,var(--shop-accent,#ff6b00) 20%,#fff)}.yw-md-actions textarea{min-height:80px;border:1px solid #d8dee8;border-radius:4px;padding:9px;font:inherit}.yw-md-actions label{display:grid;gap:6px;font-weight:700;font-size:.88rem}.yw-md-btn{min-height:44px;border-radius:4px;font-weight:850;cursor:pointer;border:1px solid var(--shop-accent,#ff6b00);background:var(--shop-accent,#ff6b00);color:#fff}.yw-md-btn.reject{background:#fff;color:#b42318;border-color:#f4c7c2}.yw-md small.muted{color:#667085}@media(max-width:640px){.yw-md-actions{grid-template-columns:1fr}}</style>';
    echo '<main class="yw-md"><div class="yw-md-logo">';
    $logo=yw_company_logo_path($company);
    echo $logo!==''?'<img src="/'.e($logo).'" alt="'.e($company['legal_name']).'">':'<strong>'.e($company['legal_name']).'</strong>';
    echo '</div><section class="yw-md-card"><h1>Bestellung #'.$orderId.' entscheiden</h1><p>'.e($order['employee_name']).' · '.e(date('d.m.Y H:i',strtotime((string)$order['created_at']))).' Uhr · angemeldet als Verantwortliche/r <strong>'.e($account['name']).'</strong></p>';
    if($error!=='')echo '<div class="flash error">'.e($error).'</div>';
    echo '<ul class="yw-md-items">';
    foreach(order_items($orderId) as $item){$unit=(int)($item['unit_gross_cents']??0);if($unit<=0)$unit=(int)$item['unit_price_cents'];echo '<li><span>'.(int)$item['quantity'].'× '.e($item['product_name']).' · Größe '.e($item['size']).'</span><strong>'.money($unit*(int)$item['quantity']).'</strong></li>';}
    if((int)($order['shipping_gross_cents']??0)>0)echo '<li><span>Versand</span><strong>'.money((int)$order['shipping_gross_cents']).'</strong></li>';
    echo '</ul>';
    $gross=(int)($order['gross_total_cents']??0);if($gross<=0)$gross=(int)$order['total_cents'];
    echo '<div class="yw-md-sum"><span>Gesamt brutto <strong>'.money($gross).'</strong></span><span>Firma <strong>'.money((int)$order['company_total_cents']).'</strong></span><span>Mitarbeiter <strong>'.money((int)$order['employee_total_cents']).'</strong></span></div>';
    if(trim((string)($order['note']??''))!=='')echo '<p><strong>Hinweis des Mitarbeiters:</strong> '.e((string)$order['note']).'</p>';
    echo '<div class="yw-md-actions">';
    if($selfBlocked)echo '<div class="flash info">Das ist deine eigene Bestellung. Freigeben muss sie eine andere verantwortliche Person (Vier-Augen-Prinzip).</div>';
    else echo '<form method="post" class="'.($preset==='freigeben'?'active':'').'">'.csrf_field().'<input type="hidden" name="decision" value="approved"><label>Hinweis <small class="muted">optional</small><textarea name="decision_note" maxlength="1000"></textarea></label><button class="yw-md-btn" type="submit">Bestellung freigeben</button></form>';
    echo '<form method="post" class="'.($preset==='ablehnen'?'active':'').'">'.csrf_field().'<input type="hidden" name="decision" value="rejected"><label>Ablehnungsgrund <small class="muted">erforderlich</small><textarea name="decision_note" maxlength="1000" required placeholder="Kurzer Hinweis für den Mitarbeiter"></textarea></label><button class="yw-md-btn reject" type="submit">Bestellung ablehnen</button></form>';
    echo '</div><p class="muted" style="margin-top:18px"><small class="muted">Erst mit dem Klick auf eine der Schaltflächen wird entschieden. Alle Freigaben findest du auch im Firmenshop unter <a href="/'.e($slug).'/freigaben">Freigaben</a>.</small></p></section></main>';
    company_shop_footer($company);
    render_foot();
}
