<?php
declare(strict_types=1);

/*
 * 10.11: Anmeldung per E-Mail-Link für Mitarbeitende (zusätzlich zum Passwort-Login).
 *
 * - Einmal-Token (256 Bit), in der Datenbank nur als SHA-256-Hash, 15 Minuten gültig,
 *   einmal nutzbar und an Konto und Firma gebunden.
 * - Gleiche Antwort, egal ob die Adresse existiert; Drosselung über login_throttled().
 * - Der Link öffnet eine Bestätigungsseite; angemeldet wird erst per POST mit CSRF-Prüfung,
 *   damit Link-Scanner in Mailprogrammen das Token nicht verbrauchen.
 */

const YW_LOGIN_LINK_MINUTES = 15;

function yw_login_link_url(string $slug,string $token): string {
    return workwearcenter_url($slug.'/anmeldelink/'.$token);
}

/** Zusätzliches Formular unter dem Passwort-Login. */
function yw_magic_login_form(array $company): string {
    if(!yw_orders_schema_ready())return '';
    return '<details class="yw-magic-login" style="margin-top:18px;padding-top:16px;border-top:1px solid #e7ebf0"><summary style="cursor:pointer;font-weight:750">'.e(t('magic.summary')).'</summary>'
        .'<form method="post" action="/'.e($company['slug']).'/anmeldelink" class="form-stack" style="margin-top:12px">'.csrf_field()
        .'<div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>'
        .'<label>'.e(t('common.email')).'<input type="email" name="email" autocomplete="email" required></label>'
        .'<button class="btn" type="submit">'.e(t('magic.send')).'</button>'
        .'<small style="color:#667085">'.e(t('magic.hint',['minutes'=>YW_LOGIN_LINK_MINUTES])).'</small></form></details>';
}

/** POST /<slug>/anmeldelink – Link anfordern. Antwortet immer gleich. */
function yw_magic_login_request(string $slug): void {
    $q=db()->prepare('SELECT * FROM companies WHERE slug=? AND active=1 LIMIT 1');$q->execute([$slug]);$company=$q->fetch();
    if(!$company || !yw_orders_schema_ready()){http_response_code(404);simple_message('Shop nicht gefunden','Dieser Unternehmensshop ist nicht verfügbar.');return;}
    if(!is_post())redirect('/'.$slug.'/login');
    yw_shop_lang_init($company);
    verify_csrf();
    $email=strtolower(trim(is_string($_POST['email']??null)?$_POST['email']:''));
    $botField=trim(is_string($_POST['website']??null)?$_POST['website']:'');
    $globalKey='magic-global-'.(int)$company['id'];
    if($botField==='' && !login_throttled($globalKey,40) && filter_var($email,FILTER_VALIDATE_EMAIL)){
        record_login_failure($globalKey);
        $emailKey='magic-email-'.(int)$company['id'].'-'.hash('sha256',$email);
        // Je Adresse höchstens 5 Links in 15 Minuten, unabhängig von der IP.
        if(!login_throttled($emailKey,5,false)){
            record_login_failure($emailKey,false);
            $q=db()->prepare("SELECT id,name,email,language FROM employee_accounts WHERE company_id=? AND email=? AND active=1 AND registration_status='approved' AND anonymized_at IS NULL AND (employment_start IS NULL OR employment_start<=CURDATE()) AND (employment_end IS NULL OR employment_end>=CURDATE()) LIMIT 1");
            $q->execute([(int)$company['id'],$email]);$account=$q->fetch();
            if($account){
                $token=random_token();
                // Ältere, noch offene Links dieser Person werden ungültig.
                db()->prepare('UPDATE yw_login_links SET used_at=NOW() WHERE account_id=? AND used_at IS NULL')->execute([(int)$account['id']]);
                db()->prepare('INSERT INTO yw_login_links(account_id,company_id,token_hash,expires_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL '.YW_LOGIN_LINK_MINUTES.' MINUTE))')
                    ->execute([(int)$account['id'],(int)$company['id'],hash('sha256',$token)]);
                // 10.12: Sprache des Kontos, sonst die gerade angezeigte Sprache
                $linkUrl=yw_login_link_url($slug,$token);
                yw_with_lang(yw_lang_valid($account['language']??null)?yw_account_lang($account,$company):yw_lang(),fn()=>send_system_mail((string)$account['email'],t('mail.magic.subject',['shop'=>$company['shop_name']]),
                    t('mail.hello',['name'=>$account['name']])."\n\n".t('mail.magic.body',['minutes'=>YW_LOGIN_LINK_MINUTES])."\n\n".t('mail.magic.button').': '.$linkUrl."\n\n".t('mail.ignore')));
                audit_log('employee.login_link_requested','employee',(int)$account['id'],['company_id'=>(int)$company['id']]);
            }
        }
    }
    yw_shop_auth_message($company,(string)$company['slug'],t('pw.mail_checked_title'),t('magic.sent',['minutes'=>YW_LOGIN_LINK_MINUTES]),yw_magic_login_intro());
}

/** Gültiges Token → [Linkzeile, Konto] oder null. */
function yw_login_link_resolve(array $company,string $token,bool $lock=false): ?array {
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))return null;
    $q=db()->prepare("SELECT l.id link_id,ea.* FROM yw_login_links l JOIN employee_accounts ea ON ea.id=l.account_id
        WHERE l.token_hash=? AND l.company_id=? AND ea.company_id=l.company_id AND l.used_at IS NULL AND l.expires_at>NOW()
        AND ea.active=1 AND ea.registration_status='approved' AND ea.anonymized_at IS NULL
        AND (ea.employment_start IS NULL OR ea.employment_start<=CURDATE()) AND (ea.employment_end IS NULL OR ea.employment_end>=CURDATE()) LIMIT 1".($lock?' FOR UPDATE':''));
    $q->execute([hash('sha256',$token),(int)$company['id']]);
    $row=$q->fetch();
    return $row?:null;
}

/** GET/POST /<slug>/anmeldelink/<token> */
function yw_magic_login_page(string $slug,string $token): void {
    $q=db()->prepare('SELECT * FROM companies WHERE slug=? AND active=1 LIMIT 1');$q->execute([$slug]);$company=$q->fetch();
    if(!$company || !yw_orders_schema_ready()){http_response_code(404);simple_message('Link ungültig','Bitte fordere auf der Anmeldeseite einen neuen Link an.');return;}
    $throttleKey='magic-token-'.(int)$company['id'];
    yw_shop_lang_init($company);
    if(login_throttled($throttleKey,20)){http_response_code(429);simple_message(t('err.too_many_title'),t('err.wait15'));return;}
    $row=yw_login_link_resolve($company,$token);
    if($row)yw_shop_lang_init($company,$row);
    if(!$row){
        record_login_failure($throttleKey);
        http_response_code(410);
        yw_shop_auth_message($company,$slug,t('magic.expired_title'),t('magic.expired_text',['minutes'=>YW_LOGIN_LINK_MINUTES]),yw_magic_login_intro());return;
    }
    if(is_post()){
        verify_csrf();
        try{
            db()->beginTransaction();
            $row=yw_login_link_resolve($company,$token,true);
            if(!$row)throw new DomainException('used');
            db()->prepare('UPDATE yw_login_links SET used_at=NOW() WHERE id=? AND used_at IS NULL')->execute([(int)$row['link_id']]);
            db()->prepare('UPDATE employee_accounts SET last_login_at=NOW() WHERE id=?')->execute([(int)$row['id']]);
            db()->commit();
        }catch(Throwable $e){
            if(db()->inTransaction())db()->rollBack();
            if(!$e instanceof DomainException)error_log('WorkwearCenter login link: '.$e->getMessage());
            http_response_code(410);yw_shop_auth_message($company,$slug,t('magic.expired_title'),t('magic.request_new'),yw_magic_login_intro());return;
        }
        clear_login_failures('employee-'.(int)$company['id'].'-'.hash('sha256',strtolower((string)$row['email'])));
        session_regenerate_id(true);
        unset($_SESSION['admin_preview_company']);
        $_SESSION['employee_account_id']=(int)$row['id'];
        $_SESSION['employee_auth_version']=(int)($row['auth_version']??1);
        audit_log('employee.login_link_used','employee',(int)$row['id'],['company_id'=>(int)$company['id']]);
        redirect('/'.$slug);
    }
    $card='<h2>'.e(t('auth.login_title')).'</h2><p>'.e(t('magic.confirm',['name'=>$row['name'],'shop'=>$company['shop_name']])).'</p><form method="post" class="form-stack">'.csrf_field().'<button class="cp-btn-primary" type="submit">'.e(t('mail.magic.button')).'</button></form><div class="cp-login-help"><span>'.e(t('magic.once')).'</span></div>';
    yw_shop_auth_page($company,t('magic.page_title',['shop'=>$company['shop_name']]),yw_magic_login_intro(),$card);
}

/** Linke Spalte der Anmeldelink-Seiten (gleicher Rahmen wie die Anmeldeseite). */
function yw_magic_login_intro(): array {
    return [t('shop.staff_shop'),t('magic.intro_headline'),t('magic.intro_text',['minutes'=>YW_LOGIN_LINK_MINUTES])];
}
