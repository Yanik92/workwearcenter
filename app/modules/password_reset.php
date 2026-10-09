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
    $subject=$newAccount?'Dein Zugang zum WorkwearCenter':'Passwort für das WorkwearCenter zurücksetzen';
    $intro=$newAccount?'Für dich wurde ein Firmenkonto angelegt.':'Für dein Firmenkonto wurde ein neues Passwort angefordert.';
    send_system_mail($email,$subject,"Hallo ".$name.",\n\n".$intro."\nÜber diesen Link legst du innerhalb von 60 Minuten ein neues Passwort fest:\n".$url."\n\nWenn du das nicht angefordert hast, kannst du diese Nachricht ignorieren.",$immediate);
}

function yw_password_forgot_page(string $slug): void {
    $q=db()->prepare('SELECT * FROM companies WHERE slug=? AND active=1'); $q->execute([$slug]); $company=$q->fetch();
    if(!$company){http_response_code(404);simple_message('Nicht verfügbar','Firmenshop nicht gefunden.');return;}
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
        yw_shop_auth_message($company,$slug,'E-Mail wird geprüft','Falls ein aktiver Zugang existiert, wurde ein Link zum Zurücksetzen versendet.',yw_password_page_intro()); return;
    }
    $card='<h2>Passwort vergessen</h2><p>Gib die E-Mail-Adresse deines persönlichen Firmenkontos ein.</p><form method="post" class="form-stack">'.csrf_field().'<div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><label>E-Mail<input type="email" name="email" autocomplete="email" required></label><button class="cp-btn-primary" type="submit">Link anfordern</button></form><div class="cp-login-help"><a href="/'.e($slug).'/login">Zurück zum Login</a></div>';
    yw_shop_auth_page($company,'Passwort vergessen – '.$company['shop_name'],yw_password_page_intro(),$card);
}

/** Linke Spalte der Passwort-Seiten im Firmenshop. */
function yw_password_page_intro(): array {
    return ['Mitarbeiter-Shop','Neues Passwort in zwei Minuten.','Wir schicken dir einen Link per E-Mail. Damit legst du innerhalb von 60 Minuten ein neues Passwort fest.'];
}

function yw_password_reset_page(string $slug,string $token): void {
    if(!preg_match('/^[a-f0-9]{64}$/D',$token)){http_response_code(404);simple_message('Link ungültig','Bitte fordere einen neuen Link an.');return;}
    $hash=hash('sha256',$token);
    $q=db()->prepare("SELECT r.id reset_id,ea.id account_id,ea.company_id,c.shop_name,c.legal_name,c.logo_path,c.primary_color,c.accent_color FROM yw_password_resets r JOIN employee_accounts ea ON ea.id=r.account_id JOIN companies c ON c.id=ea.company_id WHERE r.token_hash=? AND r.used_at IS NULL AND r.expires_at>NOW() AND ea.active=1 AND ea.registration_status='approved' AND (ea.employment_start IS NULL OR ea.employment_start<=CURDATE()) AND (ea.employment_end IS NULL OR ea.employment_end>=CURDATE()) AND c.active=1 AND c.slug=? LIMIT 1");
    $q->execute([$hash,$slug]); $row=$q->fetch();
    if(!$row){http_response_code(410);simple_message('Link abgelaufen','Bitte fordere über den Login einen neuen Passwort-Link an.');return;}
    $error='';
    if(is_post()){
        verify_csrf(); $password=is_string($_POST['password']??null)?$_POST['password']:''; $repeat=is_string($_POST['password_repeat']??null)?$_POST['password_repeat']:'';
        try{
            if(strlen($password)<12 || strlen($password)>72) throw new DomainException('Passwort: 12 bis 72 Bytes verwenden.');
            if(!hash_equals($password,$repeat)) throw new DomainException('Die Passwörter stimmen nicht überein.');
            db()->beginTransaction();
            $q=db()->prepare("SELECT ea.id FROM yw_password_resets r JOIN employee_accounts ea ON ea.id=r.account_id JOIN companies c ON c.id=ea.company_id WHERE r.id=? AND r.token_hash=? AND r.used_at IS NULL AND r.expires_at>NOW() AND ea.active=1 AND ea.registration_status='approved' AND (ea.employment_start IS NULL OR ea.employment_start<=CURDATE()) AND (ea.employment_end IS NULL OR ea.employment_end>=CURDATE()) AND c.active=1 AND c.slug=? FOR UPDATE");
            $q->execute([$row['reset_id'],$hash,$slug]); $accountId=(int)$q->fetchColumn();
            if(!$accountId) throw new DomainException('Link abgelaufen.');
            db()->prepare('UPDATE employee_accounts SET password_hash=?,auth_version=auth_version+1 WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$accountId]);
            db()->prepare('UPDATE yw_password_resets SET used_at=NOW() WHERE account_id=? AND used_at IS NULL')->execute([$accountId]);
            db()->commit();
            audit_log('employee.password_reset_completed','employee',$accountId,['company_id'=>(int)$row['company_id']]);
            yw_shop_auth_message($row,$slug,'Passwort gespeichert','Du kannst dich jetzt mit deinem neuen Passwort anmelden.',yw_password_page_intro()); return;
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();$error=$e instanceof DomainException?$e->getMessage():'Passwort konnte nicht gespeichert werden.';}
    }
    $card='<h2>Neues Passwort</h2><p>Mindestens 12 Zeichen. Danach meldest du dich wie gewohnt an.</p>';
    if($error)$card.='<div class="flash error" role="alert">'.e($error).'</div>';
    $card.='<form method="post" class="form-stack">'.csrf_field().'<label>Passwort<input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required></label><label>Wiederholen<input type="password" name="password_repeat" minlength="12" maxlength="72" autocomplete="new-password" required></label><button class="cp-btn-primary" type="submit">Passwort speichern</button></form>';
    yw_shop_auth_page($row,'Neues Passwort – '.$row['shop_name'],yw_password_page_intro(),$card);
}
