<?php
declare(strict_types=1);

/*
 * Admin-Konten: eigenes Passwort, Zwei-Faktor-Anmeldung (TOTP, RFC 6238),
 * Wiederherstellungscodes und Verwaltung weiterer Administratoren.
 * Kompatibel mit Google Authenticator, Microsoft Authenticator, 1Password, Bitwarden u. a.
 */

function yw_base32_encode(string $bin): string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';$out='';
    foreach(str_split($bin) as $c)$bits.=str_pad(decbin(ord($c)),8,'0',STR_PAD_LEFT);
    foreach(str_split($bits,5) as $chunk)$out.=$alphabet[bindec(str_pad($chunk,5,'0'))];
    return $out;
}

function yw_base32_decode(string $b32): string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';$bits='';
    foreach(str_split(strtoupper(preg_replace('/[^A-Za-z2-7]/','',$b32))) as $c)$bits.=str_pad(decbin(strpos($alphabet,$c)),5,'0',STR_PAD_LEFT);
    $out='';foreach(str_split($bits,8) as $byte)if(strlen($byte)===8)$out.=chr(bindec($byte));
    return $out;
}

function yw_totp_code(string $secret,int $step): string {
    $key=yw_base32_decode($secret);
    $hash=hash_hmac('sha1',pack('J',$step),$key,true);
    $offset=ord($hash[19])&0x0f;
    $value=((ord($hash[$offset])&0x7f)<<24)|((ord($hash[$offset+1])&0xff)<<16)|((ord($hash[$offset+2])&0xff)<<8)|(ord($hash[$offset+3])&0xff);
    return str_pad((string)($value%1000000),6,'0',STR_PAD_LEFT);
}

/** Prüft einen 6-stelligen Code (±30 Sekunden Toleranz). Liefert den Zeitschritt oder null. */
function yw_totp_verify(string $secret,string $code,?int $lastStep=null): ?int {
    $code=preg_replace('/\s+/','',$code);
    if(!preg_match('/^\d{6}$/',$code))return null;
    $now=intdiv(time(),30);
    foreach([0,-1,1] as $drift){
        $step=$now+$drift;
        if($lastStep!==null && $step<=$lastStep)continue; // Code nicht zweimal verwenden
        if(hash_equals(yw_totp_code($secret,$step),$code))return $step;
    }
    return null;
}

function yw_totp_uri(string $secret,string $email): string {
    $issuer='WorkwearCenter';
    return 'otpauth://totp/'.rawurlencode($issuer.':'.$email).'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&algorithm=SHA1&digits=6&period=30';
}

function yw_generate_recovery_codes(): array {
    $codes=[];
    for($i=0;$i<10;$i++){$raw=strtoupper(bin2hex(random_bytes(5)));$codes[]=substr($raw,0,5).'-'.substr($raw,5,5);}
    return $codes;
}

function yw_use_recovery_code(array $admin,string $input): bool {
    $input=strtoupper(preg_replace('/[^A-Za-z0-9]/','',$input));
    if(strlen($input)!==10)return false;
    $normalized=substr($input,0,5).'-'.substr($input,5,5);
    $hashes=json_decode((string)($admin['recovery_codes_json']??''),true)?:[];
    foreach($hashes as $i=>$hash){
        if(is_string($hash) && password_verify($normalized,$hash)){
            unset($hashes[$i]);
            // Nur wenn die Liste seit dem Lesen unverändert ist – sonst wurde der Code parallel schon verbraucht.
            $u=db()->prepare('UPDATE admins SET recovery_codes_json=? WHERE id=? AND recovery_codes_json=?');$u->execute([json_encode(array_values($hashes)),(int)$admin['id'],(string)$admin['recovery_codes_json']]);
            return $u->rowCount()===1;
        }
    }
    return false;
}

function yw_admin_current(): ?array {
    if(empty($_SESSION['admin_id']))return null;
    $q=db()->prepare('SELECT * FROM admins WHERE id=? LIMIT 1');$q->execute([(int)$_SESSION['admin_id']]);
    return $q->fetch()?:null;
}

function yw_admin_complete_login(array $admin): never {
    unset($_SESSION['admin_2fa']);
    session_regenerate_id(true);
    $_SESSION['admin_id']=(int)$admin['id'];
    $_SESSION['admin_auth_version']=(int)($admin['auth_version']??1);
    try{db()->prepare('UPDATE admins SET last_login_at=NOW() WHERE id=?')->execute([(int)$admin['id']]);}catch(Throwable $ignored){}
    audit_log('admin.login','admin',(int)$admin['id']);
    redirect('/admin');
}

/** Zweiter Anmeldeschritt nach korrektem Passwort. */
function admin_2fa_page(): void {
    $pending=$_SESSION['admin_2fa']??null;
    if(!is_array($pending) || ($pending['time']??0)<time()-300){unset($_SESSION['admin_2fa']);flash('error','Bitte erneut anmelden.');redirect('/admin/login');}
    $q=db()->prepare('SELECT * FROM admins WHERE id=? AND active=1 LIMIT 1');$q->execute([(int)$pending['id']]);$admin=$q->fetch();
    if(!$admin || !(int)$admin['totp_enabled']){unset($_SESSION['admin_2fa']);redirect('/admin/login');}
    $error='';
    if(is_post()){
        verify_csrf();
        $key='admin-2fa-'.(int)$admin['id'];
        if(login_throttled($key,6) || login_throttled($key.'-global',15,false))$error='Zu viele Versuche. Bitte 15 Minuten warten.';
        else{
            $code=is_string($_POST['code']??null)?$_POST['code']:'';
            $step=yw_totp_verify((string)$admin['totp_secret'],$code,$admin['totp_last_step']!==null?(int)$admin['totp_last_step']:null);
            // Zeitschritt atomar verbrauchen: zwei gleichzeitige Anfragen mit demselben Code melden nur einmal an.
            if($step!==null && ($u=db()->prepare('UPDATE admins SET totp_last_step=? WHERE id=? AND (totp_last_step IS NULL OR totp_last_step<?)'))->execute([$step,(int)$admin['id'],$step]) && $u->rowCount()===1){
                clear_login_failures($key);clear_login_failures($key.'-global',false);
                yw_admin_complete_login($admin);
            }
            if(yw_use_recovery_code($admin,$code)){
                audit_log('admin.recovery_code_used','admin',(int)$admin['id']);
                clear_login_failures($key);clear_login_failures($key.'-global',false);
                $left=count(json_decode((string)$admin['recovery_codes_json'],true)?:[])-1;
                flash('info','Du hast einen Wiederherstellungscode verwendet. Noch '.$left.' Codes übrig – bei Bedarf unter „Mein Konto“ neue erzeugen.');
                yw_admin_complete_login($admin);
            }
            record_login_failure($key);record_login_failure($key.'-global',false);
            $error='Der Code ist nicht korrekt oder abgelaufen.';
        }
    }
    render_head('Bestätigung – Verwaltung');
    echo site_brand_style();
    echo '<div class="login-brandbar"><div class="wrap">'.site_brand_html().'</div></div>';
    echo '<main class="login-shell" style="--primary:#fc6904;--accent:#fc6904"><section class="login-card"><div class="login-accent"></div><div class="login-body"><div class="eyebrow">ZWEITER SCHRITT</div><h1>Code bestätigen</h1><p>Öffne deine Authenticator-App und gib den 6-stelligen Code für <strong>WorkwearCenter</strong> ein.</p>';
    if($error)echo '<div class="flash error">'.e($error).'</div>';
    echo '<form method="post" class="form-stack">'.csrf_field().'<label>Code<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="11" required autofocus class="code-input" placeholder="123 456"></label><button class="btn primary" type="submit">Anmelden</button></form>';
    echo '<details class="details space-top"><summary>Kein Zugriff auf die App?</summary><div class="details-inner"><p class="muted">Gib stattdessen einen deiner Wiederherstellungscodes ein (Format XXXXX-XXXXX). Jeder Code funktioniert nur einmal.</p></div></details>';
    echo '<p><a href="/admin/login">Abbrechen</a></p></div></section></main>';
    global_footer();render_foot();
}

function yw_admin_account_action(string $action): never {
    $me=yw_admin_current();
    if(!$me)redirect('/admin/login');
    try{
        if($action==='account_password'){
            $current=is_string($_POST['current_password']??null)?$_POST['current_password']:'';
            $new=is_string($_POST['new_password']??null)?$_POST['new_password']:'';
            $repeat=is_string($_POST['new_password_repeat']??null)?$_POST['new_password_repeat']:'';
            if(!password_verify($current,(string)$me['password_hash']))throw new RuntimeException('Das aktuelle Passwort ist nicht korrekt.');
            if(strlen($new)<12||strlen($new)>72)throw new RuntimeException('Neues Passwort: 12 bis 72 Zeichen verwenden.');
            if(!hash_equals($new,$repeat))throw new RuntimeException('Die neuen Passwörter stimmen nicht überein.');
            if(password_verify($new,(string)$me['password_hash']))throw new RuntimeException('Das neue Passwort muss sich vom bisherigen unterscheiden.');
            db()->prepare('UPDATE admins SET password_hash=?,auth_version=auth_version+1 WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),(int)$me['id']]);
            $_SESSION['admin_auth_version']=(int)$me['auth_version']+1; // eigene Sitzung bleibt, andere Geräte werden abgemeldet
            session_regenerate_id(true);
            audit_log('admin.password_changed','admin',(int)$me['id']);
            flash('success','Passwort geändert. Andere angemeldete Geräte wurden abgemeldet.');
        }elseif($action==='account_profile'){
            $name=trim((string)($_POST['name']??''));
            if(mb_strlen($name)>160)throw new RuntimeException('Name ist zu lang.');
            db()->prepare('UPDATE admins SET name=? WHERE id=?')->execute([$name!==''?$name:null,(int)$me['id']]);
            flash('success','Profil gespeichert.');
        }elseif($action==='account_2fa_start'){
            $_SESSION['admin_2fa_setup']=['secret'=>yw_base32_encode(random_bytes(20)),'time'=>time()];
            flash('info','Scanne jetzt den QR-Code mit deiner Authenticator-App und bestätige mit dem angezeigten Code.');
        }elseif($action==='account_2fa_confirm'){
            $setup=$_SESSION['admin_2fa_setup']??null;
            if(!is_array($setup)||($setup['time']??0)<time()-900)throw new RuntimeException('Die Einrichtung ist abgelaufen. Bitte neu starten.');
            $step=yw_totp_verify((string)$setup['secret'],(string)($_POST['code']??''));
            if($step===null)throw new RuntimeException('Der Code passt nicht. Prüfe, ob die Uhrzeit deines Telefons stimmt, und versuche es mit dem nächsten Code.');
            $codes=yw_generate_recovery_codes();
            db()->prepare('UPDATE admins SET totp_secret=?,totp_enabled=1,totp_last_step=?,recovery_codes_json=?,auth_version=auth_version+1 WHERE id=?')
                ->execute([$setup['secret'],$step,json_encode(array_map(fn($c)=>password_hash($c,PASSWORD_DEFAULT),$codes)),(int)$me['id']]);
            $_SESSION['admin_auth_version']=(int)$me['auth_version']+1;
            unset($_SESSION['admin_2fa_setup']);
            $_SESSION['admin_recovery_codes_once']=$codes;
            audit_log('admin.2fa_enabled','admin',(int)$me['id']);
            flash('success','Zwei-Faktor-Anmeldung ist aktiv. Bitte speichere jetzt die Wiederherstellungscodes.');
        }elseif($action==='account_2fa_disable' || $action==='account_2fa_new_codes'){
            $password=is_string($_POST['password']??null)?$_POST['password']:'';
            if(!password_verify($password,(string)$me['password_hash']))throw new RuntimeException('Passwort ist nicht korrekt.');
            if(yw_totp_verify((string)$me['totp_secret'],(string)($_POST['code']??''))===null)throw new RuntimeException('Der Code aus der Authenticator-App ist nicht korrekt.');
            if($action==='account_2fa_disable'){
                db()->prepare('UPDATE admins SET totp_secret=NULL,totp_enabled=0,totp_last_step=NULL,recovery_codes_json=NULL WHERE id=?')->execute([(int)$me['id']]);
                audit_log('admin.2fa_disabled','admin',(int)$me['id']);
                flash('success','Zwei-Faktor-Anmeldung wurde ausgeschaltet. Wir empfehlen, sie wieder einzuschalten.');
            }else{
                $codes=yw_generate_recovery_codes();
                db()->prepare('UPDATE admins SET recovery_codes_json=? WHERE id=?')->execute([json_encode(array_map(fn($c)=>password_hash($c,PASSWORD_DEFAULT),$codes)),(int)$me['id']]);
                $_SESSION['admin_recovery_codes_once']=$codes;
                audit_log('admin.recovery_codes_renewed','admin',(int)$me['id']);
                flash('success','Neue Wiederherstellungscodes erzeugt. Die alten sind ab sofort ungültig.');
            }
        }elseif($action==='admin_invite'){
            $name=trim((string)($_POST['name']??''));$email=strtolower(trim((string)($_POST['email']??'')));
            if($name===''||mb_strlen($name)>160)throw new RuntimeException('Bitte einen Namen angeben.');
            if(mb_strlen($email)>190||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Bitte eine gültige E-Mail-Adresse angeben.');
            $q=db()->prepare('SELECT id FROM admins WHERE email=?');$q->execute([$email]);if($q->fetchColumn())throw new RuntimeException('Für diese E-Mail-Adresse gibt es bereits ein Admin-Konto.');
            db()->prepare('INSERT INTO admins(email,password_hash,name,active) VALUES(?,?,?,1)')->execute([$email,password_hash(random_token(),PASSWORD_DEFAULT),$name]);
            $id=(int)db()->lastInsertId();
            $url=yw_create_admin_password_reset($id,24);
            send_system_mail($email,'Einladung zur WorkwearCenter-Verwaltung',"Hallo ".$name.",\n\ndu wurdest als Administrator für die WorkwearCenter-Verwaltung eingeladen.\nÜber diesen Link legst du innerhalb von 24 Stunden dein Passwort fest:\n".$url."\n\nBitte richte danach unter „Mein Konto“ die Zwei-Faktor-Anmeldung ein.",true);
            audit_log('admin.invited','admin',$id);
            flash('success','Einladung an '.$email.' wurde versendet (24 Stunden gültig).');
        }elseif($action==='admin_toggle'){
            $id=(int)($_POST['admin_id']??0);
            if($id===(int)$me['id'])throw new RuntimeException('Du kannst dein eigenes Konto nicht deaktivieren.');
            $q=db()->prepare('SELECT active FROM admins WHERE id=?');$q->execute([$id]);$active=$q->fetchColumn();
            if($active===false)throw new RuntimeException('Admin-Konto nicht gefunden.');
            db()->prepare('UPDATE admins SET active=?,auth_version=auth_version+1 WHERE id=?')->execute([(int)$active?0:1,$id]);
            audit_log((int)$active?'admin.deactivated':'admin.activated','admin',$id);
            flash('success',(int)$active?'Admin-Konto deaktiviert und abgemeldet.':'Admin-Konto wieder aktiviert.');
        }else throw new RuntimeException('Aktion unbekannt.');
    }catch(RuntimeException $e){flash('error',$e->getMessage());}
    catch(Throwable $e){error_log('Admin account: '.$e->getMessage());flash('error','Speichern fehlgeschlagen.');}
    redirect('/admin?tab=account');
}

function yw_admin_account_page(): void {
    $me=yw_admin_current();if(!$me)return;
    $codes=$_SESSION['admin_recovery_codes_once']??null;unset($_SESSION['admin_recovery_codes_once']);
    if(is_array($codes)){
        echo '<section class="card recovery-card"><h2>Deine Wiederherstellungscodes</h2><p>Diese Codes werden <strong>nur jetzt einmal</strong> angezeigt. Drucke sie aus oder speichere sie im Passwort-Manager. Mit jedem Code kannst du dich einmal anmelden, falls dein Telefon verloren geht.</p><div class="recovery-codes">';
        foreach($codes as $code)echo '<code>'.e($code).'</code>';
        echo '</div><div class="actions"><button type="button" class="btn" data-copy="'.e(implode("\n",$codes)).'">Alle kopieren</button><button type="button" class="btn" data-print>Drucken</button></div></section>';
    }

    echo '<div class="settings-grid">';
    // Profil + Passwort
    echo '<section class="card"><h2>Mein Konto</h2><p class="muted">Angemeldet als <strong>'.e($me['email']).'</strong>'.($me['last_login_at']?' · letzte Anmeldung '.e(date('d.m.Y H:i',strtotime((string)$me['last_login_at']))).' Uhr':'').'</p>';
    echo '<form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="account_profile"><label>Anzeigename<input name="name" maxlength="160" value="'.e($me['name']??'').'"></label><button class="btn small">Speichern</button></form><hr>';
    echo '<h3>Passwort ändern</h3><form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="account_password"><label>Aktuelles Passwort<input type="password" name="current_password" autocomplete="current-password" required></label><label>Neues Passwort <small>mindestens 12 Zeichen</small><input type="password" name="new_password" minlength="12" maxlength="72" autocomplete="new-password" required></label><label>Neues Passwort wiederholen<input type="password" name="new_password_repeat" minlength="12" maxlength="72" autocomplete="new-password" required></label><button class="btn primary">Passwort ändern</button></form></section>';

    // 2FA
    echo '<section class="card"><h2>Zwei-Faktor-Anmeldung</h2>';
    if((int)$me['totp_enabled']){
        $left=count(json_decode((string)($me['recovery_codes_json']??''),true)?:[]);
        echo '<div class="flash success"><strong>Aktiv.</strong> Bei jeder Anmeldung wird zusätzlich ein Code aus deiner Authenticator-App abgefragt.</div><p class="muted">Verbleibende Wiederherstellungscodes: <strong>'.$left.'</strong></p>';
        echo '<details class="details"><summary>Neue Wiederherstellungscodes erzeugen</summary><div class="details-inner"><form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="account_2fa_new_codes"><label>Passwort<input type="password" name="password" required></label><label>Aktueller Code aus der App<input name="code" inputmode="numeric" maxlength="7" required></label><button class="btn small">Neue Codes erzeugen</button></form></div></details>';
        echo '<details class="details"><summary>Zwei-Faktor-Anmeldung ausschalten</summary><div class="details-inner"><form method="post" class="form-stack" data-confirm="Zwei-Faktor-Anmeldung wirklich ausschalten? Dein Konto ist dann nur noch durch das Passwort geschützt.">'.csrf_field().'<input type="hidden" name="action" value="account_2fa_disable"><label>Passwort<input type="password" name="password" required></label><label>Aktueller Code aus der App<input name="code" inputmode="numeric" maxlength="7" required></label><button class="btn small danger">Ausschalten</button></form></div></details>';
    }else{
        $setup=$_SESSION['admin_2fa_setup']??null;
        if(is_array($setup) && ($setup['time']??0)>=time()-900){
            $uri=yw_totp_uri((string)$setup['secret'],(string)$me['email']);
            echo '<ol class="setup-steps"><li>Authenticator-App auf dem Smartphone öffnen (z. B. Google oder Microsoft Authenticator).</li><li>QR-Code scannen.</li><li>Den angezeigten 6-stelligen Code unten eingeben.</li></ol>';
            echo '<div class="totp-setup"><div class="totp-qr" data-qr="'.e($uri).'" role="img" aria-label="QR-Code für die Authenticator-App"></div><div><p class="muted">QR-Code lässt sich nicht scannen? Schlüssel manuell eingeben:</p><code class="totp-secret">'.e(trim(chunk_split((string)$setup['secret'],4,' '))).'</code></div></div>';
            echo '<form method="post" class="form-stack space-top">'.csrf_field().'<input type="hidden" name="action" value="account_2fa_confirm"><label>Code aus der App<input name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required class="code-input" placeholder="123 456"></label><button class="btn primary">Bestätigen und aktivieren</button></form>';
            echo '<script src="/assets/vendor/qrcode.js" defer></script>';
        }else{
            echo '<div class="flash info"><strong>Empfohlen:</strong> Schütze die Verwaltung zusätzlich mit einem Code vom Smartphone. Selbst wenn dein Passwort bekannt würde, kommt niemand ohne dein Telefon hinein.</div>';
            echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="account_2fa_start"><button class="btn primary">Zwei-Faktor-Anmeldung einrichten</button></form>';
        }
    }
    echo '</section></div>';

    // Admin-Verwaltung
    $admins=db()->query('SELECT id,email,name,active,totp_enabled,last_login_at FROM admins ORDER BY active DESC,email')->fetchAll();
    echo '<section class="card"><div class="title-row"><div><h2>Administratoren</h2><p class="muted">Jede Person bekommt ein eigenes Konto. Gemeinsam genutzte Zugänge vermeiden.</p></div></div><div class="table-wrap"><table class="table"><thead><tr><th>Name</th><th>E-Mail</th><th>Zwei-Faktor</th><th>Letzte Anmeldung</th><th>Status</th><th></th></tr></thead><tbody>';
    foreach($admins as $a){
        echo '<tr><td>'.e($a['name']?:'–').((int)$a['id']===(int)$me['id']?' <span class="muted">(du)</span>':'').'</td><td>'.e($a['email']).'</td><td><span class="badge '.((int)$a['totp_enabled']?'approved':'pending').'">'.((int)$a['totp_enabled']?'Aktiv':'Nicht eingerichtet').'</span></td><td>'.($a['last_login_at']?e(date('d.m.Y H:i',strtotime((string)$a['last_login_at']))):'–').'</td><td>'.((int)$a['active']?'Aktiv':'Deaktiviert').'</td><td>';
        if((int)$a['id']!==(int)$me['id'])echo '<form method="post" data-confirm="'.((int)$a['active']?'Admin-Konto deaktivieren?':'Admin-Konto aktivieren?').'">'.csrf_field().'<input type="hidden" name="action" value="admin_toggle"><input type="hidden" name="admin_id" value="'.(int)$a['id'].'"><button class="btn small">'.((int)$a['active']?'Deaktivieren':'Aktivieren').'</button></form>';
        echo '</td></tr>';
    }
    echo '</tbody></table></div><details class="details space-top"><summary>Weiteren Administrator einladen</summary><div class="details-inner"><form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="admin_invite"><div class="grid2"><label>Name<input name="name" maxlength="160" required></label><label>E-Mail<input type="email" name="email" maxlength="190" required></label></div><p class="muted">Die Person erhält einen 24 Stunden gültigen Link und legt ihr Passwort selbst fest.</p><button class="btn primary small">Einladung senden</button></form></div></details></section>';
}
