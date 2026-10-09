<?php
declare(strict_types=1);

function yw_password_reset_url(string $slug,string $token): string {
    return 'https://'.workwearcenter_domain().'/'.$slug.'/passwort-neu/'.$token;
}

function yw_create_password_reset(int $accountId,string $slug): string {
    $token=random_token();
    db()->prepare('UPDATE yw_password_resets SET used_at=NOW() WHERE account_id=? AND used_at IS NULL')->execute([$accountId]);
    db()->prepare('INSERT INTO yw_password_resets(account_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 60 MINUTE))')
        ->execute([$accountId,hash('sha256',$token)]);
    return yw_password_reset_url($slug,$token);
}

function yw_send_password_link(int $accountId,string $name,string $email,string $slug,bool $newAccount=false,bool $immediate=false): void {
    $url=yw_create_password_reset($accountId,$slug);
    // 10.12: in der Sprache des Kontos (auch wenn die Verwaltung das Konto anlegt)
    yw_with_lang(yw_account_lang_by_id($accountId),function() use($email,$name,$url,$newAccount,$immediate){
        $subject=$newAccount?t('mail.pw.new_subject'):t('mail.pw.reset_subject');
        $intro=$newAccount?t('mail.pw.new_intro'):t('mail.pw.reset_intro');
        send_system_mail($email,$subject,t('mail.hello',['name'=>$name])."\n\n".$intro."\n".t('mail.pw.link_text')."\n".$url."\n\n".t('mail.ignore'),$immediate);
    });
}

function yw_password_forgot_page(string $slug): void {
    $q=db()->prepare('SELECT * FROM companies WHERE slug=? AND active=1'); $q->execute([$slug]); $company=$q->fetch();
    if(!$company){http_response_code(404);simple_message('Nicht verfügbar','Firmenshop nicht gefunden.');return;}
    yw_shop_lang_init($company);
    if(is_post()){
        verify_csrf();
        $email=strtolower(trim(is_string($_POST['email']??null)?$_POST['email']:''));
        $botField=trim(is_string($_POST['website']??null)?$_POST['website']:'');
        $globalKey='reset-global-'.$company['id'];
        if($botField==='' && !login_throttled($globalKey,40)){
            record_login_failure($globalKey);
            if(filter_var($email,FILTER_VALIDATE_EMAIL)){
                $emailKey='reset-email-'.$company['id'].'-'.hash('sha256',$email);
                if(!login_throttled($emailKey,5)){
                    record_login_failure($emailKey);
                    $q=db()->prepare("SELECT id,name,email FROM employee_accounts WHERE company_id=? AND email=? AND active=1 AND registration_status='approved' AND (employment_start IS NULL OR employment_start<=CURDATE()) AND (employment_end IS NULL OR employment_end>=CURDATE()) LIMIT 1");
                    $q->execute([$company['id'],$email]); $account=$q->fetch();
                    if($account){
                        yw_send_password_link((int)$account['id'],(string)$account['name'],(string)$account['email'],$slug);
                        audit_log('employee.password_reset_requested','employee',(int)$account['id'],['company_id'=>(int)$company['id']]);
                    }
                }
            }
        }
        yw_shop_auth_message($company,$slug,t('pw.mail_checked_title'),t('pw.mail_checked_text'),yw_password_page_intro()); return;
    }
    $card='<h2>'.e(t('pw.forgot_title')).'</h2><p>'.e(t('pw.forgot_sub')).'</p><form method="post" class="form-stack">'.csrf_field().'<div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><label>'.e(t('common.email')).'<input type="email" name="email" autocomplete="email" required></label><button class="cp-btn-primary" type="submit">'.e(t('pw.request')).'</button></form><div class="cp-login-help"><a href="/'.e($slug).'/login">'.e(t('pw.back_to_login')).'</a></div>';
    yw_shop_auth_page($company,t('pw.forgot_page_title',['shop'=>$company['shop_name']]),yw_password_page_intro(),$card);
}

/** Linke Spalte der Passwort-Seiten im Firmenshop. */
function yw_password_page_intro(): array {
    return [t('shop.staff_shop'),t('pw.intro_headline'),t('pw.intro_text')];
}

function yw_password_reset_page(string $slug,string $token): void {
    $q=db()->prepare('SELECT * FROM companies WHERE slug=? AND active=1 LIMIT 1');$q->execute([$slug]);$shop=$q->fetch();
    if($shop)yw_shop_lang_init($shop); // Fehlermeldungen schon in der gewählten Sprache
    if(!preg_match('/^[a-f0-9]{64}$/D',$token)){http_response_code(404);simple_message(t('pw.link_invalid_title'),t('pw.link_invalid_text'));return;}
    $hash=hash('sha256',$token);
    $q=db()->prepare("SELECT r.id reset_id,ea.id account_id,ea.company_id,ea.language,c.slug,c.shop_languages,c.shop_name,c.legal_name,c.logo_path,c.primary_color,c.accent_color FROM yw_password_resets r JOIN employee_accounts ea ON ea.id=r.account_id JOIN companies c ON c.id=ea.company_id WHERE r.token_hash=? AND r.used_at IS NULL AND r.expires_at>NOW() AND ea.active=1 AND ea.registration_status='approved' AND (ea.employment_start IS NULL OR ea.employment_start<=CURDATE()) AND (ea.employment_end IS NULL OR ea.employment_end>=CURDATE()) AND c.active=1 AND c.slug=? LIMIT 1");
    $q->execute([$hash,$slug]); $row=$q->fetch();
    if(!$row){http_response_code(410);simple_message(t('pw.link_expired_title'),t('pw.link_expired_text'));return;}
    yw_shop_lang_init($row,['language'=>$row['language']]); // Link gehört zu einem Konto: dessen Sprache
    $error='';
    if(is_post()){
        verify_csrf(); $password=is_string($_POST['password']??null)?$_POST['password']:''; $repeat=is_string($_POST['password_repeat']??null)?$_POST['password_repeat']:'';
        try{
            if(strlen($password)<12 || strlen($password)>72) throw new DomainException(t('pw.length'));
            if(!hash_equals($password,$repeat)) throw new DomainException(t('pw.mismatch'));
            db()->beginTransaction();
            $q=db()->prepare("SELECT ea.id FROM yw_password_resets r JOIN employee_accounts ea ON ea.id=r.account_id JOIN companies c ON c.id=ea.company_id WHERE r.id=? AND r.token_hash=? AND r.used_at IS NULL AND r.expires_at>NOW() AND ea.active=1 AND ea.registration_status='approved' AND (ea.employment_start IS NULL OR ea.employment_start<=CURDATE()) AND (ea.employment_end IS NULL OR ea.employment_end>=CURDATE()) AND c.active=1 AND c.slug=? FOR UPDATE");
            $q->execute([$row['reset_id'],$hash,$slug]); $accountId=(int)$q->fetchColumn();
            if(!$accountId) throw new DomainException(t('pw.link_expired_title'));
            db()->prepare('UPDATE employee_accounts SET password_hash=?,auth_version=auth_version+1 WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$accountId]);
            db()->prepare('UPDATE yw_password_resets SET used_at=NOW() WHERE account_id=? AND used_at IS NULL')->execute([$accountId]);
            db()->commit();
            audit_log('employee.password_reset_completed','employee',$accountId,['company_id'=>(int)$row['company_id']]);
            yw_shop_auth_message($row,$slug,t('pw.saved_title'),t('pw.saved_text'),yw_password_page_intro()); return;
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();$error=$e instanceof DomainException?$e->getMessage():t('pw.save_failed');}
    }
    $card='<h2>'.e(t('pw.new_title')).'</h2><p>'.e(t('pw.new_sub')).'</p>';
    if($error)$card.='<div class="flash error" role="alert">'.e($error).'</div>';
    $card.='<form method="post" class="form-stack">'.csrf_field().'<label>'.e(t('common.password')).'<input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required></label><label>'.e(t('pw.repeat')).'<input type="password" name="password_repeat" minlength="12" maxlength="72" autocomplete="new-password" required></label><button class="cp-btn-primary" type="submit">'.e(t('pw.save')).'</button></form>';
    yw_shop_auth_page($row,t('pw.new_page_title',['shop'=>$row['shop_name']]),yw_password_page_intro(),$card);
}
