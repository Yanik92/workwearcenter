<?php
declare(strict_types=1);

/*
 * 10.11: Anbindung an Lexware Office über die offizielle Public API (https://developers.lexware.io/docs/).
 *
 * - Authentifizierung: „Authorization: Bearer <API-Schlüssel>“ (config.php: lexware_api_key, leer = aus)
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
 */

const YW_LEXWARE_DEFAULT_BASE='https://api.lexware.io';
const YW_LEXWARE_APP_URL='https://app.lexware.de';
const YW_LEXWARE_MIN_INTERVAL=0.6;
const YW_LEXWARE_MAX_LINES=300;

final class YwLexwareException extends RuntimeException {
    /** $uncertain: Die Anfrage kann trotz Fehler in Lexware angekommen sein (Zeitüberschreitung, 504). */
    public function __construct(string $message,public readonly int $status=0,public readonly bool $uncertain=false){parent::__construct($message);}
}

/* ---------- Konfiguration und Einstellungen ---------- */

function yw_lexware_api_key(): string { return trim((string)(cfg('lexware_api_key')??'')); }
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
        'lexware.invoice_created'=>'Lexware: Rechnungsentwurf angelegt',
        'lexware.invoice_failed'=>'Lexware: Rechnungsentwurf fehlgeschlagen',
        'lexware.invoice_uncertain'=>'Lexware: Ergebnis unklar – bitte prüfen',
        'lexware.document_refreshed'=>'Lexware: Belegstatus aktualisiert',
        'lexware.document_released'=>'Lexware: Belegverknüpfung entfernt',
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
        $label=$grouping==='cost_center'?(trim((string)($o['cost_center']??''))!==''?'Kostenstelle: '.trim((string)$o['cost_center']):'Ohne Kostenstelle'):'';
        $ref=yw_order_number((int)$o['id']).($withNames && trim((string)($o['employee_name']??''))!==''?' ('.trim((string)$o['employee_name']).')':'');
        $groups[$label]['orders'][$ref]=true;
        foreach($itemsByOrder[(int)$o['id']]??[] as $it){
            $net=(int)($it['unit_net_cents']??0);if($net<=0)$net=(int)($it['unit_price_cents']??0);
            $rate=(float)($it['vat_rate']??19);
            $key='i|'.$it['product_name'].'|'.$it['size'].'|'.$net.'|'.yw_rate_bp($rate);
            $groups[$label]['lines'][$key]??=['name'=>(string)$it['product_name'],'size'=>(string)$it['size'],'net'=>$net,'vat'=>$rate,'qty'=>0,'refs'=>[]];
            $groups[$label]['lines'][$key]['qty']+=(int)$it['quantity'];
            $groups[$label]['lines'][$key]['refs'][$ref]=true;
        }
        $shipNet=(int)($o['shipping_net_cents']??0);
        if($shipNet>0){
            $rate=(float)($o['shipping_rate']??19);
            $key='s|'.$shipNet.'|'.yw_rate_bp($rate);
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

/**
 * Legt einen Rechnungsentwurf an. Reihenfolge schützt vor Doppelanlage:
 * 1. Beleg + Auftragszuordnung lokal reservieren (eindeutige Schlüssel), 2. Lexware aufrufen,
 * 3. Ergebnis speichern. Bei sicherem Fehler wird die Reservierung zurückgenommen, bei unklarem
 * Ergebnis (Zeitüberschreitung) bleibt sie als „unklar“ stehen, bis jemand in Lexware nachsieht.
 */
function yw_lexware_create_invoice(int $companyId,array $orderIds,string $kind,?string $period,string $groupLabel,array $meta): array {
    $company=yw_lexware_company($companyId);
    $contactId=(string)($company['lexware_contact_id']??'');
    if(!yw_lexware_uuid_ok($contactId))throw new DomainException('Diese Firma ist noch nicht mit einem Lexware-Kontakt verknüpft. Bitte zuerst unter System → Lexware Office den Kontakt anlegen oder verknüpfen.');
    if(!yw_lexware_enabled())throw new DomainException('Lexware Office ist nicht eingerichtet (lexware_api_key in der config.php).');
    sort($orderIds);
    $dedupe=$kind==='order'?'order:'.(int)$orderIds[0]:'monthly:'.$companyId.':'.$period.':'.substr(hash('sha256',$groupLabel.'|'.implode(',',$orderIds)),0,40);
    $settings=yw_lexware_settings();
    try{
        db()->beginTransaction();
        $in=implode(',',array_fill(0,count($orderIds),'?'));
        $q=db()->prepare("SELECT id,status FROM orders WHERE company_id=? AND id IN ($in) FOR UPDATE");$q->execute(array_merge([$companyId],$orderIds));
        $rows=$q->fetchAll();
        if(count($rows)!==count($orderIds))throw new DomainException('Mindestens ein Auftrag gehört nicht zu dieser Firma.');
        foreach($rows as $r)if($r['status']!=='approved')throw new DomainException('Nur freigegebene Aufträge können abgerechnet werden ('.yw_order_number((int)$r['id']).' ist nicht freigegeben).');
        $q=db()->prepare("SELECT order_id FROM yw_lexware_document_orders WHERE order_id IN ($in) FOR UPDATE");$q->execute($orderIds);
        if($taken=$q->fetchAll(PDO::FETCH_COLUMN))throw new DomainException('Bereits in einem Lexware-Beleg: '.implode(', ',array_map(fn($id)=>yw_order_number((int)$id),$taken)).'. Keine Doppelanlage.');
        [$orders,$items]=yw_lexware_load_orders($companyId,$orderIds);
        $built=yw_lexware_build_lines($orders,$items,['grouping'=>$meta['grouping']??'none','employee_names'=>$settings['employee_names']==='1','unit'=>$settings['unit']]);
        $priced=count($built['calc']);
        if($priced===0)throw new DomainException('Keine abrechenbaren Positionen.');
        if(count($built['lineItems'])>YW_LEXWARE_MAX_LINES)throw new DomainException('Die Rechnung hätte '.count($built['lineItems']).' Positionen; Lexware erlaubt höchstens '.YW_LEXWARE_MAX_LINES.'. Bitte „Je Kostenstelle eine eigene Rechnung“ wählen.');
        $expected=yw_invoice_totals($built['calc']);
        $ins=db()->prepare("INSERT INTO yw_lexware_documents(dedupe_key,company_id,kind,order_id,period,group_label,status,net_cents,gross_cents,line_count,created_by) VALUES(?,?,?,?,?,?,'creating',?,?,?,?)");
        $ins->execute([$dedupe,$companyId,$kind,$kind==='order'?(int)$orderIds[0]:null,$period,$groupLabel!==''?mb_substr($groupLabel,0,190):null,$expected['net'],$expected['gross'],count($built['lineItems']),!empty($_SESSION['admin_id'])?(int)$_SESSION['admin_id']:null]);
        $docId=(int)db()->lastInsertId();
        $link=db()->prepare('INSERT INTO yw_lexware_document_orders(order_id,document_id) VALUES(?,?)');
        foreach($orderIds as $id)$link->execute([$id,$docId]);
        db()->commit();
    }catch(PDOException $e){
        if(db()->inTransaction())db()->rollBack();
        if((string)$e->getCode()==='23000')throw new DomainException('Für diese Aufträge wird gerade bereits ein Lexware-Beleg angelegt. Keine Doppelanlage.');
        throw $e;
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}

    $payload=yw_lexware_invoice_payload($contactId,$built['lineItems'],$meta+['settings'=>$settings]);
    try{
        $result=yw_lexware_request('POST','/v1/invoices',$payload);
        $lexId=(string)($result['id']??'');
        if(!yw_lexware_uuid_ok($lexId))throw new YwLexwareException('Lexware hat keine gültige Beleg-ID zurückgegeben. Bitte in Lexware prüfen.',0,true);
    }catch(Throwable $e){
        $uncertain=$e instanceof YwLexwareException && $e->uncertain;
        $message=$e instanceof YwLexwareException||$e instanceof DomainException?$e->getMessage():'Technischer Fehler beim Senden an Lexware.';
        if(!$e instanceof YwLexwareException && !$e instanceof DomainException)error_log('WorkwearCenter Lexware: '.$e->getMessage());
        if($uncertain){
            db()->prepare("UPDATE yw_lexware_documents SET status='uncertain',last_error=? WHERE id=?")->execute([mb_substr($message,0,500),$docId]);
        }else{
            db()->prepare('DELETE FROM yw_lexware_document_orders WHERE document_id=?')->execute([$docId]);
            db()->prepare('DELETE FROM yw_lexware_documents WHERE id=?')->execute([$docId]);
        }
        foreach($orderIds as $id)audit_log($uncertain?'lexware.invoice_uncertain':'lexware.invoice_failed','order',(int)$id,['company_id'=>$companyId,'kind'=>$kind,'period'=>$period,'error'=>mb_substr($message,0,300)]);
        throw new DomainException($message.($uncertain?' Der Auftrag bleibt gesperrt, bis du unter System → Lexware Office die Beleg-ID nachträgst oder die Sperre aufhebst.':''));
    }
    db()->prepare("UPDATE yw_lexware_documents SET status='created',lexware_id=?,voucher_status='draft',last_error=NULL WHERE id=?")->execute([$lexId,$docId]);
    $doc=yw_lexware_refresh_document($docId,false);
    foreach($orderIds as $id)audit_log('lexware.invoice_created','order',(int)$id,['company_id'=>$companyId,'document_id'=>$docId,'lexware_id'=>$lexId,'kind'=>$kind,'period'=>$period,'voucher_number'=>$doc['voucher_number']??null]);
    return $doc+['expected'=>$expected];
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

function yw_lexware_month_meta(array $company,string $month,int $count,string $label,string $grouping): array {
    $start=new DateTimeImmutable($month.'-01',new DateTimeZone('Europe/Berlin'));
    return [
        'voucher_date'=>new DateTimeImmutable('today',new DateTimeZone('Europe/Berlin')),
        'shipping'=>['shippingType'=>'deliveryperiod','shippingDate'=>yw_lexware_date($start),'shippingEndDate'=>yw_lexware_date($start->modify('last day of this month'))],
        'introduction'=>'Sammelrechnung '.yw_payroll_month_label($month).': '.$count.' Auftr'.($count===1?'ag':'äge').' aus dem WorkwearCenter-Shop „'.$company['shop_name'].'“'.($label!==''?' – '.$label:'').'.',
        'grouping'=>$grouping==='cost_center'?'cost_center':'none',
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
        default=>['Wird angelegt …','pending'],
    };
    return '<span class="badge '.$class.'">'.e($label).'</span>';
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
            .'<form method="post" data-confirm="Sperre wirklich aufheben? Nur wenn der Beleg in Lexware NICHT existiert.">'.$hidden.'<input type="hidden" name="action" value="lexware_document_release"><button class="btn small danger">Ist nicht angekommen – Sperre aufheben</button></form></div></details>';
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
        $html.='<p>'.yw_lexware_doc_badge($doc).'</p><p>'.($doc['kind']==='monthly'?'In der Sammelrechnung '.e(yw_payroll_month_label((string)$doc['period'])).($doc['group_label']?' ('.e((string)$doc['group_label']).')':'').'<br>':'')
            .'<strong>Belegnummer:</strong> '.e((string)($doc['voucher_number']?:'noch keine (vergibt Lexware beim Finalisieren)')).'<br><span class="muted">ID '.e((string)($doc['lexware_id']?:'–')).'</span></p>';
        if(!empty($doc['last_error']))$html.='<p class="muted">'.e((string)$doc['last_error']).'</p>';
        if($o['status']==='cancelled')$html.='<div class="flash error">Der Auftrag wurde nach dem Rechnungsentwurf storniert – bitte den Beleg in Lexware anpassen.</div>';
        $html.='<p>'.yw_lexware_doc_link($doc).'</p>'.yw_lexware_doc_actions($doc,'order:'.$orderId);
        return $html.'</section>';
    }
    if(!yw_lexware_enabled())return $html.'<p class="muted">Nicht eingerichtet. Anleitung unter <a href="/admin?tab=lexware">System → Lexware Office</a>.</p></section>';
    if($o['status']!=='approved')return $html.'<p class="muted">Rechnungsentwürfe gibt es für freigegebene Aufträge.</p></section>';
    $q=db()->prepare('SELECT lexware_contact_id,lexware_customer_number FROM companies WHERE id=?');$q->execute([(int)$o['company_id']]);$c=$q->fetch()?:[];
    if(empty($c['lexware_contact_id']))return $html.'<p class="muted">Die Firma ist noch nicht mit einem Lexware-Kontakt verknüpft.</p><a class="btn small" href="/admin?tab=lexware&amp;company='.(int)$o['company_id'].'">Kontakt einrichten</a></section>';
    [$orders,$items]=yw_lexware_load_orders((int)$o['company_id'],[$orderId]);
    $built=yw_lexware_build_lines($orders,$items,['grouping'=>'none','employee_names'=>true,'unit'=>'Stück']);
    $expected=yw_invoice_totals($built['calc']);
    $stored=(int)($o['gross_total_cents']??0);
    $html.='<p>Kunde Nr. '.e((string)($c['lexware_customer_number']?:'–')).' · '.count($built['calc']).' Positionen · '.money($expected['gross']).' brutto</p>';
    if($stored>0 && $stored!==$expected['gross'])$html.='<p class="muted">Hinweis: Lexware rechnet '.money($expected['gross']).', gespeichert sind '.money($stored).' (Bestellung vor 10.11 mit Rundung je Stück).</p>';
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
        foreach($docs as $d)echo '<tr><td>'.e($d['kind']==='monthly'?'Sammelrechnung '.yw_payroll_month_label((string)$d['period']).($d['group_label']?' · '.$d['group_label']:''):'Auftrag '.yw_order_number((int)$d['order_id'])).'<br><span class="muted">'.e((string)($d['voucher_number']?:'ohne Nummer')).'</span></td><td>'.(int)$d['orders'].'</td><td>'.money((int)$d['gross_cents']).'</td><td>'.yw_lexware_doc_badge($d).'</td></tr>';
        echo '</tbody></table></div>';
    }
    echo '</section>';
}

/** Admin-Seite System → Lexware Office. */
function yw_lexware_admin_page(): void {
    $enabled=yw_lexware_enabled();$base=yw_lexware_base();$settings=yw_lexware_settings();
    $profile=json_decode(current_site_setting('lexware_profile_json',''),true);
    echo '<section class="card"><div class="title-row"><div><h2>Verbindung</h2><p class="muted">Offizielle Lexware Public API · höchstens 2 Anfragen je Sekunde · Rechnungen werden nur als Entwurf angelegt.</p></div><span class="badge '.($enabled?'approved':'pending').'">'.($enabled?'API-Schlüssel hinterlegt':'Nicht eingerichtet').'</span></div>';
    if(!yw_lexware_base_ok($base))echo '<div class="flash error">lexware_api_base ist ungültig. Erlaubt ist nur HTTPS (HTTP nur für einen lokalen Testserver).</div>';
    echo '<p><strong>API-Adresse:</strong> '.e($base).($base!==YW_LEXWARE_DEFAULT_BASE?' <span class="badge pending">abweichend vom Standard</span>':'').'</p>';
    if(!$enabled)echo '<p class="muted">Schlüssel in Lexware Office unter <a href="'.e(YW_LEXWARE_APP_URL.'/addons/public-api').'" target="_blank" rel="noopener noreferrer">Erweiterungen → Public API</a> erzeugen und in der config.php als <code>lexware_api_key</code> eintragen. Details: START-HIER-V10.txt.</p>';
    if(is_array($profile))echo '<p><strong>Verbunden mit:</strong> '.e((string)($profile['companyName']??'')).' · Steuerart '.e((string)($profile['taxType']??'')).((!empty($profile['smallBusiness']))?' · <span class="badge rejected">Kleinunternehmer – Rechnungen mit Steuer passen nicht</span>':'').'<br><span class="muted">Geprüft am '.e(date('d.m.Y H:i',(int)strtotime(current_site_setting('lexware_profile_checked_at','now')))).'</span></p>';
    echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="lexware_test"><input type="hidden" name="return" value="lexware"><button class="btn primary" '.($enabled?'':'disabled').'>Verbindung testen</button></form></section>';

    echo '<section class="card"><h2>Einstellungen für Rechnungsentwürfe</h2><form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="lexware_settings"><input type="hidden" name="return" value="lexware">'
        .'<div class="grid2"><label>Belegtitel <small>höchstens 25 Zeichen</small><input name="title" maxlength="25" value="'.e($settings['title']).'"></label><label>Einheit der Positionen<input name="unit" maxlength="40" value="'.e($settings['unit']).'"></label></div>'
        .'<label>Einleitung <small>davor steht automatisch Auftrag bzw. Monat</small><textarea name="introduction" rows="2" maxlength="1500">'.e($settings['introduction']).'</textarea></label>'
        .'<label>Schlusstext <small>leer = Standard aus Lexware</small><textarea name="remark" rows="2" maxlength="2000">'.e($settings['remark']).'</textarea></label>'
        .'<label>Standard für Sammelrechnungen<select name="grouping">';
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
            $what=$d['kind']==='monthly'?'Sammelrechnung '.yw_payroll_month_label((string)$d['period']).($d['group_label']?' · '.$d['group_label']:'').' ('.(int)$d['orders'].' Aufträge)':'<a href="'.e(yw_order_url((int)$d['order_id'])).'">'.e(yw_order_number((int)$d['order_id'])).'</a>';
            echo '<tr><td>'.e(date('d.m.Y H:i',(int)strtotime((string)$d['created_at']))).'</td><td>'.e($d['legal_name']).'</td><td>'.($d['kind']==='monthly'?e($what):$what).'<br><span class="muted">'.e((string)($d['voucher_number']?:'ohne Nummer')).'</span>'.(!empty($d['last_error'])?'<br><span class="muted">'.e((string)$d['last_error']).'</span>':'').'</td><td>'.money((int)$d['gross_cents']).'</td><td>'.yw_lexware_doc_badge($d).'<br>'.yw_lexware_doc_link($d).'</td><td>'.yw_lexware_doc_actions($d,'lexware').'</td></tr>';
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
    echo '<hr><h4>Monats-Sammelrechnung (Entwurf)</h4><form method="get" class="filter-bar"><input type="hidden" name="tab" value="lexware"><input type="hidden" name="company" value="'.$id.'"><label>Monat der Freigabe<input type="month" name="monat" value="'.e($month).'"></label><label>Aufteilung<select name="gruppierung">';
    foreach(yw_lexware_grouping_options() as $k=>$l)echo '<option value="'.e($k).'" '.($grouping===$k?'selected':'').'>'.e($l).'</option>';
    echo '</select></label><button class="btn">Vorschau</button></form>';
    [$open,$billed]=yw_lexware_month_orders($id,$month);
    if($billed)echo '<p class="muted">'.count($billed).' Auftr'.(count($billed)===1?'ag ist':'äge sind').' in diesem Monat bereits in Lexware-Belegen und werden nicht erneut berechnet.</p>';
    if(!$open){echo '<p class="muted">Keine offenen, freigegebenen Aufträge in '.e(yw_payroll_month_label($month)).'.</p>';return;}
    [$orders,$items]=yw_lexware_load_orders($id,$open);
    $stored=array_sum(array_map(fn($o)=>(int)$o['gross_total_cents'],$orders));
    foreach(yw_lexware_month_batches($orders,$grouping) as $label=>$ids){
        $batchOrders=array_values(array_filter($orders,fn($o)=>in_array((int)$o['id'],$ids,true)));
        $built=yw_lexware_build_lines($batchOrders,$items,['grouping'=>$grouping==='cost_center'?'cost_center':'none','employee_names'=>$settings['employee_names']==='1','unit'=>$settings['unit']]);
        $t=yw_invoice_totals($built['calc']);
        echo '<div class="card" style="margin-top:12px"><h4>'.e($label!==''?$label:'Sammelrechnung '.yw_payroll_month_label($month)).' · '.count($ids).' Auftr'.(count($ids)===1?'ag':'äge').' · '.count($built['lineItems']).' Positionen</h4>';
        echo '<div class="table-wrap"><table class="table"><thead><tr><th>Position</th><th>Menge</th><th>Netto je Einheit</th><th>USt.</th><th>Netto</th></tr></thead><tbody>';
        foreach($built['lineItems'] as $li){
            if($li['type']==='text'){echo '<tr><td colspan="5"><strong>'.e($li['name']).'</strong> <span class="muted">'.e($li['description']).'</span></td></tr>';continue;}
            $unitNet=(int)round((float)$li['unitPrice']['netAmount']*100);
            echo '<tr><td>'.e($li['name']).'<br><span class="muted">'.e($li['description']).'</span></td><td>'.(int)$li['quantity'].' '.e($li['unitName']).'</td><td>'.money($unitNet).'</td><td>'.e((string)$li['unitPrice']['taxRatePercentage']).' %</td><td>'.money($unitNet*(int)$li['quantity']).'</td></tr>';
        }
        echo '</tbody></table></div><p><strong>Netto</strong> '.money($t['net']);
        foreach($t['by_rate'] as $bp=>$r)echo ' · <strong>USt. '.e(rtrim(rtrim(number_format($bp/100,2,',',''),'0'),',')).' %</strong> '.money($r['tax']).' auf '.money($r['net']);
        echo ' · <strong>Brutto</strong> '.money($t['gross']).'</p>';
        echo '<form method="post">'.$hidden.'<input type="hidden" name="action" value="lexware_invoice_monthly"><input type="hidden" name="month" value="'.e($month).'"><input type="hidden" name="grouping" value="'.e($grouping).'"><input type="hidden" name="batch" value="'.e($label).'"><input type="hidden" name="order_ids" value="'.e(implode(',',$ids)).'"><button class="btn primary" '.(empty($company['lexware_contact_id'])?'disabled title="Zuerst Kontakt verknüpfen"':'').'>Entwurf in Lexware anlegen</button></form></div>';
    }
    $sumBatches=0;foreach(yw_lexware_month_batches($orders,$grouping) as $label=>$ids){$sumBatches+=yw_invoice_totals(yw_lexware_build_lines(array_values(array_filter($orders,fn($o)=>in_array((int)$o['id'],$ids,true))),$items,['grouping'=>'none'])['calc'])['gross'];}
    if($sumBatches!==$stored)echo '<p class="muted">Summe der einzelnen Bestellungen: '.money($stored).'. Weil Lexware die Steuer je Steuersatz auf die Rechnungssumme rechnet, kann die Sammelrechnung um wenige Cent abweichen ('.money(abs($sumBatches-$stored)).').</p>';
}

/* ---------- Aktionen ---------- */

function yw_lexware_return_url(string $return): string {
    if(preg_match('/^order:(\d+)$/',$return,$m))return yw_order_url((int)$m[1]).'#lexware';
    if(preg_match('/^company:(\d+)$/',$return,$m))return '/admin?tab=lexware&company='.(int)$m[1];
    return '/admin?tab=lexware';
}

function yw_lexware_admin_action(string $action): never {
    require_admin();
    $return=yw_lexware_return_url((string)($_POST['return']??''));
    try{
        $companyId=(int)($_POST['company_id']??0);
        switch($action){
            case 'lexware_test':
                $p=yw_lexware_request('GET','/v1/profile');
                set_site_setting('lexware_profile_json',json_encode(['companyName'=>(string)($p['companyName']??''),'organizationId'=>(string)($p['organizationId']??''),'taxType'=>(string)($p['taxType']??''),'smallBusiness'=>!empty($p['smallBusiness']),'businessFeatures'=>array_values(array_filter((array)($p['businessFeatures']??[]),'is_string'))],JSON_UNESCAPED_UNICODE));
                set_site_setting('lexware_profile_checked_at',date('Y-m-d H:i:s'));
                audit_log('lexware.connection_tested','system',null,['company'=>(string)($p['companyName']??''),'tax_type'=>(string)($p['taxType']??'')]);
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
                $month=yw_payroll_month($_POST['month']??'');$grouping=(string)($_POST['grouping']??'none');if(!isset(yw_lexware_grouping_options()[$grouping]))$grouping='none';
                $batch=is_string($_POST['batch']??null)?$_POST['batch']:'';
                $posted=array_values(array_filter(array_map('intval',explode(',',is_string($_POST['order_ids']??null)?$_POST['order_ids']:''))));
                [$open]=yw_lexware_month_orders($companyId,$month);
                [$orders]=yw_lexware_load_orders($companyId,$open);
                $batches=yw_lexware_month_batches($orders,$grouping);
                $ids=$batches[$batch]??[];
                sort($ids);sort($posted);
                if(!$ids || $ids!==$posted)throw new DomainException('Die Aufträge haben sich seit der Vorschau geändert. Bitte Vorschau neu laden und prüfen.');
                $company=yw_lexware_company($companyId);
                $doc=yw_lexware_create_invoice($companyId,$ids,'monthly',$month,$batch,yw_lexware_month_meta($company,$month,count($ids),$batch,$grouping));
                audit_log('lexware.invoice_created','company',$companyId,['company_id'=>$companyId,'document_id'=>(int)$doc['id'],'period'=>$month,'orders'=>count($ids),'voucher_number'=>$doc['voucher_number']??null]);
                flash('success','Sammelrechnung '.yw_payroll_month_label($month).($batch!==''?' ('.$batch.')':'').' als Entwurf in Lexware angelegt ('.count($ids).' Aufträge, '.money((int)$doc['gross_cents']).').');
                $return='/admin?tab=lexware&company='.$companyId.'&monat='.$month.'&gruppierung='.$grouping;
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
                $ids=db()->prepare('SELECT order_id FROM yw_lexware_document_orders WHERE document_id=?');$ids->execute([$docId]);$orderIds=$ids->fetchAll(PDO::FETCH_COLUMN);
                db()->beginTransaction();
                db()->prepare('DELETE FROM yw_lexware_document_orders WHERE document_id=?')->execute([$docId]);
                db()->prepare('DELETE FROM yw_lexware_documents WHERE id=?')->execute([$docId]);
                db()->commit();
                foreach($orderIds as $oid)audit_log('lexware.document_released','order',(int)$oid,['company_id'=>(int)$doc['company_id'],'lexware_id'=>$doc['lexware_id'],'status'=>$doc['status']]);
                audit_log('lexware.document_released','company',(int)$doc['company_id'],['company_id'=>(int)$doc['company_id'],'lexware_id'=>$doc['lexware_id'],'orders'=>count($orderIds)]);
                flash('success','Verknüpfung entfernt. Die Aufträge können erneut abgerechnet werden.');
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

/** Zeile für die Betriebsprüfung im Systemstatus. */
function yw_lexware_status_line(): array {
    if(!yw_lexware_enabled())return ['! Lexware Office manuell','Noch kein API-Schlüssel: Rechnungen werden in Lexware von Hand erstellt (System → Lexware Office).',false];
    $checked=current_site_setting('lexware_profile_checked_at','');
    return [($checked!==''?'✓':'!').' Lexware Office verbunden','Rechnungsentwürfe aus freigegebenen Aufträgen'.($checked!==''?' · zuletzt geprüft '.date('d.m.Y',(int)strtotime($checked)):' · Verbindung noch nicht getestet'),$checked!==''];
}
