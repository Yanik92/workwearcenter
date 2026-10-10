<?php
declare(strict_types=1);

/*
 * 10.11: Anbindung an Lexware Office über die offizielle Public API (https://developers.lexware.io/docs/).
 *
 * - Authentifizierung: „Authorization: Bearer <API-Schlüssel>“ (10.16: unter System → Schnittstellen
 *   verschlüsselt hinterlegt; ein Eintrag lexware_api_key in der config.php hat Vorrang; leer = aus)
 * - Basis-URL: https://api.lexware.io (config.php: lexware_api_base, z. B. für einen Testserver)
 * - Rate-Limit laut Doku: 2 Anfragen je Sekunde für alle Endpunkte zusammen, sonst HTTP 429.
 *   Wir senden höchstens eine Anfrage je 0,6 s (Puffer für Netzwerk-Jitter) und wiederholen 429
 *   mit wachsender Wartezeit.
 * - Kontakte: POST /v1/contacts (Rolle customer), GET /v1/contacts/{id}, GET /v1/contacts?name=…
 * - Rechnungen: POST /v1/invoices OHNE ?finalize=true → Entwurf (voucherStatus „draft“);
 *   GET /v1/invoices/{id} liefert Belegnummer und Status.
 * - Positionen werden mit Netto-Einzelpreis gesendet (taxType „net“); Lexware rechnet die Steuer
 *   je Steuersatz auf die Nettosumme – identisch zu pricing.php.
 * Ein Auftrag kann nur in genau einem Lexware-Beleg stehen (yw_lexware_document_orders).
 * 10.15: Lexware nimmt höchstens 300 Positionen je Rechnung an. Größere Monats-Sammelrechnungen werden automatisch
 * in mehrere Entwürfe geteilt (yw_lexware_month_plan), alle Teile vor dem Senden fest reserviert („planned“) und
 * dann einzeln gesendet; ein fehlgeschlagener Teil („failed“) wird mit denselben Aufträgen erneut gesendet.
 */

const YW_LEXWARE_DEFAULT_BASE='https://api.lexware.io';
const YW_LEXWARE_APP_URL='https://app.lexware.de';
const YW_LEXWARE_MIN_INTERVAL=0.6;
const YW_LEXWARE_MAX_LINES=300;
const YW_LEXWARE_UPDATE_HINT='Für Sammelrechnungen fehlt noch das Datenbank-Update 10.15. Bitte zuerst den Update-Assistenten (update.php) ausführen.';

final class YwLexwareException extends RuntimeException {
    /** $uncertain: Die Anfrage kann trotz Fehler in Lexware angekommen sein (Zeitüberschreitung, 504). */
    public function __construct(string $message,public readonly int $status=0,public readonly bool $uncertain=false){parent::__construct($message);}
}

/** 10.15: Senden eines Entwurfs gescheitert. $stop: betrifft vermutlich jeden weiteren Teil genauso (Schlüssel, Tarif, Erreichbarkeit, unklares Ergebnis). */
final class YwLexwareSendFailed extends DomainException {
    public function __construct(string $message,public readonly bool $uncertain=false,public readonly bool $stop=false){parent::__construct($message);}
}

/* ---------- Konfiguration und Einstellungen ---------- */

function yw_lexware_api_key(): string {
    $configured=trim((string)(cfg('lexware_api_key')??''));
    if($configured!=='' || !function_exists('yw_int_secret'))return $configured;
    return trim((string)yw_int_secret('lexware_key'));
}
function yw_lexware_enabled(): bool { return yw_lexware_api_key()!==''; }
function yw_lexware_base(): string {
    $base=trim((string)(cfg('lexware_api_base')??''));
    return rtrim($base!==''?$base:YW_LEXWARE_DEFAULT_BASE,'/');
}
/** Nur HTTPS; HTTP ausschließlich für einen lokalen Testserver. Keine Zugangsdaten/Query in der URL. */
function yw_lexware_base_ok(string $base): bool {
    $p=parse_url($base);
    if(!is_array($p) || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment']))return false;
    $scheme=strtolower((string)($p['scheme']??''));
    if($scheme==='https')return true;
    return $scheme==='http' && in_array(strtolower((string)$p['host']),['127.0.0.1','localhost','[::1]'],true);
}

function yw_lexware_settings(): array {
    $defaults=[
        'title'=>'Rechnung',
        'introduction'=>'Wir berechnen Ihnen die über das WorkwearCenter freigegebenen Bestellungen.',
        'remark'=>'',
        'unit'=>'Stück',
        'employee_names'=>'1',
        'grouping'=>'none',
    ];
    $settings=[];
    foreach($defaults as $key=>$default)$settings[$key]=current_site_setting('lexware_'.$key,$default);
    if(!in_array($settings['grouping'],['none','cost_center','split'],true))$settings['grouping']='none';
    return $settings;
}

function yw_lexware_grouping_options(): array {
    return ['none'=>'Eine Rechnung, Positionen nach Auftrag','cost_center'=>'Eine Rechnung, Zwischenüberschriften je Kostenstelle','split'=>'Je Kostenstelle eine eigene Rechnung'];
}

function yw_lexware_audit_labels(): array {
    return [
        'lexware.connection_tested'=>'Lexware: Verbindung geprüft',
        'lexware.settings_saved'=>'Lexware: Einstellungen gespeichert',
        'lexware.contact_created'=>'Lexware: Kontakt angelegt',
        'lexware.contact_linked'=>'Lexware: Kontakt verknüpft',
        'lexware.contact_unlinked'=>'Lexware: Kontaktverknüpfung gelöst',
        'lexware.invoice_planned'=>'Lexware: Sammelrechnung aufgeteilt, Aufträge zugeordnet',
        'lexware.invoice_created'=>'Lexware: Rechnungsentwurf angelegt',
        'lexware.invoice_failed'=>'Lexware: Rechnungsentwurf fehlgeschlagen',
        'lexware.invoice_uncertain'=>'Lexware: Ergebnis unklar – bitte prüfen',
        'lexware.document_refreshed'=>'Lexware: Belegstatus aktualisiert',
        'lexware.document_released'=>'Lexware: Belegverknüpfung entfernt',
        'lexware.order_removed'=>'Lexware: Auftrag aus Entwurf genommen (nicht mehr freigegeben)',
        'lexware.document_resend'=>'Lexware: Unklarer Entwurf nicht angekommen – erneut gesendet',
        'lexware.document_id_set'=>'Lexware: Beleg-ID nachgetragen',
        'lexware.error'=>'Lexware: Fehler',
        'payroll.exported'=>'Lohnabzug exportiert',
    ];
}

/* ---------- HTTP ---------- */

/** Verständliche Fehlermeldung aus HTTP-Status und Fehlerobjekt (regulär oder „IssueList“). */
function yw_lexware_error_message(int $status,mixed $json): string {
    $text=match(true){
        $status===400=>'Lexware Office hat die Anfrage als fehlerhaft abgelehnt (400).',
        $status===401=>'Lexware Office hat den API-Schlüssel nicht akzeptiert (401). Bitte lexware_api_key in der config.php prüfen oder in Lexware einen neuen Schlüssel erzeugen.',
        $status===402=>'Der Lexware-Office-Tarif erlaubt diese Funktion nicht (402).',
        $status===403=>'Keine Berechtigung für diese Aktion in Lexware Office (403).',
        $status===404=>'Der Datensatz wurde in Lexware Office nicht gefunden (404).',
        $status===406=>'Lexware Office hat die Daten nicht angenommen (406).',
        $status===409=>'Der Datensatz ist in Lexware Office in einem Zustand, der diese Aktion nicht erlaubt (409).',
        $status===415=>'Lexware Office erwartet JSON-Daten (415).',
        $status===429=>'Lexware Office begrenzt gerade die Anfragen (429). Bitte in einer Minute erneut versuchen.',
        $status===504=>'Lexware Office hat nicht rechtzeitig geantwortet (504). Der Beleg wurde möglicherweise trotzdem angelegt – bitte in Lexware prüfen.',
        $status>=500=>'Lexware Office ist gerade nicht erreichbar ('.$status.'). Bitte später erneut versuchen.',
        default=>'Unerwartete Antwort von Lexware Office ('.$status.').',
    };
    // Bei 401/403 kann die Meldung den gesendeten Schlüssel wiederholen – nie anzeigen oder speichern.
    if(!in_array($status,[400,404,406,409,415],true) || !is_array($json))return $text;
    $details=[];
    foreach((is_array($json['details']??null)?$json['details']:[]) as $d){
        if(is_array($d))$details[]=trim((string)($d['field']??'').': '.(string)($d['message']??($d['violation']??'')),': ');
    }
    foreach((is_array($json['IssueList']??null)?$json['IssueList']:[]) as $d){
        if(is_array($d))$details[]=trim((string)($d['source']??'').' ('.(string)($d['i18nKey']??($d['type']??'')).')');
    }
    if(!$details && is_string($json['message']??null))$details[]=(string)$json['message'];
    $details=array_slice(array_values(array_filter(array_map(fn($d)=>mb_substr(preg_replace('/\s+/u',' ',(string)$d),0,200),$details))),0,5);
    return $text.($details?' Details: '.implode(' · ',$details):'');
}

/** Hält den Mindestabstand zwischen zwei Anfragen ein (prozessübergreifend über die Datenbank). */
function yw_lexware_throttle(): void {
    $locked=false;
    try{
        $locked=(int)db()->query("SELECT GET_LOCK('yw_lexware_rate',10)")->fetchColumn()===1;
        $next=(float)current_site_setting('lexware_next_request','0');$now=microtime(true);
        if($next>$now)usleep((int)min(5_000_000,($next-$now)*1_000_000));
        set_site_setting('lexware_next_request',sprintf('%.3F',max(microtime(true),$next)+YW_LEXWARE_MIN_INTERVAL));
    }catch(Throwable $e){usleep((int)(YW_LEXWARE_MIN_INTERVAL*1_000_000));}
    finally{if($locked){try{db()->query("SELECT RELEASE_LOCK('yw_lexware_rate')");}catch(Throwable $ignored){}}}
}

/** Eine HTTP-Anfrage ohne Wiederholung: [Status, Rohtext, Retry-After-Sekunden]. */
function yw_lexware_transport(string $method,string $url,?string $json): array {
    $headers=['Authorization: Bearer '.yw_lexware_api_key(),'Accept: application/json'];
    if($json!==null)$headers[]='Content-Type: application/json';
    $retryAfter=0;
    if(function_exists('curl_init')){
        $ch=curl_init($url);
        curl_setopt_array($ch,[
            CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>35,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS|CURLPROTO_HTTP,
            CURLOPT_HEADERFUNCTION=>static function($ch,string $line) use (&$retryAfter): int {if(preg_match('/^Retry-After:\s*(\d+)/i',$line,$m))$retryAfter=(int)$m[1];return strlen($line);},
        ]);
        if($json!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$json);
        $raw=curl_exec($ch);$errno=curl_errno($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
        if($raw===false){
            // Verbindungsaufbau gescheitert = sicher nicht angekommen; sonst (z. B. Zeitüberschreitung) unklar.
            $notSent=in_array($errno,[6,7,35,60],true);
            throw new YwLexwareException('Lexware Office ist nicht erreichbar ('.($errno===28?'Zeitüberschreitung':mb_substr($error,0,120)).').',0,!$notSent && $method!=='GET');
        }
        return [$status,(string)$raw,$retryAfter];
    }
    $context=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$json??'','ignore_errors'=>true,'timeout'=>35,'follow_location'=>0]]);
    $raw=@file_get_contents($url,false,$context);
    $responseHeaders=$http_response_header??[];
    if($raw===false && !$responseHeaders)throw new YwLexwareException('Lexware Office ist nicht erreichbar.',0,$method!=='GET');
    $status=0;
    foreach($responseHeaders as $line){if(preg_match('#^HTTP/\S+\s+(\d{3})#',$line,$m))$status=(int)$m[1];if(preg_match('/^Retry-After:\s*(\d+)/i',$line,$m))$retryAfter=(int)$m[1];}
    return [$status,(string)$raw,$retryAfter];
}

/** Anfrage an die Lexware-API mit Rate-Limit und Wiederholung bei 429. Rückgabe: dekodiertes JSON. */
function yw_lexware_request(string $method,string $path,?array $body=null): array {
    if(!yw_lexware_enabled())throw new YwLexwareException('Lexware Office ist nicht eingerichtet: In der config.php fehlt lexware_api_key.');
    $base=yw_lexware_base();
    if(!yw_lexware_base_ok($base))throw new YwLexwareException('lexware_api_base in der config.php ist ungültig (nur HTTPS erlaubt).');
    $json=$body===null?null:json_encode($body,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    $waits=[1,2,4];
    for($attempt=0;;$attempt++){
        yw_lexware_throttle();
        [$status,$raw,$retryAfter]=yw_lexware_transport($method,$base.$path,$json);
        $decoded=json_decode($raw,true);
        if($status>=200 && $status<300)return is_array($decoded)?$decoded:[];
        // 429: laut Doku wurde der Aufruf nicht ausgeführt – gefahrlos wiederholbar.
        if($status===429 && $attempt<count($waits)){sleep(max($waits[$attempt],min(10,$retryAfter)));continue;}
        throw new YwLexwareException(yw_lexware_error_message($status,$decoded),$status,$method!=='GET' && in_array($status,[500,502,504],true));
    }
}

function yw_lexware_uuid_ok(string $id): bool { return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D',$id); }

/** Lexware-Zeitformat yyyy-MM-ddTHH:mm:ss.SSSXXX (Europe/Berlin, Tagesbeginn). */
function yw_lexware_date(DateTimeImmutable $date): string {
    return $date->setTimezone(new DateTimeZone('Europe/Berlin'))->setTime(0,0)->format('Y-m-d\TH:i:s.vP');
}

function yw_lexware_amount(int $cents): float|int { return $cents%100===0?intdiv($cents,100):round($cents/100,2); }
function yw_lexware_rate(float $rate): float|int { return abs($rate-round($rate))<0.0001?(int)round($rate):round($rate,2); }

/* ---------- Kontakte ---------- */

/** Rechnungsadresse aus der hinterlegten Lieferadresse vorschlagen (Straße, PLZ Ort). */
function yw_lexware_address_guess(array $company): array {
    $lines=array_values(array_filter(array_map('trim',preg_split('/\R/u',(string)($company['delivery_address']??''))?:[]),fn($l)=>$l!==''));
    $guess=['street'=>'','zip'=>'','city'=>'','supplement'=>''];
    foreach($lines as $i=>$line){
        if(preg_match('/^(?:D-)?(\d{5})\s+(.+)$/u',$line,$m)){
            $guess['zip']=$m[1];$guess['city']=$m[2];
            if($i>0)$guess['street']=$lines[$i-1];
            break;
        }
    }
    return $guess;
}

/** Kontakt-JSON für POST /v1/contacts (Firma mit Kundenrolle). Wirft DomainException bei ungültigen Eingaben. */
function yw_lexware_contact_payload(array $f,string $slug=''): array {
    $name=trim((string)($f['name']??''));
    if($name===''||mb_strlen($name)>190)throw new DomainException('Bitte den Firmennamen (höchstens 190 Zeichen) angeben.');
    $country=strtoupper(trim((string)($f['country']??'DE')));
    if(!preg_match('/^[A-Z]{2}(?:_[A-Z0-9]{2})?$/D',$country))throw new DomainException('Ländercode bitte als ISO-Code, z. B. DE.');
    $address=['countryCode'=>$country];
    foreach(['supplement'=>'supplement','street'=>'street','zip'=>'zip','city'=>'city'] as $key=>$field){$v=trim((string)($f[$key]??''));if($v!=='')$address[$field]=mb_substr($v,0,100);}
    $company=['name'=>$name];
    $vat=strtoupper(str_replace(' ','',trim((string)($f['vat_id']??''))));
    if($vat!==''){if(!preg_match('/^[A-Z]{2}[A-Z0-9]{2,13}$/D',$vat))throw new DomainException('USt-IdNr. ist ungültig (z. B. DE123456789).');$company['vatRegistrationId']=$vat;}
    $tax=trim((string)($f['tax_number']??''));if($tax!=='')$company['taxNumber']=mb_substr($tax,0,40);
    $email=trim((string)($f['email']??''));
    if($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL))throw new DomainException('E-Mail-Adresse für Rechnungen ist ungültig.');
    $last=trim((string)($f['contact_last']??''));
    if($last!==''){
        $person=['firstName'=>mb_substr(trim((string)($f['contact_first']??'')),0,80),'lastName'=>mb_substr($last,0,80),'primary'=>true];
        if($email!=='')$person['emailAddress']=$email;
        $company['contactPersons']=[$person];
    }
    $payload=['version'=>0,'roles'=>['customer'=>new stdClass()],'company'=>$company,'addresses'=>['billing'=>[$address]]];
    if($email!=='')$payload['emailAddresses']=['business'=>[$email]];
    $payload['note']=mb_substr('Angelegt aus dem WorkwearCenter'.($slug!==''?' (Shop /'.$slug.')':''),0,1000);
    return $payload;
}

function yw_lexware_contact_label(array $contact): string {
    if(isset($contact['company']['name']))return (string)$contact['company']['name'];
    return trim((string)($contact['person']['firstName']??'').' '.(string)($contact['person']['lastName']??''));
}

function yw_lexware_company(int $companyId): array {
    $q=db()->prepare('SELECT * FROM companies WHERE id=?');$q->execute([$companyId]);$c=$q->fetch();
    if(!$c)throw new DomainException('Firma nicht gefunden.');
    return $c;
}

function yw_lexware_store_contact(int $companyId,string $contactId,array $contact): void {
    $number=isset($contact['roles']['customer']['number'])?(string)$contact['roles']['customer']['number']:null;
    db()->prepare('UPDATE companies SET lexware_contact_id=?,lexware_customer_number=? WHERE id=?')->execute([$contactId,$number,$companyId]);
}

function yw_lexware_contact_create(int $companyId,array $form): string {
    $locked=(int)db()->query("SELECT GET_LOCK('yw_lexware_contact_".$companyId."',10)")->fetchColumn()===1;
    if(!$locked)throw new DomainException('Für diese Firma läuft bereits eine Lexware-Aktion.');
    try{
        $company=yw_lexware_company($companyId);
        if(!empty($company['lexware_contact_id']))throw new DomainException('Diese Firma ist bereits mit einem Lexware-Kontakt verknüpft.');
        $result=yw_lexware_request('POST','/v1/contacts',yw_lexware_contact_payload($form,(string)$company['slug']));
        $id=(string)($result['id']??'');
        if(!yw_lexware_uuid_ok($id))throw new YwLexwareException('Lexware hat keine gültige Kontakt-ID zurückgegeben.');
        $contact=[];
        try{$contact=yw_lexware_request('GET','/v1/contacts/'.$id);}catch(YwLexwareException $ignored){}
        yw_lexware_store_contact($companyId,$id,$contact);
        audit_log('lexware.contact_created','company',$companyId,['company_id'=>$companyId,'contact_id'=>$id,'customer_number'=>$contact['roles']['customer']['number']??null]);
        return $id;
    }finally{db()->query("SELECT RELEASE_LOCK('yw_lexware_contact_".$companyId."')");}
}

function yw_lexware_contact_link(int $companyId,string $contactId): array {
    $contactId=strtolower(trim($contactId));
    if(!yw_lexware_uuid_ok($contactId))throw new DomainException('Die Kontakt-ID muss eine Lexware-ID im Format xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx sein.');
    yw_lexware_company($companyId);
    $contact=yw_lexware_request('GET','/v1/contacts/'.$contactId);
    if(!isset($contact['roles']['customer']))throw new DomainException('Dieser Lexware-Kontakt hat keine Kundenrolle und kann keine Rechnungen erhalten.');
    if(!empty($contact['archived']))throw new DomainException('Dieser Lexware-Kontakt ist archiviert.');
    yw_lexware_store_contact($companyId,$contactId,$contact);
    audit_log('lexware.contact_linked','company',$companyId,['company_id'=>$companyId,'contact_id'=>$contactId,'customer_number'=>$contact['roles']['customer']['number']??null]);
    return $contact;
}

/** Suchtext für die Kontakt-Suche: HTML-kodiert und dann URL-kodiert (siehe Lexware-FAQ „Search string encoding“). */
function yw_lexware_search_param(string $name): string {
    return rawurlencode(str_replace(['&','<','>'],['&amp;','&lt;','&gt;'],$name));
}

function yw_lexware_contact_search(string $name): array {
    $name=trim($name);
    if(mb_strlen($name)<3)throw new DomainException('Für die Suche bitte mindestens drei Zeichen eingeben.');
    $result=yw_lexware_request('GET','/v1/contacts?customer=true&size=25&page=0&name='.yw_lexware_search_param($name));
    $out=[];
    foreach((is_array($result['content']??null)?$result['content']:[]) as $c){
        if(!is_array($c)||!yw_lexware_uuid_ok((string)($c['id']??'')))continue;
        $out[]=['id'=>(string)$c['id'],'label'=>yw_lexware_contact_label($c),'number'=>(string)($c['roles']['customer']['number']??''),'archived'=>!empty($c['archived'])];
    }
    return $out;
}

/* ---------- Rechnungen ---------- */

/** Steuersatz des Versands einer Bestellung (ab 10.11 gespeichert, sonst passend zum Bruttobetrag). */
function yw_lexware_shipping_rate(array $order,float $companyRate): float {
    if(isset($order['shipping_vat_rate']) && $order['shipping_vat_rate']!==null)return (float)$order['shipping_vat_rate'];
    $net=(int)($order['shipping_net_cents']??0);$gross=(int)($order['shipping_gross_cents']??0);
    foreach(array_unique([$companyRate,19.0,7.0,0.0]) as $rate)if(gross_from_net($net,$rate)===$gross)return $rate;
    return $companyRate;
}

/* 10.15: Schlüssel der Rechnungspositionen – gleiche Schlüssel werden zu einer Position zusammengefasst.
   Dieselben Funktionen nutzen die Positionen (yw_lexware_build_lines) und die Aufteilung (yw_lexware_month_plan). */
function yw_lexware_item_net(array $it): int { $net=(int)($it['unit_net_cents']??0);return $net>0?$net:(int)($it['unit_price_cents']??0); }
function yw_lexware_item_key(array $it): string { return 'i|'.$it['product_name'].'|'.$it['size'].'|'.yw_lexware_item_net($it).'|'.yw_rate_bp((float)($it['vat_rate']??19)); }
function yw_lexware_shipping_key(array $o): string { return 's|'.(int)($o['shipping_net_cents']??0).'|'.yw_rate_bp((float)($o['shipping_rate']??19)); }
/** Zwischenüberschrift eines Auftrags (nur bei Gruppierung nach Kostenstelle, sonst leer). */
function yw_lexware_line_group(array $o,string $grouping): string {
    if($grouping!=='cost_center')return '';
    $cc=trim((string)($o['cost_center']??''));
    return $cc!==''?'Kostenstelle: '.$cc:'Ohne Kostenstelle';
}
/** Alle Positionsschlüssel eines Auftrags inkl. Zwischenüberschrift – deren Anzahl in einer Rechnung = Lexware-Positionen. */
function yw_lexware_order_line_keys(array $o,array $items,string $grouping): array {
    $label=yw_lexware_line_group($o,$grouping);
    $keys=$label!==''?['t|'.$label=>true]:[];
    foreach($items as $it)$keys[$label."\n".yw_lexware_item_key($it)]=true;
    if((int)($o['shipping_net_cents']??0)>0)$keys[$label."\n".yw_lexware_shipping_key($o)]=true;
    return array_keys($keys);
}

/**
 * Baut die Rechnungspositionen (rein, testbar).
 * $orders: Bestellzeilen mit id, employee_name, cost_center, shipping_net_cents, shipping_rate (Prozent)
 * $itemsByOrder: order_id => order_items
 * $options: grouping ('none'|'cost_center'), employee_names (bool), unit (string)
 * Rückgabe: ['lineItems'=>API-Positionen, 'calc'=>[net_unit,qty,vat] je Preisposition]
 * Gleiche Artikel (Name, Größe, Netto-Einzelpreis, Steuersatz) werden je Gruppe zusammengefasst –
 * an der Steuer je Steuersatz ändert das nichts, hält aber Sammelrechnungen unter 300 Positionen.
 */
function yw_lexware_build_lines(array $orders,array $itemsByOrder,array $options): array {
    $grouping=(string)($options['grouping']??'none');
    $unit=trim((string)($options['unit']??'Stück'))?:'Stück';
    $withNames=!empty($options['employee_names']);
    $groups=[];
    foreach($orders as $o){
        $label=yw_lexware_line_group($o,$grouping);
        $ref=yw_order_number((int)$o['id']).($withNames && trim((string)($o['employee_name']??''))!==''?' ('.trim((string)$o['employee_name']).')':'');
        $groups[$label]['orders'][$ref]=true;
        foreach($itemsByOrder[(int)$o['id']]??[] as $it){
            $net=yw_lexware_item_net($it);
            $rate=(float)($it['vat_rate']??19);
            $key=yw_lexware_item_key($it);
            $groups[$label]['lines'][$key]??=['name'=>(string)$it['product_name'],'size'=>(string)$it['size'],'net'=>$net,'vat'=>$rate,'qty'=>0,'refs'=>[]];
            $groups[$label]['lines'][$key]['qty']+=(int)$it['quantity'];
            $groups[$label]['lines'][$key]['refs'][$ref]=true;
        }
        $shipNet=(int)($o['shipping_net_cents']??0);
        if($shipNet>0){
            $rate=(float)($o['shipping_rate']??19);
            $key=yw_lexware_shipping_key($o);
            $groups[$label]['shipping'][$key]??=['name'=>'Versand','size'=>'','net'=>$shipNet,'vat'=>$rate,'qty'=>0,'refs'=>[]];
            $groups[$label]['shipping'][$key]['qty']++;
            $groups[$label]['shipping'][$key]['refs'][$ref]=true;
        }
    }
    ksort($groups,SORT_NATURAL|SORT_FLAG_CASE);
    $lineItems=[];$calc=[];
    foreach($groups as $label=>$group){
        if($label!==''){$n=count($group['orders']);$lineItems[]=['type'=>'text','name'=>mb_substr($label,0,255),'description'=>$n.' Auftr'.($n===1?'ag':'äge')];}
        foreach(($group['lines']??[])+($group['shipping']??[]) as $key=>$line){
            $refs=array_keys($line['refs']);
            $desc=($line['size']!==''?'Größe '.$line['size'].' · ':'').(count($refs)===1?'Auftrag ':'Aufträge ').implode(', ',$refs);
            if(mb_strlen($desc)>2000)$desc=mb_substr($desc,0,1997).'…';
            $lineItems[]=['type'=>'custom','name'=>mb_substr($line['name'],0,255),'description'=>$desc,'quantity'=>$line['qty'],'unitName'=>mb_substr($unit,0,40),
                'unitPrice'=>['currency'=>'EUR','netAmount'=>yw_lexware_amount($line['net']),'taxRatePercentage'=>yw_lexware_rate($line['vat'])],'discountPercentage'=>0];
            if(str_starts_with($key,'s|'))$lineItems[array_key_last($lineItems)]['unitName']='pauschal';
            $calc[]=['net_unit'=>$line['net'],'qty'=>$line['qty'],'vat'=>$line['vat']];
        }
    }
    return ['lineItems'=>$lineItems,'calc'=>$calc];
}

/** Rechnungs-JSON für POST /v1/invoices (Entwurf, Nettopreise). */
function yw_lexware_invoice_payload(string $contactId,array $lineItems,array $meta): array {
    $settings=$meta['settings']??yw_lexware_settings();
    // 10.15: „Teil 2 von 3“ auch im Belegtitel, wenn er dann noch höchstens 25 Zeichen hat (in der Einleitung steht es immer).
    $title=trim((string)$settings['title'])?:'Rechnung';$suffix=trim((string)($meta['title_suffix']??''));
    if($suffix!=='' && mb_strlen($title.' '.$suffix)<=25)$settings['title']=$title.' '.$suffix;
    $payload=[
        'voucherDate'=>yw_lexware_date($meta['voucher_date']),
        'address'=>['contactId'=>$contactId],
        'lineItems'=>$lineItems,
        'totalPrice'=>['currency'=>'EUR'],
        'taxConditions'=>['taxType'=>'net'],
        'shippingConditions'=>$meta['shipping'],
        'title'=>mb_substr(trim((string)$settings['title'])?:'Rechnung',0,25),
        'introduction'=>mb_substr(trim(trim((string)$settings['introduction'])."\n".trim((string)($meta['introduction']??''))),0,2000),
    ];
    if(trim((string)$settings['remark'])!=='')$payload['remark']=mb_substr(trim((string)$settings['remark']),0,2000);
    return $payload;
}

/** Bestellungen samt Positionen für Rechnungen laden (nur diese Firma). */
function yw_lexware_load_orders(int $companyId,array $orderIds): array {
    $orderIds=array_values(array_unique(array_filter(array_map('intval',$orderIds))));
    if(!$orderIds)return [[],[]];
    $in=implode(',',array_fill(0,count($orderIds),'?'));
    $q=db()->prepare("SELECT o.*,COALESCE(ea.name,iv.employee_name,'') employee_name,ea.cost_center account_cost_center,c.shipping_vat_rate company_shipping_rate
        FROM orders o JOIN companies c ON c.id=o.company_id
        LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id LEFT JOIN invites iv ON iv.id=o.invite_id
        WHERE o.company_id=? AND o.id IN ($in) ORDER BY o.id");
    $q->execute(array_merge([$companyId],$orderIds));
    $orders=[];
    foreach($q->fetchAll() as $o){
        $snapshot=json_decode((string)($o['delivery_snapshot_json']??''),true);$snapshot=is_array($snapshot)?$snapshot:[];
        $o['cost_center']=trim((string)($snapshot['cost_center']??''))!==''?trim((string)$snapshot['cost_center']):trim((string)($o['account_cost_center']??''));
        if(trim((string)$o['employee_name'])==='')$o['employee_name']=(string)($snapshot['employee']??'');
        $o['shipping_rate']=yw_lexware_shipping_rate($o,(float)($o['company_shipping_rate']??19));
        $orders[]=$o;
    }
    return [$orders,order_items_grouped(array_column($orders,'id'))];
}

/** Lexware-Beleg, in dem ein Auftrag bereits steht (oder null). */
function yw_lexware_document_for_order(int $orderId): ?array {
    try{$q=db()->prepare('SELECT d.* FROM yw_lexware_document_orders l JOIN yw_lexware_documents d ON d.id=l.document_id WHERE l.order_id=?');$q->execute([$orderId]);return $q->fetch()?:null;}
    catch(Throwable $e){return null;}
}

/** 10.15: Spalten für Teilrechnungen vorhanden (Update-Assistent ausgeführt)? */
function yw_lexware_parts_ready(): bool {
    static $ready=null;
    if($ready===null){try{db()->query('SELECT part_no,part_count,line_grouping FROM yw_lexware_documents LIMIT 0');$ready=true;}catch(Throwable $e){$ready=false;}}
    return $ready;
}

/** Firma kann Rechnungsentwürfe bekommen: Lexware-Kontakt verknüpft und API-Schlüssel hinterlegt. Rückgabe: Kontakt-ID. */
function yw_lexware_check_ready(array $company): string {
    $contactId=(string)($company['lexware_contact_id']??'');
    if(!yw_lexware_uuid_ok($contactId))throw new DomainException('Diese Firma ist noch nicht mit einem Lexware-Kontakt verknüpft. Bitte zuerst unter Tagesgeschäft → Abrechnung den Kontakt anlegen oder verknüpfen.');
    if(!yw_lexware_enabled())throw new DomainException('Lexware Office ist nicht eingerichtet (lexware_api_key in der config.php).');
    return $contactId;
}

/** 10.15: Meldung für einen Auftrag, der allein mehr Positionen hat, als Lexware je Rechnung annimmt. */
function yw_lexware_too_big_message(int $orderId,int $lines): string {
    return 'Auftrag '.yw_order_number($orderId).' hat allein '.$lines.' Positionen – Lexware nimmt höchstens '.YW_LEXWARE_MAX_LINES.' je Rechnung an. Er wird nicht übertragen und bleibt offen; bitte in Lexware von Hand abrechnen.';
}

/**
 * Reserviert Rechnungsentwürfe lokal (Status „planned“) samt Auftragszuordnung – alle oder keiner, in einer Transaktion.
 * Ab hier steht jeder Auftrag in genau einem Entwurf (Primärschlüssel order_id), auch wenn das Senden später scheitert.
 * $docs: Liste von ['ids'=>Auftrags-IDs,'kind'=>'order'|'monthly','period','label','part','of','grouping'=>'none'|'cost_center']
 * Rückgabe: Beleg-IDs in derselben Reihenfolge.
 */
function yw_lexware_reserve(int $companyId,array $docs): array {
    $all=[];foreach($docs as $d)foreach($d['ids'] as $id)$all[]=(int)$id;
    if(!$all)throw new DomainException('Keine Aufträge ausgewählt.');
    if(count($all)!==count(array_unique($all)))throw new DomainException('Ein Auftrag darf nur in einem Entwurf stehen. Keine Doppelanlage.');
    $parts=yw_lexware_parts_ready();
    foreach($docs as $d)if(($d['kind']??'')!=='order' && !$parts)throw new DomainException(YW_LEXWARE_UPDATE_HINT);
    sort($all);
    $settings=yw_lexware_settings();$docIds=[];
    try{
        db()->beginTransaction();
        $in=implode(',',array_fill(0,count($all),'?'));
        $q=db()->prepare("SELECT id,status FROM orders WHERE company_id=? AND id IN ($in) FOR UPDATE");$q->execute(array_merge([$companyId],$all));
        $rows=$q->fetchAll();
        if(count($rows)!==count($all))throw new DomainException('Mindestens ein Auftrag gehört nicht zu dieser Firma.');
        foreach($rows as $r)if($r['status']!=='approved')throw new DomainException('Nur freigegebene Aufträge können abgerechnet werden ('.yw_order_number((int)$r['id']).' ist nicht freigegeben).');
        $q=db()->prepare("SELECT order_id FROM yw_lexware_document_orders WHERE order_id IN ($in) FOR UPDATE");$q->execute($all);
        if($taken=$q->fetchAll(PDO::FETCH_COLUMN))throw new DomainException('Bereits in einem Lexware-Beleg: '.implode(', ',array_map(fn($id)=>yw_order_number((int)$id),array_slice($taken,0,10))).(count($taken)>10?' und '.(count($taken)-10).' weitere':'').'. Keine Doppelanlage.');
        [$orders,$items]=yw_lexware_load_orders($companyId,$all);
        $byId=[];foreach($orders as $o)$byId[(int)$o['id']]=$o;
        foreach($docs as $d){
            $orderIds=array_map('intval',$d['ids']);sort($orderIds);
            $kind=(string)$d['kind'];$label=(string)($d['label']??'');$part=(int)($d['part']??0);$of=(int)($d['of']??0);$period=$d['period']??null;
            $grouping=($d['grouping']??'none')==='cost_center'?'cost_center':'none';
            $built=yw_lexware_build_lines(array_map(fn($id)=>$byId[$id],$orderIds),$items,['grouping'=>$grouping,'employee_names'=>$settings['employee_names']==='1','unit'=>$settings['unit']]);
            if(!$built['calc'])throw new DomainException('Keine abrechenbaren Positionen.');
            if(count($built['lineItems'])>YW_LEXWARE_MAX_LINES)throw new DomainException(count($orderIds)===1?yw_lexware_too_big_message($orderIds[0],count($built['lineItems'])):'Der Entwurf hätte '.count($built['lineItems']).' Positionen; Lexware nimmt höchstens '.YW_LEXWARE_MAX_LINES.' an. Bitte die Vorschau neu laden.');
            $expected=yw_invoice_totals($built['calc']);
            $dedupe=$kind==='order'?'order:'.$orderIds[0]:'monthly:'.$companyId.':'.$period.':'.substr(hash('sha256',$label.'|'.$part.'/'.$of.'|'.implode(',',$orderIds)),0,40);
            $cols='dedupe_key,company_id,kind,order_id,period,group_label,status,net_cents,gross_cents,line_count,created_by';
            $vals=[$dedupe,$companyId,$kind,$kind==='order'?$orderIds[0]:null,$period,$label!==''?mb_substr($label,0,190):null,'planned',$expected['net'],$expected['gross'],count($built['lineItems']),!empty($_SESSION['admin_id'])?(int)$_SESSION['admin_id']:null];
            if($parts){$cols.=',part_no,part_count,line_grouping';array_push($vals,$part>0?$part:null,$of>0?$of:null,$grouping);}
            db()->prepare("INSERT INTO yw_lexware_documents($cols) VALUES(".implode(',',array_fill(0,count($vals),'?')).")")->execute($vals);
            $docId=(int)db()->lastInsertId();$docIds[]=$docId;
            foreach(array_chunk($orderIds,500) as $chunk)db()->prepare('INSERT INTO yw_lexware_document_orders(order_id,document_id) VALUES'.implode(',',array_fill(0,count($chunk),'(?,?)')))->execute(array_merge(...array_map(fn($id)=>[$id,$docId],$chunk)));
        }
        db()->commit();
    }catch(PDOException $e){
        if(db()->inTransaction())db()->rollBack();
        if((string)$e->getCode()==='23000')throw new DomainException('Für diese Aufträge wird gerade bereits ein Lexware-Beleg angelegt. Keine Doppelanlage.');
        throw $e;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    return $docIds;
}

/**
 * Sendet einen reservierten Entwurf („planned“ oder „failed“) an Lexware. Reihenfolge schützt vor Doppelanlage:
 * 1. nur eine Anfrage kann den Beleg auf „creating“ setzen, 2. Lexware aufrufen, 3. Ergebnis speichern.
 * Unklares Ergebnis (Zeitüberschreitung) → „uncertain“, bis jemand in Lexware nachsieht. Sicherer Fehler →
 * „failed“: Die Aufträge bleiben diesem Entwurf zugeordnet und er kann erneut gesendet werden (10.15, Teilrechnungen);
 * mit $release (Einzelauftrag) wird die Reservierung wie bisher zurückgenommen.
 */
function yw_lexware_send_document(int $docId,array $meta=[],bool $release=false): array {
    $q=db()->prepare('SELECT * FROM yw_lexware_documents WHERE id=?');$q->execute([$docId]);$doc=$q->fetch();
    if(!$doc)throw new DomainException('Beleg nicht gefunden.');
    $companyId=(int)$doc['company_id'];$kind=(string)$doc['kind'];$period=$doc['period']!==null?(string)$doc['period']:null;
    $company=yw_lexware_company($companyId);
    $contactId=yw_lexware_check_ready($company);
    $claim=db()->prepare("UPDATE yw_lexware_documents SET status='creating',last_error=NULL WHERE id=? AND status IN ('planned','failed')");$claim->execute([$docId]);
    if(!$claim->rowCount())throw new DomainException('Dieser Entwurf wird bereits gesendet oder ist schon in Lexware angelegt. Keine Doppelanlage.');
    $orderIds=[];$expected=null;
    try{
        $q=db()->prepare('SELECT order_id FROM yw_lexware_document_orders WHERE document_id=? ORDER BY order_id');$q->execute([$docId]);$orderIds=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
        [$orders,$items]=yw_lexware_load_orders($companyId,$orderIds);
        // Inzwischen stornierte Aufträge nicht berechnen: aus dem Entwurf lösen (nur bei geplanten/fehlgeschlagenen Teilen möglich).
        $dropped=array_map(fn($o)=>(int)$o['id'],array_filter($orders,fn($o)=>$o['status']!=='approved'));
        if($dropped){
            db()->prepare('DELETE FROM yw_lexware_document_orders WHERE document_id=? AND order_id IN ('.implode(',',array_fill(0,count($dropped),'?')).')')->execute(array_merge([$docId],$dropped));
            foreach($dropped as $id)audit_log('lexware.order_removed','order',$id,['company_id'=>$companyId,'document_id'=>$docId,'period'=>$period]);
            $orders=array_values(array_filter($orders,fn($o)=>$o['status']==='approved'));$orderIds=array_map(fn($o)=>(int)$o['id'],$orders);
        }
        if(!$orders){
            db()->prepare('DELETE FROM yw_lexware_document_orders WHERE document_id=?')->execute([$docId]);
            db()->prepare('DELETE FROM yw_lexware_documents WHERE id=?')->execute([$docId]);
            throw new DomainException('Keine freigegebenen Aufträge mehr in diesem Entwurf – er wurde entfernt.');
        }
        $settings=yw_lexware_settings();
        $grouping=($doc['line_grouping']??($meta['grouping']??'none'))==='cost_center'?'cost_center':'none';
        $built=yw_lexware_build_lines($orders,$items,['grouping'=>$grouping,'employee_names'=>$settings['employee_names']==='1','unit'=>$settings['unit']]);
        if(!$built['calc'])throw new DomainException('Keine abrechenbaren Positionen.');
        if(count($built['lineItems'])>YW_LEXWARE_MAX_LINES)throw new DomainException(count($orders)===1?yw_lexware_too_big_message($orderIds[0],count($built['lineItems'])):'Der Entwurf hätte '.count($built['lineItems']).' Positionen; Lexware nimmt höchstens '.YW_LEXWARE_MAX_LINES.' an.');
        $expected=yw_invoice_totals($built['calc']);
        db()->prepare('UPDATE yw_lexware_documents SET net_cents=?,gross_cents=?,line_count=? WHERE id=?')->execute([$expected['net'],$expected['gross'],count($built['lineItems']),$docId]);
        if(!$meta)$meta=$kind==='order'?yw_lexware_order_meta($orders[0],$company):yw_lexware_month_meta($company,(string)$period,count($orders),(string)($doc['group_label']??''),$grouping,(int)($doc['part_no']??0),(int)($doc['part_count']??0));
        $payload=yw_lexware_invoice_payload($contactId,$built['lineItems'],$meta+['settings'=>$settings]);
        $result=yw_lexware_request('POST','/v1/invoices',$payload);
        $lexId=(string)($result['id']??'');
        if(!yw_lexware_uuid_ok($lexId))throw new YwLexwareException('Lexware hat keine gültige Beleg-ID zurückgegeben. Bitte in Lexware prüfen.',0,true);
    }catch(Throwable $e){
        $uncertain=$e instanceof YwLexwareException && $e->uncertain;
        $message=$e instanceof YwLexwareException||$e instanceof DomainException?$e->getMessage():'Technischer Fehler beim Senden an Lexware.';
        if(!$e instanceof YwLexwareException && !$e instanceof DomainException)error_log('WorkwearCenter Lexware: '.$e->getMessage());
        // Nur Fehler im Inhalt dieses Belegs betreffen die übrigen Teile nicht; Schlüssel, Tarif, Erreichbarkeit schon.
        $stop=$uncertain || ($e instanceof YwLexwareException && !in_array($e->status,[400,404,406,409,415,422],true)) || (!$e instanceof YwLexwareException && !$e instanceof DomainException);
        if($uncertain){
            db()->prepare("UPDATE yw_lexware_documents SET status='uncertain',last_error=? WHERE id=?")->execute([mb_substr($message,0,500),$docId]);
        }elseif($release){
            db()->prepare('DELETE FROM yw_lexware_document_orders WHERE document_id=?')->execute([$docId]);
            db()->prepare('DELETE FROM yw_lexware_documents WHERE id=?')->execute([$docId]);
        }else{
            db()->prepare("UPDATE yw_lexware_documents SET status='failed',last_error=? WHERE id=? AND status='creating'")->execute([mb_substr($message,0,500),$docId]);
        }
        foreach($orderIds as $id)audit_log($uncertain?'lexware.invoice_uncertain':'lexware.invoice_failed','order',(int)$id,['company_id'=>$companyId,'kind'=>$kind,'period'=>$period,'document_id'=>$docId,'error'=>mb_substr($message,0,300)]);
        throw new YwLexwareSendFailed($message.($uncertain?($kind==='order'?' Der Auftrag bleibt':' Die Aufträge dieses Entwurfs bleiben').' gesperrt, bis du unter Tagesgeschäft → Abrechnung die Beleg-ID nachträgst oder die Sperre aufhebst.':''),$uncertain,$stop);
    }
    db()->prepare("UPDATE yw_lexware_documents SET status='created',lexware_id=?,voucher_status='draft',last_error=NULL WHERE id=?")->execute([$lexId,$docId]);
    $doc=yw_lexware_refresh_document($docId,false);
    foreach($orderIds as $id)audit_log('lexware.invoice_created','order',(int)$id,['company_id'=>$companyId,'document_id'=>$docId,'lexware_id'=>$lexId,'kind'=>$kind,'period'=>$period,'voucher_number'=>$doc['voucher_number']??null]);
    return $doc+['expected'=>$expected,'orders'=>count($orderIds)];
}

/** Rechnungsentwurf für feste Aufträge sofort anlegen (Einzelauftrag): reservieren, senden; bei sicherem Fehler Reservierung zurücknehmen. */
function yw_lexware_create_invoice(int $companyId,array $orderIds,string $kind,?string $period,string $groupLabel,array $meta): array {
    yw_lexware_check_ready(yw_lexware_company($companyId));
    [$docId]=yw_lexware_reserve($companyId,[['ids'=>$orderIds,'kind'=>$kind,'period'=>$period,'label'=>$groupLabel,'grouping'=>$meta['grouping']??'none']]);
    return yw_lexware_send_document($docId,$meta,true);
}

/**
 * 10.15: Sendet reservierte Entwürfe nacheinander. Ein fehlgeschlagener Teil hält die übrigen nicht auf (er bleibt
 * „fehlgeschlagen“ und wird später erneut gesendet); bei Fehlern, die alle Teile betreffen, und nach $budget Sekunden
 * wird abgebrochen – die übrigen bleiben „geplant“. Angelegte Teile werden nie erneut gesendet.
 * Rückgabe: ['created'=>[Belege],'failed'=>[Bezeichnung=>Meldung],'pending'=>Anzahl nicht gesendeter Entwürfe,'stopped'=>abgebrochen statt Zeitbudget]
 */
function yw_lexware_send_documents(array $docIds,float $budget=20.0): array {
    if(function_exists('set_time_limit'))@set_time_limit(180);
    if(function_exists('ignore_user_abort'))ignore_user_abort(true); // Abbruch im Browser darf keinen Teil halb gesendet zurücklassen
    $start=microtime(true);$out=['created'=>[],'failed'=>[],'pending'=>0,'stopped'=>false];$stop=false;
    foreach($docIds as $docId){
        if($stop || microtime(true)-$start>$budget){$out['pending']++;continue;}
        $q=db()->prepare('SELECT * FROM yw_lexware_documents WHERE id=?');$q->execute([(int)$docId]);$title=($d=$q->fetch())?yw_lexware_doc_title($d):'Beleg '.(int)$docId;
        try{
            $doc=yw_lexware_send_document((int)$docId);$out['created'][]=$doc;
            audit_log('lexware.invoice_created','company',(int)$doc['company_id'],['company_id'=>(int)$doc['company_id'],'document_id'=>(int)$doc['id'],'period'=>$doc['period'],'part'=>(int)($doc['part_count']??0)>1?$doc['part_no'].'/'.$doc['part_count']:null,'orders'=>(int)$doc['orders'],'voucher_number'=>$doc['voucher_number']??null]);
        }catch(YwLexwareSendFailed $e){$out['failed'][$title]=$e->getMessage();$stop=$e->stop;}
        catch(DomainException $e){$out['failed'][$title]=$e->getMessage();$stop=true;}
    }
    $out['stopped']=$stop;
    return $out;
}

/** Belegnummer und Status aus Lexware nachladen (Belegnummer vergibt Lexware spätestens beim Finalisieren). */
function yw_lexware_refresh_document(int $docId,bool $strict=true): array {
    $q=db()->prepare('SELECT * FROM yw_lexware_documents WHERE id=?');$q->execute([$docId]);$doc=$q->fetch();
    if(!$doc)throw new DomainException('Beleg nicht gefunden.');
    if(!yw_lexware_uuid_ok((string)($doc['lexware_id']??'')))throw new DomainException('Für diesen Beleg ist keine Lexware-ID gespeichert.');
    try{$invoice=yw_lexware_request('GET','/v1/invoices/'.$doc['lexware_id']);}
    catch(YwLexwareException $e){if($strict)throw $e;return $doc;}
    $number=isset($invoice['voucherNumber'])&&$invoice['voucherNumber']!==null?mb_substr((string)$invoice['voucherNumber'],0,40):null;
    $status=isset($invoice['voucherStatus'])?mb_substr((string)$invoice['voucherStatus'],0,20):null;
    $gross=isset($invoice['totalPrice']['totalGrossAmount'])?(int)round((float)$invoice['totalPrice']['totalGrossAmount']*100):null;
    db()->prepare('UPDATE yw_lexware_documents SET voucher_number=?,voucher_status=?,last_error=? WHERE id=?')->execute([$number,$status,$gross!==null&&$gross!==(int)$doc['gross_cents']?'Lexware-Summe '.money($gross).' weicht von der erwarteten Summe '.money((int)$doc['gross_cents']).' ab.':null,$docId]);
    $doc['voucher_number']=$number;$doc['voucher_status']=$status;
    return $doc;
}

/* ---------- Monats-Sammelrechnung ---------- */

/** Freigegebene Aufträge einer Firma, deren Freigabe im Monat liegt: [abrechenbar, bereits in Belegen]. */
function yw_lexware_month_orders(int $companyId,string $month): array {
    [$start,$end]=yw_payroll_range($month);
    $q=db()->prepare("SELECT o.id,l.document_id FROM orders o LEFT JOIN yw_lexware_document_orders l ON l.order_id=o.id
        WHERE o.company_id=? AND o.status='approved' AND COALESCE(o.decided_at,o.created_at)>=? AND COALESCE(o.decided_at,o.created_at)<? ORDER BY o.id");
    $q->execute([$companyId,$start,$end]);
    $open=[];$billed=[];
    foreach($q->fetchAll() as $r){if($r['document_id']===null)$open[]=(int)$r['id'];else $billed[(int)$r['id']]=(int)$r['document_id'];}
    return [$open,$billed];
}

/** Aufteilung je Rechnung: Gruppenname => Auftrags-IDs (nur bei „je Kostenstelle eine Rechnung“ mehrere). */
function yw_lexware_month_batches(array $orders,string $grouping): array {
    if($grouping!=='split')return [''=>array_map(fn($o)=>(int)$o['id'],$orders)];
    $batches=[];
    foreach($orders as $o)$batches[trim((string)$o['cost_center'])!==''?'Kostenstelle: '.trim((string)$o['cost_center']):'Ohne Kostenstelle'][]=(int)$o['id'];
    ksort($batches,SORT_NATURAL|SORT_FLAG_CASE);
    return $batches;
}

/** 10.15: Aufträge je Kostenstelle in Rechnungsreihenfolge (natürlich sortiert, ohne Kostenstelle zuletzt): [['center','ids']]. */
function yw_lexware_cost_center_groups(array $orders): array {
    $by=[];foreach($orders as $o)$by['#'.trim((string)($o['cost_center']??''))][]=(int)$o['id'];
    uksort($by,fn($a,$b)=>(($a==='#')<=>($b==='#'))?:strnatcasecmp((string)$a,(string)$b));
    $out=[];foreach($by as $k=>$ids)$out[]=['center'=>substr((string)$k,1),'ids'=>$ids];
    return $out;
}

/** 10.15: Kostenstellen eines Teils als Text, z. B. „Kostenstellen: KST-1000 bis KST-1007“. */
function yw_lexware_centers_label(array $centers): string {
    $named=array_values(array_filter(array_map('strval',$centers),fn($c)=>$c!==''));$none=count($named)<count($centers);
    if(!$named)return 'Ohne Kostenstelle';
    $text=count($named)===1?'Kostenstelle: '.$named[0]:'Kostenstellen: '.(count($named)<=3?implode(', ',$named):$named[0].' bis '.$named[count($named)-1]);
    return mb_substr($text.($none?' und ohne Kostenstelle':''),0,190);
}

/**
 * 10.15: Plant die Rechnungsentwürfe einer Monats-Sammelrechnung (rein, testbar).
 * Lexware nimmt höchstens $max Positionen je Rechnung an. Was nicht in eine Rechnung passt, wird automatisch geteilt:
 * zuerst nach ganzen Kostenstellen (der Reihe nach aufgefüllt), eine allein zu große Kostenstelle nach Aufträgen
 * (aufsteigende Auftragsnummer). Die Positionen eines Auftrags stehen immer in genau einem Entwurf; ein Auftrag,
 * der allein mehr als $max Positionen hat, wird nicht verplant und unter too_big gemeldet.
 * Rückgabe: ['parts'=>[['label','part','of','ids','lines']],'too_big'=>[Auftrags-ID=>Positionen],'grouping'=>'none'|'cost_center']
 * part/of: „Teil 2 von 3“ (0/0 = ungeteilt).
 */
function yw_lexware_month_plan(array $orders,array $itemsByOrder,string $grouping,int $max=YW_LEXWARE_MAX_LINES): array {
    $lineGrouping=$grouping==='cost_center'?'cost_center':'none';
    $keys=[];$tooBig=[];$fit=[];
    foreach($orders as $o){
        $id=(int)$o['id'];$k=yw_lexware_order_line_keys($o,$itemsByOrder[$id]??[],$lineGrouping);
        if(count($k)>$max){$tooBig[$id]=count($k);continue;}
        $keys[$id]=array_fill_keys($k,true);$fit[]=$o;
    }
    $unit=static function(array $ids,string $center) use ($keys): array {$set=[];foreach($ids as $id)$set+=$keys[$id];return ['ids'=>$ids,'keys'=>$set,'centers'=>[$center]];};
    $parts=[];
    foreach(yw_lexware_month_batches($fit,$grouping) as $label=>$batchIds){
        $in=array_flip($batchIds);$groups=yw_lexware_cost_center_groups(array_filter($fit,fn($o)=>isset($in[(int)$o['id']])));
        // Ganze Kostenstellen, wo sie hineinpassen; sonst deren Aufträge einzeln.
        $units=[];
        foreach($groups as $g){$u=$unit($g['ids'],$g['center']);if(count($u['keys'])<=$max){$units[]=$u;continue;}foreach($g['ids'] as $id)$units[]=$unit([$id],$g['center']);}
        // Der Reihe nach auffüllen, bis die nächste Einheit nicht mehr passt (gleiche Artikel zählen je Rechnung nur einmal).
        $packed=[];$cur=null;
        foreach($units as $u){
            if($cur!==null){$merged=$cur['keys']+$u['keys'];if(count($merged)<=$max){$cur=['ids'=>array_merge($cur['ids'],$u['ids']),'keys'=>$merged,'centers'=>array_merge($cur['centers'],$u['centers'])];continue;}$packed[]=$cur;}
            $cur=$u;
        }
        if($cur!==null)$packed[]=$cur;
        $n=count($packed);
        foreach($packed as $i=>$p){
            $ids=$p['ids'];sort($ids);
            $parts[]=['label'=>(string)$label!==''?(string)$label:($n>1 && count($groups)>1?yw_lexware_centers_label(array_values(array_unique($p['centers']))):''),'part'=>$n>1?$i+1:0,'of'=>$n>1?$n:0,'ids'=>$ids,'lines'=>count($p['keys'])];
        }
    }
    return ['parts'=>$parts,'too_big'=>$tooBig,'grouping'=>$lineGrouping];
}

/** 10.15: Prüfsumme der Vorschau – angelegt wird nur, was der Admin so gesehen hat. */
function yw_lexware_plan_hash(array $plan,string $month,string $grouping): string {
    return hash('sha256',json_encode([$month,$grouping,array_map(fn($p)=>[$p['label'],$p['part'],$p['of'],$p['ids']],$plan['parts']),array_keys($plan['too_big'])],JSON_UNESCAPED_UNICODE));
}

function yw_lexware_month_meta(array $company,string $month,int $count,string $label,string $grouping,int $part=0,int $of=0): array {
    $start=new DateTimeImmutable($month.'-01',new DateTimeZone('Europe/Berlin'));
    $partText=$part>0 && $of>1?'Teil '.$part.' von '.$of:'';
    return [
        'voucher_date'=>new DateTimeImmutable('today',new DateTimeZone('Europe/Berlin')),
        'shipping'=>['shippingType'=>'deliveryperiod','shippingDate'=>yw_lexware_date($start),'shippingEndDate'=>yw_lexware_date($start->modify('last day of this month'))],
        'introduction'=>'Sammelrechnung '.yw_payroll_month_label($month).($partText!==''?' – '.$partText:'').': '.$count.' Auftr'.($count===1?'ag':'äge').' aus dem WorkwearCenter-Shop „'.$company['shop_name'].'“'.($label!==''?' – '.$label:'').'.',
        'grouping'=>$grouping==='cost_center'?'cost_center':'none',
        'title_suffix'=>$partText!==''?'('.$partText.')':'',
    ];
}

function yw_lexware_order_meta(array $order,array $company): array {
    $snapshot=json_decode((string)($order['delivery_snapshot_json']??''),true);$snapshot=is_array($snapshot)?$snapshot:[];
    $parts=['Bestellung '.yw_order_number((int)$order['id']).' aus dem WorkwearCenter-Shop „'.$company['shop_name'].'“'];
    if(trim((string)($snapshot['cost_center']??''))!=='')$parts[]='Kostenstelle: '.trim((string)$snapshot['cost_center']);
    if(yw_lexware_settings()['employee_names']==='1' && trim((string)($snapshot['employee']??''))!=='')$parts[]='Besteller: '.trim((string)$snapshot['employee']);
    $approved=(string)($order['decided_at']?:$order['created_at']);
    $parts[]='freigegeben am '.date('d.m.Y',(int)strtotime($approved));
    $today=new DateTimeImmutable('today',new DateTimeZone('Europe/Berlin'));
    return ['voucher_date'=>$today,'shipping'=>['shippingType'=>'delivery','shippingDate'=>yw_lexware_date($today)],'introduction'=>implode(' · ',$parts).'.','grouping'=>'none'];
}

/* ---------- Oberfläche ---------- */

function yw_lexware_doc_badge(array $doc): string {
    $status=(string)$doc['status'];
    if($status==='creating' && strtotime((string)$doc['updated_at'])<time()-300)$status='uncertain';
    [$label,$class]=match($status){
        'created'=>[match((string)($doc['voucher_status']??'draft')){'draft'=>'Entwurf in Lexware','open'=>'Offen (finalisiert)','paid'=>'Bezahlt','voided'=>'Storniert in Lexware',default=>'In Lexware'},'approved'],
        'uncertain'=>['Unklar – bitte in Lexware prüfen','rejected'],
        'planned'=>['Noch nicht gesendet','pending'],
        'failed'=>['Fehlgeschlagen – erneut senden','rejected'],
        default=>['Wird angelegt …','pending'],
    };
    return '<span class="badge '.$class.'">'.e($label).'</span>';
}

/** 10.15: Bezeichnung eines Belegs, z. B. „Sammelrechnung Oktober 2025 · Teil 2 von 4 · Kostenstellen: KST-1005 bis KST-1009“. */
function yw_lexware_doc_title(array $doc): string {
    if(($doc['kind']??'')==='order')return 'Auftrag '.yw_order_number((int)$doc['order_id']);
    $parts=['Sammelrechnung '.yw_payroll_month_label((string)$doc['period'])];
    if((int)($doc['part_count']??0)>1)$parts[]='Teil '.(int)$doc['part_no'].' von '.(int)$doc['part_count'];
    if(trim((string)($doc['group_label']??''))!=='')$parts[]=(string)$doc['group_label'];
    return implode(' · ',$parts);
}

function yw_lexware_doc_link(array $doc): string {
    if(!yw_lexware_uuid_ok((string)($doc['lexware_id']??'')))return '';
    return '<a href="'.e(YW_LEXWARE_APP_URL.'/permalink/invoices/edit/'.$doc['lexware_id']).'" target="_blank" rel="noopener noreferrer">In Lexware öffnen</a>';
}

function yw_lexware_doc_actions(array $doc,string $return): string {
    $id=(int)$doc['id'];$hidden=csrf_field().'<input type="hidden" name="document_id" value="'.$id.'"><input type="hidden" name="return" value="'.e($return).'">';
    $html='';
    if(yw_lexware_uuid_ok((string)($doc['lexware_id']??'')))$html.='<form method="post" class="inline-form">'.$hidden.'<input type="hidden" name="action" value="lexware_document_refresh"><button class="btn small">Status abrufen</button></form> ';
    $stale=$doc['status']==='uncertain' || ($doc['status']==='creating' && strtotime((string)$doc['updated_at'])<time()-300);
    if($stale){
        $html.='<details class="details"><summary>Klären</summary><div class="details-inner"><p class="muted">Schau in Lexware Office unter Rechnungen nach, ob der Entwurf angekommen ist.</p>'
            .'<form method="post" class="form-stack">'.$hidden.'<input type="hidden" name="action" value="lexware_document_set_id"><label>Lexware-Beleg-ID (aus der Adresszeile des Belegs)<input name="lexware_id" pattern="[0-9a-fA-F\-]{36}" required></label><button class="btn small primary">Ist angekommen – ID speichern</button></form>'
            .($doc['kind']!=='order'?'<form method="post" data-confirm="Wirklich erneut senden? Nur wenn der Beleg in Lexware NICHT existiert.">'.$hidden.'<input type="hidden" name="action" value="lexware_document_resend"><button class="btn small primary">Ist nicht angekommen – erneut senden</button></form>':'')
            .'<form method="post" data-confirm="Sperre wirklich aufheben? Nur wenn der Beleg in Lexware NICHT existiert.">'.$hidden.'<input type="hidden" name="action" value="lexware_document_release"><button class="btn small danger">Ist nicht angekommen – Sperre aufheben</button></form></div></details>';
    }elseif(in_array($doc['status'],['planned','failed'],true)){
        // 10.15: Nichts in Lexware angelegt – erneut senden (gleiche Aufträge, gleicher Teil) oder verwerfen.
        $html.='<form method="post" class="inline-form">'.$hidden.'<input type="hidden" name="action" value="lexware_document_send"><button class="btn small primary">'.($doc['status']==='failed'?'Erneut senden':'Jetzt senden').'</button></form>'
            .'<details class="details"><summary>Verwerfen</summary><div class="details-inner"><p class="muted">In Lexware wurde für diesen Entwurf nichts angelegt. Verworfene Aufträge gelten wieder als nicht abgerechnet und erscheinen in der nächsten Vorschau.</p><form method="post" data-confirm="Entwurf wirklich verwerfen?">'.$hidden.'<input type="hidden" name="action" value="lexware_document_release"><button class="btn small danger">Entwurf verwerfen</button></form></div></details>';
    }elseif($doc['status']==='created'){
        $html.='<details class="details"><summary>Verknüpfung entfernen</summary><div class="details-inner"><p class="muted">Nur wenn der Entwurf in Lexware gelöscht wurde. Danach können die Aufträge erneut abgerechnet werden.</p><form method="post" data-confirm="Verknüpfung wirklich entfernen? Die Aufträge gelten dann als nicht abgerechnet.">'.$hidden.'<input type="hidden" name="action" value="lexware_document_release"><button class="btn small danger">Verknüpfung entfernen</button></form></div></details>';
    }
    return $html;
}

/** Karte auf der Auftragsseite der Verwaltung. */
function yw_lexware_order_card(array $o): string {
    $orderId=(int)$o['id'];
    $html='<section class="card" id="lexware"><h2>Lexware Office</h2>';
    $doc=yw_lexware_document_for_order($orderId);
    if($doc){
        $html.='<p>'.yw_lexware_doc_badge($doc).'</p><p>'.($doc['kind']!=='order'?'In der '.e(yw_lexware_doc_title($doc)).'<br>':'')
            .'<strong>Belegnummer:</strong> '.e((string)($doc['voucher_number']?:'noch keine (vergibt Lexware beim Finalisieren)')).'<br><span class="muted">ID '.e((string)($doc['lexware_id']?:'–')).'</span></p>';
        if(!empty($doc['last_error']))$html.='<p class="muted">'.e((string)$doc['last_error']).'</p>';
        if($o['status']==='cancelled')$html.='<div class="flash error">Der Auftrag wurde nach dem Rechnungsentwurf storniert – bitte den Beleg in Lexware anpassen.</div>';
        $html.='<p>'.yw_lexware_doc_link($doc).'</p>'.yw_lexware_doc_actions($doc,'order:'.$orderId);
        return $html.'</section>';
    }
    if(!yw_lexware_enabled())return $html.'<p class="muted">Nicht verbunden. Einrichten unter <a href="/admin?tab=schnittstellen#lexware">System → Schnittstellen</a>.</p></section>';
    if($o['status']!=='approved')return $html.'<p class="muted">Rechnungsentwürfe gibt es für freigegebene Aufträge.</p></section>';
    $q=db()->prepare('SELECT lexware_contact_id,lexware_customer_number FROM companies WHERE id=?');$q->execute([(int)$o['company_id']]);$c=$q->fetch()?:[];
    if(empty($c['lexware_contact_id']))return $html.'<p class="muted">Die Firma ist noch nicht mit einem Lexware-Kontakt verknüpft.</p><a class="btn small" href="/admin?tab=lexware&amp;company='.(int)$o['company_id'].'">Kontakt einrichten</a></section>';
    [$orders,$items]=yw_lexware_load_orders((int)$o['company_id'],[$orderId]);
    $built=yw_lexware_build_lines($orders,$items,['grouping'=>'none','employee_names'=>true,'unit'=>'Stück']);
    $expected=yw_invoice_totals($built['calc']);
    $stored=(int)($o['gross_total_cents']??0);
    $html.='<p>Kunde Nr. '.e((string)($c['lexware_customer_number']?:'–')).' · '.count($built['calc']).' Positionen · '.money($expected['gross']).' brutto</p>';
    if($stored>0 && $stored!==$expected['gross'])$html.='<p class="muted">Hinweis: Lexware rechnet '.money($expected['gross']).', gespeichert sind '.money($stored).' (Bestellung vor 10.11 mit Rundung je Stück).</p>';
    if(count($built['lineItems'])>YW_LEXWARE_MAX_LINES)return $html.'<div class="flash error">'.e(yw_lexware_too_big_message($orderId,count($built['lineItems']))).'</div></section>';
    $html.='<form method="post">'.csrf_field().'<input type="hidden" name="action" value="lexware_invoice_order"><input type="hidden" name="order_id" value="'.$orderId.'"><input type="hidden" name="return" value="order:'.$orderId.'"><button class="btn small primary">Rechnungsentwurf in Lexware erzeugen</button></form><p class="muted">Wird nicht finalisiert – du prüfst und versendest die Rechnung in Lexware.</p>';
    return $html.'</section>';
}

/** Kompakte Karte in der Kundenakte (Reiter Aufträge). */
function yw_lexware_customer_card(array $company): void {
    $id=(int)$company['id'];
    echo '<section class="card"><div class="title-row"><div><h2>Lexware Office</h2><p class="muted">';
    if(!empty($company['lexware_contact_id']))echo 'Verknüpft mit Kunde Nr. '.e((string)($company['lexware_customer_number']?:'–')).' · <a href="'.e(YW_LEXWARE_APP_URL.'/permalink/contacts/view/'.$company['lexware_contact_id']).'" target="_blank" rel="noopener noreferrer">Kontakt öffnen</a>';
    else echo yw_lexware_enabled()?'Noch nicht mit einem Lexware-Kontakt verknüpft.':'Lexware Office ist nicht eingerichtet.';
    echo '</p></div><a class="btn small" href="/admin?tab=lexware&amp;company='.$id.'">Kontakt &amp; Sammelrechnung</a></div>';
    $docs=[];
    try{$q=db()->prepare('SELECT d.*,(SELECT COUNT(*) FROM yw_lexware_document_orders l WHERE l.document_id=d.id) orders FROM yw_lexware_documents d WHERE d.company_id=? ORDER BY d.id DESC LIMIT 10');$q->execute([$id]);$docs=$q->fetchAll();}catch(Throwable $e){}
    if($docs){
        echo '<div class="table-wrap"><table class="table"><thead><tr><th>Beleg</th><th>Aufträge</th><th>Brutto</th><th>Status</th></tr></thead><tbody>';
        foreach($docs as $d)echo '<tr><td>'.e(yw_lexware_doc_title($d)).'<br><span class="muted">'.e((string)($d['voucher_number']?:'ohne Nummer')).'</span></td><td>'.(int)$d['orders'].'</td><td>'.money((int)$d['gross_cents']).'</td><td>'.yw_lexware_doc_badge($d).'</td></tr>';
        echo '</tbody></table></div>';
    }
    echo '</section>';
}

/** Admin-Seite Tagesgeschäft → Abrechnung (Lexware Office). Die Verbindung selbst steht unter System → Schnittstellen. */
function yw_lexware_admin_page(): void {
    $enabled=yw_lexware_enabled();$settings=yw_lexware_settings();
    $profile=json_decode(current_site_setting('lexware_profile_json',''),true);
    if(!$enabled)echo '<div class="flash info"><strong>Lexware Office ist noch nicht verbunden.</strong> Bis dahin Rechnungen von Hand in Lexware erstellen. Verbinden: <a href="/admin?tab=schnittstellen#lexware">System → Schnittstellen → Lexware Office</a> (API-Schlüssel einfügen, fertig).</div>';
    else echo '<div class="flash success">Verbunden mit Lexware Office'.(is_array($profile)&&!empty($profile['companyName'])?': <strong>'.e((string)$profile['companyName']).'</strong>':'').(is_array($profile)&&!empty($profile['smallBusiness'])?' · <span class="badge rejected">Kleinunternehmer – Rechnungen mit Steuer passen nicht</span>':'').' · <a href="/admin?tab=schnittstellen#lexware">Verbindung</a></div>';

    echo '<section class="card"><h2>Einstellungen für Rechnungsentwürfe</h2><form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="lexware_settings"><input type="hidden" name="return" value="lexware">'
        .'<div class="grid2"><label>Belegtitel <small>höchstens 25 Zeichen</small><input name="title" maxlength="25" value="'.e($settings['title']).'"></label><label>Einheit der Positionen<input name="unit" maxlength="40" value="'.e($settings['unit']).'"></label></div>'
        .'<label>Einleitung <small>davor steht automatisch Auftrag bzw. Monat</small><textarea name="introduction" rows="2" maxlength="1500">'.e($settings['introduction']).'</textarea></label>'
        .'<label>Schlusstext <small>leer = Standard aus Lexware</small><textarea name="remark" rows="2" maxlength="2000">'.e($settings['remark']).'</textarea></label>'
        .'<label>Standard für Sammelrechnungen <small>über '.YW_LEXWARE_MAX_LINES.' Positionen wird automatisch in mehrere Entwürfe aufgeteilt</small><select name="grouping">';
    foreach(yw_lexware_grouping_options() as $k=>$l)echo '<option value="'.e($k).'" '.($settings['grouping']===$k?'selected':'').'>'.e($l).'</option>';
    echo '</select></label><label class="check"><input type="checkbox" name="employee_names" value="1" '.($settings['employee_names']==='1'?'checked':'').'><span>Namen der Besteller in den Positionstexten nennen (sonst nur Auftragsnummern)</span></label><button class="btn">Einstellungen speichern</button></form></section>';

    $companyId=(int)($_GET['company']??0);
    $companies=db()->query('SELECT id,legal_name,shop_name,lexware_contact_id,lexware_customer_number FROM companies ORDER BY legal_name')->fetchAll();
    echo '<section class="card"><h2>Firmenkunden</h2><form method="get" class="filter-bar"><input type="hidden" name="tab" value="lexware"><label>Firma<select name="company"><option value="">Bitte wählen</option>';
    foreach($companies as $c)echo '<option value="'.(int)$c['id'].'" '.((int)$c['id']===$companyId?'selected':'').'>'.e($c['legal_name']).' – '.e(empty($c['lexware_contact_id'])?'nicht verknüpft':'Kunde Nr. '.($c['lexware_customer_number']?:'?')).'</option>';
    echo '</select></label><button class="btn">Öffnen</button></form>';
    if($companyId>0){
        try{$company=yw_lexware_company($companyId);yw_lexware_admin_company($company,$settings);}
        catch(DomainException $e){echo '<div class="flash error">'.e($e->getMessage()).'</div>';}
    }
    echo '</section>';

    $docs=[];
    try{$docs=db()->query('SELECT d.*,c.legal_name,(SELECT COUNT(*) FROM yw_lexware_document_orders l WHERE l.document_id=d.id) orders FROM yw_lexware_documents d JOIN companies c ON c.id=d.company_id ORDER BY d.id DESC LIMIT 50')->fetchAll();}catch(Throwable $e){}
    echo '<section class="card"><h2>Rechnungsentwürfe</h2>';
    if(!$docs)echo '<p class="muted">Noch keine Belege an Lexware übertragen.</p>';
    else{
        echo '<div class="table-wrap"><table class="table"><thead><tr><th>Angelegt</th><th>Firma</th><th>Beleg</th><th>Brutto</th><th>Status</th><th></th></tr></thead><tbody>';
        foreach($docs as $d){
            $what=$d['kind']!=='order'?e(yw_lexware_doc_title($d).' ('.(int)$d['orders'].' Aufträge)'):'<a href="'.e(yw_order_url((int)$d['order_id'])).'">'.e(yw_order_number((int)$d['order_id'])).'</a>';
            echo '<tr><td>'.e(date('d.m.Y H:i',(int)strtotime((string)$d['created_at']))).'</td><td>'.e($d['legal_name']).'</td><td>'.$what.'<br><span class="muted">'.e((string)($d['voucher_number']?:'ohne Nummer')).'</span>'.(!empty($d['last_error'])?'<br><span class="muted">'.e((string)$d['last_error']).'</span>':'').'</td><td>'.money((int)$d['gross_cents']).'</td><td>'.yw_lexware_doc_badge($d).'<br>'.yw_lexware_doc_link($d).'</td><td>'.yw_lexware_doc_actions($d,'lexware').'</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';

    $labels=yw_lexware_audit_labels();
    $logs=db()->query("SELECT * FROM audit_log WHERE action LIKE 'lexware.%' ORDER BY id DESC LIMIT 40")->fetchAll();
    echo '<section class="card"><h2>Verlauf</h2>';
    if(!$logs)echo '<p class="muted">Noch keine Einträge.</p>';
    else{echo '<ol class="order-history">';foreach($logs as $log){$meta=json_decode((string)($log['metadata_json']??''),true)?:[];$extra=[];if(!empty($meta['voucher_number']))$extra[]=(string)$meta['voucher_number'];if(!empty($meta['error']))$extra[]=(string)$meta['error'];if($log['entity_type']==='order')$extra[]=yw_order_number((int)$log['entity_id']);if(!empty($meta['company']))$extra[]=(string)$meta['company'];echo '<li><span class="muted">'.e(date('d.m.Y H:i',(int)strtotime((string)$log['created_at']))).'</span> <strong>'.e($labels[$log['action']]??$log['action']).'</strong> '.e(implode(' · ',$extra)).'</li>';}echo '</ol>';}
    echo '</section>';
}

/** Firmenbereich auf der Lexware-Seite: Kontakt und Monats-Sammelrechnung. */
function yw_lexware_admin_company(array $company,array $settings): void {
    $id=(int)$company['id'];$hidden=csrf_field().'<input type="hidden" name="company_id" value="'.$id.'"><input type="hidden" name="return" value="company:'.$id.'">';
    echo '<hr><h3>'.e($company['legal_name']).'</h3>';
    if(!empty($company['lexware_contact_id'])){
        echo '<p><span class="badge approved">Verknüpft</span> Kunde Nr. '.e((string)($company['lexware_customer_number']?:'–')).' · <span class="muted">ID '.e((string)$company['lexware_contact_id']).'</span> · <a href="'.e(YW_LEXWARE_APP_URL.'/permalink/contacts/view/'.$company['lexware_contact_id']).'" target="_blank" rel="noopener noreferrer">In Lexware öffnen</a></p>';
        echo '<form method="post" data-confirm="Verknüpfung lösen? Der Kontakt in Lexware bleibt bestehen.">'.$hidden.'<input type="hidden" name="action" value="lexware_contact_unlink"><button class="btn small">Verknüpfung lösen</button></form>';
    }else{
        $g=yw_lexware_address_guess($company);
        echo '<div class="grid2"><div><h4>Neu in Lexware anlegen</h4><form method="post" class="form-stack">'.$hidden.'<input type="hidden" name="action" value="lexware_contact_create">'
            .'<label>Firmenname<input name="name" maxlength="190" required value="'.e($company['legal_name']).'"></label>'
            .'<label>Straße und Hausnummer<input name="street" maxlength="100" value="'.e($g['street']).'"></label>'
            .'<div class="grid2"><label>PLZ<input name="zip" maxlength="10" value="'.e($g['zip']).'"></label><label>Ort<input name="city" maxlength="100" value="'.e($g['city']).'"></label></div>'
            .'<div class="grid2"><label>Land<input name="country" maxlength="5" value="DE" required></label><label>USt-IdNr. <small>optional</small><input name="vat_id" maxlength="16"></label></div>'
            .'<label>E-Mail für Rechnungen <small>optional</small><input type="email" name="email" maxlength="190"></label>'
            .'<div class="grid2"><label>Ansprechpartner Vorname<input name="contact_first" maxlength="80"></label><label>Nachname<input name="contact_last" maxlength="80"></label></div>'
            .'<button class="btn primary">Kontakt in Lexware anlegen</button></form></div>';
        echo '<div><h4>Vorhandenen Kontakt verknüpfen</h4><form method="post" class="form-stack">'.$hidden.'<input type="hidden" name="action" value="lexware_contact_search"><label>In Lexware suchen (Name)<input name="query" minlength="3" maxlength="100" value="'.e($company['legal_name']).'"></label><button class="btn">Suchen</button></form>';
        $results=$_SESSION['lexware_search'][$id]??null;unset($_SESSION['lexware_search'][$id]);
        if(is_array($results)){
            if(!$results)echo '<p class="muted">Keine Kunden mit diesem Namen gefunden.</p>';
            else{echo '<ul class="search-list">';foreach($results as $r)echo '<li><form method="post" class="inline-form">'.$hidden.'<input type="hidden" name="action" value="lexware_contact_link"><input type="hidden" name="contact_id" value="'.e($r['id']).'"><strong>'.e($r['label']).'</strong> <span class="muted">Nr. '.e($r['number']).($r['archived']?' · archiviert':'').'</span> <button class="btn small">Verknüpfen</button></form></li>';echo '</ul>';}
        }
        echo '<form method="post" class="form-stack">'.$hidden.'<input type="hidden" name="action" value="lexware_contact_link"><label>oder Kontakt-ID eintragen<input name="contact_id" maxlength="36" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" required></label><button class="btn">Mit ID verknüpfen</button></form></div></div>';
    }

    // Monats-Sammelrechnung
    $month=yw_payroll_month($_GET['monat']??(new DateTimeImmutable('first day of last month'))->format('Y-m'));
    $grouping=(string)($_GET['gruppierung']??$settings['grouping']);if(!isset(yw_lexware_grouping_options()[$grouping]))$grouping='none';
    $hidden=csrf_field().'<input type="hidden" name="company_id" value="'.$id.'"><input type="hidden" name="return" value="company:'.$id.':'.e($month).'">';
    echo '<hr><h4>Monats-Sammelrechnung (Entwurf)</h4><form method="get" class="filter-bar"><input type="hidden" name="tab" value="lexware"><input type="hidden" name="company" value="'.$id.'"><label>Monat der Freigabe<input type="month" name="monat" value="'.e($month).'"></label><label>Aufteilung<select name="gruppierung">';
    foreach(yw_lexware_grouping_options() as $k=>$l)echo '<option value="'.e($k).'" '.($grouping===$k?'selected':'').'>'.e($l).'</option>';
    echo '</select></label><button class="btn">Vorschau</button></form>';
    echo '<p class="muted">Lexware nimmt höchstens '.YW_LEXWARE_MAX_LINES.' Positionen je Rechnung an. Größere Sammelrechnungen teilt das WorkwearCenter automatisch auf: zuerst nach ganzen Kostenstellen, eine allein zu große Kostenstelle in aufeinanderfolgende Teile („Teil 1 von 3“). Die Positionen eines Auftrags stehen immer zusammen in einem Entwurf, jeder Auftrag wird genau einmal berechnet.</p>';
    $ready=yw_lexware_parts_ready();
    if(!$ready)echo '<div class="flash error">'.e(YW_LEXWARE_UPDATE_HINT).' <a href="/update.php">Zum Update-Assistenten</a></div>';
    // 10.15: Entwürfe dieses Monats – fehlgeschlagene oder noch nicht gesendete Teile hier erneut senden.
    $docs=[];
    if($ready)try{$q=db()->prepare("SELECT d.*,(SELECT COUNT(*) FROM yw_lexware_document_orders l WHERE l.document_id=d.id) orders FROM yw_lexware_documents d WHERE d.company_id=? AND d.period=? AND d.kind<>'order' ORDER BY d.id");$q->execute([$id,$month]);$docs=$q->fetchAll();}catch(Throwable $e){}
    if($docs){
        echo '<div class="table-wrap"><table class="table"><thead><tr><th>Entwurf</th><th>Aufträge</th><th>Positionen</th><th>Brutto</th><th>Status</th><th></th></tr></thead><tbody>';
        foreach($docs as $d)echo '<tr><td>'.e(yw_lexware_doc_title($d)).'<br><span class="muted">'.e((string)($d['voucher_number']?:'ohne Nummer')).'</span>'.(!empty($d['last_error'])?'<br><span class="muted">'.e((string)$d['last_error']).'</span>':'').'</td><td>'.(int)$d['orders'].'</td><td>'.(int)$d['line_count'].'</td><td>'.money((int)$d['gross_cents']).'</td><td>'.yw_lexware_doc_badge($d).'<br>'.yw_lexware_doc_link($d).'</td><td>'.yw_lexware_doc_actions($d,'company:'.$id.':'.$month).'</td></tr>';
        echo '</tbody></table></div>';
        $pending=count(array_filter($docs,fn($d)=>in_array($d['status'],['planned','failed'],true)));
        // Nach abgelaufenem Zeitbudget einmalig automatisch weiter senden (höchstens 2 Minuten nach dem letzten Durchgang).
        $weiter=$_SESSION['lexware_weiter']??null;$auto=false;
        if(is_array($weiter) && (int)($weiter[0]??0)===$id && (string)($weiter[1]??'')===$month){unset($_SESSION['lexware_weiter']);$auto=$pending>0 && (int)($weiter[2]??0)>=time()-120;}
        if($pending)echo '<form method="post" class="space-top"'.($auto?' data-lexware-weiter':'').'>'.$hidden.'<input type="hidden" name="action" value="lexware_invoice_pending"><input type="hidden" name="month" value="'.e($month).'"><button class="btn primary">'.($pending===1?'Ausstehenden Entwurf':$pending.' ausstehende Entwürfe').' jetzt an Lexware senden</button></form><p class="muted">Gesendet werden nur fehlgeschlagene und noch nicht gesendete Teile mit ihren bisherigen Aufträgen – bereits angelegte Teile nie ein zweites Mal.</p>';
    }
    [$open,$billed]=yw_lexware_month_orders($id,$month);
    if($billed)echo '<p class="muted">'.count($billed).' Auftr'.(count($billed)===1?'ag ist':'äge sind').' in diesem Monat bereits in Lexware-Belegen und werden nicht erneut berechnet.</p>';
    if(!$open){echo '<p class="muted">Keine offenen, freigegebenen Aufträge in '.e(yw_payroll_month_label($month)).'.</p>';return;}
    [$orders,$items]=yw_lexware_load_orders($id,$open);
    $plan=yw_lexware_month_plan($orders,$items,$grouping);
    foreach($plan['too_big'] as $orderId=>$lines)echo '<div class="flash error">'.e(yw_lexware_too_big_message((int)$orderId,$lines)).' <a href="'.e(yw_order_url((int)$orderId)).'">Auftrag öffnen</a></div>';
    if(!$plan['parts'])return;
    $byId=[];foreach($orders as $o)$byId[(int)$o['id']]=$o;
    $previews=[];$sumParts=0;$stored=0;
    foreach($plan['parts'] as $p){
        $built=yw_lexware_build_lines(array_map(fn($oid)=>$byId[$oid],$p['ids']),$items,['grouping'=>$plan['grouping'],'employee_names'=>$settings['employee_names']==='1','unit'=>$settings['unit']]);
        $t=yw_invoice_totals($built['calc']);$sumParts+=$t['gross'];
        foreach($p['ids'] as $oid)$stored+=(int)$byId[$oid]['gross_total_cents'];
        $previews[]=['title'=>yw_lexware_doc_title(['kind'=>'monthly','period'=>$month,'part_no'=>$p['part'],'part_count'=>$p['of'],'group_label'=>$p['label']]),'part'=>$p,'built'=>$built,'totals'=>$t];
    }
    $n=count($previews);
    echo '<div class="flash info"><strong>'.($n===1?'Es entsteht 1 Rechnungsentwurf':'Es entstehen '.$n.' Rechnungsentwürfe').'</strong>'.($n>1?' (höchstens '.YW_LEXWARE_MAX_LINES.' Positionen je Rechnung)':'').':<ol>';
    foreach($previews as $pv)echo '<li>'.e($pv['title']).' – '.count($pv['part']['ids']).' Auftr'.(count($pv['part']['ids'])===1?'ag':'äge').', '.count($pv['built']['lineItems']).' Positionen, '.money($pv['totals']['gross']).' brutto</li>';
    echo '</ol></div>';
    foreach($previews as $pv){
        $t=$pv['totals'];
        echo '<details class="details"'.($n===1?' open':'').'><summary>'.e($pv['title']).' · '.count($pv['part']['ids']).' Auftr'.(count($pv['part']['ids'])===1?'ag':'äge').' · '.count($pv['built']['lineItems']).' Positionen</summary><div class="details-inner">';
        echo '<div class="table-wrap"><table class="table"><thead><tr><th>Position</th><th>Menge</th><th>Netto je Einheit</th><th>USt.</th><th>Netto</th></tr></thead><tbody>';
        foreach($pv['built']['lineItems'] as $li){
            if($li['type']==='text'){echo '<tr><td colspan="5"><strong>'.e($li['name']).'</strong> <span class="muted">'.e($li['description']).'</span></td></tr>';continue;}
            $unitNet=(int)round((float)$li['unitPrice']['netAmount']*100);
            echo '<tr><td>'.e($li['name']).'<br><span class="muted">'.e($li['description']).'</span></td><td>'.(int)$li['quantity'].' '.e($li['unitName']).'</td><td>'.money($unitNet).'</td><td>'.e((string)$li['unitPrice']['taxRatePercentage']).' %</td><td>'.money($unitNet*(int)$li['quantity']).'</td></tr>';
        }
        echo '</tbody></table></div><p><strong>Netto</strong> '.money($t['net']);
        foreach($t['by_rate'] as $bp=>$r)echo ' · <strong>USt. '.e(rtrim(rtrim(number_format($bp/100,2,',',''),'0'),',')).' %</strong> '.money($r['tax']).' auf '.money($r['net']);
        echo ' · <strong>Brutto</strong> '.money($t['gross']).'</p></div></details>';
    }
    $disabled=empty($company['lexware_contact_id'])?' disabled title="Zuerst Kontakt verknüpfen"':(!$ready?' disabled title="Zuerst Datenbank-Update ausführen"':'');
    echo '<form method="post" class="space-top">'.$hidden.'<input type="hidden" name="action" value="lexware_invoice_monthly"><input type="hidden" name="month" value="'.e($month).'"><input type="hidden" name="grouping" value="'.e($grouping).'"><input type="hidden" name="plan" value="'.e(yw_lexware_plan_hash($plan,$month,$grouping)).'"><button class="btn primary"'.$disabled.'>'.($n===1?'Entwurf in Lexware anlegen':$n.' Rechnungsentwürfe in Lexware anlegen').'</button></form>';
    if($n>1)echo '<p class="muted">Die Aufträge werden zuerst allen Teilen fest zugeordnet, dann nacheinander gesendet. Scheitert ein Teil, bleiben die anderen bestehen; der fehlgeschlagene Teil kann oben erneut gesendet werden.</p>';
    if($sumParts!==$stored)echo '<p class="muted">Summe der einzelnen Bestellungen: '.money($stored).'. Weil Lexware die Steuer je Steuersatz auf die Rechnungssumme rechnet, kann die Sammelrechnung um wenige Cent abweichen ('.money(abs($sumParts-$stored)).').</p>';
}

/* ---------- Aktionen ---------- */

function yw_lexware_return_url(string $return): string {
    if(preg_match('/^order:(\d+)$/',$return,$m))return yw_order_url((int)$m[1]).'#lexware';
    if($return==='schnittstellen')return '/admin?tab=schnittstellen#lexware';
    if(preg_match('/^company:(\d+)$/',$return,$m))return '/admin?tab=lexware&company='.(int)$m[1];
    if(preg_match('/^company:(\d+):(\d{4}-\d{2})$/D',$return,$m))return '/admin?tab=lexware&company='.(int)$m[1].'&monat='.yw_payroll_month($m[2]);
    return '/admin?tab=lexware';
}

/** 10.15: Ergebnis mehrerer gesendeter Entwürfe als eine verständliche Meldung. */
function yw_lexware_flash_result(int $companyId,string $month,array $result,int $total,array $tooBig): void {
    $created=count($result['created']);$gross=array_sum(array_map(fn($d)=>(int)$d['gross_cents'],$result['created']));$orders=array_sum(array_map(fn($d)=>(int)($d['orders']??0),$result['created']));
    $text='Sammelrechnung '.yw_payroll_month_label($month).': '.($total===1?($created?'Entwurf':'Kein Entwurf'):$created.' von '.$total.' Rechnungsentwürfen').' in Lexware angelegt'.($created?' ('.$orders.' Auftr'.($orders===1?'ag':'äge').', '.money($gross).' brutto)':'').'.';
    $failed=$result['failed'];
    foreach(array_slice($failed,0,3,true) as $title=>$message)$text.=' '.$title.': '.rtrim($message).(preg_match('/[.!?…]$/u',rtrim($message))?'':'.');
    if(count($failed)>3)$text.=' Weitere '.(count($failed)-3).' Entwürfe sind fehlgeschlagen.';
    // Nur das Zeitbudget je Aufruf ist abgelaufen: Die Seite sendet die übrigen Entwürfe automatisch weiter (assets/app.js, data-lexware-weiter).
    $auto=$result['pending'] && !$failed && empty($result['stopped']);
    if($auto){$_SESSION['lexware_weiter']=[$companyId,$month,time()];$text.=' '.$result['pending'].' weitere'.($result['pending']===1?'r Entwurf wird':' Entwürfe werden').' jetzt automatisch nacheinander gesendet – bitte diese Seite geöffnet lassen.';}
    else{
        if($result['pending'])$text.=' '.$result['pending'].' '.($result['pending']===1?'Entwurf wurde':'Entwürfe wurden').' noch nicht gesendet.';
        if($failed || $result['pending'])$text.=' Mit „Ausstehende Entwürfe jetzt an Lexware senden“ erneut versuchen – bereits angelegte Teile werden nicht doppelt angelegt.';
        elseif($created)$text.=' Bitte in Lexware prüfen und finalisieren.';
    }
    if($tooBig)$text.=' Nicht übernommen, weil ein Auftrag allein mehr als '.YW_LEXWARE_MAX_LINES.' Positionen hat: '.implode(', ',array_map(fn($id)=>yw_order_number((int)$id),$tooBig)).' – bitte in Lexware von Hand abrechnen.';
    flash($failed || ($result['pending'] && !$auto) || $tooBig?'error':'success',$text);
}

/** Fragt das Lexware-Profil ab und merkt sich Firma und Steuerart. Wirft YwLexwareException bei Fehlern. */
function yw_lexware_test_connection(): array {
    $p=yw_lexware_request('GET','/v1/profile');
    set_site_setting('lexware_profile_json',json_encode(['companyName'=>(string)($p['companyName']??''),'organizationId'=>(string)($p['organizationId']??''),'taxType'=>(string)($p['taxType']??''),'smallBusiness'=>!empty($p['smallBusiness']),'businessFeatures'=>array_values(array_filter((array)($p['businessFeatures']??[]),'is_string'))],JSON_UNESCAPED_UNICODE));
    set_site_setting('lexware_profile_checked_at',date('Y-m-d H:i:s'));
    audit_log('lexware.connection_tested','system',null,['company'=>(string)($p['companyName']??''),'tax_type'=>(string)($p['taxType']??'')]);
    return $p;
}

function yw_lexware_admin_action(string $action): never {
    require_admin();
    $return=yw_lexware_return_url((string)($_POST['return']??''));
    try{
        $companyId=(int)($_POST['company_id']??0);
        switch($action){
            case 'lexware_test':
                $p=yw_lexware_test_connection();
                flash('success','Verbindung zu Lexware Office steht: '.(string)($p['companyName']??'').(($p['taxType']??'net')!=='net'?' (Hinweis: Steuerart in Lexware ist „'.(string)$p['taxType'].'“, die Entwürfe werden trotzdem mit Nettopreisen angelegt).':'.'));
                break;
            case 'lexware_settings':
                $title=trim((string)($_POST['title']??''));if(mb_strlen($title)>25)throw new DomainException('Der Belegtitel darf höchstens 25 Zeichen haben.');
                $grouping=(string)($_POST['grouping']??'none');if(!isset(yw_lexware_grouping_options()[$grouping]))$grouping='none';
                set_site_setting('lexware_title',$title!==''?$title:'Rechnung');
                set_site_setting('lexware_unit',mb_substr(trim((string)($_POST['unit']??''))?:'Stück',0,40));
                set_site_setting('lexware_introduction',mb_substr(trim((string)($_POST['introduction']??'')),0,1500));
                set_site_setting('lexware_remark',mb_substr(trim((string)($_POST['remark']??'')),0,2000));
                set_site_setting('lexware_grouping',$grouping);
                set_site_setting('lexware_employee_names',($_POST['employee_names']??'')==='1'?'1':'0');
                audit_log('lexware.settings_saved','system',null);
                flash('success','Lexware-Einstellungen gespeichert.');
                break;
            case 'lexware_contact_create':
                $form=[];foreach(['name','street','zip','city','country','vat_id','tax_number','email','contact_first','contact_last','supplement'] as $k)$form[$k]=is_string($_POST[$k]??null)?$_POST[$k]:'';
                yw_lexware_contact_create($companyId,$form);
                flash('success','Kontakt wurde in Lexware Office angelegt und verknüpft.');
                break;
            case 'lexware_contact_search':
                $_SESSION['lexware_search'][$companyId]=array_slice(yw_lexware_contact_search(is_string($_POST['query']??null)?$_POST['query']:''),0,25);
                break;
            case 'lexware_contact_link':
                $contact=yw_lexware_contact_link($companyId,is_string($_POST['contact_id']??null)?$_POST['contact_id']:'');
                flash('success','Verknüpft mit „'.yw_lexware_contact_label($contact).'“.');
                break;
            case 'lexware_contact_unlink':
                db()->prepare('UPDATE companies SET lexware_contact_id=NULL,lexware_customer_number=NULL WHERE id=?')->execute([$companyId]);
                audit_log('lexware.contact_unlinked','company',$companyId,['company_id'=>$companyId]);
                flash('success','Verknüpfung gelöst.');
                break;
            case 'lexware_invoice_order':
                $orderId=(int)($_POST['order_id']??0);
                $q=db()->prepare('SELECT * FROM orders WHERE id=?');$q->execute([$orderId]);$order=$q->fetch();
                if(!$order)throw new DomainException('Auftrag nicht gefunden.');
                $company=yw_lexware_company((int)$order['company_id']);
                $doc=yw_lexware_create_invoice((int)$company['id'],[$orderId],'order',null,'',yw_lexware_order_meta($order,$company));
                flash('success','Rechnungsentwurf in Lexware angelegt'.(!empty($doc['voucher_number'])?' ('.$doc['voucher_number'].')':'').'. Bitte in Lexware prüfen und finalisieren.');
                break;
            case 'lexware_invoice_monthly':
                // 10.15: Alle Teile der Vorschau in einem Schritt fest zuordnen, dann nacheinander senden.
                $month=yw_payroll_month($_POST['month']??'');$grouping=(string)($_POST['grouping']??'none');if(!isset(yw_lexware_grouping_options()[$grouping]))$grouping='none';
                $return='/admin?tab=lexware&company='.$companyId.'&monat='.$month.'&gruppierung='.$grouping;
                if(!yw_lexware_parts_ready())throw new DomainException(YW_LEXWARE_UPDATE_HINT);
                yw_lexware_check_ready(yw_lexware_company($companyId));
                [$open]=yw_lexware_month_orders($companyId,$month);
                [$orders,$items]=yw_lexware_load_orders($companyId,$open);
                $plan=yw_lexware_month_plan($orders,$items,$grouping);
                if(!$plan['parts'] || !hash_equals(yw_lexware_plan_hash($plan,$month,$grouping),is_string($_POST['plan']??null)?$_POST['plan']:''))throw new DomainException('Die Aufträge haben sich seit der Vorschau geändert. Bitte Vorschau neu laden und prüfen.');
                $docIds=yw_lexware_reserve($companyId,array_map(fn($p)=>['ids'=>$p['ids'],'kind'=>'monthly','period'=>$month,'label'=>$p['label'],'part'=>$p['part'],'of'=>$p['of'],'grouping'=>$plan['grouping']],$plan['parts']));
                audit_log('lexware.invoice_planned','company',$companyId,['company_id'=>$companyId,'period'=>$month,'documents'=>count($docIds),'orders'=>array_sum(array_map(fn($p)=>count($p['ids']),$plan['parts'])),'skipped'=>array_keys($plan['too_big'])]);
                yw_lexware_flash_result($companyId,$month,yw_lexware_send_documents($docIds),count($docIds),array_keys($plan['too_big']));
                break;
            case 'lexware_invoice_pending':
                // 10.15: Fehlgeschlagene oder nicht gesendete Teile eines Monats erneut senden (gleiche Aufträge, gleicher Teil).
                $month=yw_payroll_month($_POST['month']??'');
                $return='/admin?tab=lexware&company='.$companyId.'&monat='.$month;
                if(!yw_lexware_parts_ready())throw new DomainException(YW_LEXWARE_UPDATE_HINT);
                yw_lexware_check_ready(yw_lexware_company($companyId));
                $q=db()->prepare("SELECT id FROM yw_lexware_documents WHERE company_id=? AND period=? AND kind<>'order' AND status IN ('planned','failed') ORDER BY id");$q->execute([$companyId,$month]);
                $docIds=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
                if(!$docIds)throw new DomainException('Für diesen Monat stehen keine Entwürfe mehr aus.');
                yw_lexware_flash_result($companyId,$month,yw_lexware_send_documents($docIds),count($docIds),[]);
                break;
            case 'lexware_document_resend':
            case 'lexware_document_send':
                $docId=(int)($_POST['document_id']??0);
                $q=db()->prepare('SELECT * FROM yw_lexware_documents WHERE id=?');$q->execute([$docId]);$doc=$q->fetch();
                if(!$doc)throw new DomainException('Beleg nicht gefunden.');
                if($action==='lexware_document_resend'){
                    // 10.15: Unklarer Teil laut Prüfung nicht in Lexware angekommen → mit denselben Aufträgen als derselbe Teil erneut senden.
                    if($doc['kind']==='order')throw new DomainException('Für Einzelaufträge bitte „Sperre aufheben“ und den Entwurf neu erzeugen.');
                    $q=db()->prepare("UPDATE yw_lexware_documents SET status='failed',last_error='Laut Prüfung nicht in Lexware angekommen.' WHERE id=? AND (status='uncertain' OR (status='creating' AND updated_at<NOW()-INTERVAL 5 MINUTE))");$q->execute([$docId]);
                    if(!$q->rowCount())throw new DomainException('Beleg nicht gefunden oder bereits geklärt.');
                    audit_log('lexware.document_resend','company',(int)$doc['company_id'],['company_id'=>(int)$doc['company_id'],'document_id'=>$docId,'period'=>$doc['period'],'status'=>$doc['status']]);
                }
                $doc=yw_lexware_send_document($docId,[],$doc['kind']==='order');
                if($doc['kind']!=='order')audit_log('lexware.invoice_created','company',(int)$doc['company_id'],['company_id'=>(int)$doc['company_id'],'document_id'=>$docId,'period'=>$doc['period'],'orders'=>(int)$doc['orders'],'voucher_number'=>$doc['voucher_number']??null]);
                flash('success',yw_lexware_doc_title($doc).' als Entwurf in Lexware angelegt ('.(int)$doc['orders'].' Auftr'.((int)$doc['orders']===1?'ag':'äge').', '.money((int)$doc['gross_cents']).'). Bitte in Lexware prüfen und finalisieren.');
                break;
            case 'lexware_document_refresh':
                $doc=yw_lexware_refresh_document((int)($_POST['document_id']??0));
                audit_log('lexware.document_refreshed','company',(int)$doc['company_id'],['company_id'=>(int)$doc['company_id'],'document_id'=>(int)$doc['id'],'voucher_number'=>$doc['voucher_number']]);
                flash('success','Status aus Lexware abgerufen: '.($doc['voucher_number']?:'noch ohne Belegnummer').' · '.($doc['voucher_status']?:'unbekannt').'.');
                break;
            case 'lexware_document_set_id':
                $docId=(int)($_POST['document_id']??0);$lexId=strtolower(trim(is_string($_POST['lexware_id']??null)?$_POST['lexware_id']:''));
                if(!yw_lexware_uuid_ok($lexId))throw new DomainException('Die Beleg-ID hat nicht das Lexware-Format.');
                $invoice=yw_lexware_request('GET','/v1/invoices/'.$lexId);
                $q=db()->prepare("UPDATE yw_lexware_documents d JOIN companies c ON c.id=d.company_id SET d.status='created',d.lexware_id=?,d.last_error=NULL WHERE d.id=? AND d.status IN ('uncertain','creating') AND (c.lexware_contact_id IS NULL OR ?='' OR c.lexware_contact_id=?)");
                $contact=(string)($invoice['address']['contactId']??'');
                $q->execute([$lexId,$docId,$contact,$contact]);
                if(!$q->rowCount())throw new DomainException('Beleg nicht gefunden, bereits geklärt oder für einen anderen Kunden.');
                $doc=yw_lexware_refresh_document($docId,false);
                audit_log('lexware.document_id_set','company',(int)$doc['company_id'],['company_id'=>(int)$doc['company_id'],'document_id'=>$docId,'lexware_id'=>$lexId]);
                flash('success','Beleg-ID gespeichert.');
                break;
            case 'lexware_document_release':
                $docId=(int)($_POST['document_id']??0);
                $q=db()->prepare('SELECT * FROM yw_lexware_documents WHERE id=?');$q->execute([$docId]);$doc=$q->fetch();
                if(!$doc)throw new DomainException('Beleg nicht gefunden.');
                // Ein Entwurf, der gerade gesendet wird, darf nicht freigegeben werden (sonst Doppelberechnung möglich).
                if($doc['status']==='creating' && strtotime((string)$doc['updated_at'])>=time()-300)throw new DomainException('Dieser Entwurf wird gerade an Lexware gesendet. Bitte in fünf Minuten erneut prüfen.');
                $ids=db()->prepare('SELECT order_id FROM yw_lexware_document_orders WHERE document_id=?');$ids->execute([$docId]);$orderIds=$ids->fetchAll(PDO::FETCH_COLUMN);
                db()->beginTransaction();
                $del=db()->prepare('DELETE FROM yw_lexware_documents WHERE id=? AND status=?');$del->execute([$docId,$doc['status']]);
                if(!$del->rowCount())throw new DomainException('Der Beleg wurde inzwischen verändert. Bitte die Seite neu laden.');
                db()->prepare('DELETE FROM yw_lexware_document_orders WHERE document_id=?')->execute([$docId]);
                db()->commit();
                foreach($orderIds as $oid)audit_log('lexware.document_released','order',(int)$oid,['company_id'=>(int)$doc['company_id'],'lexware_id'=>$doc['lexware_id'],'status'=>$doc['status']]);
                audit_log('lexware.document_released','company',(int)$doc['company_id'],['company_id'=>(int)$doc['company_id'],'lexware_id'=>$doc['lexware_id'],'orders'=>count($orderIds)]);
                flash('success',(in_array($doc['status'],['planned','failed'],true)?'Entwurf verworfen.':'Verknüpfung entfernt.').' Die Aufträge können erneut abgerechnet werden.');
                break;
            default:
                throw new DomainException('Aktion unbekannt.');
        }
    }catch(Throwable $e){
        if(db()->inTransaction())db()->rollBack();
        $message=$e instanceof DomainException||$e instanceof YwLexwareException?$e->getMessage():'Die Lexware-Aktion ist fehlgeschlagen. Details stehen im Serverprotokoll.';
        if(!$e instanceof DomainException && !$e instanceof YwLexwareException)error_log('WorkwearCenter Lexware action: '.$e->getMessage());
        audit_log('lexware.error','system',null,['action'=>$action,'company_id'=>$companyId??0,'error'=>mb_substr($message,0,300)]);
        flash('error',$message);
    }
    redirect($return);
}
