<?php
declare(strict_types=1);

function yw_admin_password_reset_url(string $token): string {
    return workwearcenter_url('admin/passwort-neu/'.$token);
}

function yw_create_admin_password_reset(int $adminId,int $hours=1): string {
    $minutes=max(15,min(48*60,$hours*60));
    $token=random_token();
    db()->prepare('UPDATE yw_admin_password_resets SET used_at=NOW() WHERE admin_id=? AND used_at IS NULL')->execute([$adminId]);
    db()->prepare('INSERT INTO yw_admin_password_resets(admin_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL '.$minutes.' MINUTE))')
        ->execute([$adminId,hash('sha256',$token)]);
    return yw_admin_password_reset_url($token);
}

function admin_password_forgot_page(): void {
    if(admin_logged_in())redirect('/admin');
    if(is_post()){
        verify_csrf();
        $email=strtolower(trim(is_string($_POST['email']??null)?$_POST['email']:''));
        $botField=trim(is_string($_POST['website']??null)?$_POST['website']:'');
        if($botField==='' && !login_throttled('admin-reset-global',20)){
            record_login_failure('admin-reset-global');
            if(filter_var($email,FILTER_VALIDATE_EMAIL)){
                $emailKey='admin-reset-email-'.hash('sha256',$email);
                if(!login_throttled($emailKey,5,false)){ // je Adresse, unabhängig von der IP
                    record_login_failure($emailKey,false);
                    $q=db()->prepare('SELECT id,email FROM admins WHERE email=? AND active=1 LIMIT 1');$q->execute([$email]);$admin=$q->fetch();
                    if($admin){
                        $url=yw_create_admin_password_reset((int)$admin['id']);
                        send_system_mail((string)$admin['email'],'Passwort für die WorkwearCenter-Verwaltung zurücksetzen',"Für die WorkwearCenter-Verwaltung wurde ein neues Passwort angefordert.\n\nLink (60 Minuten gültig): ".$url."\n\nWenn Sie das nicht angefordert haben, können Sie diese Nachricht ignorieren.");
                        audit_log('admin.password_reset_requested','admin',(int)$admin['id']);
                    }
                }
            }
        }
        simple_message('E-Mail wird geprüft','Falls das Administratorkonto existiert, wurde ein Link zum Zurücksetzen versendet.');
        return;
    }
    render_head('Admin-Passwort vergessen');
    echo site_brand_style();
    echo '<div class="login-brandbar"><div class="wrap">'.site_brand_html().'</div></div>';
    echo '<main class="login-shell" style="--primary:#101b2d;--accent:#ff6b00"><section class="login-card"><div class="login-accent"></div><div class="login-body"><div class="eyebrow">YANIK WORKWEAR</div><h1>Passwort zurücksetzen</h1><p>Gib die E-Mail-Adresse des Administratorkontos ein.</p><form method="post" class="form-stack">'.csrf_field().'<div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><label>E-Mail<input type="email" name="email" autocomplete="email" required></label><button class="btn primary" type="submit">Link anfordern</button></form><p><a href="/admin/login">Zurück zur Anmeldung</a></p></div></section></main>';
    global_footer();render_foot();
}

function admin_password_reset_page(string $token): void {
    if(admin_logged_in())redirect('/admin');
    if(!preg_match('/^[a-f0-9]{64}$/D',$token)){http_response_code(404);simple_message('Link ungültig','Bitte fordere einen neuen Passwort-Link an.');return;}
    $hash=hash('sha256',$token);
    $q=db()->prepare('SELECT r.id reset_id,a.id admin_id FROM yw_admin_password_resets r JOIN admins a ON a.id=r.admin_id WHERE r.token_hash=? AND r.used_at IS NULL AND r.expires_at>NOW() AND a.active=1 LIMIT 1');
    $q->execute([$hash]);$row=$q->fetch();
    if(!$row){http_response_code(410);simple_message('Link abgelaufen','Bitte fordere über die Admin-Anmeldung einen neuen Passwort-Link an.');return;}
    $error='';
    if(is_post()){
        verify_csrf();$password=is_string($_POST['password']??null)?$_POST['password']:'';$repeat=is_string($_POST['password_repeat']??null)?$_POST['password_repeat']:'';
        try{
            if(strlen($password)<12 || strlen($password)>72)throw new DomainException('Passwort: 12 bis 72 Bytes verwenden.');
            if(!hash_equals($password,$repeat))throw new DomainException('Die Passwörter stimmen nicht überein.');
            db()->beginTransaction();
            $q=db()->prepare('SELECT admin_id FROM yw_admin_password_resets WHERE id=? AND token_hash=? AND used_at IS NULL AND expires_at>NOW() FOR UPDATE');$q->execute([(int)$row['reset_id'],$hash]);$adminId=(int)$q->fetchColumn();
            if(!$adminId)throw new DomainException('Link abgelaufen.');
            db()->prepare('UPDATE admins SET password_hash=?,auth_version=auth_version+1 WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$adminId]);
            db()->prepare('UPDATE yw_admin_password_resets SET used_at=NOW() WHERE admin_id=? AND used_at IS NULL')->execute([$adminId]);
            db()->commit();
            audit_log('admin.password_reset_completed','admin',$adminId);
            unset($_SESSION['admin_id'],$_SESSION['admin_auth_version']);session_regenerate_id(true);
            flash('success','Passwort gespeichert. Du kannst dich jetzt anmelden.');
            redirect('/admin/login');
        }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();$error=$e instanceof DomainException?$e->getMessage():'Passwort konnte nicht gespeichert werden.';}
    }
    render_head('Neues Admin-Passwort');echo site_brand_style();
    echo '<div class="login-brandbar"><div class="wrap">'.site_brand_html().'</div></div><main class="login-shell" style="--primary:#101b2d;--accent:#ff6b00"><section class="login-card"><div class="login-accent"></div><div class="login-body"><div class="eyebrow">YANIK WORKWEAR</div><h1>Neues Passwort</h1>';
    if($error)echo '<div class="flash error">'.e($error).'</div>';
    echo '<form method="post" class="form-stack">'.csrf_field().'<label>Passwort<input type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required></label><label>Wiederholen<input type="password" name="password_repeat" minlength="12" maxlength="72" autocomplete="new-password" required></label><button class="btn primary" type="submit">Passwort speichern</button></form></div></section></main>';
    global_footer();render_foot();
}
