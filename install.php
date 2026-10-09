<?php
declare(strict_types=1);

/*
 * Einrichtungs-Assistent für eine NEUE Installation.
 * Bei einer bestehenden Installation sperrt er sich selbst (sobald ein Admin existiert).
 * Schutz vor Fremdinstallation: Der install_key aus der config.php muss eingegeben werden.
 */
if(!is_file(__DIR__.'/config.php')){
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    exit('<!doctype html><meta charset="utf-8"><title>Einrichtung</title><body style="font-family:system-ui;max-width:640px;margin:60px auto;padding:0 20px;line-height:1.6"><h1>Einrichtung vorbereiten</h1><ol><li><code>config.example.php</code> kopieren und als <code>config.php</code> speichern.</li><li>Datenbankzugang, Mailversand und einen langen <code>install_key</code> eintragen.</li><li>Diese Seite neu laden.</li></ol></body>');
}
require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/migrations.php';
require_once __DIR__.'/app/schema_base.php';
require_once __DIR__.'/app/modules/public.php';
require_once __DIR__.'/app/modules/system.php';

function yw_install_done(): bool {
    try{return (int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn()>0;}catch(Throwable $e){return false;}
}

$error='';$done=false;
if(yw_install_done()){
    http_response_code(403);
    render_head('Bereits eingerichtet');
    echo '<main class="install-bg"><section class="install-card"><div class="eyebrow">WORKWEARCENTER</div><h1>Bereits eingerichtet</h1><p>Dieses System ist bereits installiert. Für Updates bitte als Administrator anmelden und den Update-Assistenten verwenden.</p><p><a class="btn primary" href="/admin/login">Zur Anmeldung</a></p></section></main>';
    render_foot();exit;
}
$key=(string)(cfg('install_key')?:'');
if(is_post()){
    verify_csrf();
    try{
        if(login_throttled('install',5))throw new RuntimeException('Zu viele Versuche. Bitte 15 Minuten warten.');
        if(strlen($key)<16)throw new RuntimeException('In der config.php fehlt ein install_key mit mindestens 16 Zeichen.');
        if(!hash_equals($key,(string)($_POST['install_key']??''))){record_login_failure('install');throw new RuntimeException('Der Installationsschlüssel ist nicht korrekt.');}
        $name=trim((string)($_POST['name']??''));$email=strtolower(trim((string)($_POST['email']??'')));
        $password=(string)($_POST['password']??'');$repeat=(string)($_POST['password_repeat']??'');
        if($name===''||mb_strlen($name)>160)throw new RuntimeException('Bitte deinen Namen angeben.');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Bitte eine gültige E-Mail-Adresse angeben.');
        if(strlen($password)<12||strlen($password)>72)throw new RuntimeException('Passwort: 12 bis 72 Zeichen.');
        if(!hash_equals($password,$repeat))throw new RuntimeException('Die Passwörter stimmen nicht überein.');
        $locked=(int)db()->query("SELECT GET_LOCK('yanik_workwear_install',5)")->fetchColumn()===1;
        if(!$locked)throw new RuntimeException('Eine andere Einrichtung läuft gerade.');
        try{
            yw_create_base_schema(db());
            run_schema_updates(db());
            if(yw_install_done())throw new RuntimeException('Das System wurde inzwischen bereits eingerichtet.');
            db()->prepare('INSERT INTO admins(email,password_hash,name,active) VALUES(?,?,?,1)')->execute([$email,password_hash($password,PASSWORD_DEFAULT),$name]);
            audit_log('system.installed','system',null);
        }finally{db()->query("SELECT RELEASE_LOCK('yanik_workwear_install')");}
        clear_login_failures('install');
        redirect('/admin/login?installed=1');
    }catch(RuntimeException $e){$error=$e->getMessage();}
    catch(Throwable $e){error_log('Install: '.$e->getMessage());$error='Einrichtung fehlgeschlagen. Datenbankzugang und Rechte prüfen (Details im Serverprotokoll).';}
}
render_head('WorkwearCenter einrichten');
echo '<main class="install-bg"><section class="install-card"><div class="eyebrow">NEUINSTALLATION · VERSION '.e(app_version()).'</div><h1>WorkwearCenter einrichten</h1><p>Die Datenbankverbindung funktioniert. Jetzt werden alle Tabellen angelegt und dein Administratorkonto erstellt.</p>';
if(strlen($key)<16)echo '<div class="flash error">In der <code>config.php</code> fehlt noch der Eintrag <code>install_key</code> (mindestens 16 Zeichen). Er verhindert, dass Fremde die Einrichtung übernehmen.</div>';
if($error)echo '<div class="flash error">'.e($error).'</div>';
echo '<form method="post" class="form-stack">'.csrf_field().'<label>Installationsschlüssel <small>aus der config.php</small><input type="password" name="install_key" required autocomplete="off"></label><hr><label>Dein Name<input name="name" maxlength="160" required value="'.e((string)($_POST['name']??'')).'"></label><label>E-Mail für die Anmeldung<input type="email" name="email" required value="'.e((string)($_POST['email']??'')).'"></label><div class="grid2"><label>Passwort <small>mind. 12 Zeichen</small><input type="password" name="password" minlength="12" maxlength="72" required autocomplete="new-password"></label><label>Passwort wiederholen<input type="password" name="password_repeat" minlength="12" maxlength="72" required autocomplete="new-password"></label></div><button class="btn primary" type="submit">System einrichten</button></form><p class="muted">Nach der Anmeldung bitte unter „Mein Konto“ die Zwei-Faktor-Anmeldung einrichten und unter „Systemstatus“ die Betriebsprüfung durchgehen.</p></section></main>';
render_foot();
