<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
/** Unerwartete Fehler mit einer Referenz protokollieren, ohne Zugangstokens im Pfad mitzuschreiben. */
function yw_capture_runtime_error(string $type,string $message,string $file,int $line): string {
    try{$id='WWC-'.date('Ymd-His').'-'.strtoupper(bin2hex(random_bytes(3)));}
    catch(Throwable $e){$id='WWC-'.date('Ymd-His').'-'.strtoupper(substr(sha1(uniqid('',true)),0,6));}
    $route=(string)(parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH)?:'/');
    $route=(string)preg_replace('#/(passwort-neu|anmeldelink|mail-freigabe)/[^/]+#','/$1/[redigiert]',$route);
    $safeMessage=str_replace(["\r","\n","\t"],' ',strip_tags($message));
    $safeMessage=(string)preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu','[E-Mail]',$safeMessage);
    $safeMessage=(string)preg_replace('/(?i)\b(password|passwort|token|secret|api[_-]?key)\s*[=:]\s*[^\s,;]+/','$1=[redigiert]',$safeMessage);
    $safeMessage=(string)preg_replace('/(?<![A-Za-z0-9])[a-f0-9]{40,}(?![A-Za-z0-9])/i','[Token]',$safeMessage);
    if(function_exists('mb_substr'))$safeMessage=mb_substr($safeMessage,0,500);else $safeMessage=substr($safeMessage,0,500);
    $entry=[
        'at'=>date(DATE_ATOM),
        'id'=>$id,
        'route'=>$route,
        'type'=>$type,
        'message'=>$safeMessage,
        'file'=>basename($file),
        'line'=>max(0,$line),
    ];
    $json=json_encode($entry,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    $stored=false;
    if(is_string($json)&&function_exists('yw_private_dir')){
        try{
            $log=yw_private_dir().'/runtime-errors.jsonl';
            $lock=@fopen($log.'.lock','c');
            if($lock&&@flock($lock,LOCK_EX)){
                @chmod($log.'.lock',0640);
                if(is_file($log)&&(int)@filesize($log)>=1048576){
                    @unlink($log.'.2');
                    if(is_file($log.'.1'))@rename($log.'.1',$log.'.2');
                    @rename($log,$log.'.1');
                }
                $fh=@fopen($log,'ab');
                if($fh){
                    $record=$json."\n";
                    $stored=@fwrite($fh,$record)===strlen($record);
                    @fflush($fh);
                    @fclose($fh);
                }
                @flock($lock,LOCK_UN);
                @fclose($lock);
                if($stored)@chmod($log,0640);
            }
        }catch(Throwable $ignored){}
    }
    // Der Serverlog bleibt Rückfallebene, falls private_dir fehlt oder nicht beschreibbar ist.
    error_log('WorkwearCenter['.$id.'] '.($json?:$type.' · '.basename($file).':'.$line).($stored?'':' · privates Protokoll nicht verfügbar'));
    return $id;
}

// Schwere PHP-Fehler werden in den privaten Datenordner statt in den Webroot geschrieben.
register_shutdown_function(static function(): void {
    $err=error_get_last();
    if($err && in_array($err['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR],true)){
        yw_capture_runtime_error('fatal',(string)$err['message'],(string)$err['file'],(int)$err['line']);
    }
});
// Unerwartete Fehler (z. B. Datenbank bricht mitten in der Seite ab): protokollieren und statt einer leeren Seite einen Hinweis zeigen.
set_exception_handler(static function(Throwable $e): void {
    $incident=yw_capture_runtime_error(get_class($e),$e->getMessage(),$e->getFile(),$e->getLine());
    // Tabelle oder Spalte fehlt (SQLSTATE 42S02/42S22): Schema-Marke verwerfen, der nächste Aufruf prüft vollständig und zeigt „Update erforderlich“.
    if($e instanceof PDOException && in_array((string)$e->getCode(),['42S02','42S22'],true) && function_exists('yw_schema_forget'))try{if(db()->inTransaction())db()->rollBack();yw_schema_forget();}catch(Throwable $ignored){}
    if(!headers_sent())http_response_code(500);
    $headers=headers_list();
    $requestPath=(string)(parse_url((string)($_SERVER['REQUEST_URI']??'/'),PHP_URL_PATH)?:'/');
    $jsonResponse=str_starts_with($requestPath,'/api/')||preg_grep('#^Content-Type:\s*application/json#i',$headers);
    if($jsonResponse){
        if(!headers_sent()){
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error'=>'internal_error','reference'=>$incident],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        }
        return;
    }
    if(preg_grep('#^Content-Type:#i',$headers) && !preg_grep('#^Content-Type:\s*text/html#i',$headers))return; // CSV/ZIP: nichts anhängen
    if(!headers_sent())header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Fehler</title><body style="font-family:system-ui;max-width:640px;margin:60px auto;padding:0 20px;line-height:1.6"><h1>Das hat gerade nicht geklappt</h1><p>Bitte die Seite in einem Moment neu laden. Die Fehlerreferenz hilft dem Support bei der Suche.</p><p>Referenz: <code>'.htmlspecialchars($incident,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8').'</code></p><p><a href="/">Zur Startseite</a></p></body>';
});
require __DIR__ . '/app/bootstrap.php';
require_once __DIR__.'/app/modules/corporate.php';
require_once __DIR__.'/app/modules/admin.php';
require_once __DIR__.'/app/modules/public.php';
require_once __DIR__.'/app/modules/demo.php';
require_once __DIR__.'/app/modules/shop.php';
require_once __DIR__.'/app/modules/system.php';
if(!yw_schema_ready() && !in_array(path_only(),['/admin/login','/admin/logout','/admin/2fa'],true)) {
    http_response_code(503);
    render_head('Systemupdate erforderlich');
    // Verbindungsabbruch/Überlast ist kein Update-Fall: Besucher sehen dann nur „kurz nicht erreichbar“, der Grund steht im Protokoll.
    $dbDown=false;try{db()->query('SELECT 1');}catch(Throwable $e){$dbDown=true;error_log('WorkwearCenter DB: '.yw_schema_problem());}
    $fresh=false;if(!$dbDown)try{db()->query('SELECT 1 FROM admins LIMIT 1');}catch(Throwable $e){$fresh=$e instanceof PDOException && $e->getCode()==='42S02';}
    if($dbDown) echo '<main class="install-bg"><section class="install-card"><h1>Kurz nicht erreichbar</h1><p>Die Datenbank antwortet gerade nicht. Bitte die Seite in einer Minute neu laden.</p></section></main>';
    elseif($fresh) echo '<main class="install-bg"><section class="install-card"><h1>Neues System</h1><p>Die Datenbank ist noch leer. Bitte den Einrichtungs-Assistenten starten.</p><a class="btn primary" href="/install.php">Einrichtung starten</a></section></main>';
    else echo '<main class="install-bg"><section class="install-card"><h1>Update erforderlich</h1><p>Die Programmdateien sind neuer als die Datenbank. Bitte als Administrator anmelden und den Update-Assistenten ausführen.</p><a class="btn primary" href="/admin/login">Anmelden</a> <a class="btn" href="/update.php">Zum Update-Assistenten</a></section></main>';
    render_foot(); exit;
}

$path = path_only();

// Sitemap und robots.txt passend zur tatsächlich genutzten Adresse (mit/ohne www) erzeugen.
if ($path === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=UTF-8');
    $base = 'https://'.yw_public_host();
    echo '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
    foreach (yw_public_pages() as $loc => $page) {
        if (!$page['index']) continue;
        echo '  <url><loc>'.e($base.$loc).'</loc><priority>'.$page['priority'].'</priority></url>'."\n";
    }
    echo "</urlset>\n"; exit;
}
if ($path === '/robots.txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /update.php\nDisallow: /install.php\nDisallow: /api/\nDisallow: /probeshop/\nDisallow: /uploads/probeshop/\nDisallow: /*/login\nDisallow: /*/registrieren\nDisallow: /*/passwort-\nDisallow: /*/anmeldelink\nDisallow: /*/sprache\nDisallow: /*/mail-freigabe\nDisallow: /*/mitarbeiter\nDisallow: /*/bestellungen\nDisallow: /*/freigaben\nDisallow: /*/produkte\nDisallow: /*/profil\nDisallow: /*/auswertung.csv\nDisallow: /*/lohnabzug.csv\n\nSitemap: https://".yw_public_host()."/sitemap.xml\n";
    exit;
}

// Deliver queued messages in small batches after normal requests. This keeps
// larger employee imports moving without requiring a separately configured cron job.
register_shutdown_function(static function(): void {
    if(function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
    // Erinnerungen und Aufbewahrungsfristen höchstens einmal täglich, falls kein Cronjob eingerichtet ist.
    try { if(function_exists('current_site_setting') && current_site_setting('reminders_last_run','')<date('Y-m-d')){ set_site_setting('reminders_last_run',date('Y-m-d')); yw_send_reminders(3); yw_send_budget_year_mails(200); yw_probeshop_cleanup(); yw_retention_prune(); yw_cleanup_expired_security_rows();} } catch(Throwable $e) {}
    try { yw_process_mail_queue(2); }
    catch(Throwable $e) { error_log('WorkwearCenter background mail queue: '.$e->getMessage()); }
    // Übergabe an die Auftragsverwaltung als Rückfallebene zum Cronjob.
    try { yw_process_handover_queue(1); }
    catch(Throwable $e) { error_log('WorkwearCenter background handover: '.$e->getMessage()); }
});

/* Schnittstelle für die Auftragsverwaltung (eigene Authentifizierung per API-Schlüssel) */
if (str_starts_with($path, '/api/')) { yw_api_route($path); }

// 10.22: Impressum und Datenschutzerklärung bleiben auch im Wartungsmodus erreichbar (Pflichtangaben).
if (maintenance_enabled() && !str_starts_with($path, '/admin') && !in_array($path, ['/impressum', '/datenschutz'], true)) {
    maintenance_page();
    exit;
}

/* Öffentliche WorkwearCenter-Seiten */
if ($path === '/leistungen') { services_page(); exit; }
if ($path === '/ueber-uns') { about_page(); exit; }
if ($path === '/so-funktionierts') { how_it_works_page(); exit; }
if ($path === '/kontakt') { contact_page(); exit; }
if ($path === '/anfrage') { inquiry_page(); exit; }
if ($path === '/impressum') { imprint_page(); exit; }
if ($path === '/datenschutz') { privacy_page(); exit; }
if ($path === '/mustershop') { mustershop_page(); exit; }
if ($path === '/zugang') { customer_access_page(); exit; }
// 10.23: Branchen und Themenseiten (app/modules/site_topics.php)
if ($path === '/branchen') { industries_page(); exit; }
if ($path === '/arbeitskleidung-bestellsystem') { topic_ordering_system_page(); exit; }
if ($path === '/mitarbeitershop-arbeitskleidung') { topic_employee_shop_page(); exit; }
if ($path === '/workwear-management') { topic_management_page(); exit; }
if (preg_match('#^/probeshop/([^/]{1,100})$#', $path, $match)) { yw_probeshop_route($match[1]); exit; }

/* Admin */
if ($path === '/admin/passwort-vergessen') { admin_password_forgot_page(); exit; }
if (preg_match('#^/admin/passwort-neu/([a-f0-9]{64})$#', $path, $match)) { admin_password_reset_page($match[1]); exit; }
if ($path === '/admin/login') { admin_login_page(); exit; }
if (preg_match('#^/admin/vorschau/(\d+)$#', $path, $m)) { yw_admin_preview_start((int)$m[1]); }
if ($path === '/admin/vorschau-beenden') { yw_admin_preview_stop(); }
if ($path === '/admin/sicherung') { yw_backup_download(); }
if (preg_match('#^/admin/anfrage-datei/(\d+)$#', $path, $m)) { yw_inquiry_attachment_download((int)$m[1]); }
if (preg_match('#^/admin/aushang/(\d+)$#', $path, $m)) { yw_admin_poster_page((int)$m[1]); exit; }
if ($path === '/admin/2fa') { admin_2fa_page(); exit; }
if ($path === '/admin/logout') {
    if (is_post()) {
        verify_csrf();
        unset($_SESSION['admin_id'],$_SESSION['admin_auth_version']);
        session_regenerate_id(true);
    }
    redirect('/admin/login');
}
if ($path === '/admin/export.csv') { export_orders_csv(); exit; }
if ($path === '/admin/beschaffung.csv') { export_procurement_csv(); exit; }
if ($path === '/admin/produkte-vorlage.csv') { yw_product_csv_template(); exit; }
if ($path === '/admin/produkte.csv') { yw_product_csv_export(); exit; }
if ($path === '/admin/lohnabzug.csv') { yw_payroll_admin_export(); }
if ($path === '/admin') { admin_page(); exit; }

/* Unternehmensshops über Pfade, z. B. /leniger */
if (preg_match('#^/([a-z0-9-]+)/registrieren$#', $path, $match)) { employee_register_page_by_slug($match[1]); exit; }
if (preg_match('#^/([a-z0-9-]+)/passwort-vergessen$#', $path, $match)) { yw_password_forgot_page($match[1]); exit; }
if (preg_match('#^/([a-z0-9-]+)/passwort-neu/([a-f0-9]{64})$#', $path, $match)) { yw_password_reset_page($match[1],$match[2]); exit; }
if (preg_match('#^/([a-z0-9-]+)/login$#', $path, $match)) { employee_account_login_page($match[1]); exit; }
/* 10.12: Sprachwahl im Firmenshop (POST mit CSRF) */
if (preg_match('#^/([a-z0-9-]+)/sprache$#', $path, $match)) { yw_lang_switch($match[1]); }
/* 10.11: Anmeldelink per E-Mail und Freigabe aus der E-Mail (eigene Module) */
if (preg_match('#^/([a-z0-9-]+)/anmeldelink$#', $path, $match)) { yw_magic_login_request($match[1]); exit; }
if (preg_match('#^/([a-z0-9-]+)/anmeldelink/([a-f0-9]{64})$#', $path, $match)) { yw_magic_login_page($match[1],$match[2]); exit; }
if (preg_match('#^/([a-z0-9-]+)/mail-freigabe/([a-f0-9]{96})$#', $path, $match)) { yw_mail_decision_page($match[1],$match[2]); exit; }
if (preg_match('#^/([a-z0-9-]+)/logout$#', $path, $match)) { company_portal_logout($match[1]); exit; }
if (preg_match('#^/([a-z0-9-]+)/mitarbeiter\.csv$#', $path, $match)) { yw_customer_employee_export($match[1]); exit; }
if (preg_match('#^/([a-z0-9-]+)/auswertung\.csv$#', $path, $match)) { yw_customer_report_export($match[1]); exit; }
if (preg_match('#^/([a-z0-9-]+)/lohnabzug\.csv$#', $path, $match)) { yw_payroll_manager_export($match[1]); }

/* alter Freigabe-Link bleibt nur als Weiterleitung bestehen */
if (preg_match('#^/([a-z0-9-]+)/freigabe$#', $path, $match)) { redirect('/'.$match[1].'/freigaben'); }

if (preg_match('#^/([a-z0-9-]+)/sammelbestellung$#', $path, $match)) {
    company_portal_page($match[1],'team-order');
    exit;
}
if (preg_match('#^/([a-z0-9-]+)/(produkte|bestellungen|freigaben|mitarbeiter|profil|spind)$#', $path, $match)) {
    company_portal_page($match[1],$match[2]);
    exit;
}
if (preg_match('#^/([a-z0-9-]+)$#', $path, $match)) {
    company_portal_page($match[1],'overview');
    exit;
}

if($path!=='/'){
    http_response_code(404);
    website_not_found_page();
    exit;
}
landing_page();
