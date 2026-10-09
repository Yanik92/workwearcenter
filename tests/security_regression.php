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

echo "\nAlle Sicherheits-Regressionstests bestanden.\n";
