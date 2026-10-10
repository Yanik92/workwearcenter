<?php
declare(strict_types=1);

$config=['base_domain'=>'workwearcenter.de','db_pass'=>'test-secret'];
$_SESSION=[];
require dirname(__DIR__).'/app/functions.php';
require dirname(__DIR__).'/app/modules/public.php';
require dirname(__DIR__).'/app/modules/corporate.php';
require dirname(__DIR__).'/app/modules/admin_account.php';
require dirname(__DIR__).'/app/modules/shop.php';
if(!function_exists('app_version')){function app_version(): string {return 'test';}}

function test_ok(bool $condition,string $message): void {
    if(!$condition) throw new RuntimeException('FEHLER: '.$message);
    echo "OK: {$message}\n";
}

function test_throws(callable $callback,string $message): void {
    try{$callback();}
    catch(DomainException){echo "OK: {$message}\n";return;}
    throw new RuntimeException('FEHLER: '.$message);
}

$cart=yw_normalize_cart_selection([1=>'2',17=>'1'],[1=>'M',17=>'XL']);
test_ok($cart[1]['quantity']===2 && $cart[17]['size']==='XL','gültiger Warenkorb wird normalisiert');
test_throws(fn()=>yw_normalize_cart_selection(['01'=>'10'],['01'=>'M']),'nicht-kanonische Produkt-ID 01 wird abgelehnt');
test_throws(fn()=>yw_normalize_cart_selection(['1x'=>'10'],['1x'=>'M']),'gemischte Produkt-ID 1x wird abgelehnt');
test_throws(fn()=>yw_normalize_cart_selection([1=>'11'],[1=>'M']),'Positionsmenge über 10 wird abgelehnt');

test_ok(yw_public_link('javascript:alert(1)','/anfrage')==='/anfrage','gefährliches CTA-Schema wird verworfen');
test_ok(yw_public_link('//example.org','/anfrage')==='/anfrage','protokollrelative CTA-URL wird verworfen');
test_ok(yw_public_link('https://yanik-textildruck.de','')==='https://yanik-textildruck.de','HTTPS-CTA bleibt erlaubt');
test_ok(yw_upload_path_ok('uploads/products/product-a1b2.webp','products'),'erwarteter Uploadpfad wird akzeptiert');
test_ok(!yw_upload_path_ok('uploads/products/../../config.php','products'),'Pfadnavigation im Uploadpfad wird abgelehnt');
test_ok(!yw_upload_path_ok('uploads/products/bild.php','products'),'ausführbare Uploadendung wird abgelehnt');

$externalMail=yw_mail_html('Anfrage',"Nachricht:\nRechnung: https://example.org/falsch");
test_ok(!str_contains($externalMail,'href="https://example.org/falsch"'),'externe Anfrage-URL wird nicht zum Markenbutton');
$internalMail=yw_mail_html('Zugang',"Anmeldung: https://workwearcenter.de/firma/login");
test_ok(str_contains($internalMail,'href="https://workwearcenter.de/firma/login"'),'interner Aktionslink wird als Button dargestellt');
test_ok(yw_mail_action_url('https://workwearcenter.de.example.org/firma/login')===null,'ähnlich aussehende Fremddomain wird im Mailbutton abgelehnt');

$passwordInfo=password_get_info(yw_dummy_password_hash());
test_ok(($passwordInfo['algoName']??'unknown')==='bcrypt','Dummy-Hash ist ein gültiger bcrypt-Hash');
test_ok(sanitize_rich_text('<img src=x onerror=alert(1)><strong>Sicher</strong>')==='<strong>Sicher</strong>','Rich-Text entfernt aktiven Bildinhalt');

[$periodStart,$periodEnd]=yw_period(10,new DateTimeImmutable('2026-02-15 12:00:00',new DateTimeZone('Europe/Berlin')));
test_ok($periodStart==='2025-10-01 00:00:00' && $periodEnd==='2026-10-01 00:00:00','Budgetjahr wird über den Jahreswechsel korrekt berechnet');
test_ok(yw_csv_safe('=HYPERLINK("https://example.org")')[0]==="'",'CSV-Formeln werden neutralisiert');
test_ok(yw_registration_policy('pin_approval')===[true,true],'Registrierungsmodus PIN plus Freigabe ist eindeutig');
test_throws(fn()=>yw_check_limits(30000,29000,1001),'Budgetüberschreitung wird serverseitig abgelehnt');
test_throws(fn()=>yw_check_limits(null,0,0,5,4,2),'Mengenüberschreitung wird serverseitig abgelehnt');

$_SERVER['REMOTE_ADDR']='127.0.0.55';
record_login_failure('custom-limit');
record_login_failure('custom-limit');
test_ok(!login_throttled('custom-limit',3),'individuelles Anmeldelimit bleibt vor Grenzwert offen');
record_login_failure('custom-limit');
test_ok(login_throttled('custom-limit',3),'individuelles Anmeldelimit greift am Grenzwert');
clear_login_failures('custom-limit');

$config=array_merge($config,[
    'mail_transport'=>'smtp','mail_from'=>'noreply@workwearcenter.de','mail_reply_to'=>'info@yanik-textildruck.de',
    'smtp_host'=>'smtp.example.test','smtp_port'=>587,'smtp_encryption'=>'tls','smtp_user'=>'mailer','smtp_pass'=>'secret',
]);
test_ok(yw_mail_config_issues()===[],'vollständige SMTP-Konfiguration wird akzeptiert');
$config['smtp_encryption']='falsch';
test_ok(yw_mail_config_issues()!==[],'ungültige SMTP-Verschlüsselung wird erkannt');
$config['smtp_encryption']='tls';
yw_validate_uploaded_raster(dirname(__DIR__).'/assets/media/yanik-workwear-logo.png','Testlogo');
test_ok(true,'gültige Rastergrafik besteht die Upload-Inhaltsprüfung');
try{yw_validate_uploaded_raster(__FILE__,'Testdatei');throw new RuntimeException('FEHLER: Nichtbild wurde akzeptiert');}
catch(RuntimeException $e){test_ok(!str_starts_with($e->getMessage(),'FEHLER:'),'Nichtbild wird trotz Dateiendung/MIME-Prüfung abgelehnt');}

// 9.1: reservierte Kurznamen und RFC-konforme Mailkodierung
test_ok(!slug_ok('impressum') && !slug_ok('admin') && slug_ok('musterbau'),'Systemseiten sind als Shop-Kurzname gesperrt');
$mime=yw_mail_multipart('Test',str_repeat('Sehr lange Zeile ohne Umbruch ',200),'b');
test_ok(max(array_map('strlen',explode("\r\n",$mime)))<=998,'Mailteile halten die RFC-Zeilenlänge von 998 Zeichen ein');
test_ok(str_contains($mime,'Content-Transfer-Encoding: base64'),'Mailteile sind Base64-kodiert');
test_ok(yw_login_bucket('x',true)!==yw_login_bucket('x',false),'kontobasierte Drosselung ist unabhängig von der IP');

// 10.0: Zwei-Faktor-Anmeldung (RFC 6238 Testvektor) und Übergabe-Signatur
$rfcSecret=yw_base32_encode('12345678901234567890');
test_ok(yw_totp_code($rfcSecret,1)==='287082','TOTP entspricht dem RFC-6238-Testvektor');
test_ok(yw_base32_decode(yw_base32_encode('WorkwearCenter'))==='WorkwearCenter','Base32 ist verlustfrei');
$now=intdiv(time(),30);$code=yw_totp_code($rfcSecret,$now);
test_ok(yw_totp_verify($rfcSecret,$code)===$now,'aktueller TOTP-Code wird akzeptiert');
test_ok(yw_totp_verify($rfcSecret,$code,$now)===null,'bereits verwendeter TOTP-Code wird abgelehnt');
test_ok(yw_totp_verify($rfcSecret,'12345')===null,'unvollständiger Code wird abgelehnt');
test_ok(yw_handover_signature('geheim','1700000000','{"a":1}')==='sha256='.hash_hmac('sha256','1700000000.{"a":1}','geheim'),'Übergabe-Signatur ist HMAC-SHA256 über Zeitstempel und Inhalt');
test_ok(yw_order_number(42)==='WWC-000042','Auftragsnummer ist sprechend und stabil');
$config['handover_mode']='push';$config['handover_url']='http://example.org/x';$config['handover_secret']=str_repeat('a',40);
test_ok(yw_handover_config_issues()!==[],'unverschlüsselte Übergabe-Adresse wird abgelehnt');
$config['handover_url']='https://auftraege.example.org/eingang';
test_ok(yw_handover_config_issues()===[],'HTTPS-Übergabe-Adresse mit langem Schlüssel wird akzeptiert');
$config['handover_secret']='kurz';
test_ok(yw_handover_config_issues()!==[],'zu kurzer Übergabe-Schlüssel wird abgelehnt');
// 10.17: config.php hat Vorrang vor der Einrichtung in der Verwaltung; ohne Angabe bleibt das bisherige Format
test_ok(yw_az_from_config() && yw_handover_config()['source']==='config','handover_mode push in der config.php gilt (nur Anzeige in der Verwaltung)');
unset($config['handover_format']);test_ok(yw_handover_config()['format']==='workwearcenter','config.php ohne handover_format bleibt beim Schema v1');
$config['handover_mode']='off';test_ok(!yw_az_from_config(),'handover_mode off: Einrichtung in der Verwaltung möglich');
$config['handover_mode']='push';$config['handover_secret']=str_repeat('a',40);

// 10.1: Übersetzung in das Format der Auftragszentrale
$v1=['order'=>['number'=>'WWC-000009','note'=>'Eilt','approved_by'=>['name'=>'Maria'],'approved_at'=>'2026-09-26T10:00:00+02:00','totals'=>['net_cents'=>1000,'gross_cents'=>1190,'company_share_cents'=>1190,'employee_share_cents'=>0,'shipping'=>['gross_cents'=>0]]],'customer'=>['legal_name'=>'Musterbau GmbH','slug'=>'musterbau','shop_name'=>'Shop'],'employee'=>['name'=>'Max','email'=>'max@musterbau.de','cost_center'=>'120'],'delivery'=>['mode'=>'delivery','address_lines'=>['Musterbau GmbH','Weg 1']],'items'=>[['name'=>'Polo','sku'=>'P1','quantity'=>2,'color'=>'Navy','size'=>'L','unit_net_cents'=>500,'unit_gross_cents'=>595,'vat_rate'=>19,'decorations'=>[['position_label'=>'Brust links','method_label'=>'Stick','text'=>'Logo']]]]];
$az=yw_handover_to_auftragszentrale($v1);
test_ok($az['event']==='order.upsert' && $az['order']['id']==='WWC-000009' && $az['order']['source']['approvalStatus']==='approved','Auftragszentrale erhält freigegebenen Auftrag mit WWC-Nummer');
test_ok($az['order']['lines'][0]['article']==='Polo (P1)' && $az['order']['lines'][0]['decorations'][0]['position']==='Brust links: Logo','Artikel und Veredelung werden verständlich übertragen');
test_ok(str_contains($az['order']['notes'],'Kostenstelle: 120') && str_contains($az['order']['notes'],'Musterbau GmbH, Weg 1'),'Kostenstelle und Lieferadresse stehen im Auftrag');

// 10.3: Statusrückmeldung, Sendungsverfolgung, öffentliche Adresse
test_ok(str_starts_with((string)yw_tracking_link('DHL','00340434161234567890',null),'https://www.dhl.de/') ,'DHL-Sendungsnummer wird zum Verfolgungslink');
test_ok(yw_tracking_link('Eigene Spedition','ABC123',null)===null,'unbekannter Paketdienst ohne Link bleibt ohne Link');
test_ok(yw_tracking_link('DHL','x"><script>',null)===null || !str_contains((string)yw_tracking_link('DHL','x"><script>',null),'<script'),'Sendungsnummer kann keinen Code einschleusen');
$_SERVER['HTTP_HOST']='www.workwearcenter.de';test_ok(yw_public_host()==='www.workwearcenter.de','Aufruf mit www bleibt bei www');
$_SERVER['HTTP_HOST']='boese.example.org';test_ok(yw_public_host()==='workwearcenter.de','fremder Host-Header landet nie in Links');
test_ok(in_array('production',YW_STATUS_EVENTS,true) && !in_array('cancelled',YW_STATUS_EVENTS,true),'nur definierte Produktionsstände werden angenommen');

// 10.6: Logo-Dateien (SVG) werden entschärft
$svg=yw_sanitize_svg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" onload="alert(1)"><script>alert(1)</script><a href="javascript:x"><rect width="10" height="10"/></a></svg>');
test_ok(!str_contains($svg,'script')&&!str_contains($svg,'onload')&&!str_contains($svg,'javascript'),'SVG-Logo: Skripte und Ereignisse werden entfernt');
$caught=false;try{yw_sanitize_svg('<!DOCTYPE svg [<!ENTITY x "y">]><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"/>');}catch(Throwable $e){$caught=true;}
test_ok($caught,'SVG-Logo: DOCTYPE/ENTITY wird abgelehnt');
test_ok(yw_motif_file_path(['file_path'=>'uploads/motifs/motif-ab12.svg'])!=='' && yw_motif_file_path(['file_path'=>'../config.php'])==='','Motivpfad nur aus dem Motiv-Ordner');
test_ok(yw_zones_sanitize(['front'=>['chest_left'=>['x'=>60,'y'=>30,'w'=>14],'kaputt'=>['x'=>1,'y'=>1,'w'=>1]],'nix'=>[]])===['front'=>['chest_left'=>['x'=>60.0,'y'=>30.0,'w'=>14.0]]],'Druckzonen werden bereinigt');

// 10.8: API v1 – Schlüsselrechte und HTTPS-Pflicht
test_ok(yw_api_scopes(['scopes'=>'orders:read, orders:write ,,'])===['orders:read','orders:write'],'API-Rechte werden sauber aus dem Schlüssel gelesen');
test_ok(!in_array('customers:read',yw_api_scopes(['scopes'=>'orders:read']),true),'API-Schlüssel ohne customers:read hat dieses Recht nicht');
$_SERVER['HTTPS']='';$_SERVER['HTTP_X_FORWARDED_PROTO']='';$_SERVER['REMOTE_ADDR']='203.0.113.9';
test_ok(!yw_api_https_ok(),'API über unverschlüsseltes HTTP von außen wird abgelehnt');
$_SERVER['HTTP_X_FORWARDED_PROTO']='https';test_ok(yw_api_https_ok(),'API hinter HTTPS-Proxy wird angenommen');
$_SERVER['HTTP_X_FORWARDED_PROTO']='';$_SERVER['HTTPS']='on';test_ok(yw_api_https_ok(),'API über HTTPS wird angenommen');
test_ok(preg_match('/^Bearer\s+(wwc_[a-f0-9]{48})$/','Bearer wwc_'.str_repeat('a',48))===1 && preg_match('/^Bearer\s+(wwc_[a-f0-9]{48})$/','Bearer wwc_'.str_repeat('a',47).'G')===0,'nur korrekt aufgebaute API-Schlüssel werden überhaupt geprüft');
test_ok(count(YW_API_SCOPES)===4 && isset(YW_API_SCOPES['orders:write']),'API-Rechte sind fest definiert');

// 10.10: SVG-Präfix-Trick, Katalog-SKUs je Farbe
$prefixed=yw_sanitize_svg('<svg xmlns="http://www.w3.org/2000/svg" xmlns:x="http://www.w3.org/1999/xlink" viewBox="0 0 10 10"><a x:href="javascript:alert(1)"><rect width="5" height="5"/></a><a href=" java&#x09;script:alert(2)">t</a><use href="#r"/></svg>');
test_ok(!str_contains(strtolower($prefixed),'javascript')&&str_contains($prefixed,'href="#r"'),'SVG-Logo: Verweise mit eigenem Namensraum-Präfix werden entfernt, interne bleiben');
test_ok(yw_katalog_product_sku('jn840','Navy Blue')==='JN840-NAVYBLUE','Katalog: SKU je Farbe');
test_ok(yw_katalog_base_sku('JN840-NAVYBLUE','navy blue')==='JN840' && yw_katalog_base_sku('JN840','navy')==='JN840','Katalog: Grund-SKU wird erkannt (alt und neu)');

// 10.11: Sammelbestellung, Jahreswechsel-Mail, Umtausch & Retouren, Freigabe-Links
$berlin=new DateTimeZone('Europe/Berlin');
test_ok(yw_batch_next_date('monthly',1,null,new DateTimeImmutable('2026-10-09 13:00',$berlin))->format('Y-m-d H:i')==='2026-11-01 00:00','Sammeltermin monatlich: nächster Monatserster');
test_ok(yw_batch_next_date('monthly',15,null,new DateTimeImmutable('2026-10-09 13:00',$berlin))->format('Y-m-d')==='2026-10-15','Sammeltermin monatlich: Stichtag im laufenden Monat');
test_ok(yw_batch_next_date('monthly',1,null,new DateTimeImmutable('2026-11-01 00:00',$berlin))->format('Y-m-d')==='2026-12-01','Sammeltermin: genau am Stichtag gilt der nächste Termin');
test_ok(yw_batch_next_date('monthly',28,null,new DateTimeImmutable('2026-12-30 08:00',$berlin))->format('Y-m-d')==='2027-01-28','Sammeltermin über den Jahreswechsel');
test_ok(yw_batch_next_date('monthly',99,null,new DateTimeImmutable('2026-02-27 08:00',$berlin))->format('Y-m-d')==='2026-02-28','Stichtag wird auf 1–28 begrenzt');
test_ok(yw_batch_next_date('biweekly',1,'2026-10-01',new DateTimeImmutable('2026-10-09 13:00',$berlin))->format('Y-m-d')==='2026-10-15','Sammeltermin alle 14 Tage ab Starttermin');
test_ok(yw_batch_next_date('biweekly',1,'2026-10-01',new DateTimeImmutable('2026-10-15 00:00',$berlin))->format('Y-m-d')==='2026-10-29','14-Tage-Termin genau am Stichtag springt weiter');
test_ok(yw_batch_next_date('biweekly',1,'2026-03-20',new DateTimeImmutable('2026-04-02 12:00',$berlin))->format('Y-m-d H:i')==='2026-04-03 00:00','14-Tage-Termin über die Sommerzeitumstellung');
test_ok(yw_batch_next_date('biweekly',1,'2026-12-24',new DateTimeImmutable('2026-10-09 13:00',$berlin))->format('Y-m-d')==='2026-12-24','zukünftiger Starttermin ist der erste Sammeltermin');
test_ok(yw_batch_next_date('off',1,null)===null && yw_batch_next_date('irgendwas',1,null)===null,'ohne Sammelbestellung kein Stichtag');
test_ok(yw_budget_mail_due('2026-10-01 00:00:00',null,new DateTimeImmutable('2026-10-03 07:00',$berlin)),'Jahreswechsel-Mail in den ersten Tagen des Budgetjahres fällig');
test_ok(!yw_budget_mail_due('2026-10-01 00:00:00','2026-10-01',new DateTimeImmutable('2026-10-03 07:00',$berlin)),'Jahreswechsel-Mail mit Merker nicht doppelt');
test_ok(yw_budget_mail_due('2026-10-01 00:00:00','2025-10-01',new DateTimeImmutable('2026-10-03 07:00',$berlin)),'Merker des Vorjahres verhindert die neue Mail nicht');
test_ok(!yw_budget_mail_due('2026-01-01 00:00:00',null,new DateTimeImmutable('2026-10-09 07:00',$berlin)),'Einschalten mitten im Budgetjahr löst keine Mail aus');
test_ok(yw_exchange_budget_effect('return','done',-2380)===-2380 && yw_exchange_budget_effect('return','received',-2380)===0,'Rückgabe: Gutschrift erst nach Abschluss');
test_ok(yw_exchange_budget_effect('exchange','requested',500)===500 && yw_exchange_budget_effect('exchange','rejected',500)===0,'Tausch: Mehrkosten reservieren Budget ab Antrag, Ablehnung gibt frei');
test_ok(yw_exchange_budget_effect('exchange','approved',-300)===0 && yw_exchange_budget_effect('exchange','done',-300)===-300 && yw_exchange_budget_effect('exchange','done',0)===0,'Tausch: Minderkosten nach Abschluss, gleicher Preis neutral');
test_ok(yw_exchange_transition_allowed('requested','approved') && yw_exchange_transition_allowed('received','done') && !yw_exchange_transition_allowed('done','rejected') && !yw_exchange_transition_allowed('rejected','approved') && !yw_exchange_transition_allowed('approved','requested'),'Umtausch: nur zulässige Statuswechsel');
test_ok(yw_exchange_window_base(['fulfillment_status'=>'shipped','production_updated_at'=>'2026-10-05 10:00:00','decided_at'=>'2026-09-01 10:00:00','created_at'=>'2026-09-01'])==='2026-10-05 10:00:00','Frist beginnt mit dem Versand');
test_ok(yw_exchange_window_base(['fulfillment_status'=>'production','production_updated_at'=>'2026-10-05','decided_at'=>'2026-09-01 10:00:00','created_at'=>'2026-08-30'])==='2026-09-01 10:00:00','ohne Versand beginnt die Frist mit der Freigabe');
test_ok(yw_exchange_deadline('2026-09-01 10:00:00',30)->format('Y-m-d H:i:s')==='2026-10-01 23:59:59' && yw_exchange_deadline('2026-09-01',0)===null,'Frist: 30 Tage bis Tagesende, 0 = aus');
$xOrder=['status'=>'approved','fulfillment_status'=>'new','production_updated_at'=>null,'decided_at'=>'2026-09-01 10:00:00','created_at'=>'2026-09-01 09:00:00'];
test_ok(yw_exchange_window_open(['exchange_window_days'=>30],$xOrder,new DateTimeImmutable('2026-10-01 20:00',$berlin)) && !yw_exchange_window_open(['exchange_window_days'=>30],$xOrder,new DateTimeImmutable('2026-10-02 00:01',$berlin)),'Frist endet am letzten Tag um Mitternacht');
test_ok(!yw_exchange_window_open(['exchange_window_days'=>30],['status'=>'pending']+$xOrder,new DateTimeImmutable('2026-09-02',$berlin)),'nur freigegebene Bestellungen sind tauschbar');
$sig=yw_decision_signature(str_repeat('a',32),5,7,3,'2026-10-16 10:00:00','k');
test_ok($sig!==yw_decision_signature(str_repeat('a',32),5,8,3,'2026-10-16 10:00:00','k') && $sig!==yw_decision_signature(str_repeat('a',32),5,7,4,'2026-10-16 10:00:00','k') && $sig!==yw_decision_signature(str_repeat('a',32),5,7,3,'2026-10-23 10:00:00','k'),'Freigabe-Token: Signatur bindet Person, Firma und Ablauf');
test_ok(yw_decision_token_parts(str_repeat('a',32).$sig)===[str_repeat('a',32),$sig] && yw_decision_token_parts(str_repeat('a',95))===null && yw_decision_token_parts(strtoupper(str_repeat('a',96)))===null,'Freigabe-Token: nur exaktes Format wird geprüft');
$decisionMail=yw_mail_html('Freigabe',"Freigeben: https://workwearcenter.de/firma/mail-freigabe/".str_repeat('a',96)."?aktion=freigeben\nAblehnen: https://workwearcenter.de.evil.example/x");
test_ok(str_contains($decisionMail,'>Freigeben</a>') && !str_contains($decisionMail,'evil.example/x"'),'Freigabe-Mail: Button nur für eigene Domain');
test_ok(yw_handover_status_label('manual')[0]==='Manuell übertragen','Übergabestatus „manuell übertragen“ ist benannt');
// 10.11: Probeshop, Verlauf in Klartext, QR-Aushang
require_once dirname(__DIR__).'/app/modules/probeshop.php';
test_ok(yw_probeshop_token_ok(str_repeat('ab',32)) && !yw_probeshop_token_ok(str_repeat('a',63)) && !yw_probeshop_token_ok(str_repeat('A',64)) && !yw_probeshop_token_ok(str_repeat('a',64)."\n"),'Probeshop: nur 64 Hex-Zeichen sind ein gültiger Token');
test_ok(strlen(random_token())===64 && yw_probeshop_token_ok(random_token()),'Probeshop: erzeugte Tokens sind 256 Bit Zufall');
test_ok(yw_probeshop_logo_path_ok('uploads/probeshop/'.str_repeat('0f',16).'.svg'),'Probeshop: Logo mit Zufallsnamen wird akzeptiert');
test_ok(!yw_probeshop_logo_path_ok('uploads/probeshop/../config.php') && !yw_probeshop_logo_path_ok('uploads/probeshop/'.str_repeat('0f',16).'.php') && !yw_probeshop_logo_path_ok('uploads/probeshop/logo.png') && !yw_probeshop_logo_path_ok('uploads/motifs/'.str_repeat('0f',16).'.png'),'Probeshop: fremde, ratbare oder ausführbare Pfade werden abgelehnt');
test_ok(yw_color_luminance('#fff')===1.0 && yw_color_luminance('#000000')===0.0 && yw_color_luminance('rot')===null,'Farbhelligkeit wird berechnet');
test_ok(yw_svg_average_luminance('<svg><path d="M0 0"/></svg>')===0.0 && yw_svg_average_luminance('<svg><path fill="#ffffff"/><text style="fill:#fff">A</text></svg>')===1.0,'SVG-Helligkeit: ohne Farbe schwarz, weiße Füllung hell');
test_ok(yw_probeshop_tone_for_luminance(0.1)==='light' && yw_probeshop_tone_for_luminance(0.9)==='dark' && yw_probeshop_tone_for_luminance(null)==='dark','Probeshop: dunkles Logo auf heller Kleidung, helles auf dunkler');
test_ok(yw_slug_reserved('probeshop'),'Kurzname „probeshop“ ist reserviert');
$zones=yw_zones_sanitize(['front'=>['chest_left'=>['x'=>62,'y'=>30,'w'=>17]]]);
test_ok((yw_zone_for($zones,'chest_left',['front'])['zone']['w']??0)==17.0,'Probeshop nutzt die Druckzonen-Logik der Logo-Vorschau');
test_ok(str_contains(yw_privacy_template(),'Probeshop für Interessenten') && str_contains(yw_privacy_template(),'30 Tagen'),'Datenschutz-Vorlage beschreibt den Probeshop');
test_ok(yw_account_anonymized('geloescht-3-ab@anonymisiert.invalid') && yw_account_anonymized('x@firma.de','2026-01-01 10:00:00') && !yw_account_anonymized('x@firma.de',null),'gelöschte Konten werden erkannt');
test_ok(yw_history_status_label('pending')==='Freigabe offen' && yw_history_status_label('production')==='In Produktion' && yw_history_status_label('cancelled')==='Storniert','Verlauf: Statuswerte in Klartext');
test_ok(yw_actor_label('employee')==='Mitarbeiter/in' && yw_actor_label('admin')==='Verwaltung' && yw_actor_label('api:auftragszentrale')==='Schnittstelle auftragszentrale' && yw_actor_label('guest',null,['via'=>'api:fanbande'])==='Schnittstelle fanbande' && yw_actor_label('guest')==='System','Verlauf: Akteure in Klartext');
test_ok(yw_actor_label('employee',5,[],['employee'=>[5=>'Konto gelöscht (DSGVO)']])==='Mitarbeiter/in · Konto gelöscht (DSGVO)','Verlauf: gelöschtes Konto ohne Namen/Mail');
test_ok(yw_history_details(['decision'=>'rejected','self_decision'=>true])==='→ Abgelehnt (eigene Bestellung)' && yw_history_details(['previous_status'=>'approved','reason'=>'Größe'])==='vorher: Freigegeben · Grund: Größe','Verlauf: Zusatzangaben übersetzt');
test_ok(yw_entity_label('order',12)==='Auftrag WWC-000012' && yw_entity_label('employee',3)==='Mitarbeiterkonto #3' && yw_entity_label(null,null)==='–','Protokoll: Objekte in Klartext');
$pinSteps=yw_poster_steps(['registration_mode'=>'pin_approval'],'registrieren');$openSteps=yw_poster_steps(['registration_mode'=>'open'],'registrieren');
test_ok(count($pinSteps)===4 && str_contains(implode(' ',$pinSteps),'Firmen-PIN') && count($openSteps)===3 && !str_contains(implode(' ',$openSteps),'PIN'),'QR-Aushang: Schritte passen zu den Registrierungsregeln');
test_ok(str_contains(implode(' ',yw_poster_steps(['registration_mode'=>'approval'],'login')),'anmelden'),'QR-Aushang: Schritte für den Login-Link');
// Firmenshop-Oberfläche (Paket design): Farbgruppen, Beschreibung, Kontraste
$g=yw_shop_group_products([
    ['id'=>1,'name'=>'Workwear Sweatshirt','sku'=>'JN840-NAVY','color'=>'Navy','brand'=>'J&N'],
    ['id'=>2,'name'=>'T-Shirt','sku'=>'YW-1','color'=>'Navy','brand'=>'Yanik'],
    ['id'=>3,'name'=>'Workwear Sweatshirt','sku'=>'JN840-BLACK','color'=>'Black','brand'=>'J&N'],
    ['id'=>4,'name'=>'T-Shirt','sku'=>'YW-2','color'=>'Rot','brand'=>'Yanik','custom_name'=>''],
    ['id'=>5,'name'=>'T-Shirt','sku'=>'YW-3','color'=>'navy','brand'=>'Yanik'],
    ['id'=>6,'name'=>'T-Shirt','sku'=>'YW-4','color'=>'Weiß','brand'=>'Anders'],
    ['id'=>7,'name'=>'Cap','sku'=>'','color'=>'','brand'=>''],
    ['id'=>8,'name'=>'Cap','sku'=>'','color'=>'','brand'=>''],
]);
$ids=array_map(fn($x)=>array_map(fn($p)=>$p['id'],$x),$g);
test_ok($ids===[[1,3],[2,4],[5],[6],[7],[8]],'Farbgruppen: Grund-SKU bzw. Name+Marke, gleiche Farbe/andere Marke/ohne Farbe bleiben getrennt');
$parts=yw_shop_description_parts("Klassisches Sweatshirt\n\nStrapazierfähig\nInnenseite angeraut\n60°C waschbar, trocknergeeignet\n\nMaterial: Oberstoff (290 g/m²): 70% Baumwolle");
test_ok($parts['intro']==='Klassisches Sweatshirt' && $parts['bullets']===['Strapazierfähig','Innenseite angeraut'] && $parts['care']===['60°C waschbar, trocknergeeignet'] && $parts['material']===['Oberstoff (290 g/m²): 70% Baumwolle'],'Beschreibung: Stichpunkte, Pflege und Material getrennt');
test_ok(yw_shop_description_parts('Ein Satz, 60 °C waschbar.')['intro']==='Ein Satz, 60 °C waschbar.','einzeiliger Text bleibt Absatz');
test_ok(yw_shop_on_color('#e0a526')==='#111827' && yw_shop_on_color('#0f3d3e')==='#ffffff','Schriftfarbe auf Firmenfarbe nach Kontrast');
test_ok(yw_shop_contrast(yw_shop_ink('#ffd400'),'#ffffff')>=4.5 && yw_shop_ink('#123456')==='#123456','Akzentfarbe als Text erreicht 4,5:1');
test_ok(yw_shop_initials('Krämer Haustechnik GmbH & Co. KG')==='KH' && yw_shop_initials('nordbau')==='N','Initialen-Platzhalter');
$pb=['net'=>1000,'vat'=>19.0,'gross'=>1190,'employee'=>238,'employer'=>952,'employee_percent'=>20.0];
test_ok(str_contains(yw_shop_price_html([$pb]),'Dein Anteil') && str_contains(yw_shop_price_html([$pb]),'2,38'),'Karte mit Eigenanteil zeigt nur den Anteil');
$pb['employee_percent']=0.0;$pb['employee']=0;
test_ok(str_contains(yw_shop_price_html([$pb,['gross'=>990]+$pb]),'ab 9,90') && str_contains(yw_shop_price_html([$pb]),'übernimmt deine Firma'),'Karte bei 100 % Firma: Hinweis und „ab“-Preis');
// 10.11: Rundung je Position, Steuer je Steuersatz (wie Lexware Office bei Nettorechnungen)
$c=yw_calc_items([['net_unit'=>840,'qty'=>3,'vat'=>19]]);
test_ok($c['net']===2520 && $c['tax']===479 && $c['gross']===2999,'3 × 8,40 € netto zu 19 % ergibt 29,99 € brutto (bisher 3 × 10,00 € = 30,00 €)');
$c=yw_calc_items([['net_unit'=>1003,'qty'=>1,'vat'=>19],['net_unit'=>1003,'qty'=>1,'vat'=>19]]);
test_ok($c['tax']===381 && $c['lines'][0]['gross']===1194 && $c['lines'][1]['gross']===1193 && $c['gross']===2387,'zwei Positionen je 10,03 €: 3,81 € Steuer auf die Summe, Rest-Cent an die erste Position');
$c=yw_calc_items([['net_unit'=>1340,'qty'=>1,'vat'=>19],['net_unit'=>832,'qty'=>1,'vat'=>7],['net_unit'=>500,'qty'=>1,'vat'=>0]]);
test_ok($c['tax_by_rate']===[0=>0,700=>58,1900=>255] && $c['net']===2672 && $c['gross']===2985,'Beispiel der Lexware-Doku: 26,72 € netto, 0,58 € + 2,55 € Steuer, 29,85 € brutto');
$c=yw_calc_items([['net_unit'=>840,'qty'=>3,'vat'=>19]],30.0);
test_ok($c['employee']===900 && $c['company']===2099,'Eigenanteil 30 % je Position: 899,7 → 9,00 €, Firma 20,99 €');
$c=yw_calc_items([['net_unit'=>1001,'qty'=>1,'vat'=>19],['net_unit'=>1001,'qty'=>1,'vat'=>19]],50.0);
test_ok($c['employee']+$c['company']===$c['gross'] && $c['lines'][0]['employee']+$c['lines'][0]['company']===$c['lines'][0]['gross'],'Eigenanteil plus Firmenanteil ergibt immer exakt den Bruttobetrag');
$items=yw_calc_items([['net_unit'=>1060,'qty'=>1,'vat'=>19]]);
$shippingTax=yw_shipping_tax_cents($items['net_by_rate'],460,19.0);
test_ok($items['gross']===1261 && $shippingTax===88 && $items['gross']+460+$shippingTax===yw_invoice_totals([['net_unit'=>1060,'qty'=>1,'vat'=>19],['net_unit'=>460,'qty'=>1,'vat'=>19]])['gross'],'Versand 4,60 € netto: 0,88 € Steuer, Bestellsumme 18,09 € = Lexware-Summe');
test_ok(yw_shipping_tax_cents(yw_calc_items([['net_unit'=>1000,'qty'=>1,'vat'=>7]])['net_by_rate'],495,19.0)===94,'Versand mit anderem Steuersatz als die Ware wird eigenständig gerundet');
test_ok(yw_order_item_amounts(['quantity'=>3,'unit_price_cents'=>840,'unit_net_cents'=>840,'unit_gross_cents'=>1000,'employee_share_cents'=>300,'company_share_cents'=>700,'line_gross_cents'=>null])===['net'=>2520,'gross'=>3000,'employee'=>900,'company'=>2100],'Altbestellung (vor 10.11) behält Stückrundung 3 × 10,00 €');
test_ok(yw_order_item_amounts(['quantity'=>3,'unit_gross_cents'=>1000,'line_net_cents'=>2520,'line_gross_cents'=>2999,'line_employee_cents'=>900,'line_company_cents'=>2099])['gross']===2999,'neue Bestellung liest Positionswerte');
test_ok(yw_percent_of(1999,12.5)===250 && yw_percent_of(0,30)===0 && yw_tax_for_net(1495,1900)===284,'Prozentwerte werden kaufmännisch und ohne Gleitkommafehler gerundet');

$x=yw_calc_items([['net_unit'=>1003,'qty'=>1,'vat'=>19],['net_unit'=>1103,'qty'=>1,'vat'=>19]],20.0);$y=yw_calc_items([['net_unit'=>1103,'qty'=>1,'vat'=>19],['net_unit'=>1003,'qty'=>1,'vat'=>19]],20.0);
test_ok($x['lines'][1]['gross']===$y['lines'][0]['gross'] && $x['lines'][0]['gross']===$y['lines'][1]['gross'] && $x['employee']===$y['employee'] && $x['gross']===$y['gross'],'Rest-Cent bei gleichem Rest an den größeren Betrag – unabhängig von der Artikelreihenfolge (wie assets/shop.js)');
$shopJs=(string)file_get_contents(dirname(__DIR__).'/assets/shop.js');
test_ok(str_contains($shopJs,'(lines[b].rem - lines[a].rem) || (lines[b].net - lines[a].net) || (a - b)') && str_contains($shopJs,'Math.floor((Math.max(0, net) * bp + 5000) / 10000)'),'Live-Summe im Shop nutzt dieselbe Rundung wie pricing.php');

// 10.11: Lexware-Anbindung (reine Logik)
test_ok(yw_lexware_base_ok('https://api.lexware.io') && yw_lexware_base_ok('http://127.0.0.1:8105') && !yw_lexware_base_ok('http://api.lexware.io') && !yw_lexware_base_ok('https://user:pw@api.lexware.io') && !yw_lexware_base_ok('ftp://x'),'Lexware-Basis-URL nur HTTPS (HTTP nur lokal)');
test_ok(!str_contains(yw_lexware_error_message(403,['message'=>"'geheim123' not a valid key=value pair"]),'geheim123'),'Lexware-Fehler 403 gibt den API-Schlüssel nie aus');
$msg=yw_lexware_error_message(406,['status'=>406,'details'=>[['violation'=>'NOTNULL','field'=>'lineItems[0].unitPrice.taxRatePercentage','message'=>'darf nicht leer sein']]]);
test_ok(str_contains($msg,'nicht angenommen') && str_contains($msg,'lineItems[0].unitPrice.taxRatePercentage: darf nicht leer sein'),'Lexware-Validierungsfehler wird verständlich mit Feld angezeigt');
test_ok(str_contains(yw_lexware_error_message(406,['IssueList'=>[['i18nKey'=>'missing_entity','source'=>'company.name','type'=>'validation_failure']]]),'company.name (missing_entity)'),'Lexware-Altformat IssueList wird gelesen');
test_ok(str_contains(yw_lexware_error_message(429,null),'429') && str_contains(yw_lexware_error_message(504,null),'möglicherweise trotzdem'),'Rate-Limit und Zeitüberschreitung werden erklärt');
$contact=yw_lexware_contact_payload(['name'=>'Muster Bau GmbH','street'=>'Hauptstr. 5','zip'=>'33154','city'=>'Salzkotten','country'=>'de','vat_id'=>'DE 123456789','email'=>'rechnung@muster.de','contact_last'=>'Meier'],'muster');
test_ok(json_encode($contact['roles'])==='{"customer":{}}' && $contact['version']===0 && $contact['addresses']['billing'][0]['countryCode']==='DE' && $contact['company']['vatRegistrationId']==='DE123456789' && $contact['company']['contactPersons'][0]['lastName']==='Meier','Lexware-Kontakt: Kundenrolle als leeres Objekt, Adresse, USt-IdNr.');
test_throws(fn()=>yw_lexware_contact_payload(['name'=>'X','country'=>'Deutschland']),'Lexware-Kontakt: Ländercode wird geprüft');
test_ok(yw_lexware_search_param('Müller & Söhne')==='M%C3%BCller%20%26amp%3B%20S%C3%B6hne','Lexware-Kontaktsuche: & wird HTML- und URL-kodiert');
test_ok(yw_lexware_address_guess(['delivery_address'=>"Muster Bau GmbH\nWerkstr. 7\n33154 Salzkotten"])==['street'=>'Werkstr. 7','zip'=>'33154','city'=>'Salzkotten','supplement'=>''],'Rechnungsadresse wird aus der Lieferadresse vorgeschlagen');
test_ok(yw_lexware_date(new DateTimeImmutable('2026-10-09 15:30',new DateTimeZone('Europe/Berlin')))==='2026-10-09T00:00:00.000+02:00','Lexware-Datumsformat mit Millisekunden und Offset');
$orders=[['id'=>12,'employee_name'=>'Mia','cost_center'=>'4711','shipping_net_cents'=>495,'shipping_rate'=>19.0],['id'=>13,'employee_name'=>'Tom','cost_center'=>'','shipping_net_cents'=>495,'shipping_rate'=>19.0]];
$itemsBy=[12=>[['product_name'=>'T-Shirt','size'=>'M','quantity'=>3,'unit_net_cents'=>840,'vat_rate'=>'19.00']],13=>[['product_name'=>'T-Shirt','size'=>'M','quantity'=>1,'unit_net_cents'=>840,'vat_rate'=>'19.00'],['product_name'=>'Jacke','size'=>'XL','quantity'=>1,'unit_net_cents'=>4990,'vat_rate'=>'19.00']]];
$built=yw_lexware_build_lines($orders,$itemsBy,['grouping'=>'none','employee_names'=>true,'unit'=>'Stück']);
$first=$built['lineItems'][0];
test_ok(count($built['lineItems'])===3 && $first['quantity']===4 && $first['unitPrice']['netAmount']===8.4 && $first['unitPrice']['taxRatePercentage']===19 && $first['description']==='Größe M · Aufträge WWC-000012 (Mia), WWC-000013 (Tom)','Sammelrechnung fasst gleiche Artikel zusammen (Menge, Netto-Einzelpreis, Steuersatz, Auftragsnummern)');
$ship=$built['lineItems'][2];
test_ok($ship['name']==='Versand' && $ship['quantity']===2 && $ship['unitPrice']['netAmount']===4.95,'Versand ist eine eigene Position');
test_ok(yw_invoice_totals($built['calc'])===['net'=>9340,'tax'=>1775,'gross'=>11115,'by_rate'=>[1900=>['net'=>9340,'tax'=>1775]]],'erwartete Lexware-Summe: 93,40 € netto + 17,75 € Steuer = 111,15 €');
$grouped=yw_lexware_build_lines($orders,$itemsBy,['grouping'=>'cost_center','employee_names'=>false]);
test_ok($grouped['lineItems'][0]['type']==='text' && $grouped['lineItems'][0]['name']==='Kostenstelle: 4711' && !str_contains(json_encode($grouped['lineItems'],JSON_UNESCAPED_UNICODE),'Mia'),'Gruppierung nach Kostenstelle mit Zwischenüberschrift, Namen abschaltbar');
$payload=yw_lexware_invoice_payload('be9475f4-ef80-442b-8ab9-3ab8b1a2aeb9',$built['lineItems'],['voucher_date'=>new DateTimeImmutable('2026-10-09'),'shipping'=>['shippingType'=>'delivery','shippingDate'=>'2026-10-09T00:00:00.000+02:00'],'introduction'=>'Bestellung','settings'=>['title'=>'Rechnung über Arbeitskleidung lang','introduction'=>'Hallo','remark'=>'','unit'=>'Stück','employee_names'=>'1','grouping'=>'none']]);
test_ok($payload['taxConditions']['taxType']==='net' && $payload['address']['contactId']==='be9475f4-ef80-442b-8ab9-3ab8b1a2aeb9' && mb_strlen($payload['title'])===25 && !isset($payload['remark']) && !isset($payload['voucherStatus']),'Rechnungsentwurf: Nettopreise, Kontakt-ID, Titel ≤ 25 Zeichen, kein Finalisieren');
test_ok(yw_lexware_month_batches([['id'=>1,'cost_center'=>'B'],['id'=>2,'cost_center'=>''],['id'=>3,'cost_center'=>'B']],'split')===['Kostenstelle: B'=>[1,3],'Ohne Kostenstelle'=>[2]],'je Kostenstelle eine eigene Rechnung');

// 10.15: Sammelrechnung automatisch aufteilen – höchstens N Positionen je Entwurf, ein Auftrag nie geteilt, jeder Auftrag genau einmal
$lxOrders=[];$lxItems=[];
foreach([1=>['A',3],2=>['A',2],3=>['B',4],4=>['C',3],5=>['C',3],6=>['C',3],7=>['C',3],8=>['C',3],9=>['C',12],10=>['',2]] as $lxId=>[$lxCc,$lxN]){
    $lxOrders[]=['id'=>$lxId,'employee_name'=>'','cost_center'=>$lxCc,'shipping_net_cents'=>495,'shipping_rate'=>19.0];
    for($k=1;$k<=$lxN;$k++)$lxItems[$lxId][]=['product_name'=>'Artikel '.$lxId.'-'.$k,'size'=>'M','quantity'=>1,'unit_net_cents'=>1000,'vat_rate'=>'19.00'];
}
$lxById=array_column($lxOrders,null,'id');
$lxCheck=static function(array $plan,int $max) use ($lxById,$lxItems): bool {
    $seen=[];
    foreach($plan['parts'] as $p){
        if($p['lines']>$max || $p['lines']!==count(yw_lexware_build_lines(array_map(fn($id)=>$lxById[$id],$p['ids']),$lxItems,['grouping'=>$plan['grouping']])['lineItems']))return false;
        foreach($p['ids'] as $id){if(isset($seen[$id]))return false;$seen[$id]=true;}
    }
    return count($seen)+count($plan['too_big'])===count($lxById) && !array_intersect_key($seen,$plan['too_big']);
};
$lxPlan=yw_lexware_month_plan($lxOrders,$lxItems,'none',10);
test_ok($lxCheck($lxPlan,10) && array_column($lxPlan['parts'],'ids')===[[1,2,3],[4,5,6],[7,8,10]] && array_column($lxPlan['parts'],'lines')===[10,10,9],'Aufteilung: ganze Kostenstellen A+B zusammen, zu große Kostenstelle C in aufeinanderfolgende Teile, gleiche Versandposition zählt je Entwurf einmal');
test_ok(array_column($lxPlan['parts'],'part')===[1,2,3] && array_column($lxPlan['parts'],'of')===[3,3,3] && array_column($lxPlan['parts'],'label')===['Kostenstellen: A, B','Kostenstelle: C','Kostenstelle: C und ohne Kostenstelle'],'Aufteilung: „Teil 1 von 3“ mit den enthaltenen Kostenstellen');
test_ok($lxPlan['too_big']===[9=>13] && str_contains(yw_lexware_too_big_message(9,13),'WWC-000009 hat allein 13 Positionen'),'Auftrag, der allein zu groß ist, wird nicht verplant und klar gemeldet (Rest wird trotzdem abgerechnet)');
$lxGrouped=yw_lexware_month_plan($lxOrders,$lxItems,'cost_center',10);
test_ok($lxCheck($lxGrouped,10) && $lxGrouped['grouping']==='cost_center' && count(array_filter($lxGrouped['parts'],fn($p)=>array_intersect([1,2],$p['ids'])))===1,'Aufteilung mit Zwischenüberschriften: Überschriften zählen als Position, Kostenstelle A bleibt in einem Entwurf');
$lxSplit=yw_lexware_month_plan($lxOrders,$lxItems,'split',10);
test_ok($lxCheck($lxSplit,10) && array_map(fn($p)=>[$p['label'],$p['part'],$p['of']],$lxSplit['parts'])===[['Kostenstelle: A',0,0],['Kostenstelle: B',0,0],['Kostenstelle: C',1,2],['Kostenstelle: C',2,2],['Ohne Kostenstelle',0,0]],'je Kostenstelle: nur die zu große Kostenstelle wird in „Teil 1 von 2“ geteilt');
$lxNoCc=yw_lexware_month_plan(array_map(fn($o)=>['cost_center'=>'']+$o,$lxOrders),$lxItems,'none',10);
test_ok($lxCheck($lxNoCc,10) && array_unique(array_column($lxNoCc['parts'],'label'))===[''] && count($lxNoCc['parts'])>1 && $lxNoCc['parts'][0]['part']===1,'ohne Kostenstellen: aufeinanderfolgende Teile nach Auftragsnummer, nur „Teil x von y“');
$lxFit=yw_lexware_month_plan($lxOrders,$lxItems,'none');
test_ok(count($lxFit['parts'])===1 && $lxFit['parts'][0]['part']===0 && $lxFit['parts'][0]['label']==='' && count($lxFit['parts'][0]['ids'])===10 && !$lxFit['too_big'],'passt alles in eine Rechnung, bleibt es bei einem ungeteilten Entwurf');
test_ok(yw_lexware_plan_hash($lxPlan,'2025-10','none')!==yw_lexware_plan_hash(yw_lexware_month_plan(array_slice($lxOrders,1),$lxItems,'none',10),'2025-10','none'),'Vorschau-Prüfsumme ändert sich, sobald sich die Aufträge ändern');
$lxMeta=yw_lexware_month_meta(['shop_name'=>'Grossbau Shop'],'2025-10',107,'Kostenstellen: A, B','none',2,7);
$lxPay=yw_lexware_invoice_payload('be9475f4-ef80-442b-8ab9-3ab8b1a2aeb9',[],$lxMeta+['settings'=>['title'=>'Rechnung','introduction'=>'','remark'=>'']]);
test_ok($lxPay['title']==='Rechnung (Teil 2 von 7)' && str_contains($lxPay['introduction'],'Teil 2 von 7: 107 Aufträge') && str_contains($lxPay['introduction'],'Kostenstellen: A, B'),'Teilrechnung: „Teil 2 von 7“ in Titel und Einleitung');
test_ok(yw_lexware_invoice_payload('be9475f4-ef80-442b-8ab9-3ab8b1a2aeb9',[],$lxMeta+['settings'=>['title'=>'Arbeitskleidung Rechnung','introduction'=>'','remark'=>'']])['title']==='Arbeitskleidung Rechnung','Teilrechnung: zu langer Titel bleibt unverändert (höchstens 25 Zeichen), Teil steht in der Einleitung');
$lxCode=(string)file_get_contents(dirname(__DIR__).'/app/modules/lexware.php');
test_ok(str_contains($lxCode,"SET status='creating',last_error=NULL WHERE id=? AND status IN ('planned','failed')") && str_contains($lxCode,"SET status='failed',last_error=? WHERE id=? AND status='creating'"),'Teil wird nur von einer Anfrage gesendet; sicherer Fehler bleibt „fehlgeschlagen“ mit denselben Aufträgen (erneut sendbar, keine Doppelanlage)');
test_ok(str_contains($lxCode,"\$doc['status']==='creating' && strtotime((string)\$doc['updated_at'])>=time()-300)throw") && !str_contains($lxCode,'Bitte „Je Kostenstelle eine eigene Rechnung“ wählen'),'Entwurf im Versand kann nicht freigegeben werden; alter Hinweis „je Kostenstelle wählen“ entfernt');
$lxMig=(string)file_get_contents(dirname(__DIR__).'/app/migrations.php');
test_ok(preg_match("/\/\/ 10\.15 – Lexware[^\n]*\n(?:[^\n]*\n)*?\s*schema_add_column\(\\\$pdo,'yw_lexware_documents','part_no'[^\n]*\n\s*schema_add_column\(\\\$pdo,'yw_lexware_documents','part_count'[^\n]*\n\s*schema_add_column\(\\\$pdo,'yw_lexware_documents','line_grouping'[^\n]*\n\s*schema_mark_version\(\\\$pdo,'10\.15\.0'/u",$lxMig)===1 && str_contains((string)file_get_contents(dirname(__DIR__).'/app/functions.php'),"const YW_SCHEMA_REQUIRED='10.15.0';"),'Datenbank-Update 10.15: Teil-Spalten idempotent vor der Versionsmarke 10.15.0, die das Programm verlangt');
// 10.15: Nur wenn das Zeitbudget je Aufruf abläuft, sendet die Seite die übrigen Entwürfe automatisch weiter – nach Fehler oder Abbruch nie
$_SESSION=[];yw_lexware_flash_result(3,'2026-08',['created'=>[['gross_cents'=>1190,'orders'=>2]],'failed'=>[],'pending'=>13,'stopped'=>false],14,[]);
test_ok(($_SESSION['lexware_weiter'][0]??0)===3 && ($_SESSION['lexware_weiter'][1]??'')==='2026-08' && $_SESSION['flash'][0]==='success' && str_contains($_SESSION['flash'][1],'13 weitere Entwürfe werden jetzt automatisch'),'Zeitbudget abgelaufen: übrige Entwürfe werden automatisch weiter gesendet');
$_SESSION=[];yw_lexware_flash_result(3,'2026-08',['created'=>[],'failed'=>['Teil 2'=>'Abgelehnt (406).'],'pending'=>5,'stopped'=>false],6,[]);$lxAuto=isset($_SESSION['lexware_weiter']) || $_SESSION['flash'][0]!=='error';
$_SESSION=[];yw_lexware_flash_result(3,'2026-08',['created'=>[],'failed'=>[],'pending'=>5,'stopped'=>true],5,[]);$lxAuto=$lxAuto || isset($_SESSION['lexware_weiter']) || $_SESSION['flash'][0]!=='error';$_SESSION=[];
$lxJs=(string)file_get_contents(dirname(__DIR__).'/assets/app.js');
test_ok(!$lxAuto && str_contains($lxCode,"' data-lexware-weiter'") && str_contains($lxCode,'>=time()-120') && str_contains($lxJs,"form[data-lexware-weiter]"),'kein automatisches Weitersenden nach fehlgeschlagenem Teil oder Abbruch (Schlüssel, Zeitüberschreitung); Weitersenden nur kurz nach dem letzten Durchgang');

// 10.11: Lohnabzug-Export
$rows=yw_payroll_aggregate([
    ['id'=>5,'employee_account_id'=>7,'invite_id'=>1,'employee_total_cents'=>900,'name'=>'Zoe Berg','employee_number'=>'P-7','cost_center'=>'','delivery_snapshot_json'=>'{"cost_center":"KST9"}'],
    ['id'=>9,'employee_account_id'=>7,'invite_id'=>1,'employee_total_cents'=>1250,'name'=>'Zoe Berg','employee_number'=>'P-7','cost_center'=>'','delivery_snapshot_json'=>'{}'],
    ['id'=>6,'employee_account_id'=>3,'invite_id'=>2,'employee_total_cents'=>499,'name'=>'=HYPERLINK("x")','employee_number'=>'+49','cost_center'=>'KST1','delivery_snapshot_json'=>null],
]);
test_ok(count($rows)===2 && $rows[1]['employee_cents']===2150 && $rows[1]['orders']===['WWC-000005','WWC-000009'] && $rows[1]['cost_center']==='KST9','Lohnabzug: Summe je Person mit Auftragsnummern und Kostenstelle');
$csv=yw_payroll_csv_rows('2026-10',$rows);
test_ok($csv[0][2]==="'=HYPERLINK(\"x\")" && $csv[0][1]==="'+49" && $csv[1][4]==='21,50','Lohnabzug: Formel-Schutz und Betrag mit Komma');
test_ok(yw_payroll_month('2026-13')!=='2026-13' && yw_payroll_month('2026-02')==='2026-02' && yw_payroll_range('2026-12')===['2026-12-01 00:00:00','2027-01-01 00:00:00'],'Lohnabzug: Monat wird geprüft, Jahreswechsel stimmt');

// 10.12: Mehrsprachiger Firmenshop
$de=require dirname(__DIR__).'/app/lang/de.php';
$plural=['de'=>2,'en'=>2,'tr'=>2,'pl'=>3,'ru'=>3,'ro'=>3];
$placeholders=fn($v)=>(preg_match_all('/\{[a-z_]+\}/',implode('|',(array)$v),$m)?array_values(array_unique($m[0])):[]);
foreach(array_keys(YW_LANGS) as $code){
    $table=require dirname(__DIR__).'/app/lang/'.$code.'.php';
    test_ok(array_keys(array_diff_key($de,$table))===[] && array_keys(array_diff_key($table,$de))===[],"Sprache {$code}: gleiche Schlüssel wie Deutsch");
    $bad=[];
    foreach($de as $key=>$value){
        $tv=$table[$key];
        if($key==='fmt.months'){if(count($tv)!==12)$bad[]=$key;continue;}
        if(is_array($value)!==is_array($tv)||(is_array($tv)&&count($tv)!==$plural[$code]))$bad[]=$key;
        $a=$placeholders($value);$b=$placeholders($tv);sort($a);sort($b);
        if($a!==$b)$bad[]=$key;
    }
    test_ok($bad===[],"Sprache {$code}: Platzhalter und Pluralformen vollständig".($bad?' ('.implode(', ',$bad).')':''));
}
test_ok(yw_plural_index('pl',1)===0 && yw_plural_index('pl',3)===1 && yw_plural_index('pl',5)===2 && yw_plural_index('pl',12)===2 && yw_plural_index('pl',22)===1,'Plural Polnisch: 1 / 2–4 / 5+ (12 → viele, 22 → wenige)');
test_ok(yw_plural_index('ru',21)===0 && yw_plural_index('ru',11)===2 && yw_plural_index('ru',24)===1,'Plural Russisch: 21 → eins, 11 → viele, 24 → wenige');
test_ok(yw_plural_index('ro',1)===0 && yw_plural_index('ro',19)===1 && yw_plural_index('ro',20)===2,'Plural Rumänisch: 20 → „de“-Form');
test_ok(yw_with_lang('pl',fn()=>tn('product.colors',5))==='5 kolorów' && yw_with_lang('pl',fn()=>tn('product.colors',3))==='3 kolory','tn() wählt die polnische Pluralform');
test_ok(yw_with_lang('pl',fn()=>yw_money(123456))==="1\u{00A0}234,56\u{00A0}€" && yw_with_lang('en',fn()=>yw_money(123456))==='€1,234.56' && yw_money(-990)==='-9,90 €','Beträge im Format der Sprache, Euro bleibt');
test_ok(yw_with_lang('tr',fn()=>yw_percent(20.0))==='%20' && yw_with_lang('en',fn()=>yw_date('2026-10-09'))==='09/10/2026','Prozent und Datum je Sprache');
test_ok(yw_lang()==='de' && yw_with_lang('xx',fn()=>yw_lang())==='de','unbekannte Sprache fällt auf Deutsch zurück, yw_with_lang stellt zurück');
test_ok(yw_with_lang('ru',fn()=>t('gibt.es.nicht'))==='gibt.es.nicht' && yw_with_lang('ru',fn()=>t('greet.day_name',['name'=>'<b>']))==='Привет, <b>.','fehlender Schlüssel liefert den Schlüssel; Platzhalter werden nur ersetzt (Escaping beim Ausgeben)');
test_ok(yw_lang_from_header('tr-TR,tr;q=0.9,de;q=0.8',['de','tr'])==='tr' && yw_lang_from_header('fr,pl;q=0.5,en;q=0.7',['de','en','pl'])==='en' && yw_lang_from_header('fr,es',['de','pl'])===null && yw_lang_from_header('pl;q=0',['de','pl'])===null,'Accept-Language: Gewichtung, nur angebotene Sprachen, q=0 ignoriert');
test_ok(yw_company_langs_value(['pl','tr','xx'])==='de,pl,tr' && yw_company_langs_value(array_keys(YW_LANGS))===null && yw_company_langs_value([])==='de','Firmen-Sprachauswahl: Deutsch immer dabei, alle = NULL, Unbekanntes verworfen');
test_ok(yw_company_langs(['shop_languages'=>'tr,pl'])===['de','pl','tr'] && count(yw_company_langs(['shop_languages'=>null]))===6,'angebotene Sprachen der Firma (Standard: alle)');
test_ok(yw_lang_back_url('nordbau','/nordbau/produkte?artikel=3')==='/nordbau/produkte?artikel=3' && yw_lang_back_url('nordbau','//evil.example/nordbau')==='/nordbau' && yw_lang_back_url('nordbau','https://evil.example')==='/nordbau' && yw_lang_back_url('nordbau','/nordbauer')==='/nordbau' && yw_lang_back_url('nordbau','/nordbau/../admin')==='/nordbau' && yw_lang_back_url('nordbau','/nordbau/\\evil')==='/nordbau','Sprachwahl: Rücksprung nur in denselben Shop (kein offener Redirect)');
test_ok(yw_with_lang('pl',fn()=>yw_shop_greeting('Ola Nowak',8))==='Dzień dobry, Ola.' && yw_shop_greeting('Max',14)==='Hallo, Max.' && yw_shop_greeting('Max',20)==='Guten Abend, Max.','Begrüßung nach Tageszeit und Sprache');
$plMail=yw_with_lang('pl',fn()=>yw_mail_html(t('mail.confirm.subject',['id'=>5]),t('mail.hello',['name'=>'Ola']).
"\n\n".t('mail.sum.total').': 12,00 €'."\n".t('mail.view_status').': https://workwearcenter.de/firma/bestellungen'));
test_ok(str_contains($plMail,'<html lang="pl"') && str_contains($plMail,'Zobacz status zamówienia') && str_contains($plMail,'Pozdrawiamy'),'Mail-Rahmen und Schaltfläche in der Sprache der Person');

// 10.13.2: Anmeldung – Laufzeit und Drosselung je Konto
test_ok((int)(password_get_info(yw_dummy_password_hash())['options']['cost']??0)===(int)PASSWORD_BCRYPT_DEFAULT_COST,'Dummy-Hash hat dieselben Kosten wie echte Passwort-Hashes (keine Konto-Erkennung über die Laufzeit)');
$shopSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/shop.php');
test_ok(str_contains($shopSrc,'login_throttled($loginKey,25,false)') && str_contains($shopSrc,'record_login_failure($loginKey,false)'),'Mitarbeiter-Anmeldung drosselt zusätzlich IP-unabhängig je Konto');

$regSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/registration.php');
test_ok(str_contains($regSrc,'login_throttled($pinKey,30,false)') && str_contains($regSrc,'record_login_failure($pinKey,false)'),'Firmen-PIN der Registrierung wird IP-unabhängig je Firma gedrosselt');

foreach(['password_reset.php','admin_password_reset.php'] as $resetFile){$resetSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/'.$resetFile);test_ok(str_contains($resetSrc,'login_throttled($emailKey,5,false)') && str_contains($resetSrc,'record_login_failure($emailKey,false)'),"Passwort-Links je Adresse IP-unabhängig begrenzt ($resetFile)");}

$accountSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/admin_account.php');
test_ok(str_contains($accountSrc,'(totp_last_step IS NULL OR totp_last_step<?)') && str_contains($accountSrc,'AND recovery_codes_json=?') && str_contains($accountSrc,'return $u->rowCount()===1;'),'TOTP-Code und Wiederherstellungscode werden atomar nur einmal verbraucht (keine Mehrfachanmeldung durch parallele Anfragen)');

$customerSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/customer_admin.php');
test_ok(substr_count($customerSrc,'AND company_id=? AND anonymized_at IS NULL FOR UPDATE')>=1 && str_contains($customerSrc,"registration_status='approved' AND anonymized_at IS NULL FOR UPDATE"),'anonymisierte Konten lassen sich im Firmenshop weder reaktivieren noch mit Passwort-Link versehen');

// 10.14: API-Schlüssel je Firma, Vorschau nur per POST, Sitzungs-Höchstdauer, Einrichtungslinks
$apiSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/api.php');
test_ok(substr_count($apiSrc,'yw_api_assert_order_scope((int)$m[1])')===3 && substr_count($apiSrc,'yw_api_company_scope())!==null')>=3,'auf eine Firma beschränkte API-Schlüssel sehen und ändern nur Kunden und Bestellungen dieser Firma');
test_ok(str_contains($apiSrc,"'version'=>\$key?app_version():null"),'API-Ping verrät die Version nur mit gültigem Schlüssel');
$toolsSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/admin_tools.php');
test_ok((bool)preg_match('/function yw_admin_preview_start\(int \$companyId\): never \{\s*require_admin\(\);.*?if\(!is_post\(\)\).*?verify_csrf\(\);/s',$toolsSrc),'Shop-Vorschau startet nur per POST mit CSRF-Prüfung');
$bootSrc=(string)file_get_contents(dirname(__DIR__).'/app/bootstrap.php');
test_ok(str_contains($bootSrc,"time()-(int)\$_SESSION['started_at']>43200"),'Sitzungen enden spätestens 12 Stunden nach der Anmeldung');
$pwSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/password_reset.php');
test_ok(str_contains($pwSrc,'$newAccount?10080:60') && str_contains($pwSrc,'max(1,min(10080,$minutes))'),'Einrichtungslinks neuer Konten 7 Tage, Passwort-vergessen-Links 60 Minuten gültig');
// Härtung Dateien/Server: SVG mit eingebettetem XSLT, Fremd-Namensräume und externe CSS-Adressen
$xslSvg=yw_sanitize_svg('<?xml-stylesheet type="text/xsl" href=""?><svg xmlns="http://www.w3.org/2000/svg" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:h="http://www.w3.org/1999/xhtml" xsl:version="1.0" width="10" height="10"><xsl:element name="script">alert(1)</xsl:element><h:form action="https://example.org"/><rect width="10" height="10"/></svg>');
test_ok(!str_contains($xslSvg,'xml-stylesheet')&&!str_contains($xslSvg,'xsl:element')&&!str_contains($xslSvg,'h:form')&&str_contains($xslSvg,'<rect'),'SVG-Logo: XSLT-Verarbeitungsanweisung und Fremd-Elemente (XSL, XHTML) werden entfernt');
$cssSvg=yw_sanitize_svg('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><style>rect{fill:u\72l(https://example.org/x)}</style><rect fill="url(#g)" filter="url(https://example.org/f#a)"/></svg>');
test_ok(!str_contains($cssSvg,'example.org')&&str_contains($cssSvg,'fill="url(#g)"'),'SVG-Logo: externe Adressen auch über CSS-Escapes und url() in Attributen werden entfernt, interne Verläufe bleiben');

// Härtung Server: Sperren gelten auch ohne mod_rewrite, versteckte Dateien und Skript-Endungen in uploads/
$denied=static function(string $htaccess,string $file): bool {
    preg_match_all('#<FilesMatch "([^"]+)">\s*Require all denied#',(string)file_get_contents(dirname(__DIR__).'/'.$htaccess),$m);
    foreach($m[1] as $pattern)if(preg_match('#'.str_replace('#','\#',$pattern).'#',$file))return true;
    return false;
};
foreach(['app','tests','tools'] as $dir)test_ok(preg_match('/^Require all denied$/m',(string)@file_get_contents(dirname(__DIR__).'/'.$dir.'/.htaccess'))===1,"{$dir}/ hat eine eigene .htaccess mit „Require all denied“ (Schutz ohne mod_rewrite)");
test_ok($denied('.htaccess','.htaccess')&&$denied('.htaccess','.gitignore')&&$denied('.htaccess','openapi.yaml')&&!$denied('.htaccess','robots.txt')&&!$denied('.htaccess','app.js'),'Haupt-.htaccess sperrt versteckte Dateien und Dokumentation, nicht aber robots.txt und Assets');
test_ok($denied('uploads/.htaccess','x.pht')&&$denied('uploads/.htaccess','x.php7')&&$denied('uploads/.htaccess','x.html')&&$denied('uploads/.htaccess','x.shtml')&&$denied('uploads/.htaccess','x.json')&&!$denied('uploads/.htaccess','logo.png')&&!$denied('uploads/.htaccess','logo.svg'),'uploads/.htaccess sperrt Skript-, HTML- und Datenendungen, Bilder bleiben abrufbar');
test_ok(str_contains((string)file_get_contents(dirname(__DIR__).'/uploads/.htaccess'),"default-src 'none'; style-src 'unsafe-inline'; sandbox"),'SVG aus uploads/ wird mit CSP inkl. sandbox ausgeliefert');

// Härtung Dateien: ZIP-Eintrag mit vorgetäuschter kleiner Größe wird nicht unbegrenzt entpackt
if(class_exists('ZipArchive')){
    $zipFile=tempnam(sys_get_temp_dir(),'wwc-zip');$zip=new ZipArchive();$zip->open($zipFile,ZipArchive::OVERWRITE);
    $zip->addFromString('wwc_regressionstest_bombe.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==').str_repeat("\0",41*1024*1024));$zip->addFromString('../../wwc_regressionstest.php','<?php');$zip->close();
    $raw=(string)file_get_contents($zipFile);$needle='wwc_regressionstest_bombe.png';
    foreach([["PK\x03\x04",22,30,26],["PK\x01\x02",24,46,28]] as [$sig,$sizeAt,$nameAt,$lenAt])for($p=0;($p=strpos($raw,$sig,$p))!==false;$p+=4)if(substr($raw,$p+$nameAt,unpack('v',substr($raw,$p+$lenAt,2))[1])===$needle)$raw=substr_replace($raw,pack('V',1000),$p+$sizeAt,4);
    file_put_contents($zipFile,$raw);
    $imported=yw_katalog_import_zip($zipFile);@unlink($zipFile);
    test_ok($imported===0&&!is_file(yw_katalog_images_dir().'/'.$needle)&&!is_file(dirname(yw_katalog_images_dir(),3).'/wwc_regressionstest.php'),'Katalog-ZIP: Eintrag mit vorgetäuschter Größe (ZIP-Bombe) und Pfad nach oben werden verworfen');
}
test_ok(str_contains((string)file_get_contents(dirname(__DIR__).'/app/modules/api.php'),"'version'=>\$key?app_version():null"),'API-Ping verrät die Programmversion nur mit gültigem Schlüssel');
test_ok(str_contains((string)file_get_contents(dirname(__DIR__).'/app/modules/corporate_admin.php'),"'/.htaccess'=>'RewriteRule'"),'Systemstatus prüft auch versteckte Dateien, uploads/-Sperre und SVG-Header');

// Härtung CSRF: GET-Links mit Wirkung (Vorschau, Sicherung) nicht von fremden Seiten aus auslösbar
$crossSite=static fn(string $site): string => (string)shell_exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg('$_SERVER["HTTP_SEC_FETCH_SITE"]='.var_export($site,true).';require '.var_export(dirname(__DIR__).'/app/functions.php',true).';yw_block_cross_site_get();echo "weiter";').' 2>&1');
test_ok(!str_contains($crossSite('cross-site'),'weiter')&&str_contains($crossSite('same-origin'),'weiter')&&str_contains($crossSite(''),'weiter'),'Fremdseiten-Aufruf (Sec-Fetch-Site: cross-site) wird abgewiesen, eigene Links und alte Browser nicht');
$adminTools=(string)file_get_contents(dirname(__DIR__).'/app/modules/admin_tools.php');
test_ok(substr_count($adminTools,'yw_block_cross_site_get();')>=2,'Vorschau beenden und Datensicherung prüfen Sec-Fetch-Site (Vorschau starten nur per POST+CSRF)');

// 10.15: Bilder aus dem Daiber-Shop – nur feste HTTPS-Adressen, nur Bilder der passenden Farbe
test_ok(yw_daiber_url_allowed('https://www.daiber.shop/search?search=JN840')&&yw_daiber_url_allowed('https://daiber-prod-media-hp.s3.sbg.perf.cloud.ovh.net/media/a/b.jpg')&&yw_daiber_url_allowed('https://daiber-images.synaigy.com/http/media/a/b.jpg?w=1000'),'Daiber-Abgleich: Shop, Bildspeicher und Bilddienst sind erlaubt');
test_ok(!yw_daiber_url_allowed('http://www.daiber.shop/')&&!yw_daiber_url_allowed('https://www.daiber.shop.example.org/')&&!yw_daiber_url_allowed('https://evil@www.daiber.shop/')&&!yw_daiber_url_allowed('https://www.daiber.shop:8443/')&&!yw_daiber_url_allowed('https://127.0.0.1/')&&!yw_daiber_url_allowed('file:///etc/passwd'),'Daiber-Abgleich: kein HTTP, keine fremden Hosts, Zugangsdaten, Ports oder lokalen Adressen (SSRF)');
$daiberPage='<div data-variant-switch-options="{&quot;url&quot;:&quot;https:\/\/www.daiber.shop\/detail\/abc\/switch&quot;}"></div>'
    .'<label class="product-detail-configurator-option-label is-combinable is-display-media" title="black/red" for="'.str_repeat('a',32).'-'.str_repeat('b',32).'-x"><img src="https://daiber-prod-media-hp.s3.sbg.perf.cloud.ovh.net/media/1/jn010-schwarz.33960_master.jpg?ts=1"></label>'
    .'<label class="product-detail-configurator-option-label is-display-media" title="navy" for="'.str_repeat('a',32).'-'.str_repeat('c',32).'-x"><img src="https://evil.example.org/x_master.jpg"></label>';
$parsed=yw_daiber_parse_article($daiberPage);
test_ok(($parsed['colors']['black/red']['img']??'')==='https://daiber-prod-media-hp.s3.sbg.perf.cloud.ovh.net/media/1/jn010-schwarz.33960_master.jpg'&&!isset($parsed['colors']['navy'])&&$parsed['switch']==='https://www.daiber.shop/detail/abc/switch','Daiber-Seite: Farbe → Masterbild erkannt, Bilder fremder Hosts verworfen');
$details=yw_daiber_detail_urls('https://daiber-prod-media-hp.s3.sbg.perf.cloud.ovh.net/media/2/jn010-ocs-schwarz.33960_detail_1.jpg "https://daiber-prod-media-hp.s3.sbg.perf.cloud.ovh.net/media/3/art-jn010-weiss.33960_detail_2.jpg" https://daiber-prod-media-hp.s3.sbg.perf.cloud.ovh.net/media/4/jn010-rot.33962_detail_3.jpg https://evil.example.org/media/jn010.33960_detail_4.jpg','https://daiber-prod-media-hp.s3.sbg.perf.cloud.ovh.net/media/1/jn010-schwarz.33960_master.jpg');
test_ok($details===['https://daiber-prod-media-hp.s3.sbg.perf.cloud.ovh.net/media/2/jn010-ocs-schwarz.33960_detail_1.jpg'],'Daiber-Variante: nur Detailbilder derselben Farbe, keine Modellfotos (art-) und keine fremden Hosts');
$zones=['front'=>['sleeve_left'=>['x'=>88,'y'=>30,'w'=>8],'sleeve_right'=>['x'=>12,'y'=>30,'w'=>8]],'sleeve'=>['sleeve_left'=>['x'=>50,'y'=>35,'w'=>16],'sleeve_right'=>['x'=>50,'y'=>35,'w'=>16]]];
test_ok(yw_zone_for($zones,'sleeve_left',['front','sleeve'])['view']==='sleeve'&&yw_zone_for($zones,'sleeve_right',['front','sleeve'])['view']==='front'&&yw_zone_for($zones,'sleeve_left',['front'])['view']==='front','Ärmelfoto zeigt den linken Ärmel, der rechte bleibt vorne – zwei Ärmel-Logos liegen nie übereinander');
test_ok(!yw_daiber_save_image('<?php echo 1;',sys_get_temp_dir().'/wwc-daiber-test.jpg')&&!is_file(sys_get_temp_dir().'/wwc-daiber-test.jpg'),'Daiber-Abgleich: Nicht-Bilder werden nicht gespeichert');
// 10.15: Sammel-Erinnerung an Verantwortliche (höchstens eine Mail am Tag) und Aufbewahrungsfristen
test_ok(yw_reminder_is_new(null,'2026-10-01 09:00:00',null,3) && !yw_reminder_is_new(null,'2026-10-01 09:00:00','2026-10-04 10:00:00',3) && yw_reminder_is_new('2026-10-10 08:00:00','2026-10-07 09:00:00','2026-10-01 00:00:00',3) && !yw_reminder_is_new('2026-10-10 08:00:00','2026-10-07 07:00:00',null,3),'Sammel-Erinnerung: Anlass nur, was seit der letzten Sammel-Erinnerung neu fällig wurde (ohne frühere: noch nie Erinnertes)');
$digestOrders=[];for($i=1;$i<=25;$i++)$digestOrders[]=['id'=>100+$i,'employee_name'=>$i===1?"Eva: https://example.org/x\nBetreff":'Person '.$i,'created_at'=>'2026-10-0'.(1+$i%9).' 08:00:00','gross_total_cents'=>1000*$i,'total_cents'=>0];
$digestRegs=[['id'=>7,'name'=>'Ola Nowak','created_at'=>'2026-10-02 10:00:00']];
[$digestSubject,$digestText]=yw_with_lang('pl',fn()=>yw_reminder_digest_mail(['name'=>'Kierownik'],['slug'=>'nordbau','legal_name'=>'Nordbau GmbH'],$digestOrders,$digestRegs,3,['o125'=>true]));
$digestHtml=yw_with_lang('pl',fn()=>yw_mail_html($digestSubject,$digestText));
test_ok($digestSubject==='Przypomnienie: 26 spraw czeka na ciebie' && str_contains($digestText,'Zamówienia do zatwierdzenia: 25') && str_contains($digestText,'… i 5 kolejnych') && substr_count($digestText,'WWC-')===20 && strpos($digestText,'WWC-000125')<strpos($digestText,'WWC-000101') && str_contains($digestText,'WWC-000125: Person 25 · ') && str_contains($digestText,' · nowe'),'Sammel-Erinnerung: Sprache der Person, Anzahl, höchstens 20 Einträge (neue zuerst, markiert) und Hinweis auf weitere');
test_ok(substr_count($digestHtml,'href="https://workwearcenter.de/nordbau/freigaben"')===1 && substr_count($digestHtml,'href="https://workwearcenter.de/nordbau/mitarbeiter"')===1 && !str_contains($digestHtml,'href="https://example.org') && !str_contains($digestText,"\nBetreff"),'Sammel-Erinnerung: genau ein Link zu Freigaben und Mitarbeitern, Namen werden nie zu Links oder neuen Zeilen');
$toolsSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/admin_tools.php');
test_ok(str_contains($toolsSrc,"UPDATE employee_accounts SET reminder_digest_at=? WHERE id=? AND reminder_digest_at<=>? AND (reminder_digest_at IS NULL OR reminder_digest_at<=DATE_SUB(NOW(),INTERVAL 24 HOUR))") && str_contains($toolsSrc,'if($claim->rowCount()!==1)continue;') && !str_contains($toolsSrc,'notify_company_managers((int)$o['),'Sammel-Erinnerung wird atomar je Person vorgemerkt (parallele Läufe: höchstens eine Mail in 24 Stunden), keine Mail mehr je Bestellung');
$pruneSrc=substr($toolsSrc,strpos($toolsSrc,'function yw_retention_prune('),strpos($toolsSrc,'function yw_retention_card(')-strpos($toolsSrc,'function yw_retention_prune('));
preg_match_all('/DELETE FROM ([a-z_]+)/',$pruneSrc,$pruneTables);
test_ok(array_values(array_unique($pruneTables[1]))===['yw_mail_queue','audit_log'] && substr_count($pruneSrc,' LIMIT ".$batch')===3 && str_contains($pruneSrc,"action NOT LIKE 'lexware.%' AND action NOT LIKE 'payroll.%'") && str_contains($pruneSrc,"status='sent' AND sent_at<DATE_SUB(NOW(),INTERVAL 90 DAY)") && str_contains($pruneSrc,"status='failed' AND created_at<DATE_SUB(NOW(),INTERVAL 180 DAY)"),'Aufbewahrung löscht stapelweise nur Protokoll und Mailwarteschlange (nie Aufträge, Umtausch, Lexware; Lexware-/Lohnabzug-Protokoll bleibt)');
test_ok(yw_audit_retention_months('3')===6 && yw_audit_retention_months('')===24 && yw_audit_retention_months('-12')===24 && yw_audit_retention_months('999')===120 && yw_audit_retention_months('36')===36,'Aufbewahrungsfrist Aktivitätsprotokoll: Standard 24, mindestens 6, höchstens 120 Monate');
$cronSrc=(string)file_get_contents(dirname(__DIR__).'/cron.php');$adminSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/corporate_admin.php');
test_ok(str_contains($cronSrc,'$pruned=yw_retention_prune();') && str_contains($cronSrc,'$reminded=yw_send_reminders(3);') && (bool)preg_match("/require_admin\(\); verify_csrf\(\);\s*if\(\\\$action==='yw_retention'\)yw_retention_save_action\(\);/",$adminSrc),'Cronjob räumt auf und erinnert gesammelt; Frist ändern nur angemeldet mit CSRF-Prüfung');
// 10.15: Vier-Augen-Schutz für Verantwortliche (Firmenshop) und Missbrauchsschutz Anfrageformular
$appointOff=['id'=>1,'managers_can_appoint'=>'0'];$appointOn=['id'=>1,'managers_can_appoint'=>'1'];$actor=['id'=>1];
$otherManager=['id'=>5,'role'=>'manager','registration_status'=>'approved','active'=>'1','employment_start'=>null,'employment_end'=>null,'anonymized_at'=>null];
$staff=['role'=>'employee','id'=>6]+$otherManager;
$wanted=static fn(array $w=[]): array => $w+['role'=>'manager','registration_status'=>'approved','active'=>1,'employment_start'=>null,'employment_end'=>null];
$guardCode=static function(callable $fn): int {try{$fn();return 0;}catch(DomainException $e){return (int)$e->getCode();}};
test_ok(!yw_managers_can_appoint([]) && !yw_managers_can_appoint($appointOff) && yw_managers_can_appoint($appointOn),'Ernennen durch Verantwortliche: Standard aus (auch ohne Datenbankspalte)');
test_ok($guardCode(fn()=>yw_customer_role_guard($appointOn,$actor,$otherManager,$wanted(['role'=>'employee']),['role'=>'employee','active'=>'1']))===403,'Firmenshop: andere Verantwortliche lassen sich nicht herabstufen (auch mit Ernennungsrecht)');
test_ok($guardCode(fn()=>yw_customer_role_guard($appointOn,$actor,$otherManager,$wanted(['active'=>0]),['role'=>'manager','registration_status'=>'approved']))===403,'Firmenshop: andere Verantwortliche lassen sich nicht sperren (Haken „Aktiv“ entfernt)');
test_ok($guardCode(fn()=>yw_customer_role_guard($appointOff,$actor,$otherManager,$wanted(['registration_status'=>'rejected']),['registration_status'=>'rejected']))===403,'Firmenshop: Zugang anderer Verantwortlicher lässt sich nicht ablehnen');
test_ok($guardCode(fn()=>yw_customer_role_guard($appointOff,$actor,$otherManager,$wanted(['employment_end'=>'2020-01-01']),['employment_end'=>'2020-01-01']))===403,'Firmenshop: Austritt anderer Verantwortlicher lässt sich nicht setzen');
test_ok($guardCode(fn()=>yw_customer_role_guard($appointOn,$actor,['active'=>'0']+$otherManager,$wanted(),['role'=>'manager','active'=>'1']))===403,'Firmenshop: gesperrte Verantwortliche lassen sich nicht wieder freischalten');
$kept=yw_customer_role_guard($appointOff,$actor,$otherManager,$wanted(['role'=>'employee','active'=>0,'registration_status'=>'rejected']),['name'=>'Erika']);
test_ok($kept['role']==='manager' && $kept['active']===1 && $kept['registration_status']==='approved' && $kept['employment_end']===null,'Firmenshop: Speichern ohne Rollenfelder (gesperrte Felder) lässt Rolle, Zugang und Austritt unverändert');
test_ok($guardCode(fn()=>yw_customer_role_guard($appointOff,$actor,$staff,$wanted(),['role'=>'manager']))===403 && yw_customer_role_guard($appointOn,$actor,$staff,$wanted(),['role'=>'manager'])['role']==='manager','Firmenshop: Ernennen nur mit Firmen-Einstellung');
test_ok(yw_customer_role_guard($appointOff,$actor,['id'=>1]+$otherManager,$wanted(['role'=>'employee','active'=>0,'registration_status'=>'rejected']),['role'=>'employee'])===$wanted(),'Firmenshop: eigenes Konto bleibt Verantwortlicher, freigegeben und aktiv');
test_ok(yw_manager_change_action($staff,['role'=>'manager']+$staff)==='employee.role_granted' && yw_manager_change_action($otherManager,['role'=>'employee']+$otherManager)==='employee.role_revoked' && yw_manager_change_action($otherManager,['active'=>0]+$otherManager)==='employee.manager_deactivated' && yw_manager_change_action($otherManager,['employment_end'=>'2020-01-01']+$otherManager)==='employee.manager_deactivated' && yw_manager_change_action(['active'=>0]+$otherManager,$otherManager)==='employee.manager_reactivated' && yw_manager_change_action($staff,['active'=>0]+$staff)===null && yw_manager_change_action($otherManager,['department'=>'Lager']+$otherManager)===null,'Protokoll: Ernennen, Entziehen, Sperren und Freischalten von Verantwortlichen werden erkannt, sonstige Änderungen nicht');
$customerSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/customer_admin.php');
test_ok(str_contains($customerSrc,"if((string)\$q->fetchColumn()==='manager')throw new DomainException(t('people.err.manager_anonymize'),403);") && str_contains($customerSrc,"\$fields[5]=\$found['employment_start'];\$fields[6]=\$found['employment_end'];") && str_contains($customerSrc,"audit_log('employee.manager_change_blocked'"),'Firmenshop: Löschen und CSV-Austritt anderer Verantwortlicher gesperrt, abgewiesene Versuche protokolliert');
$decideSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/order_mail_actions.php');$exchangeSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/exchange.php');
test_ok(str_contains($decideSrc,"if(\$selfApproval && yw_other_manager_count((int)\$company['id'],(int)\$account['id'])>0)") && str_contains($decideSrc,"if(\$selfApproval)audit_log('order.four_eyes_fallback'") && str_contains($decideSrc,"\$selfBlocked=\$own && yw_other_manager_count("),'Vier-Augen-Prinzip: eigene Bestellung weder im Shop noch per Mail-Link freigebbar; Rückfall wird protokolliert');
test_ok(str_contains($exchangeSrc,"if(\$self && \$decision==='approved' && yw_other_manager_count((int)\$company['id'],(int)\$account['id'])>0)return ['error',t('x.err.four_eyes')];") && str_contains($exchangeSrc,"audit_log('exchange.four_eyes_fallback'"),'Vier-Augen-Prinzip: eigener Umtausch nicht selbst freigebbar; Rückfall wird protokolliert');
test_ok(str_contains($shopSrc,"(\$own&&\$others>0?'':'<button class=\"cp-btn approve\"") && str_contains($decideSrc,"AND registration_status='approved' AND anonymized_at IS NULL AND (employment_start IS NULL OR employment_start<=CURDATE()) AND (employment_end IS NULL OR employment_end>=CURDATE()) AND id<>?"),'Freigaben-Seite ohne Freigeben-Knopf für eigene Bestellungen; als zweite Person zählt nur, wer heute freigeben kann');
test_ok(substr_count($shopSrc,"yw_pending_decision_count((int)\$company['id'],(int)\$account['id'])")===3 && str_contains($decideSrc,"AND status='pending'\".(\$others?' AND NOT (employee_account_id<=>?)':'')") && str_contains($exchangeSrc,"\$others===0||(int)\$r['employee_account_id']!==(int)\$account['id']"),'Freigaben: eigene Bestellungen und Anträge zählen nicht als „wartet auf deine Entscheidung“, solange eine andere Person freigibt (Seite, Startseite und Zähler im Menü gleich)');
test_ok(str_contains((string)file_get_contents(dirname(__DIR__).'/app/migrations.php'),"schema_add_column(\$pdo,'companies','managers_can_appoint','TINYINT(1) NOT NULL DEFAULT 0',\$changes);"),'Firmen-Einstellung „Verantwortliche dürfen ernennen“ ist standardmäßig aus');
if(!function_exists('current_site_setting')){function current_site_setting(string $key,string $default=''): string {return $default;}}
[$confirmSubject,$confirmText]=yw_inquiry_confirmation_text(null);[$confirmSubjectProbe,$confirmTextProbe]=yw_inquiry_confirmation_text(str_repeat('ab',32));
test_ok((new ReflectionFunction('yw_inquiry_confirmation_text'))->getNumberOfParameters()===1 && str_starts_with($confirmText,"Guten Tag,\n") && $confirmSubject==='Ihre Anfrage beim WorkwearCenter' && str_contains($confirmTextProbe,'/probeshop/'.str_repeat('ab',32)),'Anfrage: Eingangsbestätigung ist fester Text ohne Name, Firma oder Nachricht der eingebenden Person');
test_ok(yw_inquiry_recipient_key('Opfer+Spam@Example.org')===yw_inquiry_recipient_key('opfer@example.org') && yw_inquiry_recipient_key('o.p.f.e.r@googlemail.com')===yw_inquiry_recipient_key('opfer@gmail.com') && yw_inquiry_recipient_key('opfer@example.org')!==yw_inquiry_recipient_key('opfer@example.com'),'Anfrage: Empfängerlimit erkennt dieselbe Mailbox trotz +Zusatz, Großschreibung und Gmail-Punkten');
$probeSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/probeshop.php');$publicSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/public.php');
test_ok(YW_INQUIRY_CONFIRM_PER_HOUR===30 && str_contains($probeSrc,'confirmation_sent_at>DATE_SUB(NOW(),INTERVAL 24 HOUR)') && str_contains($probeSrc,"confirmation_sent_at>DATE_SUB(NOW(),INTERVAL 1 HOUR)')->fetchColumn()>=YW_INQUIRY_CONFIRM_PER_HOUR") && str_contains($publicSrc,"if(\$confirmation==='ok')yw_inquiry_confirmation_mail(\$email,\$probeshopToken);") && strpos($publicSrc,'send_inquiry_mail([')<strpos($publicSrc,"if(\$confirmation==='ok')yw_inquiry_confirmation_mail("),'Anfrage: höchstens 1 Bestätigung je Adresse in 24 h und 30 je Stunde; Anfrage und interne Mail an Yanik bleiben');

// 10.15: Tempo bei großen Datenmengen – gleiche Ergebnisse mit weniger Abfragen (SQLite im Speicher statt MySQL)
$adminSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/admin.php');$handoverSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/handover.php');$migrationSrc=(string)file_get_contents(dirname(__DIR__).'/app/migrations.php');
test_ok(str_contains((string)file_get_contents(dirname(__DIR__).'/app/functions.php'),"FROM yw_mail_queue IGNORE INDEX FOR ORDER BY (PRIMARY) WHERE status='pending'") && str_contains($migrationSrc,"'idx_yw_mail_next','`status`,`id`,`available_at`'"),'Mailwarteschlange: nächste Mails über Index (status,id) statt Durchlauf aller versendeten');
test_ok(str_contains($handoverSrc,"FROM yw_handover h IGNORE INDEX FOR ORDER BY (PRIMARY) \$join WHERE h.status=\$status ORDER BY h.id DESC LIMIT 100") && !str_contains($handoverSrc,'SELECT MAX(COALESCE(acknowledged_at,delivered_at)) FROM yw_handover') && str_contains($migrationSrc,"'idx_yw_handover_next'") && str_contains($migrationSrc,"'idx_yw_handover_done'"),'Übergabeprotokoll: je Status höchstens 100 über den Index statt alle Übergaben sortieren');
test_ok(!str_contains($adminSrc,'(SELECT COUNT(*) FROM invites i WHERE i.company_id=c.id)') && str_contains($adminSrc,'COUNT(*) amount,SUM(o.created_at<?) overdue') && !str_contains((string)file_get_contents(dirname(__DIR__).'/app/modules/payroll.php'),'COALESCE(o.decided_at,o.created_at)>=?') && str_contains($migrationSrc,"'idx_yw_orders_payroll'"),'Kundenakte, Übersicht und Lohnabzug ohne Unterabfragen je Firma, ohne alle offenen Aufträge, ohne COALESCE im WHERE');
if(extension_loaded('pdo_sqlite')){
    require_once dirname(__DIR__).'/app/modules/admin.php';
    $sqlite=static fn(): PDO => new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    // Schema-Prüfung: Seitenaufrufe vergleichen nur die Marke; neue Prüfliste, fehlende Spalte und update.php wirken sofort.
    $pdo=$sqlite();$tables=[];
    foreach(yw_schema_probes() as $probe){preg_match('/^SELECT (.+) FROM (\w+) LIMIT 0$/',$probe,$pm);foreach(explode(',',$pm[1]) as $col)$tables[$pm[2]][$col]=$col;}
    unset($tables['companies']['shop_languages']);
    foreach($tables as $table=>$cols)$pdo->exec('CREATE TABLE '.$table.' ('.implode(',',$cols).')');
    $pdo->exec('CREATE TABLE yw_schema_versions (version TEXT PRIMARY KEY)');$pdo->exec('CREATE TABLE site_settings (setting_key TEXT PRIMARY KEY,setting_value TEXT NOT NULL)');$pdo->exec("INSERT INTO yw_schema_versions VALUES('10.12.0')");
    yw_schema_state(true);
    test_ok(!yw_schema_ready() && str_contains(yw_schema_problem(),'shop_languages') && yw_schema_state()['signature']==='','Schema: neue Programmdateien mit fehlender Spalte → sofort „Update erforderlich“, keine Marke');
    $pdo->exec('ALTER TABLE companies ADD COLUMN shop_languages');
    test_ok(yw_schema_ready() && yw_schema_state()['signature']===yw_schema_signature() && strlen(yw_schema_signature())===40,'Schema: direkt nach update.php bereit, Marke für diese Prüfliste gesetzt');
    $pdo->exec('ALTER TABLE yw_probeshops RENAME TO yw_probeshops_weg');
    test_ok(!yw_schema_ready(true) && yw_schema_state()['signature']==='' && (int)$pdo->query("SELECT COUNT(*) FROM site_settings WHERE setting_key='schema_checked'")->fetchColumn()===0 && !yw_schema_ready(),'Schema: update.php/Systemstatus prüfen vollständig; fehlt etwas, wird die Marke verworfen und der Shop sperrt wieder');
    $pdo->exec("INSERT INTO site_settings VALUES('schema_checked','".yw_schema_signature()."')");yw_schema_state(true);
    test_ok(yw_schema_ready(),'Schema: Seitenaufrufe vergleichen nur die Marke (eine Abfrage)');
    yw_schema_forget();
    test_ok((int)$pdo->query("SELECT COUNT(*) FROM site_settings WHERE setting_key='schema_checked'")->fetchColumn()===0 && yw_schema_state()['signature']==='' && !yw_schema_ready(true) && str_contains(yw_schema_problem(),'yw_probeshops') && str_contains((string)file_get_contents(dirname(__DIR__).'/index.php'),"in_array((string)\$e->getCode(),['42S02','42S22'],true) && function_exists('yw_schema_forget')"),'Schema: fehlende Tabelle/Spalte im laufenden Betrieb verwirft die Marke → nächster Aufruf „Update erforderlich“');
    $pdo->exec("REPLACE INTO site_settings VALUES('schema_checked','andere-pruefliste')");yw_schema_state(true);
    test_ok(!yw_schema_ready(true) && !yw_schema_ready() && str_contains(yw_schema_problem(),'yw_probeshops'),'Schema: andere Prüfliste (neue Dateien) oder zurückgespielte Sicherung → wieder volle Prüfung');
    $pdo->exec('ALTER TABLE yw_probeshops_weg RENAME TO yw_probeshops');
    test_ok(yw_schema_ready() && yw_schema_state()['signature']===yw_schema_signature(),'Schema: nach dem Update wieder bereit, Marke erneuert');
    test_ok(str_contains(yw_schema_notice(),'Datenbank-Update fehlt'),'Roter Hinweis, solange der Datenbankstand '.YW_SCHEMA_REQUIRED.' fehlt');
    $pdo->exec("INSERT INTO yw_schema_versions VALUES('".YW_SCHEMA_REQUIRED."')");yw_schema_state(true);
    test_ok(yw_schema_notice()==='','Roter Hinweis verschwindet direkt nach update.php');
    // Firmenliste/Kundenakte: gruppierte Teilabfragen = bisherige Unterabfragen je Firma (auch Firma ohne Daten)
    $pdo=$sqlite();
    $pdo->exec('CREATE TABLE companies (id INTEGER PRIMARY KEY,legal_name,shop_name,slug,active,delivery_mode,delivery_address,registration_mode,approval_required,logo_path)');
    $pdo->exec('CREATE TABLE invites (id INTEGER PRIMARY KEY,company_id INTEGER,employee_name)');$pdo->exec('CREATE TABLE company_products (company_id INTEGER,product_id INTEGER)');
    $pdo->exec('CREATE TABLE employee_accounts (id INTEGER PRIMARY KEY,company_id INTEGER,active INTEGER,registration_status,role,name,employee_number,cost_center)');
    $pdo->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY,company_id INTEGER,status,fulfillment_status,gross_total_cents INTEGER,total_cents INTEGER,employee_total_cents INTEGER,employee_account_id INTEGER,invite_id INTEGER,delivery_snapshot_json,decided_at,created_at)');
    $pdo->exec("INSERT INTO companies VALUES(1,'A GmbH','A','a',1,'pickup','',NULL,1,NULL),(2,'B KG','B','b',1,'company','',NULL,1,NULL),(3,'Leer','L','l',0,'company','Str. 1','open',0,NULL)");
    $pdo->exec("INSERT INTO invites(company_id,employee_name) VALUES(1,'X'),(1,'Y'),(2,'Z')");$pdo->exec('INSERT INTO company_products VALUES(1,5),(1,6),(2,5)');
    $pdo->exec("INSERT INTO employee_accounts(company_id,active,registration_status,role) VALUES(1,1,'approved','manager'),(1,1,'approved','employee'),(1,1,'pending','employee'),(1,0,'approved','employee'),(2,1,'approved','employee'),(2,1,'approved','manager'),(2,0,'approved','manager')");
    $pdo->exec("INSERT INTO orders(company_id,status,fulfillment_status,gross_total_cents,total_cents) VALUES(1,'approved','completed',1190,1000),(1,'approved','new',0,500),(1,'pending','new',900,900),(2,'approved','completed',0,700),(2,'cancelled','new',300,300),(1,'approved','shipped',2380,2000)");
    $old=$pdo->query("SELECT c.id,(SELECT COUNT(*) FROM invites i WHERE i.company_id=c.id) invite_count,(SELECT COUNT(*) FROM employee_accounts ea WHERE ea.company_id=c.id AND ea.active=1) registered_count,(SELECT COUNT(*) FROM orders o WHERE o.company_id=c.id AND o.status='approved') approved_order_count,(SELECT COUNT(*) FROM orders o WHERE o.company_id=c.id AND o.status='approved' AND o.fulfillment_status='completed') completed_order_count,(SELECT COALESCE(SUM(CASE WHEN o.gross_total_cents>0 THEN o.gross_total_cents ELSE o.total_cents END),0) FROM orders o WHERE o.company_id=c.id AND o.status='approved') revenue_cents FROM companies c ORDER BY c.id DESC")->fetchAll();
    $kpi=static fn(array $rows): array => array_map(static fn(array $r): array => array_map('intval',[$r['id'],$r['invite_count'],$r['registered_count'],$r['approved_order_count'],$r['completed_order_count'],$r['revenue_cents']]),$rows);
    $single=array_merge(...array_map(static fn(array $r): array => yw_admin_companies((int)$r['id']),$old));
    test_ok($kpi(yw_admin_companies())===$kpi($old) && $kpi($single)===$kpi($old) && $kpi(yw_admin_companies(0,true,2))===array_slice($kpi($old),0,2) && yw_admin_companies(9)===[] && !isset(yw_admin_companies(2,false)[0]['revenue_cents']),'Übersicht, Kundenliste und Kundenakte: Kennzahlen gleich wie mit Unterabfragen je Firma (auch leere und unbekannte Firma)');
    $steps=static fn(array $c): array => array_map(static fn(array $s): bool => $s[2],yw_company_setup_steps($c));$same=true;
    foreach(yw_admin_companies() as $c){$plain=$c;unset($plain['setup_products'],$plain['setup_managers'],$plain['setup_employees']);if($steps($c)!==$steps($plain))$same=false;}
    test_ok($same,'Einrichtungsschritte: mitgelieferte Zähler ergeben dieselben Haken wie Einzelabfragen');
    // Lohnabzug: zwei Bereiche statt COALESCE im WHERE, Übersicht in einer Abfrage – gleiche Bestellungen, Summen und Personen
    $pdo->exec("INSERT INTO employee_accounts(id,company_id,active,registration_status,role,name,employee_number,cost_center) VALUES(50,7,1,'approved','employee','Anna','100','KS1'),(51,7,1,'approved','employee','Bert','200','KS2')");$pdo->exec("INSERT INTO invites(id,company_id,employee_name) VALUES(60,7,'Gast')");
    $month0=(new DateTimeImmutable('first day of this month',new DateTimeZone('Europe/Berlin')))->setTime(0,0);$insert=$pdo->prepare("INSERT INTO orders(company_id,status,employee_total_cents,employee_account_id,invite_id,decided_at,created_at) VALUES(?,?,?,?,?,?,?)");
    mt_srand(1015);$fmt=static fn(DateTimeImmutable $d): string => $d->format('Y-m-d H:i:s');
    for($i=0;$i<600;$i++){
        $created=$month0->modify('-'.mt_rand(0,7).' month')->modify('+'.mt_rand(0,30).' day')->modify('+'.mt_rand(0,86399).' second');
        if($i%9===0)$created=$month0->modify('-'.mt_rand(0,7).' month')->modify($i%2?'-1 second':'+0 second'); // genau an der Monatsgrenze
        $decided=$i%4===0?null:($i%7===0?$fmt($month0->modify('-'.mt_rand(0,6).' month')->modify($i%2?'-1 second':'+0 second')):$fmt($created->modify('+'.mt_rand(0,20).' day')));
        $insert->execute([$i%10===0?8:7,['approved','approved','approved','pending','cancelled','rejected'][$i%6],$i%11===0?0:mt_rand(1,5000),$i%5===0?0:50+$i%2,$i%5===0?60:null,$decided,$fmt($created)]);
    }
    $all=$pdo->query('SELECT * FROM orders WHERE company_id IN (7,8) ORDER BY id')->fetchAll();
    $expectedOrders=static fn(string $month): array => array_values(array_filter($all,static function(array $o)use($month): bool {[$s,$e]=yw_payroll_range($month);$at=$o['decided_at']??$o['created_at'];return (int)$o['company_id']===7&&$o['status']==='approved'&&(int)$o['employee_total_cents']>0&&$at>=$s&&$at<$e;}));
    $same=true;$expected=[];$counted=0;
    for($i=-1;$i<8;$i++){
        $month=$month0->modify('-'.$i.' month')->format('Y-m');$want=$expectedOrders($month);$counted+=count($want);
        if(array_map('intval',array_column(yw_payroll_orders(7,$month),'id'))!==array_map('intval',array_column($want,'id')))$same=false;
        if($i>=0&&$i<6){$rows=yw_payroll_aggregate(array_map(static function(array $o): array {$o['name']=['50'=>'Anna','51'=>'Bert'][(string)$o['employee_account_id']]??'';return $o;},$want));$expected[$month]=['sum'=>array_sum(array_column($rows,'employee_cents')),'persons'=>count($rows),'orders'=>array_sum(array_map(fn($r)=>count($r['orders']),$rows))];}
    }
    test_ok($same && $counted>100,'Lohnabzug: je Monat dieselben Bestellungen wie mit COALESCE(Freigabe, Bestellung), auch genau an Monatsgrenzen ('.$counted.')');
    test_ok(yw_payroll_overview(7)===$expected && yw_payroll_overview(7,0)===[],'Lohnabzug-Übersicht: eine Abfrage für sechs Monate, gleiche Summen, Personen und Bestellungen');
    $pdo=null;
}

// 10.15: Große Kunden – Listen mit Suche/Seite/Filter; nach dem Speichern zurück an dieselbe Stelle, nie auf fremde Ziele
test_ok(yw_customer_list_state(['suche'=>' Neumann ','seite'=>'2','aseite'=>'1','produkt'=>'250','anzeige'=>'katalog','profil_anzeige'=>'boese','profil_seite'=>'01','artikel'=>['x'],'company'=>'9','tab'=>'orders','fremd'=>'1'])===['suche'=>'Neumann','seite'=>'2','anzeige'=>'katalog','produkt'=>'250'],'Kundenakte: Listenzustand nur aus bekannten, bereinigten Parametern (Seite 1, ungültige Ansicht/Seite, Arrays und fremde Schlüssel fallen weg)');
test_ok(mb_strlen(yw_customer_list_state(['artikel'=>str_repeat('ä',300)])['artikel'])===120,'Kundenakte: Suchbegriff im Listenzustand ist begrenzt');
test_ok(yw_customer_return_to('//evil.example/admin?tab=companies&company=3')===[0,'','']&&yw_customer_return_to('https://evil.example/admin?tab=companies&company=3')===[0,'','']&&yw_customer_return_to('/admin?tab=orders&company=3')===[0,'','']&&yw_customer_return_to('/admin?tab=companies&company=03')===[0,'','']&&yw_customer_return_to('/admin?tab=companies&company[]=3')===[0,'','']&&yw_customer_return_to('/admin?tab=companies&company=3&x='.str_repeat('a',2100))===[0,'',''],'Kundenakte: return_to fremder Hosts, anderer Bereiche, ungültiger Firmen-ID oder Überlänge wird verworfen');
test_ok(yw_customer_return_to('/admin?tab=companies&company=3&bereich=kollektion&artikel=Hose&aseite=2&produkt=250&evil=%3Cscript%3E&action=delete')===[3,'kollektion','/admin?tab=companies&company=3&bereich=kollektion&artikel=Hose&aseite=2&produkt=250']&&yw_customer_return_to('/admin?tab=companies&company=3&bereich=Kollektion%3C&suche=x')===[3,'uebersicht','/admin?tab=companies&company=3'],'Kundenakte: return_to wird nur aus bekannten Parametern neu aufgebaut');
$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/admin?tab=companies&company=3&bereich=regeln&profil=5&profil_seite=2';$_GET=['tab'=>'companies','company'=>'3','bereich'=>'regeln','profil'=>'5','profil_seite'=>'2','x'=>'"><script>'];
test_ok(str_contains(csrf_field(),'name="return_to" value="/admin?tab=companies&amp;company=3&amp;bereich=regeln&amp;profil=5&amp;profil_seite=2"'),'Kundenakte: Formulare merken Reiter, Profil und Seite der Liste (ohne fremde Parameter)');
$_SERVER['REQUEST_METHOD']='POST';$_POST=['return_to'=>'/admin?tab=companies&company=3&bereich=kollektion&artikel=Hose&aseite=2&produkt=250'];$_SESSION['flash']=['success','Gespeichert.'];
test_ok(yw_admin_return_target('/admin?tab=companies&company=3#produkt-250')==='/admin?tab=companies&company=3&bereich=kollektion&artikel=Hose&aseite=2&produkt=250#produkt-250','Kundenakte: nach dem Speichern zurück mit Suche, Seite und Artikel');
test_ok(yw_admin_return_target('/admin?tab=companies&company=4')==='/admin?tab=companies&company=4'&&yw_admin_return_target('/admin?tab=companies&company=3&bereich=regeln')==='/admin?tab=companies&company=3&bereich=regeln'&&yw_admin_return_target('/admin?tab=orders')==='/admin?tab=orders','Kundenakte: return_to gilt nur für dieselbe Firma und überschreibt kein ausdrückliches anderes Ziel');
$_SESSION['flash']=['error','Fehler'];
test_ok(yw_admin_return_target('/admin?tab=companies')==='/admin?tab=companies&company=3&bereich=kollektion&artikel=Hose&aseite=2&produkt=250','Kundenakte: nach einer Fehlermeldung zurück in dieselbe Liste statt auf die Kundenliste');
$_SERVER['REQUEST_URI']='/grossbau/mitarbeiter';$_POST=[];$_SESSION=[];
require_once dirname(__DIR__).'/app/modules/customer_admin.php';
$_GET=['suche'=>' Neu"mann ','seite'=>'3','jahr'=>'vorjahr','fremd'=>'x'];
test_ok(yw_people_return_url('grossbau','person-12')==='/grossbau/mitarbeiter?jahr=vorjahr&suche=Neu%22mann&seite=3#person-12','Firmenshop Mitarbeiter: nach dem Speichern zurück mit Suche, Seite und Jahr zur Person');
$_GET=['seite'=>'1e3','suche'=>['x'],'jahr'=>'2020'];
test_ok(yw_people_return_url('grossbau','x" onmouseover')==='/grossbau/mitarbeiter','Firmenshop Mitarbeiter: ungültige Seite, Suche, Jahr und Sprungmarke werden verworfen');
$_GET=[];$_SERVER['REQUEST_METHOD']='GET';$_SERVER['REQUEST_URI']='/';
$adminSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/admin.php');$corpAdminSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/corporate_admin.php');$adminCustomerSrc=(string)file_get_contents(dirname(__DIR__).'/app/modules/admin_customer.php');
test_ok(str_contains($adminSrc,"\$_POST['scope_products']")&&str_contains($corpAdminSrc,"\$_POST['scope_products']")&&str_contains($adminCustomerSrc,'name="scope_products[]"'),'Kollektion und Profilsortiment speichern nur die Artikel der angezeigten Seite (scope_products)');
test_ok(substr_count($adminCustomerSrc,'render_company_product_customization_form(')===2&&str_contains($adminCustomerSrc,'if($in && $openId===$pid){render_company_product_customization_form(')&&str_contains($adminCustomerSrc,' LIMIT $perPage OFFSET '),'Kundenakte Kollektion: seitenweise Liste, Bearbeitungsformular nur für den gewählten Artikel');
test_ok(str_contains($adminSrc,'Höchstens 500 Artikel auf einmal')&&str_contains($corpAdminSrc,"'yw_profile_bulk'"),'Sammelaktionen für Kollektion und Profile sind begrenzt');
test_ok(str_contains($customerSrc,'redirect(yw_people_return_url(')&&str_contains($regSrc,'yw_people_return_url((string)$company[\'slug\'],\'personen\')'),'Firmenshop Mitarbeiter: Speichern, Freigeben und Passwort-Link behalten Suche und Seite');
$appJs=(string)file_get_contents(dirname(__DIR__).'/assets/app.js');
test_ok(str_contains($appJs,'[data-check-all]')&&str_contains($appJs,'[data-flash]')&&str_contains($appJs,'data-unsaved-warning'),'app.js: „Alle auf dieser Seite“, Rückmeldung am Sprungziel und Hinweis auf ungespeicherte Häkchen');

// 10.15 Review: Rückmeldung am Sprungziel nicht als schmale Rasterkachel (Probeshop) und nie unter der festen Kopfzeile (#grenze-12, #budget-12)
test_ok(str_contains($appJs,'gridTemplateColumns')&&str_contains($appJs,"ps.flexDirection.indexOf('row') === 0")&&str_contains($appJs,'getComputedStyle(flash).scrollMarginTop'),'app.js: Rückmeldung über mehrspaltigen Rastern und mit Abstand zur festen Kopfzeile');
// Geöffneter Artikel: „Auswahl speichern“ klebt nicht über dem Formular, ungespeicherte Änderungen am Artikel lösen einen Hinweis aus
test_ok(str_contains($adminCustomerSrc,"collection-savebar'.(\$editorOpen?' is-static':'')")&&str_contains($adminSrc,'.collection-savebar.is-static{position:static')&&str_contains($shopSrc,"(\$closeUrl!==null?' data-unsaved-warning':'')"),'Kollektion: Speicherleiste bei geöffnetem Artikel nicht klebend, Artikelformular warnt vor ungespeicherten Änderungen');
test_ok(str_contains($corpAdminSrc,"' von '.\$companyProductCount.' Firmenprodukten freigegeben.")&&!str_contains($corpAdminSrc,"\$limits['counts']['alle'].' Firmenprodukten"),'Regeln: Profilzusammenfassung zählt alle Firmenprodukte, nicht nur die Treffer der Stückzahl-Suche');
test_ok(str_contains($adminSrc,"'yw_profile_bulk'=>'Profilsortiment per Sammelaktion geändert'"),'Aktivitätsprotokoll: Sammelaktion im Profil hat eine lesbare Bezeichnung');
test_ok(str_contains($toolsSrc,"yw_customer_state_url((int)\$o['company_id'],'kollektion',['produkt'=>(string)\$itemProduct])"),'Auftrag: Artikelname öffnet den Artikel in der Kollektion (Korrekturabzug)');

echo "\nAlle Sicherheits-Regressionstests bestanden.\n";
