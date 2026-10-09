<?php
declare(strict_types=1);

function db(): PDO { global $pdo; return $pdo; }
function cfg(string $key): mixed { global $config; return $config[$key] ?? null; }
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function path_only(): string { return '/' . ltrim((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/'); }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function redirect(string $url): never { header('Location: ' . $url, true, 303); exit; }
function random_token(): string { return bin2hex(random_bytes(32)); }
function yw_dummy_password_hash(): string {
    // Valid bcrypt cost-12 hash used only to equalise unknown-account logins.
    return '$2y$12$wLhB7ybs9BxhwD8cCvQ9IeBQCTPBN55VjA61nFj8s8Q8z7yR5q72K';
}
function money(int $cents): string { return number_format($cents / 100, 2, ',', '.') . ' €'; }
function color_ok(string $color): bool { return (bool)preg_match('/^#[0-9a-f]{6}$/i', $color); }
function slug_ok(string $slug): bool { return (bool)preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $slug) && !yw_slug_reserved($slug); }
/** Kurznamen, die mit festen Seiten, Ordnern oder Systemdateien kollidieren würden. */
function yw_slug_reserved(string $slug): bool {
    return in_array($slug,['admin','leistungen','ueber-uns','so-funktionierts','kontakt','anfrage','impressum','datenschutz','mustershop','agb','assets','uploads','app','tests','update','cron','index','config','sitemap','robots','well-known','api','login','logout','shop','www','mail','zugang','kunden','kunden-login','2fa','probeshop'],true);
}
function yw_upload_path_ok(string $path,string $folder): bool {
    return (bool)preg_match('#^uploads/'.preg_quote($folder,'#').'/[a-zA-Z0-9][a-zA-Z0-9._-]*\.(?:png|jpe?g|webp)$#D',$path);
}
function yw_local_image_path(string $path,array $uploadFolders=[],bool $allowMedia=false): string {
    $path=trim($path);
    foreach($uploadFolders as $folder){
        if(yw_upload_path_ok($path,(string)$folder))return $path;
    }
    if($allowMedia && preg_match('#^assets/media/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9][a-zA-Z0-9._-]*\.(?:png|jpe?g|webp)$#D',$path))return $path;
    return '';
}
function yw_validate_uploaded_raster(string $tmp,string $label='Bild'): void {
    $info=@getimagesize($tmp);
    if(!is_array($info) || !isset($info[0],$info[1],$info[2]))throw new RuntimeException($label.' ist keine lesbare Bilddatei.');
    $width=(int)$info[0];$height=(int)$info[1];$type=(int)$info[2];
    $allowed=[IMAGETYPE_JPEG,IMAGETYPE_PNG];
    if(defined('IMAGETYPE_WEBP'))$allowed[]=(int)constant('IMAGETYPE_WEBP');
    if(!in_array($type,$allowed,true))throw new RuntimeException($label.' muss PNG, JPG oder WebP sein.');
    if($width<1||$height<1||$width>12000||$height>12000||$width*$height>40_000_000)throw new RuntimeException($label.' hat unzulässige Abmessungen (maximal 40 Megapixel).');
}
function yw_company_logo_path(array $company): string {
    return yw_local_image_path((string)($company['logo_path']??''),['logos']);
}
/**
 * Privater Datenordner für Dateien, die nie über das Web erreichbar sein dürfen
 * (z. B. Lieferantenlisten mit Einkaufspreisen). Am sichersten liegt er außerhalb
 * des Webroots ('private_dir' in der config.php). Ohne Eintrag wird app/private
 * verwendet: dort sperren die Haupt-.htaccess und eine eigene .htaccess den Zugriff.
 */
function yw_private_dir(): string {
    $configured=trim((string)(cfg('private_dir')??''));
    $dir=$configured!==''?rtrim($configured,'/\\'):__DIR__.'/private';
    if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Privater Datenordner konnte nicht angelegt werden: '.$dir);
    if($configured===''){
        if(!is_file($dir.'/.htaccess'))@file_put_contents($dir.'/.htaccess',"Require all denied\n");
        if(!is_file($dir.'/index.html'))@file_put_contents($dir.'/index.html','');
    }
    return $dir;
}
function workwearcenter_domain(): string { return (string)(cfg('base_domain') ?: 'workwearcenter.de'); }
/** Öffentliche Adresse so, wie der Shop tatsächlich aufgerufen wird (mit oder ohne www). */
function yw_public_host(): string {
    $base=strtolower(workwearcenter_domain());
    $host=strtolower((string)preg_replace('/:\d+$/','',(string)($_SERVER['HTTP_HOST']??'')));
    return $host==='www.'.$base?$host:$base;
}
function workwearcenter_url(string $path=''): string {
    $path='/'.ltrim($path,'/');
    return 'https://'.workwearcenter_domain().($path==='/'?'':$path);
}

function csrf(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">'; }
function verify_csrf(): void {
    if (empty($_SESSION['csrf']) || !is_string($_POST['csrf'] ?? null) || !hash_equals((string)$_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(419); exit('Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden.');
    }
}
function flash(string $type, string $message): void { $_SESSION['flash'] = [$type, $message]; }
function take_flash(): ?array { $flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $flash; }

function admin_logged_in(): bool {
    if(empty($_SESSION['admin_id'])) return false;
    try{$q=db()->prepare('SELECT auth_version FROM admins WHERE id=? AND active=1 LIMIT 1');$q->execute([(int)$_SESSION['admin_id']]);$version=$q->fetchColumn();return $version!==false && (int)$version===(int)($_SESSION['admin_auth_version']??0);}
    catch(Throwable $e){
        // Kompatibilitätsweg ausschließlich bis der kumulative Update-Assistent
        // die auth_version-Spalte angelegt hat.
        try{$q=db()->prepare('SELECT id FROM admins WHERE id=? LIMIT 1');$q->execute([(int)$_SESSION['admin_id']]);return (bool)$q->fetchColumn();}
        catch(Throwable $ignored){return false;}
    }
}
function require_admin(): void { if (!admin_logged_in()) redirect('/admin/login'); }
function login_throttled(string $key,int $limit=8,bool $perIp=true): bool {
    $limit=max(1,min(1000,$limit));
    if(yw_schema_ready()){
        $q=db()->prepare('SELECT attempts FROM yw_login_attempts WHERE bucket=? AND started_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)');
        $q->execute([yw_login_bucket($key,$perIp)]);
        return (int)$q->fetchColumn()>=$limit;
    }
    $item = $_SESSION['login_attempts'][$key] ?? ['count' => 0, 'since' => time()];
    if (time() - (int)$item['since'] > 900) { unset($_SESSION['login_attempts'][$key]); return false; }
    return (int)$item['count'] >= $limit;
}
function record_login_failure(string $key,bool $perIp=true): void {
    if(yw_schema_ready()){
        $q=db()->prepare("INSERT INTO yw_login_attempts(bucket,attempts,started_at) VALUES(?,1,NOW()) ON DUPLICATE KEY UPDATE attempts=IF(started_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE),1,attempts+1),started_at=IF(started_at<DATE_SUB(NOW(),INTERVAL 15 MINUTE),NOW(),started_at)");
        $q->execute([yw_login_bucket($key,$perIp)]); return;
    }
    $item = $_SESSION['login_attempts'][$key] ?? ['count' => 0, 'since' => time()];
    $item['count']++; $_SESSION['login_attempts'][$key] = $item;
}
function clear_login_failures(string $key,bool $perIp=true): void {
    unset($_SESSION['login_attempts'][$key]);
    if(yw_schema_ready()) db()->prepare('DELETE FROM yw_login_attempts WHERE bucket=?')->execute([yw_login_bucket($key,$perIp)]);
}

function yw_login_bucket(string $key,bool $perIp=true): string {
    $scope=$perIp?(string)($_SERVER['REMOTE_ADDR']??'local'):'*';
    return hash_hmac('sha256',$key.'|'.$scope,(string)cfg('db_pass'));
}

function audit_log(string $action, ?string $entityType=null, ?int $entityId=null, array $metadata=[]): void {
    try {
        $actorType='guest'; $actorId=null; $companyId=null;
        if(!empty($_SESSION['admin_id'])) { $actorType='admin'; $actorId=(int)$_SESSION['admin_id']; }
        elseif(!empty($_SESSION['employee_account_id'])) {
            $actorType='employee'; $actorId=(int)$_SESSION['employee_account_id'];
            $stmt=db()->prepare('SELECT company_id FROM employee_accounts WHERE id=?');
            $stmt->execute([$actorId]); $companyId=(int)$stmt->fetchColumn() ?: null;
        }
        if(isset($metadata['company_id'])) $companyId=(int)$metadata['company_id'];
        $ip=(string)($_SERVER['REMOTE_ADDR']??'');
        $ipHash=$ip!=='' ? hash_hmac('sha256',$ip,(string)cfg('db_pass')) : null;
        $stmt=db()->prepare('INSERT INTO audit_log(actor_type,actor_id,company_id,action,entity_type,entity_id,metadata_json,ip_hash) VALUES(?,?,?,?,?,?,?,?)');
        $stmt->execute([$actorType,$actorId,$companyId,$action,$entityType,$entityId,$metadata?json_encode($metadata,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null,$ipHash]);
    } catch(Throwable $e) {
        error_log('WorkwearCenter audit log failed: '.$e->getMessage());
    }
}

function yw_mail_address(string $key,string $fallback): string {
    $value=trim((string)(cfg($key)?:$fallback));
    return filter_var($value,FILTER_VALIDATE_EMAIL)?$value:$fallback;
}

function yw_mail_config_issues(): array {
    $issues=[];
    $transport=(string)(cfg('mail_transport')?:'mail');
    $from=(string)(cfg('mail_from')?:'noreply@'.workwearcenter_domain());
    $reply=(string)(cfg('mail_reply_to')?:'info@yanik-textildruck.de');
    if(!in_array($transport,['mail','smtp'],true))$issues[]='mail_transport muss „mail“ oder „smtp“ sein';
    if(!filter_var($from,FILTER_VALIDATE_EMAIL))$issues[]='mail_from ist keine gültige E-Mail-Adresse';
    if(!filter_var($reply,FILTER_VALIDATE_EMAIL))$issues[]='mail_reply_to ist keine gültige E-Mail-Adresse';
    if($transport==='smtp'){
        $host=trim((string)cfg('smtp_host'));
        $port=(int)(cfg('smtp_port')?:0);
        $encryption=(string)(cfg('smtp_encryption')?:'');
        $user=(string)cfg('smtp_user');
        $pass=(string)cfg('smtp_pass');
        if($host===''||preg_match('/[\r\n\s:\/]/',$host))$issues[]='smtp_host fehlt oder ist ungültig';
        if($port<1||$port>65535)$issues[]='smtp_port muss zwischen 1 und 65535 liegen';
        if(!in_array($encryption,['tls','ssl','none'],true))$issues[]='smtp_encryption muss tls, ssl oder none sein';
        if(($user==='')!==($pass===''))$issues[]='SMTP-Benutzer und SMTP-Passwort müssen gemeinsam gesetzt sein';
    }
    return $issues;
}

function yw_mail_action_url(string $value): ?string {
    if(!filter_var($value,FILTER_VALIDATE_URL)) return null;
    $parts=parse_url($value);
    $scheme=strtolower((string)($parts['scheme']??''));
    $host=strtolower((string)($parts['host']??''));
    $base=strtolower(workwearcenter_domain());
    if($scheme!=='https' || !in_array($host,[$base,'www.'.$base],true)) return null;
    return $value;
}

function yw_mail_html(string $subject,string $message): string {
    $safeSubject=htmlspecialchars($subject,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $lines=preg_split('/\r?\n/',trim($message))?:[];
    $content='';$details=[];
    foreach($lines as $line){
        $line=trim($line);
        if($line===''){
            if($details){
                $content.='<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:18px 0;border-collapse:separate;border-spacing:0;border:1px solid #e7e9ee;border-radius:10px;background:#f7f8fa">';
                foreach($details as [$label,$value])$content.='<tr><td style="padding:10px 14px;color:#697386;font-size:13px;border-bottom:1px solid #e7e9ee">'.$label.'</td><td style="padding:10px 14px;color:#172033;font-size:14px;font-weight:700;text-align:right;border-bottom:1px solid #e7e9ee">'.$value.'</td></tr>';
                $content.='</table>';$details=[];
            }
            continue;
        }
        $actionUrl=yw_mail_action_url($line);
        if($actionUrl!==null){
            $url=htmlspecialchars($actionUrl,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            $content.='<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0"><tr><td bgcolor="#f36c21" style="border-radius:8px"><a href="'.$url.'" style="display:inline-block;padding:14px 22px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700">Jetzt im WorkwearCenter öffnen</a></td></tr></table>';
            continue;
        }
        if(preg_match('/^([^:]{2,32}):\s*(https?:\/\/\S+)$/u',$line,$match) && ($actionUrl=yw_mail_action_url($match[2]))!==null){
            if($details){
                $content.='<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:18px 0;border-collapse:separate;border-spacing:0;border:1px solid #e7e9ee;border-radius:10px;background:#f7f8fa">';
                foreach($details as [$label,$value])$content.='<tr><td style="padding:10px 14px;color:#697386;font-size:13px;border-bottom:1px solid #e7e9ee">'.$label.'</td><td style="padding:10px 14px;color:#172033;font-size:14px;font-weight:700;text-align:right;border-bottom:1px solid #e7e9ee">'.$value.'</td></tr>';
                $content.='</table>';$details=[];
            }
            $url=htmlspecialchars($actionUrl,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            $label=htmlspecialchars($match[1],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            $content.='<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0"><tr><td bgcolor="#f36c21" style="border-radius:8px"><a href="'.$url.'" style="display:inline-block;padding:14px 22px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700">'.$label.'</a></td></tr></table>';
            continue;
        }
        if(preg_match('/^([^:]{2,32}):\s*(.+)$/u',$line,$match)){
            $value=htmlspecialchars($match[2],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            // Nur Sendungsverfolgung bekannter Paketdienste wird verlinkt (kein Phishing über Freitexte).
            if(preg_match('#^https://(?:www\.)?(?:dhl\.de|tracking\.dpd\.de|ups\.com|gls-group\.com|myhermes\.de)/\S*$#i',trim($match[2])))$value='<a href="'.$value.'" style="color:#d95a0c">Sendung verfolgen</a>';
            $details[]=[htmlspecialchars($match[1],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'),$value];
            continue;
        }
        if($details){
            $content.='<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:18px 0;border-collapse:separate;border-spacing:0;border:1px solid #e7e9ee;border-radius:10px;background:#f7f8fa">';
            foreach($details as [$label,$value])$content.='<tr><td style="padding:10px 14px;color:#697386;font-size:13px;border-bottom:1px solid #e7e9ee">'.$label.'</td><td style="padding:10px 14px;color:#172033;font-size:14px;font-weight:700;text-align:right;border-bottom:1px solid #e7e9ee">'.$value.'</td></tr>';
            $content.='</table>';$details=[];
        }
        $content.='<p style="margin:0 0 14px;color:#3c4658;font-size:15px;line-height:1.65">'.htmlspecialchars($line,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>';
    }
    if($details){
        $content.='<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:18px 0;border-collapse:separate;border-spacing:0;border:1px solid #e7e9ee;border-radius:10px;background:#f7f8fa">';
        foreach($details as [$label,$value])$content.='<tr><td style="padding:10px 14px;color:#697386;font-size:13px;border-bottom:1px solid #e7e9ee">'.$label.'</td><td style="padding:10px 14px;color:#172033;font-size:14px;font-weight:700;text-align:right;border-bottom:1px solid #e7e9ee">'.$value.'</td></tr>';
        $content.='</table>';
    }
    $logo='';
    try {
        $logoPath=yw_site_logo_path();
        if($logoPath==='assets/media/workwearcenter-logo.png')$logoPath='assets/media/workwearcenter-logo-dunkel.png'; // weiße Schrift wäre auf weißem Mailhintergrund unsichtbar
        if(preg_match('#^(?:uploads/branding|assets/media)/[a-zA-Z0-9._-]+\.(?:png|jpe?g|webp)$#',$logoPath)){
            $logoUrl='https://'.workwearcenter_domain().'/'.$logoPath;
            $logo='<img src="'.htmlspecialchars($logoUrl,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'" width="190" alt="WorkwearCenter by Yanik Textildruck" style="display:block;width:190px;max-width:100%;height:auto;border:0">';
        }
    } catch(Throwable $ignored) {}
    if($logo==='') $logo='<table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="width:42px;height:42px;background:#f36c21;color:#ffffff;font-size:16px;font-weight:900;text-align:center">YW</td><td style="padding-left:13px"><div style="font-size:13px;letter-spacing:1.6px;font-weight:800;color:#ffffff">YANIK WORKWEAR</div><div style="margin-top:4px;font-size:12px;color:#b9c0cc">by Yanik Textildruck</div></td></tr></table>';
    return '<!doctype html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$safeSubject.'</title></head><body style="margin:0;padding:0;background:#ece9e3;font-family:Arial,Helvetica,sans-serif"><div style="display:none;max-height:0;overflow:hidden;color:transparent">'.$safeSubject.'</div><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#ece9e3"><tr><td align="center" style="padding:34px 12px"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #ddd9d1"><tr><td bgcolor="#172033" style="padding:26px 34px;background:#172033;border-top:7px solid #f36c21;border-bottom:1px solid #27364d">'.$logo.'</td></tr><tr><td style="padding:34px 34px 36px"><div style="margin-bottom:13px;font-size:11px;letter-spacing:1.7px;font-weight:800;color:#f36c21">WORKWEARCENTER · SERVICEINFORMATION</div><h1 style="margin:0 0 24px;color:#172033;font-size:27px;line-height:1.22">'.$safeSubject.'</h1>'.$content.'<p style="margin:30px 0 0;padding-top:22px;border-top:1px solid #e7e3dc;color:#3c4658;font-size:15px;line-height:1.6">Viele Grüße<br><strong>Ihr Team vom WorkwearCenter</strong></p></td></tr><tr><td style="padding:21px 34px;background:#172033;color:#b9c0cc;font-size:12px;line-height:1.65"><strong style="color:#ffffff">WorkwearCenter.de</strong><br>Der Unternehmensshop von WorkwearCenter by Yanik Textildruck<br><a href="https://workwearcenter.de" style="color:#f7955f;text-decoration:none">workwearcenter.de</a> · <a href="mailto:info@yanik-textildruck.de" style="color:#f7955f;text-decoration:none">info@yanik-textildruck.de</a></td></tr></table><div style="max-width:620px;padding:13px 4px 0;text-align:center;color:#7a7f87;font-size:11px;line-height:1.45">Diese Nachricht wurde automatisch aufgrund eines Vorgangs im WorkwearCenter versendet.</div></td></tr></table></body></html>';
}

function yw_mail_multipart(string $subject,string $message,string $boundary): string {
    $plain=preg_replace('/\r?\n/',"\r\n",trim($message))."\r\n\r\nViele Grüße\r\nIhr Team vom WorkwearCenter\r\nworkwearcenter.de";
    $html=yw_mail_html($subject,$message);
    // RFC 5322 erlaubt höchstens 998 Zeichen pro Zeile. Das HTML-Layout und lange
    // Anfragetexte überschreiten das deutlich; Base64 mit 76er-Zeilen ist für alle
    // Mailserver unkritisch und schützt zusätzlich vor Punkt-/Zeilenende-Problemen.
    $encode=static fn(string $part): string => rtrim(chunk_split(base64_encode($part),76,"\r\n"));
    return '--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".$encode($plain)."\r\n--".$boundary."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".$encode($html)."\r\n--".$boundary."--";
}

function yw_mail_transport(string $to,string $subject,string $message,?string $replyOverride=null): void {
    $configIssues=yw_mail_config_issues();
    if($configIssues)throw new RuntimeException('Mailkonfiguration unvollständig: '.$configIssues[0].'.');
    $domain=(string)cfg('base_domain');$from=yw_mail_address('mail_from','noreply@'.$domain);$reply=yw_mail_address('mail_reply_to','info@yanik-textildruck.de');
    if($replyOverride!==null && filter_var($replyOverride,FILTER_VALIDATE_EMAIL) && !preg_match('/[\r\n]/',$replyOverride)) $reply=$replyOverride;
    if((string)(cfg('mail_transport')?:'mail')==='smtp'){yw_smtp_deliver($to,$subject,$message,$from,$reply);return;}
    $boundary='yw_'.bin2hex(random_bytes(12));$body=yw_mail_multipart($subject,$message,$boundary);
    $headers=['MIME-Version: 1.0','Content-Type: multipart/alternative; boundary="'.$boundary.'"','From: WorkwearCenter <'.$from.'>','Reply-To: '.$reply];
    // Absender auch im Umschlag setzen (-f), sonst nutzen viele Hoster den Webserver-Benutzer und SPF/DMARC schlagen fehl.
    $params=preg_match('/^[A-Za-z0-9._%+@-]+$/',$from)?'-f'.$from:'';
    if(!@mail($to,'=?UTF-8?B?'.base64_encode($subject).'?=',$body,implode("\r\n",$headers),$params)) throw new RuntimeException('Mailtransport hat die Nachricht nicht angenommen.');
}

function yw_smtp_read($socket): array {
    $text='';$code=0;
    while(($line=fgets($socket,2048))!==false){$text.=$line;if(preg_match('/^(\d{3})([ -])/',$line,$m)){$code=(int)$m[1];if($m[2]===' ')break;}}
    if($code===0)throw new RuntimeException('Keine gültige SMTP-Antwort.');return [$code,$text];
}

function yw_smtp_command($socket,?string $command,array $expected): string {
    if($command!==null && fwrite($socket,$command."\r\n")===false)throw new RuntimeException('SMTP-Verbindung unterbrochen.');
    [$code,$text]=yw_smtp_read($socket);if(!in_array($code,$expected,true))throw new RuntimeException('SMTP-Fehler '.$code.'.');return $text;
}

function yw_smtp_deliver(string $to,string $subject,string $message,string $from,string $reply): void {
    $host=trim((string)cfg('smtp_host'));$port=(int)(cfg('smtp_port')?:587);$encryption=(string)(cfg('smtp_encryption')?:'tls');if(!in_array($encryption,['tls','ssl','none'],true))$encryption='tls';
    if($host===''||$port<1||$port>65535)throw new RuntimeException('SMTP ist nicht vollständig konfiguriert.');
    $remote=($encryption==='ssl'?'ssl':'tcp').'://'.$host.':'.$port;$errorNo=0;$errorText='';
    $socket=@stream_socket_client($remote,$errorNo,$errorText,15,STREAM_CLIENT_CONNECT);if(!$socket)throw new RuntimeException('SMTP-Verbindung fehlgeschlagen.');stream_set_timeout($socket,15);
    try{
        $ehlo=preg_replace('/[^a-z0-9.-]/i','',(string)cfg('base_domain'))?:'localhost';yw_smtp_command($socket,null,[220]);yw_smtp_command($socket,'EHLO '.$ehlo,[250]);
        if($encryption==='tls'){yw_smtp_command($socket,'STARTTLS',[220]);if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('SMTP-TLS fehlgeschlagen.');yw_smtp_command($socket,'EHLO '.$ehlo,[250]);}
        $user=(string)cfg('smtp_user');$pass=(string)cfg('smtp_pass');if($user!==''){yw_smtp_command($socket,'AUTH LOGIN',[334]);yw_smtp_command($socket,base64_encode($user),[334]);yw_smtp_command($socket,base64_encode($pass),[235]);}
        yw_smtp_command($socket,'MAIL FROM:<'.$from.'>',[250]);yw_smtp_command($socket,'RCPT TO:<'.$to.'>',[250,251]);yw_smtp_command($socket,'DATA',[354]);
        $safeSubject=str_replace(["\r","\n"],' ',$subject);$boundary='yw_'.bin2hex(random_bytes(12));$body=yw_mail_multipart($safeSubject,$message,$boundary);$body=preg_replace('/^\./m','..',$body);
        $headers="Date: ".date(DATE_RFC2822)."\r\nFrom: WorkwearCenter <".$from.">\r\nReply-To: ".$reply."\r\nTo: <".$to.">\r\nSubject: =?UTF-8?B?".base64_encode($safeSubject)."?=\r\nMIME-Version: 1.0\r\nContent-Type: multipart/alternative; boundary=\"".$boundary."\"";
        yw_smtp_command($socket,$headers."\r\n\r\n".$body."\r\n.",[250]);yw_smtp_command($socket,'QUIT',[221]);
    }finally{fclose($socket);}
}

function yw_deliver_queued_mail(int $id): bool {
    try{$claim=db()->prepare("UPDATE yw_mail_queue SET status='sending',available_at=DATE_ADD(NOW(),INTERVAL 15 MINUTE) WHERE id=? AND status='pending' AND available_at<=NOW()");$claim->execute([$id]);if(!$claim->rowCount())return false;$q=db()->prepare("SELECT * FROM yw_mail_queue WHERE id=? AND status='sending'");$q->execute([$id]);$row=$q->fetch();if(!$row)return false;yw_mail_transport((string)$row['to_email'],(string)$row['subject'],(string)$row['body'],($row['reply_to']??'')!==''?(string)$row['reply_to']:null);db()->prepare("UPDATE yw_mail_queue SET status='sent',sent_at=NOW(),last_error=NULL WHERE id=? AND status='sending'")->execute([$id]);return true;}
    catch(Throwable $e){$next=(new DateTimeImmutable())->modify('+'.min(60,2**min(6,(int)($row['attempts']??0))).' minutes')->format('Y-m-d H:i:s');try{db()->prepare("UPDATE yw_mail_queue SET attempts=attempts+1,status=IF(attempts>=7,'failed','pending'),available_at=?,last_error=? WHERE id=?")->execute([$next,mb_substr($e->getMessage(),0,500),$id]);}catch(Throwable $ignored){}error_log('WorkwearCenter mail queue: '.$e->getMessage());return false;}
}

function yw_process_mail_queue(int $limit=3): void {
    $limit=max(1,min(100,$limit));
    $locked=(int)db()->query("SELECT GET_LOCK('yanik_workwear_mail_worker',0)")->fetchColumn()===1;
    if(!$locked) return;
    try{
        db()->exec("UPDATE yw_mail_queue SET status='pending' WHERE status='sending' AND available_at<=NOW()");
        $ids=db()->query("SELECT id FROM yw_mail_queue WHERE status='pending' AND available_at<=NOW() ORDER BY id LIMIT ".$limit)->fetchAll(PDO::FETCH_COLUMN);
        foreach($ids as $id)yw_deliver_queued_mail((int)$id);
    }finally{
        try{db()->query("SELECT RELEASE_LOCK('yanik_workwear_mail_worker')");}catch(Throwable $ignored){}
    }
}

function yw_cleanup_expired_security_rows(): void {
    // Diese Tabellen sind reine Sicherheits-/Transportdaten und dürfen nicht
    // unbegrenzt wachsen. Fach-, Bestell- und Auditdaten werden nicht berührt.
    try {
        db()->exec("DELETE FROM yw_login_attempts WHERE started_at<DATE_SUB(NOW(),INTERVAL 1 DAY)");
        db()->exec("DELETE FROM yw_password_resets WHERE created_at<DATE_SUB(NOW(),INTERVAL 7 DAY)");
        db()->exec("DELETE FROM yw_admin_password_resets WHERE created_at<DATE_SUB(NOW(),INTERVAL 7 DAY)");
        // Datensparsamkeit (DSGVO Art. 5): versendete Mails enthalten Namen und Bestelldaten
        // und werden nach 90 Tagen entfernt; erledigte Anfragen nach 24 Monaten.
        db()->exec("DELETE FROM yw_mail_queue WHERE status='sent' AND sent_at<DATE_SUB(NOW(),INTERVAL 90 DAY)");
        db()->exec("DELETE FROM inquiries WHERE status='done' AND created_at<DATE_SUB(NOW(),INTERVAL 24 MONTH)");
    } catch(Throwable $e) {
        error_log('WorkwearCenter security cleanup failed: '.$e->getMessage());
    }
}

function send_system_mail(string $to,string $subject,string $message,bool $immediate=false,?string $replyTo=null): bool {
    if(!filter_var($to,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$to))return false;$subject=mb_substr(str_replace(["\r","\n"],' ',$subject),0,255);
    if($replyTo!==null && (!filter_var($replyTo,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$replyTo))) $replyTo=null;
    try{$q=db()->prepare('INSERT INTO yw_mail_queue(to_email,subject,body,reply_to) VALUES(?,?,?,?)');$q->execute([$to,$subject,$message,$replyTo]);$id=(int)db()->lastInsertId();return $immediate?yw_deliver_queued_mail($id):true;}
    catch(Throwable $e){try{yw_mail_transport($to,$subject,$message,$replyTo);return true;}catch(Throwable $mailError){error_log('WorkwearCenter mail failed: '.$mailError->getMessage());return false;}}
}

function notify_company_managers(int $companyId,string $subject,string $message): void {
    try {
        $stmt=db()->prepare("SELECT email FROM employee_accounts WHERE company_id=? AND role='manager' AND active=1 AND registration_status='approved' AND (employment_start IS NULL OR employment_start<=CURDATE()) AND (employment_end IS NULL OR employment_end>=CURDATE())");
        $stmt->execute([$companyId]);
        foreach($stmt->fetchAll() as $row) send_system_mail((string)$row['email'],$subject,$message);
    } catch(Throwable $e) {
        error_log('WorkwearCenter manager notification failed: '.$e->getMessage());
    }
}

function notify_order_employee(int $orderId,string $subject,string $message): void {
    try {
        $stmt=db()->prepare("SELECT ea.email FROM orders o JOIN employee_accounts ea ON ea.company_id=o.company_id AND (ea.id=o.employee_account_id OR (o.employee_account_id IS NULL AND ea.invite_id=o.invite_id)) WHERE o.id=? AND ea.active=1 AND ea.registration_status='approved' AND (ea.employment_start IS NULL OR ea.employment_start<=CURDATE()) AND (ea.employment_end IS NULL OR ea.employment_end>=CURDATE()) ORDER BY (ea.id=o.employee_account_id) DESC,ea.id LIMIT 1");
        $stmt->execute([$orderId]);
        $email=$stmt->fetchColumn();
        if($email) send_system_mail((string)$email,$subject,$message);
    } catch(Throwable $e) {
        error_log('WorkwearCenter employee notification failed: '.$e->getMessage());
    }
}

function yw_order_mail_summary(int $orderId): string {
    $q=db()->prepare("SELECT o.*,c.legal_name,COALESCE(ea.name,i.employee_name,'Unbekannt') employee_name FROM orders o JOIN companies c ON c.id=o.company_id LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id LEFT JOIN invites i ON i.id=o.invite_id WHERE o.id=? LIMIT 1");
    $q->execute([$orderId]);$order=$q->fetch();
    if(!$order) return 'Bestellung: #'.$orderId;
    $status=['pending'=>'Freigabe erforderlich','approved'=>'Freigegeben','rejected'=>'Abgelehnt','cancelled'=>'Zurückgezogen'][(string)$order['status']]??(string)$order['status'];
    $text="Bestellung: #".$orderId."\nFirma: ".$order['legal_name']."\nMitarbeiter: ".$order['employee_name']."\nStatus: ".$status."\n\nBestellte Artikel:\n";
    foreach(order_items($orderId) as $item){
        $text.=(int)$item['quantity'].' × '.$item['product_name'].' · Größe '.$item['size'].' · '.money(yw_order_item_amounts($item)['gross'])."\n";
    }
    if((int)($order['shipping_gross_cents']??0)>0)$text.='Versand: '.money((int)$order['shipping_gross_cents'])."\n";
    $gross=(int)($order['gross_total_cents']??0);if($gross<=0)$gross=(int)$order['total_cents'];
    $text.="\nGesamt brutto: ".money($gross)."\nFirma übernimmt: ".money((int)($order['company_total_cents']??0));
    if((int)($order['employee_total_cents']??0)>0)$text.="\nMitarbeiteranteil: ".money((int)$order['employee_total_cents']);
    return $text;
}

function employee_budget_remaining(array $company,array $account): ?int {
    $budget=yw_budget_limit($company,$account);
    return $budget===null ? null : max(0,$budget-yw_budget_used($company,$account));
}

function upload_logo(?array $file, ?string $oldPath = null): ?string {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return $oldPath;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 1_500_000) throw new RuntimeException('Logo konnte nicht hochgeladen werden oder ist größer als 1,5 MB.');
    $tmp=(string)($file['tmp_name']??'');
    if($tmp==='' || !is_uploaded_file($tmp)) throw new RuntimeException('Logo wurde nicht als gültiger Upload erkannt.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) throw new RuntimeException('Erlaubt sind PNG, JPG und WebP.');
    yw_validate_uploaded_raster($tmp,'Logo');
    $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $relative = 'uploads/logos/' . $name;
    $dir=dirname(__DIR__).'/uploads/logos';
    if(!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new RuntimeException('Logo-Ordner konnte nicht erstellt werden.');
    if (!move_uploaded_file($tmp, dirname(__DIR__) . '/' . $relative)) throw new RuntimeException('Logo konnte nicht gespeichert werden.');
    return $relative;
}

function delete_company_logo(?string $path): void {
    if(!$path || !yw_upload_path_ok($path,'logos'))return;
    $file=dirname(__DIR__).'/'.$path;
    if(is_file($file))@unlink($file);
}

function order_items(int $orderId): array { $stmt=db()->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id'); $stmt->execute([$orderId]); return $stmt->fetchAll(); }

function order_items_grouped(array $orderIds): array {
    $ids=array_values(array_unique(array_filter(array_map('intval',$orderIds),fn(int $id):bool=>$id>0)));
    $grouped=[];foreach($ids as $id)$grouped[$id]=[];
    foreach(array_chunk($ids,500) as $chunk){
        $placeholders=implode(',',array_fill(0,count($chunk),'?'));
        $q=db()->prepare("SELECT * FROM order_items WHERE order_id IN ($placeholders) ORDER BY order_id,id");$q->execute($chunk);
        foreach($q as $row)$grouped[(int)$row['order_id']][]=$row;
    }
    return $grouped;
}

function app_version(): string {
    static $version = null;
    if ($version !== null) return $version;
    $file = dirname(__DIR__) . '/VERSION';
    $value = is_file($file) ? trim((string)file_get_contents($file)) : '';
    return $version = ($value !== '' ? $value : 'unbekannt');
}

function yw_site_logo_path(): string {
    $fallback='assets/media/workwearcenter-logo.png';
    if(!function_exists('current_site_setting')) return $fallback;
    try{$path=trim((string)current_site_setting('site_logo',''));}
    catch(Throwable $e){return $fallback;}
    return $path!=='' && yw_upload_path_ok($path,'branding') ? $path : $fallback;
}

function render_head(string $title, ?array $brand = null): void {
    $primary = $brand && color_ok((string)($brand['primary_color']??'')) ? (string)$brand['primary_color'] : '#101b2d';
    $accent = $brand && color_ok((string)($brand['accent_color']??'')) ? (string)$brand['accent_color'] : '#ff6b00';
    $path=path_only();
    $public=in_array($path,['/','/leistungen','/ueber-uns','/so-funktionierts','/mustershop','/kontakt','/anfrage','/impressum','/datenschutz','/zugang'],true);
    $robots=$public&&$path!=='/zugang'?'index,follow,max-image-preview:large':($path==='/zugang'?'noindex,follow':'noindex,nofollow');
    $url='https://'.yw_public_host().$path;
    $canonical=$public?'<link rel="canonical" href="'.e($url).'">':'';
    $descriptions=[
        '/'=>'Firmenkleidung mit eigenem Online-Shop: Ihr Team bestellt selbst, Sie geben frei, wir besticken und bedrucken in Salzkotten. Yanik Textildruck – für Betriebe in Paderborn und OWL.',
        '/leistungen'=>'Arbeitskleidung und Teamkleidung mit Logo: Stick, Textildruck und Transferdruck aus eigener Produktion in Salzkotten – inklusive Firmenshop mit Budgets und Freigaben.',
        '/ueber-uns'=>'Yanik Textildruck aus Salzkotten: Textilveredelung aus eigener Hand, persönliche Beratung und ein Online-Shop für die Firmenkleidung Ihres Teams.',
        '/so-funktionierts'=>'In vier Schritten zum eigenen Firmenshop: Kollektion abstimmen, Regeln festlegen, Team bestellen lassen, fertig veredelte Kleidung erhalten.',
        '/mustershop'=>'Probieren Sie einen Beispiel-Firmenshop aus – mit echten Artikeln, Budget, Größenwahl und Freigabe durch den Verantwortlichen.',
        '/kontakt'=>'Kontakt zu Yanik Textildruck in Salzkotten: Beratung zu Firmenkleidung, Veredelung und eigenem Unternehmensshop.',
        '/anfrage'=>'Unverbindlich anfragen: eigener Online-Shop für die Firmenkleidung Ihres Teams, mit Logo-Veredelung von Yanik Textildruck.',
        '/impressum'=>'Impressum von Yanik Textildruck, Salzkotten – Betreiber des WorkwearCenters.',
        '/datenschutz'=>'Datenschutzhinweise für das WorkwearCenter von Yanik Textildruck.',
        '/zugang'=>'Kunden-Login: Über den Kurznamen direkt zum eigenen Firmenshop im WorkwearCenter.',
    ];
    $description=$descriptions[$path]??'Geschützter Unternehmensshop für Firmenkleidung von WorkwearCenter.';
    $social=$public?'<meta property="og:type" content="website"><meta property="og:locale" content="de_DE"><meta property="og:site_name" content="WorkwearCenter"><meta property="og:title" content="'.e($title).'"><meta property="og:description" content="'.e($description).'"><meta property="og:url" content="'.e($url).'"><meta property="og:image" content="'.e('https://'.yw_public_host().'/assets/media/screens/shop-desktop.webp').'"><meta name="twitter:card" content="summary_large_image">':'';
    $favicon='';
    $structured='';
    if($public){
        $logo=yw_site_logo_path();
        $favicon='<link rel="icon" type="image/png" href="/assets/media/workwearcenter-icon.png"><link rel="apple-touch-icon" href="/assets/media/workwearcenter-icon.png">';
        $organization=[
            '@context'=>'https://schema.org',
            '@type'=>'LocalBusiness',
            'name'=>current_site_setting('imprint_company','Yanik Textildruck'),
            'alternateName'=>['WorkwearCenter','Yanik Workwear'],
            'description'=>'Textilveredelung (Stick und Druck) und Online-Shops für Firmenkleidung.',
            'url'=>'https://'.yw_public_host(),
            'sameAs'=>['https://www.yanik-textildruck.de'],
            'areaServed'=>['Salzkotten','Paderborn','Ostwestfalen-Lippe','Deutschland'],
            'email'=>current_site_setting('imprint_email','info@yanik-textildruck.de'),
        ];
        $organization['logo']='https://'.yw_public_host().'/'.$logo;
        $organization['image']='https://'.yw_public_host().'/assets/media/screens/shop-desktop.webp';
        $street=trim((string)current_site_setting('imprint_street',''));$city=trim((string)current_site_setting('imprint_city',''));
        if($street!=='' && preg_match('/^(\d{5})\s+(.+)$/u',$city,$cm))$organization['address']=['@type'=>'PostalAddress','streetAddress'=>$street,'postalCode'=>$cm[1],'addressLocality'=>$cm[2],'addressCountry'=>'DE'];
        $structured='<script type="application/ld+json">'.json_encode($organization,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP).'</script>';
    }
    $preload=$path==='/mustershop'?'<link rel="preload" as="image" href="/assets/media/products/workwear-tshirt.webp" type="image/webp">':'';
    echo '<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="'.$robots.'"><meta name="description" content="'.e($description).'">'.$canonical.$social.$favicon.$preload.'<meta name="theme-color" content="'.e($primary).'"><title>' . e($title) . '</title><link rel="stylesheet" href="/assets/style.css"><link rel="stylesheet" href="/assets/v9.css?v='.e(app_version()).'">'.($public||$path==='/zugang'||str_starts_with($path,'/probeshop/')?'<link rel="stylesheet" href="/assets/site.css?v='.e(app_version()).'"><link rel="preload" href="/assets/fonts/InterVariable.woff2" as="font" type="font/woff2" crossorigin>':'').'<style>:root{--primary:' . e($primary) . ';--accent:' . e($accent) . '}</style>'.$structured.'</head><body>';
}
function render_flash(): void { if ($f=take_flash()) echo '<div class="flash ' . e($f[0]) . '">' . e($f[1]) . '</div>'; }
function render_foot(): void { echo '<script src="/assets/app.js?v='.e(app_version()).'" defer></script></body></html>'; }
