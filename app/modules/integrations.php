<?php
declare(strict_types=1);

/*
 * 10.16: Alle Schnittstellen an einem Ort (Admin → System → Schnittstellen).
 *
 * - Auftragszentrale (Übergabe freigegebener Aufträge + Statusrückmeldung) und Lexware Office
 *   lassen sich hier einrichten, ohne die config.php per FTP zu ändern.
 * - Zugangsdaten liegen verschlüsselt (AES-256-GCM) in site_settings. Der Schlüssel dazu liegt im
 *   privaten Datenordner (schnittstellen.key) und ist nicht Teil der Datensicherung: Eine gestohlene
 *   Datenbanksicherung allein verrät die Zugangsdaten nicht.
 * - Steht ein Wert in der config.php, hat er weiterhin Vorrang (die Seite zeigt ihn dann nur an).
 * - Änderungen verlangen das eigene Admin-Passwort, werden protokolliert und allen Administratoren gemeldet.
 */

const YW_AZ_DEFAULT_URL='https://auftraege.yanik-textildruck.de/channel.php';

/* ---------- Verschlüsselte Zugangsdaten ---------- */

/** 32-Byte-Schlüssel aus dem privaten Datenordner; wird beim ersten Speichern angelegt. null = nicht verfügbar. */
function yw_int_key(bool $create=false): ?string {
    static $key=null;
    if(is_string($key) && $key!=='')return $key;
    try{$file=yw_private_dir().'/schnittstellen.key';}catch(Throwable $e){return null;}
    if(!is_file($file)){
        if(!$create)return null;
        $fh=@fopen($file,'x'); // nur anlegen, nie einen vorhandenen Schlüssel überschreiben
        if($fh){fwrite($fh,base64_encode(random_bytes(32))."\n");fclose($fh);@chmod($file,0600);}
    }
    $bin=base64_decode(trim((string)@file_get_contents($file)),true);
    if($bin===false || strlen($bin)!==32)return null;
    return $key=$bin;
}

function yw_int_secret_set(string $name,string $plain): void {
    if($plain===''){set_site_setting('int_secret_'.$name,'');return;}
    $key=yw_int_key(true);
    if($key===null)throw new DomainException('Der private Datenordner ist nicht beschreibbar – Zugangsdaten können nicht sicher gespeichert werden.');
    $iv=random_bytes(12);$tag='';
    $cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'wwc-int|'.$name,16);
    if($cipher===false)throw new DomainException('Verschlüsselung fehlgeschlagen (OpenSSL).');
    set_site_setting('int_secret_'.$name,'v1:'.base64_encode($iv.$tag.$cipher));
}

/** Klartext, '' wenn nichts gespeichert ist, null wenn gespeichert, aber nicht lesbar (Schlüsseldatei fehlt oder wurde ersetzt). */
function yw_int_secret(string $name): ?string {
    $stored=current_site_setting('int_secret_'.$name,'');
    if($stored==='')return '';
    $raw=str_starts_with($stored,'v1:')?base64_decode(substr($stored,3),true):false;
    $key=yw_int_key();
    if($raw===false || strlen($raw)<29 || $key===null)return null;
    $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),'wwc-int|'.$name);
    return $plain===false?null:$plain;
}

/* ---------- Herkunft der Einstellungen ---------- */

/** Auftragszentrale: true, wenn die config.php die Übergabe festlegt (dann nur Anzeige). */
function yw_az_from_config(): bool {
    return in_array(strtolower(trim((string)(cfg('handover_mode')?:'off'))),['push','pull','both'],true);
}
function yw_lexware_from_config(): bool { return trim((string)(cfg('lexware_api_key')??''))!==''; }

/** Prüft das eigene Admin-Passwort für Änderungen an Zugangsdaten (gedrosselt wie die Anmeldung). */
function yw_admin_confirm_password(string $password): void {
    if(login_throttled('admin-confirm',8))throw new DomainException('Zu viele falsche Passwörter. Bitte 15 Minuten warten.');
    $q=db()->prepare('SELECT password_hash FROM admins WHERE id=? AND active=1');$q->execute([(int)($_SESSION['admin_id']??0)]);
    if($password==='' || !password_verify($password,(string)$q->fetchColumn())){record_login_failure('admin-confirm');throw new DomainException('Zur Bestätigung bitte dein aktuelles Admin-Passwort eingeben.');}
    clear_login_failures('admin-confirm');
}

/** Meldet allen aktiven Administratoren eine geänderte Schnittstelle (Schutz vor unbemerkten Änderungen). */
function yw_int_notify_admins(string $what): void {
    try{
        $q=db()->prepare('SELECT name FROM admins WHERE id=?');$q->execute([(int)($_SESSION['admin_id']??0)]);$who=(string)($q->fetchColumn()?:'ein Administrator');
        foreach(db()->query('SELECT email FROM admins WHERE active=1') as $a)send_system_mail((string)$a['email'],'WorkwearCenter: Schnittstelle geändert',"Hallo,\n\n".$who.' hat am '.date('d.m.Y').' um '.date('H:i')." Uhr folgende Schnittstelle geändert:\n\n".$what."\n\nWarst du das nicht, melde dich sofort an, prüfe die Einstellung und ändere dein Passwort.\n\nSchnittstellen: ".workwearcenter_url('admin?tab=schnittstellen'));
    }catch(Throwable $e){error_log('Integration notify: '.$e->getMessage());}
}

/* ---------- Aktionen ---------- */

function yw_admin_integration_action(string $action): never {
    $target='/admin?tab=schnittstellen';
    try{
        if($action==='int_az_save'){
            $target.='#auftragszentrale';
            if(yw_az_from_config())throw new DomainException('Die Übergabe ist fest in der config.php eingetragen und kann nur dort geändert werden.');
            yw_admin_confirm_password((string)($_POST['confirm_password']??''));
            $enabled=($_POST['enabled']??'')==='1';
            $url=trim((string)($_POST['url']??''));
            $secret=trim((string)($_POST['secret']??''));
            if($url!==''){
                $p=parse_url($url)?:[];$scheme=strtolower((string)($p['scheme']??''));$host=strtolower((string)($p['host']??''));
                if(!filter_var($url,FILTER_VALIDATE_URL) || isset($p['user']) || isset($p['pass']))throw new DomainException('Die Adresse ist ungültig.');
                if($scheme!=='https' && !($scheme==='http' && in_array($host,['localhost','127.0.0.1','::1','[::1]'],true)))throw new DomainException('Die Adresse muss mit https:// beginnen.');
                if(mb_strlen($url)>500)throw new DomainException('Die Adresse ist zu lang.');
            }
            if($secret!=='' && (strlen($secret)<32 || strlen($secret)>500 || preg_match('/\s/',$secret)))throw new DomainException('Der Schlüssel aus der Auftragszentrale muss mindestens 32 Zeichen lang sein (ohne Leerzeichen).');
            $hasSecret=$secret!=='' || strlen((string)yw_int_secret('az_secret'))>=32 || strlen((string)(cfg('handover_secret')?:''))>=32;
            if($enabled && $url==='' && trim((string)(cfg('handover_url')?:''))==='')throw new DomainException('Zum Einschalten bitte die Adresse der Auftragszentrale eintragen.');
            if($enabled && !$hasSecret)throw new DomainException('Zum Einschalten bitte den Schlüssel aus der Auftragszentrale eintragen (Verkaufskanäle → WorkwearCenter → „Schlüssel erzeugen“).');
            $before=yw_handover_config();
            set_site_setting('az_url',$url);
            if($secret!=='')yw_int_secret_set('az_secret',$secret);
            set_site_setting('az_enabled',$enabled?'1':'0');
            yw_handover_config_reset();
            $after=yw_handover_config();
            $host=(string)(parse_url($after['url'],PHP_URL_HOST)?:'–');
            audit_log('integration.az_saved','system',null,['enabled'=>$enabled,'host'=>$host,'secret_changed'=>$secret!=='']);
            if($before['mode']!==$after['mode'] || $before['url']!==$after['url'] || $secret!=='')yw_int_notify_admins('Auftragszentrale: '.($enabled?'eingeschaltet':'ausgeschaltet').' · Ziel '.$host.($secret!==''?' · neuer Schlüssel':''));
            if(!$enabled){flash('success','Gespeichert. Die Übergabe ist ausgeschaltet – freigegebene Aufträge werden gesammelt und nach dem Einschalten übertragen.');redirect($target);}
            // Gleich nach dem Speichern prüfen, ob die Auftragszentrale erreichbar ist und den Schlüssel annimmt.
            [$ok,$message]=yw_handover_ping();
            if(!$ok){flash('error','Gespeichert, aber die Verbindung klappt noch nicht: '.$message.' Aufträge bleiben gesammelt, bis die Verbindung steht.');redirect($target);}
            // Verbindung steht: gesammelte (auch zuvor fehlgeschlagene Versuche mit altem Schlüssel) sofort senden, Rest per Cronjob.
            $sent=0;$left=0;
            try{
                set_site_setting('handover_pause_until','0');
                db()->exec("UPDATE yw_handover SET available_at=NOW() WHERE status='pending' AND available_at>NOW() AND attempts>0"); // Sammelbestellungen (Stichtag, noch kein Versuch) bleiben unberührt
                $sent=yw_process_handover_queue(20);
                $left=(int)db()->query("SELECT COUNT(*) FROM yw_handover WHERE status='pending' AND available_at<=NOW()")->fetchColumn();
            }catch(Throwable $e){error_log('Handover after save: '.$e->getMessage());}
            flash('success','Gespeichert und verbunden: '.$message.($sent?' '.$sent.' gesammelte'.($sent===1?'r Auftrag wurde':' Aufträge wurden').' gleich übertragen.':'').($left?' '.$left.' weitere folgen automatisch.':''));
            redirect($target);
        }
        if($action==='int_lexware_save'){
            $target.='#lexware';
            if(yw_lexware_from_config())throw new DomainException('Der Lexware-Schlüssel steht in der config.php und kann nur dort geändert werden.');
            yw_admin_confirm_password((string)($_POST['confirm_password']??''));
            $remove=($_POST['remove']??'')==='1';
            $key=trim((string)($_POST['api_key']??''));
            if($remove){
                yw_int_secret_set('lexware_key','');
                audit_log('integration.lexware_saved','system',null,['removed'=>true]);
                yw_int_notify_admins('Lexware Office: Schlüssel entfernt, Verbindung getrennt');
                flash('success','Lexware-Schlüssel wurde entfernt. Rechnungen werden wieder von Hand in Lexware erstellt.');redirect($target);
            }
            if($key===''){
                if(!yw_lexware_enabled())throw new DomainException('Bitte den API-Schlüssel aus Lexware Office eintragen.');
                flash('success','Nichts geändert – der bisherige Lexware-Schlüssel bleibt hinterlegt.');redirect($target);
            }
            if(strlen($key)<20 || strlen($key)>200 || !preg_match('/^[A-Za-z0-9._~+\/=-]+$/',$key))throw new DomainException('Das sieht nicht wie ein Lexware-API-Schlüssel aus. Bitte den Schlüssel vollständig kopieren.');
            yw_int_secret_set('lexware_key',$key);
            audit_log('integration.lexware_saved','system',null,['removed'=>false]);
            yw_int_notify_admins('Lexware Office: neuer API-Schlüssel hinterlegt');
            try{$p=yw_lexware_test_connection();flash('success','Gespeichert und verbunden mit Lexware Office: '.(string)($p['companyName']??'').'.');}
            catch(Throwable $e){flash('error','Gespeichert, aber Lexware lehnt die Verbindung ab: '.($e instanceof YwLexwareException?$e->getMessage():'Verbindung fehlgeschlagen.').' Bitte den Schlüssel prüfen.');}
            redirect($target);
        }
        throw new DomainException('Aktion unbekannt.');
    }catch(DomainException $e){flash('error',$e->getMessage());}
    catch(Throwable $e){error_log('Integration action: '.$e->getMessage());flash('error','Speichern fehlgeschlagen.');}
    redirect($target);
}

/** Verbindungstest zur Auftragszentrale. @return array{0:bool,1:string} */
function yw_handover_ping(): array {
    if(!yw_handover_push_enabled())return [false,'Die Übergabe ist ausgeschaltet.'];
    $payload=['schema'=>YW_HANDOVER_SCHEMA,'event'=>'test','delivery_id'=>'test-'.bin2hex(random_bytes(8)),'generated_at'=>(new DateTimeImmutable())->format(DATE_ATOM),'source'=>['system'=>'WorkwearCenter','version'=>app_version()],'message'=>'Testübertragung – bitte nicht als Auftrag anlegen.'];
    [$status,$response,$error]=yw_handover_send('test',$payload['delivery_id'],(string)json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    audit_log('handover.test','handover',null,['http_status'=>$status]);
    set_site_setting('az_last_test',(string)json_encode(['at'=>date('Y-m-d H:i:s'),'ok'=>$status>=200&&$status<300,'status'=>$status]));
    if($status>=200&&$status<300)return [true,'Die Auftragszentrale hat geantwortet (HTTP '.$status.').'];
    if($status===401||$status===403)return [false,'Die Auftragszentrale lehnt den Schlüssel ab (HTTP '.$status.'). Bitte den Schlüssel neu erzeugen und eintragen.'];
    return [false,$error!==''?$error:'HTTP '.$status.' '.mb_substr(trim(strip_tags($response)),0,200)];
}

/* ---------- Seite ---------- */

/** Ein Eintrag der Übersicht: [Titel, Zustand ok|warn|off, Kurztext, Anker]. */
function yw_integration_overview(): array {
    $c=yw_handover_config();$counts=['pending'=>0,'failed'=>0,'manual'=>0];
    try{foreach(db()->query("SELECT status,COUNT(*) n FROM yw_handover WHERE status IN ('pending','failed','manual') GROUP BY status") as $r)$counts[(string)$r['status']]=(int)$r['n'];}catch(Throwable $e){}
    $issues=yw_handover_config_issues();
    if($c['mode']==='off')$az=['off',($c['secret_broken']??false)?'Gespeicherter Schlüssel nicht mehr lesbar – bitte neu eintragen':'Aus – '.$counts['pending'].' Auftr'.($counts['pending']===1?'ag':'äge').' gesammelt'];
    elseif($issues)$az=['warn',$issues[0]];
    elseif($counts['failed'])$az=['warn',$counts['failed'].' Übergabe'.($counts['failed']===1?'':'n').' fehlgeschlagen'];
    else $az=['ok','Aktiv'.($counts['pending']?' · '.$counts['pending'].' in der Warteschlange':'').($counts['manual']?' · '.$counts['manual'].' Umtausch von Hand übertragen':'')];
    $mail=['failed'=>0];try{$mail['failed']=(int)db()->query("SELECT COUNT(*) FROM yw_mail_queue WHERE status='failed'")->fetchColumn();}catch(Throwable $e){}
    $mailIssues=yw_mail_config_issues();
    $mailState=$mailIssues?['warn',$mailIssues[0]]:($mail['failed']?['warn',$mail['failed'].' E-Mail'.($mail['failed']===1?'':'s').' fehlgeschlagen']:['ok','Versand über '.strtoupper((string)(cfg('mail_transport')?:'mail'))]);
    $lexBroken=!yw_lexware_from_config() && yw_int_secret('lexware_key')===null;
    $lex=yw_lexware_enabled()?(current_site_setting('lexware_profile_checked_at','')!==''?['ok','Verbunden']:['warn','Schlüssel hinterlegt, Verbindung noch nicht bestätigt']):['off',$lexBroken?'Gespeicherter Schlüssel nicht mehr lesbar – bitte neu eintragen':'Nicht verbunden – Rechnungen von Hand'];
    $keys=0;try{$keys=(int)db()->query('SELECT COUNT(*) FROM yw_api_keys WHERE active=1')->fetchColumn();}catch(Throwable $e){}
    $daiberLast=json_decode(current_site_setting('katalog_daiber_last',''),true);
    $daiber=function_exists('yw_daiber_enabled')&&yw_daiber_enabled()?['ok','Automatisch'.(is_array($daiberLast)&&!empty($daiberLast['t'])?' · zuletzt '.date('d.m.Y H:i',(int)strtotime((string)$daiberLast['t'])):'')]:['off','Automatik aus'];
    return [
        ['Auftragszentrale','Freigegebene Aufträge senden, Produktionsstand und Sendungsnummer zurückbekommen',$az,'#auftragszentrale'],
        ['E-Mail-Versand','Einladungen, Freigaben, Statusmeldungen',$mailState,'#mail'],
        ['Lexware Office','Kundenkontakte und Rechnungsentwürfe',$lex,'#lexware'],
        ['Zugänge für andere Systeme','Fan-Bande, Partner und Abfragen über die API',$keys?['ok',$keys.' aktive'.($keys===1?'r Zugang':' Zugänge')]:['off','Keine Zugänge angelegt'],'#api'],
        ['Daiber-Produktbilder','Bilder neuer Daiber-Artikel automatisch von daiber.shop',$daiber,'/admin?tab=katalog'],
    ];
}

function yw_admin_integrations_page(): void {
    echo '<section class="card"><div class="title-row"><div><h2>Alle Schnittstellen auf einen Blick</h2><p class="muted">Jede Verbindung des WorkwearCenters zu einem anderen System steht genau einmal auf dieser Seite.</p></div></div><div class="release-checks">';
    foreach(yw_integration_overview() as [$title,$what,[$state,$text],$href]){
        $mark=$state==='ok'?'✓':($state==='warn'?'!':'○');
        echo '<a class="release-check '.($state==='ok'?'ok':'warn').'" href="'.e($href).'"><strong>'.$mark.' '.e($title).'</strong><span>'.e($text).'</span><span class="muted">'.e($what).'</span></a>';
    }
    echo '</div></section>';
    yw_admin_handover();
    yw_admin_mail_section();
    yw_lexware_connection_section();
    yw_admin_api_page();
}

/** E-Mail-Versand: Zustand, Testmail, Warteschlange (vorher im Systemstatus). */
function yw_admin_mail_section(): void {
    $mail=['pending'=>0,'failed'=>0];$failedMails=[];
    try{
        foreach(db()->query("SELECT status,COUNT(*) amount FROM yw_mail_queue WHERE status IN ('pending','failed') GROUP BY status") as $r)$mail[$r['status']]=(int)$r['amount'];
        $failedMails=db()->query("SELECT id,to_email,subject,attempts,last_error,created_at FROM yw_mail_queue WHERE status='failed' ORDER BY id DESC LIMIT 10")->fetchAll();
    }catch(Throwable $e){}
    $transport=(string)(cfg('mail_transport')?:'mail');$issues=yw_mail_config_issues();
    $ok=!$issues && !$mail['failed'];
    echo '<section class="card" id="mail"><div class="title-row"><div><h2>E-Mail-Versand</h2><p class="muted">Einladungen, Anmeldelinks, Freigaben und Statusmeldungen. Der Cronjob verschickt die Warteschlange alle fünf Minuten, normale Seitenaufrufe helfen zusätzlich mit.</p></div><span class="badge '.($ok?'approved':'rejected').'">'.($ok?'Funktioniert':'Prüfen').'</span></div>';
    echo '<div class="release-checks"><div class="release-check '.($issues?'warn':'ok').'"><strong>Versandweg</strong><span>'.e($transport==='smtp'?'SMTP über '.(string)cfg('smtp_host'):'Maildienst des Hosters').' · Absender '.e((string)(cfg('mail_from')?:'noreply@'.workwearcenter_domain())).'</span></div><div class="release-check '.($mail['failed']?'warn':'ok').'"><strong>Warteschlange</strong><span>'.$mail['pending'].' wartend · '.$mail['failed'].' fehlgeschlagen</span></div></div>';
    foreach($issues as $issue)echo '<div class="flash error space-top">'.e($issue).'. Die Zugangsdaten für den Mailversand stehen in der config.php (Anleitung: SMTP-KONFIGURATION-V9.txt).</div>';
    echo '<div class="actions space-top"><form method="post">'.csrf_field().'<input type="hidden" name="action" value="yw_mail_test"><button class="btn primary">Testmail an mich senden</button></form>';
    if($mail['failed'])echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="yw_mail_process"><button class="btn">Fehlgeschlagene E-Mails erneut senden</button></form>';
    echo '</div>';
    if($failedMails){
        echo '<h3 class="space-top">Letzte fehlgeschlagene E-Mails</h3><div class="table-wrap"><table class="table"><thead><tr><th>Empfänger</th><th>Betreff</th><th>Versuche</th><th>Fehler</th></tr></thead><tbody>';
        foreach($failedMails as $failed) echo '<tr><td>'.e($failed['to_email']).'</td><td>'.e($failed['subject']).'</td><td>'.(int)$failed['attempts'].'</td><td>'.e($failed['last_error']?:'Keine Angabe').'</td></tr>';
        echo '</tbody></table></div>';
    }
    echo '</section>';
}

/** Lexware Office: nur Verbindung (Schlüssel, Test). Rechnungsentwürfe stehen unter Tagesgeschäft → Abrechnung. */
function yw_lexware_connection_section(): void {
    $enabled=yw_lexware_enabled();$fromConfig=yw_lexware_from_config();$broken=!$fromConfig && yw_int_secret('lexware_key')===null;
    $profile=json_decode(current_site_setting('lexware_profile_json',''),true);
    $checked=current_site_setting('lexware_profile_checked_at','');
    echo '<section class="card" id="lexware"><div class="title-row"><div><h2>Lexware Office</h2><p class="muted">Legt Kundenkontakte und Rechnungsentwürfe in Lexware an (nie endgültige Rechnungen). Die Rechnungen selbst erstellst du unter <a href="/admin?tab=lexware">Tagesgeschäft → Abrechnung</a>.</p></div><span class="badge '.($enabled&&$checked!==''?'approved':'pending').'">'.($enabled?($checked!==''?'Verbunden':'Noch nicht bestätigt'):'Nicht verbunden').'</span></div>';
    if($broken)echo '<div class="flash error">Der gespeicherte Schlüssel ist nicht mehr lesbar (die Schlüsseldatei im privaten Datenordner fehlt oder wurde ersetzt). Bitte den Lexware-Schlüssel neu eintragen.</div>';
    if(!yw_lexware_base_ok(yw_lexware_base()))echo '<div class="flash error">lexware_api_base in der config.php ist ungültig. Erlaubt ist nur HTTPS.</div>';
    if($enabled && is_array($profile))echo '<p><strong>Verbunden mit:</strong> '.e((string)($profile['companyName']??'')).' · Steuerart '.e((string)($profile['taxType']??'')).(!empty($profile['smallBusiness'])?' · <span class="badge rejected">Kleinunternehmer – Rechnungen mit Steuer passen nicht</span>':'').($checked!==''?'<br><span class="muted">Geprüft am '.e(date('d.m.Y H:i',(int)strtotime($checked))).'</span>':'').'</p>';
    if($enabled)echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="lexware_test"><input type="hidden" name="return" value="schnittstellen"><button class="btn">Verbindung testen</button></form>';
    if($fromConfig){echo '<p class="muted space-top">Der Schlüssel steht in der config.php (<code>lexware_api_key</code>) und kann nur dort geändert werden. Wird er dort gelöscht, lässt er sich hier eintragen.</p></section>';return;}
    echo '<details class="details space-top"'.($enabled?'':' open').'><summary>'.($enabled?'Schlüssel ändern oder Verbindung trennen':'Lexware Office verbinden').'</summary><div class="details-inner">';
    echo '<ol class="muted"><li>In Lexware Office unter <a href="'.e(YW_LEXWARE_APP_URL.'/addons/public-api').'" target="_blank" rel="noopener noreferrer">Erweiterungen → Public API</a> einen Schlüssel erzeugen.</li><li>Hier einfügen und speichern. Die Verbindung wird sofort geprüft.</li></ol>';
    echo '<form method="post" class="form-stack" autocomplete="off">'.csrf_field().'<input type="hidden" name="action" value="int_lexware_save">'
        .'<label>API-Schlüssel aus Lexware Office'.($enabled?' <small>leer lassen = bisherigen behalten</small>':'').'<input type="password" name="api_key" maxlength="200" autocomplete="new-password" spellcheck="false"'.($enabled?'':' required').'></label>'
        .($enabled?'<label class="check"><input type="checkbox" name="remove" value="1"><span>Verbindung trennen und Schlüssel löschen</span></label>':'')
        .'<label>Dein Admin-Passwort <small>zur Bestätigung</small><input type="password" name="confirm_password" required autocomplete="current-password"></label>'
        .'<button class="btn primary">Speichern und Verbindung prüfen</button></form></div></details></section>';
}
