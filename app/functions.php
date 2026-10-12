<?php
declare(strict_types=1);

require_once __DIR__.'/i18n.php'; // 10.12: Übersetzungen, Zahlen- und Datumsformat je Sprache

function db(): PDO { global $pdo; return $pdo; }
function cfg(string $key): mixed { global $config; return $config[$key] ?? null; }
/** Upload speichern; bei vollem Speicher bleibt sonst eine abgeschnittene Datei liegen. */
function yw_move_upload(string $tmp,string $target): bool { $existed=file_exists($target); if(move_uploaded_file($tmp,$target))return true; if(!$existed)@unlink($target); return false; }
/** Exporte: Zeilen ungepuffert einzeln vom Server holen statt das ganze Ergebnis in den PHP-Speicher; im Callback keine weiteren Abfragen. */
function yw_each_row(string $sql,array $params,callable $fn): void {
    $pdo=db();$buffered=$pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);$q=null;
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
    try{$q=$pdo->prepare($sql);$q->execute($params);foreach($q as $row)$fn($row);}
    finally{if($q)$q->closeCursor();$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,$buffered);}
}
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function path_only(): string { return '/' . ltrim((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'), '/'); }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function redirect(string $url): never { yw_remember_input(); header('Location: ' . yw_admin_return_target($url), true, 303); exit; }
/**
 * Nach einer Fehlermeldung sollen die Eingaben nicht verloren gehen: Endet ein Formular mit einer
 * Fehler-Meldung, merkt sich die Sitzung die Eingaben (ohne Passwörter, PINs und Schlüssel) für den
 * nächsten Seitenaufruf; assets/app.js trägt sie wieder in dasselbe Formular ein.
 */
function yw_remember_input(): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || (($_SESSION['flash'][0] ?? '') !== 'error') || empty($_POST['action'])) return;
    $clean = static function (array $data, int $depth = 0) use (&$clean): array {
        $out = [];
        foreach ($data as $key => $value) {
            if (preg_match('/^(csrf|return_to|action)$|pass|secret|token|^pin$|_pin$|api_?key|otp|iban/i', (string)$key)) continue;
            if (is_array($value)) { if ($depth < 4) $out[$key] = $clean($value, $depth + 1); }
            elseif (is_scalar($value)) $out[$key] = mb_substr((string)$value, 0, 20000);
        }
        return $out;
    };
    $json = json_encode(['action' => (string)$_POST['action'], 'data' => $clean($_POST)], JSON_UNESCAPED_UNICODE);
    if (is_string($json) && strlen($json) <= 200000) $_SESSION['old_input'] = $json;
}
/** Gemerkte Eingaben einmalig für assets/app.js ausgeben. */
function yw_old_input_script(): string {
    $json = $_SESSION['old_input'] ?? null; unset($_SESSION['old_input']);
    if (!is_string($json) || $json === '') return '';
    return '<script type="application/json" id="yw-old-input">' . str_replace(['<', '>', '&'], ['\\u003c', '\\u003e', '\\u0026'], $json) . '</script>';
}
/**
 * Kundenakte: Nach dem Speichern zurück in denselben Reiter (z. B. „Kollektion & Design“) statt auf die
 * Übersicht oder – bei einem Fehler – auf die Kundenliste. Die Formulare der Kundenakte tragen dafür
 * return_to (siehe csrf_field()); übernommen wird nur ein Ziel derselben Firma innerhalb von /admin.
 */
function yw_admin_return_target(string $url): string {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || path_only() !== '/admin') return $url;
    // 10.15: return_to trägt auch Suche, Seite und Filter der Listen; wird nur bereinigt neu aufgebaut übernommen.
    [$backCompany, $backTab, $back] = yw_customer_return_to((string)($_POST['return_to'] ?? ''));
    if ($back === '') return $url;
    if (!str_starts_with($url, '/admin?tab=companies')) return $url;
    $anchor = (string)(parse_url($url, PHP_URL_FRAGMENT) ?? '');
    parse_str((string)(parse_url($url, PHP_URL_QUERY) ?? ''), $q);
    $sameCompany = isset($q['company']) && (int)$q['company'] === $backCompany;
    $isError = (($_SESSION['flash'][0] ?? '') === 'error') && !isset($q['company']);
    if (!$sameCompany && !$isError) return $url;
    // Ziel nennt einen anderen Reiter oder eigene Listenparameter: dann gilt das Ziel.
    if ($sameCompany && isset($q['bereich']) && ((string)$q['bereich'] !== $backTab || array_diff_key($q, ['tab' => 1, 'company' => 1, 'bereich' => 1]))) return $url;
    return $back . ($anchor !== '' ? '#' . $anchor : '');
}
/**
 * 10.15: Suche, Seite und Filter der Listen in der Kundenakte (Mitarbeiter, Kollektion, Regeln) –
 * nur bekannte Parameter, bereinigt. Damit landet man nach dem Speichern wieder an derselben Stelle.
 */
function yw_customer_list_state(array $q): array {
    $state = [];
    $enums = ['anzeige' => ['kollektion', 'katalog', 'alle'], 'profil_anzeige' => ['alle', 'freigegeben', 'offen']];
    foreach (['suche' => 's', 'seite' => 'p', 'artikel' => 's', 'kategorie' => 's', 'anzeige' => 'e', 'aseite' => 'p', 'produkt' => 'i', 'grenzen_suche' => 's', 'grenzen_kategorie' => 's', 'grenzen_seite' => 'p', 'profil' => 'i', 'profil_suche' => 's', 'profil_kategorie' => 's', 'profil_anzeige' => 'e', 'profil_seite' => 'p'] as $key => $type) {
        $v = $q[$key] ?? null;
        if (!is_string($v)) continue;
        $v = trim($v);
        if ($type === 's') { $v = trim(mb_substr($v, 0, 120)); if ($v !== '') $state[$key] = $v; }
        elseif ($type === 'e') { if (in_array($v, $enums[$key], true)) $state[$key] = $v; }
        elseif (preg_match('/^[1-9]\d{0,8}$/D', $v) && ($type === 'i' || $v !== '1')) $state[$key] = $v;
    }
    return $state;
}
/** Adresse eines Reiters der Kundenakte mit Listenzustand. */
function yw_customer_state_url(int $companyId, string $tab = 'uebersicht', array $state = []): string {
    return '/admin?' . http_build_query(['tab' => 'companies', 'company' => $companyId] + ($tab !== '' && $tab !== 'uebersicht' ? ['bereich' => $tab] : []) + ($tab !== '' && $tab !== 'uebersicht' ? yw_customer_list_state($state) : []));
}
/** return_to eines Formulars prüfen: [Firma, Reiter, bereinigte Adresse] oder [0, '', ''] – nie ein fremdes Ziel. */
function yw_customer_return_to(string $back): array {
    if (!str_starts_with($back, '/admin?') || strlen($back) > 2000) return [0, '', ''];
    parse_str((string)parse_url($back, PHP_URL_QUERY), $b);
    if (($b['tab'] ?? '') !== 'companies' || !is_string($b['company'] ?? null) || !preg_match('/^[1-9]\d{0,9}$/D', $b['company'])) return [0, '', ''];
    $tab = is_string($b['bereich'] ?? null) && preg_match('/^[a-z]{1,30}$/D', $b['bereich']) ? $b['bereich'] : 'uebersicht';
    return [(int)$b['company'], $tab, yw_customer_state_url((int)$b['company'], $tab, $b)];
}
function random_token(): string { return bin2hex(random_bytes(32)); }
function yw_dummy_password_hash(): string {
    // Gültiger bcrypt-Hash nur zum Angleichen der Laufzeit bei unbekannten Konten. Die Kosten müssen zu den
    // echten Hashes (PASSWORD_DEFAULT) passen, sonst verrät die Antwortzeit, ob eine E-Mail-Adresse ein Konto hat.
    static $hash=null;
    if($hash!==null)return $hash;
    $cost=defined('PASSWORD_BCRYPT_DEFAULT_COST')?(int)PASSWORD_BCRYPT_DEFAULT_COST:10;
    return $hash=match($cost){10=>'$2y$10$M6.6jedFguQBYO8TzUpBXOes370qu3dzreffMmOTaYDGo243rk9qG',12=>'$2y$12$wLhB7ybs9BxhwD8cCvQ9IeBQCTPBN55VjA61nFj8s8Q8z7yR5q72K',default=>password_hash('yw-dummy',PASSWORD_DEFAULT)};
}
function money(int $cents): string { return yw_money($cents); } // Format je Sprache (Deutsch: 1.234,56 €)
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
function csrf_field(): string {
    $field = '<input type="hidden" name="csrf" value="' . e(csrf()) . '">';
    // In der Kundenakte merkt sich jedes Formular den aktuellen Reiter (siehe yw_admin_return_target()).
    if (path_only() === '/admin' && ($_GET['tab'] ?? '') === 'companies' && (int)($_GET['company'] ?? 0) > 0 && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        // 10.15: mit Suche, Seite und Filtern der Liste
        $bereich = is_string($_GET['bereich'] ?? null) ? $_GET['bereich'] : '';
        $back = yw_customer_state_url((int)$_GET['company'], preg_match('/^[a-z]{1,30}$/D', $bereich) ? $bereich : 'uebersicht', $_GET);
        $field .= '<input type="hidden" name="return_to" value="' . e($back) . '">';
    }
    return $field;
}
function verify_csrf(): void {
    if (empty($_SESSION['csrf']) || !is_string($_POST['csrf'] ?? null) || !hash_equals((string)$_SESSION['csrf'], $_POST['csrf'])) {
        // Ist der Upload größer als post_max_size, verwirft PHP das ganze Formular – dann ehrlich sagen, woran es liegt.
        if (!$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            http_response_code(413); exit('<!doctype html><meta charset="utf-8"><title>Upload zu groß</title><body style="font-family:system-ui;max-width:640px;margin:60px auto;padding:0 20px;line-height:1.6"><h1>Upload zu groß</h1><p>Die Dateien waren zusammen ' . e(number_format((int)$_SERVER['CONTENT_LENGTH'] / 1048576, 1, ',', '.')) . ' MB groß, der Server nimmt höchstens ' . e((string)ini_get('post_max_size')) . ' auf einmal an. Bitte weniger oder kleinere Dateien wählen, große Bildmengen per FTP ablegen.</p><p>Mit der Zurück-Taste des Browsers zurück zur Seite.</p></body>');
        }
        http_response_code(419); exit('Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden.');
    }
}
/** GET-Links mit Wirkung (Vorschau, Sicherung): Aufruf von fremden Seiten aus abweisen (Browser senden Sec-Fetch-Site). */
function yw_block_cross_site_get(): void {
    if (strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')) === 'cross-site') { http_response_code(403); exit('Dieser Link funktioniert nur aus der Verwaltung heraus. Bitte die Seite direkt öffnen.'); }
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
                foreach($details as [$label,$value])$content.='<tr><td style="padding:10px 14px;color:#697386;font-size:13px;border-bottom:1px solid #e7e9ee">'.$label.'</td><td style="padding:10px 14px;color:#292d2f;font-size:14px;font-weight:700;text-align:right;border-bottom:1px solid #e7e9ee">'.$value.'</td></tr>';
                $content.='</table>';$details=[];
            }
            continue;
        }
        $actionUrl=yw_mail_action_url($line);
        if($actionUrl!==null){
            $url=htmlspecialchars($actionUrl,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            $content.='<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0"><tr><td bgcolor="#fc6904" style="border-radius:8px"><a href="'.$url.'" style="display:inline-block;padding:14px 22px;color:#17191a;text-decoration:none;font-size:15px;font-weight:700">'.htmlspecialchars(t('mail.open_button'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</a></td></tr></table>';
            continue;
        }
        if(preg_match('/^([^:]{2,32}):\s*(https?:\/\/\S+)$/u',$line,$match) && ($actionUrl=yw_mail_action_url($match[2]))!==null){
            if($details){
                $content.='<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:18px 0;border-collapse:separate;border-spacing:0;border:1px solid #e7e9ee;border-radius:10px;background:#f7f8fa">';
                foreach($details as [$label,$value])$content.='<tr><td style="padding:10px 14px;color:#697386;font-size:13px;border-bottom:1px solid #e7e9ee">'.$label.'</td><td style="padding:10px 14px;color:#292d2f;font-size:14px;font-weight:700;text-align:right;border-bottom:1px solid #e7e9ee">'.$value.'</td></tr>';
                $content.='</table>';$details=[];
            }
            $url=htmlspecialchars($actionUrl,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            $label=htmlspecialchars($match[1],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            $content.='<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0"><tr><td bgcolor="#fc6904" style="border-radius:8px"><a href="'.$url.'" style="display:inline-block;padding:14px 22px;color:#17191a;text-decoration:none;font-size:15px;font-weight:700">'.$label.'</a></td></tr></table>';
            continue;
        }
        if(preg_match('/^([^:]{2,32}):\s*(.+)$/u',$line,$match)){
            $value=htmlspecialchars($match[2],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            // Nur Sendungsverfolgung bekannter Paketdienste wird verlinkt (kein Phishing über Freitexte).
            if(preg_match('#^https://(?:www\.)?(?:dhl\.de|tracking\.dpd\.de|ups\.com|gls-group\.com|myhermes\.de)/\S*$#i',trim($match[2])))$value='<a href="'.$value.'" style="color:#292d2f;text-decoration:underline;text-decoration-color:#fc6904">'.htmlspecialchars(t('mail.track'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</a>';
            $details[]=[htmlspecialchars($match[1],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'),$value];
            continue;
        }
        if($details){
            $content.='<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:18px 0;border-collapse:separate;border-spacing:0;border:1px solid #e7e9ee;border-radius:10px;background:#f7f8fa">';
            foreach($details as [$label,$value])$content.='<tr><td style="padding:10px 14px;color:#697386;font-size:13px;border-bottom:1px solid #e7e9ee">'.$label.'</td><td style="padding:10px 14px;color:#292d2f;font-size:14px;font-weight:700;text-align:right;border-bottom:1px solid #e7e9ee">'.$value.'</td></tr>';
            $content.='</table>';$details=[];
        }
        $content.='<p style="margin:0 0 14px;color:#565957;font-size:15px;line-height:1.65">'.htmlspecialchars($line,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</p>';
    }
    if($details){
        $content.='<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:18px 0;border-collapse:separate;border-spacing:0;border:1px solid #e7e9ee;border-radius:10px;background:#f7f8fa">';
        foreach($details as [$label,$value])$content.='<tr><td style="padding:10px 14px;color:#697386;font-size:13px;border-bottom:1px solid #e7e9ee">'.$label.'</td><td style="padding:10px 14px;color:#292d2f;font-size:14px;font-weight:700;text-align:right;border-bottom:1px solid #e7e9ee">'.$value.'</td></tr>';
        $content.='</table>';
    }
    $logo='';
    try {
        $logoPath=yw_site_logo_path(true);
        if(preg_match('#^(?:uploads/branding|assets/media)/[a-zA-Z0-9._-]+\.(?:png|jpe?g|webp)$#',$logoPath)){
            $logoUrl='https://'.workwearcenter_domain().'/'.$logoPath;
            $logo='<img src="'.htmlspecialchars($logoUrl,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'" width="190" alt="WorkwearCenter by Yanik Textildruck" style="display:block;width:190px;max-width:100%;height:auto;border:0">';
        }
    } catch(Throwable $ignored) {}
    if($logo==='') $logo='<table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="width:42px;height:42px;background:#292d2f;color:#fc6904;font-size:16px;font-weight:900;text-align:center">W</td><td style="padding-left:13px"><div style="font-size:13px;letter-spacing:1.6px;font-weight:800;color:#ffffff">WORKWEARCENTER</div><div style="margin-top:4px;font-size:12px;color:#c7c0b6">by Yanik Textildruck</div></td></tr></table>';
    $h=static fn(string $key): string => htmlspecialchars(t($key),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    return '<!doctype html><html lang="'.yw_lang().'"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$safeSubject.'</title></head><body style="margin:0;padding:0;background:#ece9e3;font-family:Arial,Helvetica,sans-serif"><div style="display:none;max-height:0;overflow:hidden;color:transparent">'.$safeSubject.'</div><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#ece9e3"><tr><td align="center" style="padding:34px 12px"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #ddd9d1"><tr><td bgcolor="#292d2f" style="padding:26px 34px;background:#292d2f;border-top:5px solid #fc6904;border-bottom:1px solid #444547">'.$logo.'</td></tr><tr><td style="padding:34px 34px 36px"><div style="margin-bottom:13px;padding-left:10px;border-left:3px solid #fc6904;font-size:11px;letter-spacing:1.7px;font-weight:800;color:#292d2f">'.$h('mail.kicker').'</div><h1 style="margin:0 0 24px;color:#292d2f;font-size:27px;line-height:1.22">'.$safeSubject.'</h1>'.$content.'<p style="margin:30px 0 0;padding-top:22px;border-top:1px solid #e7e2da;color:#565957;font-size:15px;line-height:1.6">'.$h('mail.regards').'<br><strong>'.$h('mail.team').'</strong></p></td></tr><tr><td style="padding:21px 34px;background:#292d2f;color:#c7c0b6;font-size:12px;line-height:1.65"><strong style="color:#ffffff">WorkwearCenter.de</strong><br>'.$h('mail.footer_line').'<br><a href="https://workwearcenter.de" style="color:#f0cdb4;text-decoration:none">workwearcenter.de</a> · <a href="mailto:info@yanik-textildruck.de" style="color:#f0cdb4;text-decoration:none">info@yanik-textildruck.de</a></td></tr></table><div style="max-width:620px;padding:13px 4px 0;text-align:center;color:#7a7f87;font-size:11px;line-height:1.45">'.$h('mail.auto_note').'</div></td></tr></table></body></html>';
}

function yw_mail_multipart(string $subject,string $message,string $boundary): string {
    $plain=preg_replace('/\r?\n/',"\r\n",trim($message))."\r\n\r\n".t('mail.regards')."\r\n".t('mail.team')."\r\nworkwearcenter.de";
    $html=yw_mail_html($subject,$message);
    // RFC 5322 erlaubt höchstens 998 Zeichen pro Zeile. Das HTML-Layout und lange
    // Anfragetexte überschreiten das deutlich; Base64 mit 76er-Zeilen ist für alle
    // Mailserver unkritisch und schützt zusätzlich vor Punkt-/Zeilenende-Problemen.
    $encode=static fn(string $part): string => rtrim(chunk_split(base64_encode($part),76,"\r\n"));
    return '--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".$encode($plain)."\r\n--".$boundary."\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".$encode($html)."\r\n--".$boundary."--";
}

function yw_mail_transport(string $to,string $subject,string $message,?string $replyOverride=null,?string $lang=null): void {
    if($lang!==null && $lang!==yw_lang()){yw_with_lang($lang,fn()=>yw_mail_transport($to,$subject,$message,$replyOverride));return;} // 10.12: Mailrahmen in der Sprache der Empfängerin
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
    try{$claim=db()->prepare("UPDATE yw_mail_queue SET status='sending',available_at=DATE_ADD(NOW(),INTERVAL 15 MINUTE) WHERE id=? AND status='pending' AND available_at<=NOW()");$claim->execute([$id]);if(!$claim->rowCount())return false;$q=db()->prepare("SELECT * FROM yw_mail_queue WHERE id=? AND status='sending'");$q->execute([$id]);$row=$q->fetch();if(!$row)return false;yw_mail_transport((string)$row['to_email'],(string)$row['subject'],(string)$row['body'],($row['reply_to']??'')!==''?(string)$row['reply_to']:null,yw_lang_valid($row['language']??null)?(string)$row['language']:'de');db()->prepare("UPDATE yw_mail_queue SET status='sent',sent_at=NOW(),last_error=NULL WHERE id=? AND status='sending'")->execute([$id]);return true;}
    catch(Throwable $e){$next=(new DateTimeImmutable())->modify('+'.min(60,2**min(6,(int)($row['attempts']??0))).' minutes')->format('Y-m-d H:i:s');try{db()->prepare("UPDATE yw_mail_queue SET attempts=attempts+1,status=IF(attempts>=7,'failed','pending'),available_at=?,last_error=? WHERE id=?")->execute([$next,mb_substr($e->getMessage(),0,500),$id]);}catch(Throwable $ignored){}error_log('WorkwearCenter mail queue: '.$e->getMessage());
        // Mailserver gestört: Stapel abbrechen und Seitenaufrufe 5 Minuten nicht mit Zustellversuchen (je bis 15 s) belasten.
        $GLOBALS['yw_mail_failed']=true;try{if(function_exists('set_site_setting'))set_site_setting('mail_pause_until',(string)(time()+300));}catch(Throwable $ignored){}
        return false;}
}

function yw_process_mail_queue(int $limit=3): void {
    $limit=max(1,min(100,$limit));
    // Kleine Stapel (nach Seitenaufrufen) pausieren nach einem Zustellfehler; der Cronjob versucht es weiter.
    if($limit<10 && function_exists('current_site_setting') && (int)current_site_setting('mail_pause_until','0')>time()) return;
    $locked=(int)db()->query("SELECT GET_LOCK('yanik_workwear_mail_worker',0)")->fetchColumn()===1;
    if(!$locked) return;
    try{
        db()->exec("UPDATE yw_mail_queue SET status='pending' WHERE status='sending' AND available_at<=NOW()");
        // Index (status,id,available_at) statt Durchlauf aller Mails in id-Reihenfolge (der Optimierer schätzt das bei kleinem LIMIT falsch).
        $ids=db()->query("SELECT id FROM yw_mail_queue IGNORE INDEX FOR ORDER BY (PRIMARY) WHERE status='pending' AND available_at<=NOW() ORDER BY id LIMIT ".$limit)->fetchAll(PDO::FETCH_COLUMN);
        $GLOBALS['yw_mail_failed']=false;
        foreach($ids as $id){yw_deliver_queued_mail((int)$id);if(!empty($GLOBALS['yw_mail_failed']))break;}
    }finally{
        try{db()->query("SELECT RELEASE_LOCK('yanik_workwear_mail_worker')");}catch(Throwable $ignored){}
    }
}

function yw_cleanup_expired_security_rows(): void {
    // Diese Tabellen sind reine Sicherheits-/Transportdaten und dürfen nicht
    // unbegrenzt wachsen. Fach- und Bestelldaten werden nicht berührt; Mailwarteschlange und
    // Aktivitätsprotokoll räumt yw_retention_prune() stapelweise auf (10.15, app/modules/admin_tools.php).
    try {
        db()->exec("DELETE FROM yw_login_attempts WHERE started_at<DATE_SUB(NOW(),INTERVAL 1 DAY)");
        db()->exec("DELETE FROM yw_password_resets WHERE created_at<DATE_SUB(NOW(),INTERVAL 7 DAY)");
        db()->exec("DELETE FROM yw_admin_password_resets WHERE created_at<DATE_SUB(NOW(),INTERVAL 7 DAY)");
        // Datensparsamkeit (DSGVO Art. 5): erledigte Anfragen nach 24 Monaten.
        db()->exec("DELETE FROM inquiries WHERE status='done' AND created_at<DATE_SUB(NOW(),INTERVAL 24 MONTH)");
    } catch(Throwable $e) {
        error_log('WorkwearCenter security cleanup failed: '.$e->getMessage());
    }
}

function send_system_mail(string $to,string $subject,string $message,bool $immediate=false,?string $replyTo=null): bool {
    if(!filter_var($to,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$to))return false;$subject=mb_substr(str_replace(["\r","\n"],' ',$subject),0,255);
    if($replyTo!==null && (!filter_var($replyTo,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$replyTo))) $replyTo=null;
    try{$q=db()->prepare('INSERT INTO yw_mail_queue(to_email,subject,body,reply_to,language) VALUES(?,?,?,?,?)');$q->execute([$to,$subject,$message,$replyTo,yw_lang()]);$id=(int)db()->lastInsertId();return $immediate?yw_deliver_queued_mail($id):true;}
    catch(Throwable $e){try{yw_mail_transport($to,$subject,$message,$replyTo);return true;}catch(Throwable $mailError){error_log('WorkwearCenter mail failed: '.$mailError->getMessage());return false;}}
}

/**
 * Mail an alle aktiven Verantwortlichen einer Firma. 10.12: Statt Betreff/Text kann ein Closure
 * übergeben werden – fn(array $empfaenger): [Betreff, Text] –, das je Person in deren Sprache läuft.
 */
function notify_company_managers(int $companyId,string|Closure $subject,string $message=''): void {
    try {
        $stmt=db()->prepare("SELECT ea.id,ea.name,ea.email,ea.language,c.shop_languages FROM employee_accounts ea JOIN companies c ON c.id=ea.company_id WHERE ea.company_id=? AND ea.role='manager' AND ea.active=1 AND ea.registration_status='approved' AND (ea.employment_start IS NULL OR ea.employment_start<=CURDATE()) AND (ea.employment_end IS NULL OR ea.employment_end>=CURDATE())");
        $stmt->execute([$companyId]);
        foreach($stmt->fetchAll() as $row) yw_mail_to_person($row,$subject,$message);
    } catch(Throwable $e) {
        error_log('WorkwearCenter manager notification failed: '.$e->getMessage());
    }
}

/** Mail an die Person, die eine Bestellung aufgegeben hat (Closure wie bei notify_company_managers). */
function notify_order_employee(int $orderId,string|Closure $subject,string $message=''): void {
    try {
        $stmt=db()->prepare("SELECT ea.id,ea.name,ea.email,ea.language,c.shop_languages FROM orders o JOIN companies c ON c.id=o.company_id JOIN employee_accounts ea ON ea.company_id=o.company_id AND (ea.id=o.employee_account_id OR (o.employee_account_id IS NULL AND ea.invite_id=o.invite_id)) WHERE o.id=? AND ea.active=1 AND ea.registration_status='approved' AND (ea.employment_start IS NULL OR ea.employment_start<=CURDATE()) AND (ea.employment_end IS NULL OR ea.employment_end>=CURDATE()) ORDER BY (ea.id=o.employee_account_id) DESC,ea.id LIMIT 1");
        $stmt->execute([$orderId]);
        $row=$stmt->fetch();
        if($row) yw_mail_to_person($row,$subject,$message);
    } catch(Throwable $e) {
        error_log('WorkwearCenter employee notification failed: '.$e->getMessage());
    }
}

/**
 * 10.12: Mail an ein Mitarbeiterkonto in dessen Sprache. $person braucht email und language
 * (optional shop_languages der Firma). Ein Closure fn(array $person): [Betreff, Text] wird in
 * dieser Sprache ausgeführt; feste Texte gehen unverändert in der aktuellen Sprache hinaus.
 */
function yw_mail_to_person(array $person,string|Closure $subject,string $message='',bool $immediate=false): bool {
    $email=(string)($person['email']??'');
    if($email==='') return false;
    if(!$subject instanceof Closure) return send_system_mail($email,$subject,$message,$immediate);
    $lang=yw_account_lang($person,array_key_exists('shop_languages',$person)?['shop_languages'=>$person['shop_languages']]:null);
    return (bool)yw_with_lang($lang,function() use($person,$subject,$email,$immediate){
        [$s,$m]=$subject($person);
        return send_system_mail($email,(string)$s,(string)$m,$immediate);
    });
}

/** Bestellübersicht für Mails (in der aktuellen Sprache). */
function yw_order_mail_summary(int $orderId): string {
    $q=db()->prepare("SELECT o.*,c.legal_name,COALESCE(ea.name,i.employee_name,'') employee_name FROM orders o JOIN companies c ON c.id=o.company_id LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id LEFT JOIN invites i ON i.id=o.invite_id WHERE o.id=? LIMIT 1");
    $q->execute([$orderId]);$order=$q->fetch();
    if(!$order) return t('mail.sum.order').': #'.$orderId;
    $status=t('mail.sum.status_'.(in_array((string)$order['status'],['pending','approved','rejected','cancelled'],true)?(string)$order['status']:'pending'));
    $employee=(string)$order['employee_name']!==''?(string)$order['employee_name']:t('common.unknown');
    $text=t('mail.sum.order').": #".$orderId."\n".t('mail.sum.company').": ".$order['legal_name']."\n".t('mail.sum.employee').": ".$employee."\n".t('mail.sum.status').": ".$status."\n\n".t('mail.sum.items').":\n";
    foreach(order_items($orderId) as $item){
        $text.=(int)$item['quantity'].' × '.$item['product_name'].' · '.t('common.size_value',['size'=>$item['size']]).' · '.money(yw_order_item_amounts($item)['gross'])."\n";
    }
    if((int)($order['shipping_gross_cents']??0)>0)$text.=t('mail.sum.shipping').': '.money((int)$order['shipping_gross_cents'])."\n";
    $gross=(int)($order['gross_total_cents']??0);if($gross<=0)$gross=(int)$order['total_cents'];
    $text.="\n".t('mail.sum.total').": ".money($gross)."\n".t('mail.sum.company_pays').": ".money((int)($order['company_total_cents']??0));
    if((int)($order['employee_total_cents']??0)>0)$text.="\n".t('mail.sum.employee_share').": ".money((int)$order['employee_total_cents']);
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
    if (!yw_move_upload($tmp, dirname(__DIR__) . '/' . $relative)) throw new RuntimeException('Logo konnte nicht gespeichert werden.');
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

/**
 * Adresse einer Datei aus assets/ mit Prüfsumme als Cache-Schlüssel.
 * Ändert sich die Datei auf dem Server, lädt jeder Browser sie neu – unabhängig von der Versionsnummer.
 */
function asset_url(string $path): string {
    static $cache=[];
    if(isset($cache[$path]))return $cache[$path];
    $file=dirname(__DIR__).'/'.$path;
    $stamp=is_file($file)?substr(md5(filemtime($file).'-'.filesize($file)),0,10):app_version();
    return $cache[$path]='/'.$path.'?v='.rawurlencode($stamp);
}

/** Prüfsumme wie in app/data/dateien.json: Zeilenenden vereinheitlicht (FTP im Textmodus ändert sie). */
function yw_file_checksum(string $file): ?string {
    $data=@file_get_contents($file);
    return $data===false?null:sha1(str_replace("\r\n","\n",$data));
}

/**
 * Vergleicht die Programmdateien auf dem Server mit der Liste aus dem Release (app/data/dateien.json).
 * Findet Dateien, die beim FTP-Upload übersprungen wurden oder unvollständig angekommen sind.
 * Rückgabe: null ohne Liste, sonst ['version'=>…, 'stale'=>[Pfade], 'missing'=>[Pfade]].
 */
function yw_file_integrity(?string $prefix=null): ?array {
    static $cache=[];
    $key=(string)$prefix;
    if(array_key_exists($key,$cache))return $cache[$key];
    $root=dirname(__DIR__);
    $list=@json_decode((string)@file_get_contents($root.'/app/data/dateien.json'),true);
    if(!is_array($list)||!is_array($list['files']??null))return $cache[$key]=null;
    $stale=[];$missing=[];
    foreach($list['files'] as $path=>$sum){
        $path=(string)$path;
        if($prefix!==null&&!str_starts_with($path,$prefix))continue;
        if(str_contains($path,'..'))continue;
        $actual=yw_file_checksum($root.'/'.$path);
        if($actual===null)$missing[]=$path;
        elseif(!hash_equals((string)$sum,$actual))$stale[]=$path;
    }
    return $cache[$key]=['version'=>(string)($list['version']??''),'stale'=>$stale,'missing'=>$missing];
}

/** Hinweis für Administratoren, wenn Design-Dateien (assets/) nicht zur installierten Version passen. */
function yw_asset_integrity_notice(): string {
    $r=yw_file_integrity('assets/');
    if(!$r||(!$r['stale']&&!$r['missing']))return '';
    $files=array_merge($r['stale'],$r['missing']);
    return '<div class="flash error"><strong>Design-Dateien auf dem Server sind nicht aktuell.</strong> Deshalb sehen manche Seiten verschoben aus. Bitte diese Dateien aus dem Paket '.e($r['version']).' per FTP erneut hochladen und vorhandene Dateien dabei <em>immer überschreiben</em> (FileZilla fragt sonst ggf. „nur wenn neuer“): '.e(implode(', ',array_slice($files,0,8))).(count($files)>8?' …':'').'. Danach im Browser Strg+F5 drücken.</div>';
}

/** Letzter Datenbankstand, den dieses Programmpaket braucht (siehe app/migrations.php). */
const YW_SCHEMA_REQUIRED='10.18.0';
/**
 * Datenbankstand in einer Abfrage je Anfrage: Ist YW_SCHEMA_REQUIRED eingetragen (roter Hinweis), und für welche
 * Prüfliste wurde diese Datenbank zuletzt vollständig geprüft (Schnellweg in yw_schema_ready)? Fehlt eine Tabelle,
 * gilt beides als nicht erfüllt. $refresh liest neu (nach dem Setzen der Marke).
 */
function yw_schema_state(bool $refresh=false): array {
    static $state=null;
    if($state!==null&&!$refresh)return $state;
    $state=['required'=>false,'signature'=>''];
    try{$q=db()->prepare("SELECT (SELECT COUNT(*) FROM yw_schema_versions WHERE version=?) required,(SELECT setting_value FROM site_settings WHERE setting_key='schema_checked') signature");$q->execute([YW_SCHEMA_REQUIRED]);$row=$q->fetch();$state=['required'=>(int)($row['required']??0)>0,'signature'=>(string)($row['signature']??'')];}catch(Throwable $e){}
    return $state;
}
/**
 * Marke verwerfen, wenn eine Tabelle oder Spalte fehlt, obwohl die Programmdateien gleich geblieben sind (z. B. einzelne
 * ältere Tabelle zurückgespielt): Der nächste Aufruf prüft wieder vollständig und sperrt wie früher mit „Update erforderlich“.
 */
function yw_schema_forget(): void {
    if(yw_schema_state()['signature']==='')return;
    try{db()->exec("DELETE FROM site_settings WHERE setting_key='schema_checked'");}catch(Throwable $ignored){}
    yw_schema_state(true);
}
/** Roter Hinweis in der Verwaltung, solange nach einem Upload der Update-Assistent noch nicht gelaufen ist. */
function yw_schema_notice(): string {
    if(yw_schema_state()['required'])return '';
    return '<div class="flash error"><strong>Datenbank-Update fehlt.</strong> Die neuen Programmdateien sind hochgeladen, die Datenbank aber noch nicht angepasst. Bitte jetzt den <a href="/update.php">Update-Assistenten</a> ausführen, sonst funktionieren einzelne Bereiche nicht.</div>';
}

function yw_site_logo_path(bool $darkSurface=false): string {
    $lightLogo='assets/media/workwearcenter-logo-anthrazit.png';
    $darkLogo='assets/media/workwearcenter-logo.png';
    $fallback=$darkSurface?$darkLogo:$lightLogo;
    if(!function_exists('current_site_setting')) return $fallback;
    try{$path=trim((string)current_site_setting('site_logo',''));}
    catch(Throwable $e){return $fallback;}
    $defaultLogos=['assets/media/workwearcenter-logo.png','assets/media/workwearcenter-logo-dunkel.png','assets/media/workwearcenter-logo-ice.svg','assets/media/workwearcenter-logo-warm.svg'];
    if($path===''||in_array($path,$defaultLogos,true))return $fallback;
    return yw_upload_path_ok($path,'branding') ? $path : $fallback;
}

function render_head(string $title, ?array $brand = null): void {
    $primary = $brand && color_ok((string)($brand['primary_color']??'')) ? (string)$brand['primary_color'] : '#fc6904';
    $accent = $brand && color_ok((string)($brand['accent_color']??'')) ? (string)$brand['accent_color'] : '#fc6904';
    $buttonInk='#17191a';
    if($brand){
        $rgb=[hexdec(substr($primary,1,2))/255,hexdec(substr($primary,3,2))/255,hexdec(substr($primary,5,2))/255];
        $linear=array_map(static fn(float $v): float=>$v<=.04045?$v/12.92:(($v+.055)/1.055)**2.4,$rgb);
        $luminance=$linear[0]*.2126+$linear[1]*.7152+$linear[2]*.0722;
        $buttonInk=$luminance>.179?'#17191a':'#ffffff';
    }
    $brandTokens=$brand?';--wc-accent:'.e($primary).';--wc-orange:'.e($accent).';--wc-button-ink:'.$buttonInk:'';
    $path=path_only();
    $public=in_array($path,['/','/leistungen','/ueber-uns','/so-funktionierts','/mustershop','/kontakt','/anfrage','/impressum','/datenschutz','/zugang'],true);
    // 10.20: Die Website-Seiten nutzen nur noch assets/web.css (Mustershop und Shops bleiben unverändert).
    $website=yw_is_website_path($path);
    $robots=$public&&$path!=='/zugang'?'index,follow,max-image-preview:large':($path==='/zugang'?'noindex,follow':'noindex,nofollow');
    $url='https://'.yw_public_host().$path;
    $canonical=$public?'<link rel="canonical" href="'.e($url).'">':'';
    $descriptions=[
        '/'=>'WorkwearCenter ist der digitale Firmenshop für Arbeitskleidung: Mitarbeitende bestellen selbst oder Verantwortliche erfassen Teamaufträge – mit Stick und Druck aus eigener Produktion in Paderborn.',
        '/leistungen'=>'Arbeitskleidung und Teamkleidung mit Logo: Stick, Textildruck und Transferdruck aus eigener Produktion in Paderborn – inklusive Firmenshop mit Budgets und Freigaben.',
        '/ueber-uns'=>'Yanik Textildruck: Textilveredelung aus eigener Hand mit Produktion in Paderborn, persönliche Beratung und ein Online-Shop für die Firmenkleidung deines Teams.',
        '/so-funktionierts'=>'In fünf Schritten zum eigenen Firmenshop: anfragen, Kollektion abstimmen, freigeben, Team bestellen lassen, fertig veredelte Kleidung erhalten.',
        '/mustershop'=>'Probier einen Beispiel-Firmenshop aus – mit echten Artikeln, Budget, Größenwahl und Freigabe durch den Verantwortlichen.',
        '/kontakt'=>'Kontakt zu Yanik Textildruck: persönliche Beratung zu Firmenkleidung, Veredelung und eigenem Unternehmensshop.',
        '/anfrage'=>'Unverbindlich anfragen: eigener Online-Shop für die Firmenkleidung deines Teams, mit Logo-Veredelung von Yanik Textildruck.',
        '/impressum'=>'Impressum von Yanik Textildruck – Betreiber des WorkwearCenters.',
        '/datenschutz'=>'Datenschutzhinweise für das WorkwearCenter von Yanik Textildruck.',
        '/zugang'=>'Kunden-Login: Über den Kurznamen direkt zum eigenen Firmenshop im WorkwearCenter.',
    ];
    $description=$descriptions[$path]??(yw_lang_shop_active()?t('meta.description'):'Geschützter Unternehmensshop für Firmenkleidung von WorkwearCenter.');
    $shareImage='https://'.yw_public_host().'/'.yw_site_logo_path();
    $imageAlt=$path==='/'?'WorkwearCenter – digitaler Firmenshop für Arbeitskleidung':'WorkwearCenter';
    $social=$public?'<meta property="og:type" content="website"><meta property="og:locale" content="de_DE"><meta property="og:site_name" content="WorkwearCenter"><meta property="og:title" content="'.e($title).'"><meta property="og:description" content="'.e($description).'"><meta property="og:url" content="'.e($url).'"><meta property="og:image" content="'.e($shareImage).'"><meta property="og:image:alt" content="'.e($imageAlt).'"><meta name="twitter:card" content="summary"><meta name="twitter:image" content="'.e($shareImage).'"><meta name="twitter:image:alt" content="'.e($imageAlt).'">':'';
    // Symbol auf allen Seiten (sonst fragt der Browser /favicon.ico an und erhält eine 404-Seite).
    $favicon='<link rel="icon" type="image/svg+xml" href="/assets/media/workwearcenter-mark-warm.svg"><link rel="apple-touch-icon" href="/assets/media/workwearcenter-icon.png">';
    $structured='';
    if($public){
        $logo=yw_site_logo_path();
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
        $organization['image']=$shareImage;
        $street=trim((string)current_site_setting('imprint_street',''));$city=trim((string)current_site_setting('imprint_city',''));
        if($street!=='' && preg_match('/^(\d{5})\s+(.+)$/u',$city,$cm))$organization['address']=['@type'=>'PostalAddress','streetAddress'=>$street,'postalCode'=>$cm[1],'addressLocality'=>$cm[2],'addressCountry'=>'DE'];
        $structured='<script type="application/ld+json">'.json_encode($organization,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP).'</script>';
    }
    $preload=$path==='/mustershop'?'<link rel="preload" as="image" href="/assets/media/products/workwear-tshirt.webp" type="image/webp">':'';
    echo '<!doctype html><html lang="'.e(yw_lang()).'"'.(yw_lang_shop_active()?' data-yw-i18n="'.e(yw_js_i18n()).'"':'').'><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="'.$robots.'"><meta name="description" content="'.e($description).'">'.$canonical.$social.$favicon.$preload.'<meta name="theme-color" content="'.e($primary).'"><title>' . e($title) . '</title>'.($website?'<link rel="preload" href="/assets/fonts/Archivo-Variable.woff2" as="font" type="font/woff2" crossorigin><link rel="preload" href="/assets/fonts/Manrope-Variable.woff2" as="font" type="font/woff2" crossorigin><link rel="stylesheet" href="'.e(asset_url('assets/web.css')).'">'.$structured.'</head><body class="w-body">':'<link rel="stylesheet" href="'.e(asset_url('assets/style.css')).'"><link rel="stylesheet" href="'.e(asset_url('assets/v9.css')).'">'.($public||$path==='/zugang'||str_starts_with($path,'/probeshop/')?'<link rel="stylesheet" href="'.e(asset_url('assets/site.css')).'"><link rel="preload" href="/assets/fonts/InterVariable.woff2" as="font" type="font/woff2" crossorigin>':'').'<link rel="stylesheet" href="'.e(asset_url('assets/workwear-ui.css')).'"><style>:root{--primary:' . e($primary) . ';--accent:' . e($accent) . $brandTokens.'}</style>'.$structured.'</head><body>');
}
// 10.15: data-flash – assets/app.js setzt die Rückmeldung nach dem Speichern an das Sprungziel (z. B. #person-12).
function render_flash(): void { if ($f=take_flash()) echo '<div class="flash ' . e($f[0]) . '" data-flash>' . e($f[1]) . '</div>'; }
function render_foot(): void { echo yw_old_input_script().'<script src="'.e(asset_url('assets/app.js')).'" defer></script>'.(yw_is_website_path(path_only())?'<script src="'.e(asset_url('assets/web.js')).'" defer></script>':'').'</body></html>'; }
/** 10.20: Seiten der öffentlichen Website im neuen Design (ohne Mustershop, Probeshop und Firmenshops). */
function yw_is_website_path(string $path): bool { return in_array($path,['/','/leistungen','/ueber-uns','/so-funktionierts','/kontakt','/anfrage','/impressum','/datenschutz','/zugang'],true); }
