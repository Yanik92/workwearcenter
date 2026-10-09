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

echo "\nAlle Sicherheits-Regressionstests bestanden.\n";
