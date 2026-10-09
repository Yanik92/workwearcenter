<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
// Schwere PHP-Fehler für die Systemprüfung auf update.php festhalten (ohne Besucherdaten).
register_shutdown_function(static function(): void {
    $err=error_get_last();
    if($err && in_array($err['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR],true)){
        @file_put_contents(__DIR__.'/app/letzter-fehler.txt',date('d.m.Y H:i:s').' · '.(string)parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH)."\n".$err['message'].' ('.basename((string)$err['file']).':'.$err['line'].')');
    }
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
    $fresh=true;try{db()->query('SELECT 1 FROM admins LIMIT 1');$fresh=false;}catch(Throwable $e){}
    if($fresh) echo '<main class="install-bg"><section class="install-card"><h1>Neues System</h1><p>Die Datenbank ist noch leer. Bitte den Einrichtungs-Assistenten starten.</p><a class="btn primary" href="/install.php">Einrichtung starten</a></section></main>';
    else echo '<main class="install-bg"><section class="install-card"><h1>Update erforderlich</h1><p>Die Programmdateien sind neuer als die Datenbank. Bitte als Administrator anmelden und den Update-Assistenten ausführen.</p><a class="btn primary" href="/admin/login">Anmelden</a> <a class="btn" href="/update.php">Zum Update-Assistenten</a></section></main>';
    render_foot(); exit;
}

$path = path_only();

// Sitemap und robots.txt passend zur tatsächlich genutzten Adresse (mit/ohne www) erzeugen.
if ($path === '/sitemap.xml') {
    header('Content-Type: application/xml; charset=UTF-8');
    $base = 'https://'.yw_public_host();
    echo '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
    foreach (['/' => '1.0', '/leistungen' => '0.9', '/so-funktionierts' => '0.8', '/mustershop' => '0.8', '/ueber-uns' => '0.7', '/anfrage' => '0.8', '/kontakt' => '0.7', '/impressum' => '0.2', '/datenschutz' => '0.2'] as $loc => $prio) {
        echo '  <url><loc>'.e($base.($loc === '/' ? '/' : $loc)).'</loc><priority>'.$prio.'</priority></url>'."\n";
    }
    echo "</urlset>\n"; exit;
}
if ($path === '/robots.txt') {
    header('Content-Type: text/plain; charset=UTF-8');
    echo "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /update.php\nDisallow: /install.php\nDisallow: /api/\nDisallow: /probeshop/\nDisallow: /uploads/probeshop/\nDisallow: /*/login\nDisallow: /*/registrieren\nDisallow: /*/passwort-\nDisallow: /*/anmeldelink\nDisallow: /*/mail-freigabe\nDisallow: /*/mitarbeiter\nDisallow: /*/bestellungen\nDisallow: /*/freigaben\nDisallow: /*/produkte\nDisallow: /*/profil\nDisallow: /*/auswertung.csv\nDisallow: /*/lohnabzug.csv\n\nSitemap: https://".yw_public_host()."/sitemap.xml\n";
    exit;
}

// Deliver queued messages in small batches after normal requests. This keeps
// larger employee imports moving without requiring a separately configured cron job.
register_shutdown_function(static function(): void {
    if(function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
    // Erinnerungen höchstens einmal täglich, falls kein Cronjob eingerichtet ist.
    try { if(function_exists('current_site_setting') && current_site_setting('reminders_last_run','')<date('Y-m-d')){ set_site_setting('reminders_last_run',date('Y-m-d')); yw_send_reminders(3); yw_send_budget_year_mails(200); yw_probeshop_cleanup();} } catch(Throwable $e) {}
    try { yw_process_mail_queue(2); }
    catch(Throwable $e) { error_log('WorkwearCenter background mail queue: '.$e->getMessage()); }
    // Übergabe an die Auftragsverwaltung als Rückfallebene zum Cronjob.
    try { yw_process_handover_queue(1); }
    catch(Throwable $e) { error_log('WorkwearCenter background handover: '.$e->getMessage()); }
});

/* Schnittstelle für die Auftragsverwaltung (eigene Authentifizierung per API-Schlüssel) */
if (str_starts_with($path, '/api/')) { yw_api_route($path); }

if (maintenance_enabled() && !str_starts_with($path, '/admin')) {
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
if (preg_match('#^/probeshop/([^/]{1,100})$#', $path, $match)) { yw_probeshop_route($match[1]); exit; }

/* Admin */
if ($path === '/admin/passwort-vergessen') { admin_password_forgot_page(); exit; }
if (preg_match('#^/admin/passwort-neu/([a-f0-9]{64})$#', $path, $match)) { admin_password_reset_page($match[1]); exit; }
if ($path === '/admin/login') { admin_login_page(); exit; }
if (preg_match('#^/admin/vorschau/(\d+)$#', $path, $m)) { yw_admin_preview_start((int)$m[1]); }
if ($path === '/admin/vorschau-beenden') { yw_admin_preview_stop(); }
if ($path === '/admin/sicherung') { yw_backup_download(); }
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

if (preg_match('#^/([a-z0-9-]+)/(produkte|bestellungen|freigaben|mitarbeiter|profil)$#', $path, $match)) {
    company_portal_page($match[1],$match[2]);
    exit;
}
if (preg_match('#^/([a-z0-9-]+)$#', $path, $match)) {
    company_portal_page($match[1],'overview');
    exit;
}

if($path!=='/'){
    http_response_code(404);
    simple_message('Seite nicht gefunden','Die angeforderte Seite existiert nicht oder ist nicht mehr verfügbar.');
    exit;
}
landing_page();
