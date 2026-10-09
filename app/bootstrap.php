<?php
declare(strict_types=1);

$configFile = dirname(__DIR__) . '/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    exit('Konfiguration fehlt. Bitte config.php aus der bestehenden Installation wiederherstellen.');
}
$config = require $configFile;
$config['base_domain'] = 'workwearcenter.de';
$config['app_name'] = 'WorkwearCenter by Yanik Textildruck';
date_default_timezone_set('Europe/Berlin');
ini_set('session.use_strict_mode', '1');

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$requestHost = strtolower((string)preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$productionHosts = [strtolower((string)$config['base_domain']), 'www.'.strtolower((string)$config['base_domain'])];

// Never create an authenticated production session over an unencrypted request.
// The web-server redirect remains the first line of defence; this is the fallback.
if (!$secure && in_array($requestHost, $productionHosts, true)) {
    $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
    if ($requestUri === '' || $requestUri[0] !== '/') $requestUri = '/';
    header('Location: https://'.$requestHost.$requestUri, true, 308);
    exit;
}
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; object-src 'none'; connect-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
if($secure) header('Strict-Transport-Security: max-age=15552000');

session_name('yanik_workwear_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['created_at'])) $_SESSION['created_at'] = time();
if (time() - (int)$_SESSION['created_at'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['created_at'] = time();
}
// Höchstdauer zählt ab der Anmeldung: solange niemand angemeldet ist, läuft die Uhr nicht.
if (empty($_SESSION['started_at']) || (empty($_SESSION['admin_id']) && empty($_SESSION['employee_account_id']))) $_SESSION['started_at'] = time();
// Abmeldung nach 4 h ohne Aktivität und spätestens nach 12 h (absolute Höchstdauer einer Sitzung)
if((!empty($_SESSION['last_activity']) && time()-(int)$_SESSION['last_activity']>14400) || time()-(int)$_SESSION['started_at']>43200){
    session_unset();
    session_regenerate_id(true);
    $_SESSION['started_at'] = time();
} elseif(!empty($_SESSION['admin_id']) && !empty($_SESSION['last_activity']) && time()-(int)$_SESSION['last_activity']>7200){
    // Verwaltung: nach 2 Stunden ohne Aktivität wird nur die Admin-Anmeldung beendet.
    unset($_SESSION['admin_id'],$_SESSION['admin_auth_version'],$_SESSION['admin_preview_company']);
    session_regenerate_id(true);
}
$_SESSION['last_activity']=time();
// PHP responses can contain personal order, budget and employee data. Static
// assets are served by Apache before the router and retain their own cache rule.
header('Cache-Control: no-store, private');

try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_port'], $config['db_name']);
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    // PHP rechnet in Europe/Berlin (Budgetjahr, Anzeige), MySQL-Funktionen wie NOW()
    // und CURDATE() sonst in der Serverzeit des Hosters (oft UTC). Der aktuelle
    // Offset inklusive Sommerzeit hält beide Seiten konsistent.
    try { $pdo->exec("SET time_zone = '".(new DateTimeImmutable('now'))->format('P')."'"); }
    catch (Throwable $ignored) {}
} catch (Throwable $exception) {
    http_response_code(500);
    exit('Datenbankverbindung fehlgeschlagen. Bitte config.php prüfen.');
}

require __DIR__ . '/functions.php';
require_once __DIR__.'/modules/corporate.php';
