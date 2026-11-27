<?php
declare(strict_types=1);

/*
 * 10.27: Spam-Schutz für das Anfrageformular – ohne Fremddienst, ohne zusätzliche Cookies.
 *
 * Bausteine:
 *  1. Verstecktes Feld „website“ (Honigtopf): ausgefüllt = Bot, still verworfen (wie bisher).
 *  2. Signiertes Formular-Ticket mit Zeitpunkt: zu schnell abgeschickt (< 3 s), abgelaufen oder gefälscht = Verdacht.
 *  3. Kleine Rechenaufgabe im Browser (Proof of Work, SHA-256): löst assets/web.js unsichtbar beim Ausfüllen.
 *     Einfache Bots ohne JavaScript lösen sie nicht. Jedes Ticket gilt nur einmal.
 *  4. Inhaltsprüfung: Links in Name/Firma, viele Links in der Nachricht, typische Spam-Wörter, Wegwerf-Adressen,
 *     E-Mail-Domain ohne Mailserver, auffällig viele Anfragen.
 *
 * Verdächtige Anfragen werden NICHT abgelehnt: Sie landen in der Verwaltung unter „Spam-Verdacht“, ohne Mail an uns,
 * ohne Eingangsbestätigung an die angegebene Adresse und ohne Probeshop. Der Absender sieht dieselbe Dankeseite.
 * Nach 30 Tagen werden sie automatisch gelöscht (yw_cleanup_expired_security_rows).
 */

const YW_GUARD_BITS=16;          // Schwierigkeit der Rechenaufgabe: im Mittel 65.000 Versuche, am PC deutlich unter 1 s
const YW_GUARD_MIN_SECONDS=3;    // Schneller füllt kein Mensch ein Formular aus
const YW_GUARD_MAX_SECONDS=172800; // Ticket 48 Stunden gültig (Tab über Nacht offen)
const YW_SPAM_KEEP_DAYS=30;

function yw_guard_key(): string {
    return hash('sha256','yw-form-guard|'.(string)(cfg('app_secret')?:'').'|'.(string)cfg('db_pass'),true);
}

/** Verstecktes Feld mit Ticket und Rechenaufgabe; Ausgabe innerhalb des <form>. */
function yw_guard_fields(string $form='inquiry'): string {
    $payload=$form.'.'.time().'.'.bin2hex(random_bytes(12)).'.'.YW_GUARD_BITS;
    $sig=rtrim(strtr(base64_encode(hash_hmac('sha256',$payload,yw_guard_key(),true)),'+/','-_'),'=');
    return '<input type="hidden" name="yw_guard" value="'.e($payload.'.'.$sig).'" data-yw-guard><input type="hidden" name="yw_guard_n" value="">'
        .'<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>';
}

/** Anzahl führender Null-Bits eines Binär-Hashes. */
function yw_guard_zero_bits(string $hash): int {
    $bits=0;
    for($i=0,$n=strlen($hash);$i<$n;$i++){
        $b=ord($hash[$i]);
        if($b===0){$bits+=8;continue;}
        for($m=0x80;$m>0&&!($b&$m);$m>>=1)$bits++;
        break;
    }
    return $bits;
}

/**
 * Prüft Ticket und Rechenaufgabe. Liefert eine Liste von Gründen (leer = in Ordnung).
 * $now nur für Tests.
 */
function yw_guard_check(string $form,array $post,?int $now=null): array {
    $now=$now??time();
    $ticket=is_string($post['yw_guard']??null)?$post['yw_guard']:'';
    $nonce=is_string($post['yw_guard_n']??null)?$post['yw_guard_n']:'';
    $parts=explode('.',$ticket);
    if(count($parts)!==5)return ['Formular ohne gültiges Sicherheitsticket abgeschickt'];
    [$f,$t,$salt,$bits,$sig]=$parts;
    $payload=$f.'.'.$t.'.'.$salt.'.'.$bits;
    $expected=rtrim(strtr(base64_encode(hash_hmac('sha256',$payload,yw_guard_key(),true)),'+/','-_'),'=');
    if(!hash_equals($expected,$sig)||$f!==$form||!ctype_digit($t)||!ctype_digit($bits))return ['Sicherheitsticket gefälscht oder beschädigt'];
    $reasons=[];
    $age=$now-(int)$t;
    if($age<YW_GUARD_MIN_SECONDS)$reasons[]='Schon nach '.max(0,$age).' '.(max(0,$age)===1?'Sekunde':'Sekunden').' abgeschickt (zu schnell für einen Menschen)';
    elseif($age>YW_GUARD_MAX_SECONDS)$reasons[]='Formular war länger als 48 Stunden offen';
    if($nonce===''||!ctype_digit($nonce)||strlen($nonce)>12)$reasons[]='Rechenaufgabe im Browser nicht gelöst (kein JavaScript – typisch für Bots)';
    elseif(yw_guard_zero_bits(hash('sha256',$payload.':'.$nonce,true))<(int)$bits)$reasons[]='Rechenaufgabe im Browser falsch gelöst';
    elseif(!yw_guard_mark_used($salt))$reasons[]='Dasselbe Formular wurde mehrfach abgeschickt';
    return $reasons;
}

/** Ticket nur einmal verwenden. false = schon benutzt. Ohne Tabelle (update.php noch nicht gelaufen) immer true. */
function yw_guard_mark_used(string $salt): bool {
    if(!yw_spam_schema_ready())return true;
    try{
        $q=db()->prepare('INSERT IGNORE INTO yw_form_guard_used(ticket_hash,used_at) VALUES(?,NOW())');
        $q->execute([hash('sha256',$salt)]);
        return $q->rowCount()===1;
    }catch(Throwable $e){error_log('WorkwearCenter form guard: '.$e->getMessage());return true;}
}

/** Datenbank kennt Spam-Status und Ticket-Tabelle (ab update.php 10.27.0). */
function yw_spam_schema_ready(): bool {
    static $ready=null;
    if($ready!==null)return $ready;
    try{db()->query('SELECT spam_reason FROM inquiries LIMIT 0');db()->query('SELECT ticket_hash FROM yw_form_guard_used LIMIT 0');return $ready=true;}
    catch(Throwable $e){return $ready=false;}
}

/** Wörter, die in echten Arbeitskleidungs-Anfragen praktisch nie vorkommen. */
function yw_spam_words(): array {
    return ['viagra','cialis','casino','porn','sex dating','bitcoin','crypto','forex','loan offer','backlink','seo service','seo services','rank your website','first page of google','guest post','web traffic','increase traffic','link building','telegram:','whatsapp:+','binary option','escort','payday','unsubscribe','dear sir','dear webmaster','we are offering','our services include','lead generation'];
}

function yw_spam_disposable_domains(): array {
    return ['mailinator.com','guerrillamail.com','guerrillamail.de','sharklasers.com','10minutemail.com','10minutemail.de','tempmail.com','temp-mail.org','temp-mail.io','tempmail.net','yopmail.com','yopmail.fr','trashmail.com','trashmail.de','trashmail.net','wegwerfmail.de','wegwerfmail.net','spambog.com','spambog.de','einrot.com','throwawaymail.com','getnada.com','maildrop.cc','dispostable.com','fakeinbox.com','mintemail.com','emailondeck.com','mohmal.com','moakt.com','tmail.ws','byom.de','muellmail.com','discard.email','mailnesia.com','mytemp.email','burnermail.io','spam4.me','grr.la','emailfake.com','tempail.com','tempr.email','inboxkitten.com','minuteinbox.com'];
}

/** Hat die Domain einen Mailserver? null = DNS am Server nicht nutzbar (dann nicht werten). */
function yw_spam_mail_domain_ok(string $email): ?bool {
    $domain=strtolower((string)substr(strrchr($email,'@')?:'',1));
    if($domain===''||!function_exists('checkdnsrr'))return null;
    static $dnsWorks=null;
    if($dnsWorks===null)$dnsWorks=@checkdnsrr('gmail.com','MX')||@checkdnsrr('web.de','MX');
    if(!$dnsWorks)return null;
    return @checkdnsrr($domain.'.','MX')||@checkdnsrr($domain.'.','A')||@checkdnsrr($domain.'.','AAAA');
}

/**
 * Inhaltliche Prüfung einer Anfrage. $f: company,name,email,phone,message.
 * Liefert Gründe (leer = unauffällig). $dns=false schaltet die DNS-Abfrage ab (Tests).
 */
function yw_spam_content_reasons(array $f,bool $dns=true): array {
    $reasons=[];
    $link='~(?:https?://|www\.|\[url|<a\s|\.(?:ru|cn|top|xyz|click|online|site|store|shop)/)~i';
    foreach(['company'=>'Firmenname','name'=>'Ansprechpartner','phone'=>'Telefon'] as $k=>$label){
        if(preg_match($link,(string)($f[$k]??'')))$reasons[]='Link im Feld „'.$label.'“';
    }
    $message=(string)($f['message']??'');
    if(preg_match_all('~(?:https?://|www\.)~i',$message)>=2)$reasons[]='Mehrere Links in der Nachricht';
    $all=mb_strtolower(implode(' ',[(string)($f['company']??''),(string)($f['name']??''),$message]));
    foreach(yw_spam_words() as $w)if(str_contains($all,$w)){$reasons[]='Typisches Spam-Wort „'.$w.'“';break;}
    // Viel kyrillische/asiatische Schrift: Interessenten schreiben uns auf Deutsch oder Englisch.
    if(preg_match_all('/[\p{Cyrillic}\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{Thai}\p{Arabic}]/u',$all)>=15)$reasons[]='Überwiegend fremde Schrift';
    $email=strtolower((string)($f['email']??''));
    $domain=(string)substr(strrchr($email,'@')?:'',1);
    if($domain!==''&&in_array($domain,yw_spam_disposable_domains(),true))$reasons[]='Wegwerf-E-Mail-Adresse ('.$domain.')';
    elseif($dns&&yw_spam_mail_domain_ok($email)===false)$reasons[]='Für die E-Mail-Domain gibt es keinen Mailserver';
    if(trim((string)($f['company']??''))!==''&&mb_strtolower(trim((string)$f['company']))===mb_strtolower(trim((string)($f['name']??'')))&&!preg_match('/\s/u',trim((string)$f['name'])))$reasons[]='Firmenname und Ansprechpartner sind dasselbe einzelne Wort';
    return $reasons;
}

/** Häufung: dieselbe Adresse oder auffällig viele Anfragen insgesamt. */
function yw_spam_volume_reasons(string $email): array {
    $reasons=[];
    try{
        $q=db()->prepare('SELECT COUNT(*) FROM inquiries WHERE email=? AND created_at>DATE_SUB(NOW(),INTERVAL 24 HOUR)');$q->execute([$email]);
        if((int)$q->fetchColumn()>=2)$reasons[]='Dieselbe E-Mail-Adresse hat heute schon zweimal angefragt';
        $spamSql=yw_spam_schema_ready()?" AND status<>'spam'":'';
        if((int)db()->query('SELECT COUNT(*) FROM inquiries WHERE created_at>DATE_SUB(NOW(),INTERVAL 1 HOUR)'.$spamSql)->fetchColumn()>=10)$reasons[]='Mehr als zehn Anfragen in der letzten Stunde';
    }catch(Throwable $e){error_log('WorkwearCenter spam volume: '.$e->getMessage());}
    return $reasons;
}

/** Zähler für die Verwaltung: abgewehrte Bots (Honigtopf) und zurückgehaltene Anfragen je Tag, 30 Tage. */
function yw_spam_count(string $kind): void {
    try{
        $stats=json_decode(current_site_setting('spam_stats',''),true);$stats=is_array($stats)?$stats:[];
        $day=date('Y-m-d');$stats[$day][$kind]=(int)($stats[$day][$kind]??0)+1;
        $limit=date('Y-m-d',strtotime('-'.YW_SPAM_KEEP_DAYS.' days'));
        foreach(array_keys($stats) as $d)if(!is_string($d)||$d<$limit)unset($stats[$d]);
        set_site_setting('spam_stats',(string)json_encode($stats));
    }catch(Throwable $e){}
}

/** Summen der letzten 30 Tage: ['blocked'=>n,'held'=>n]. */
function yw_spam_stats(): array {
    $stats=json_decode(current_site_setting('spam_stats',''),true);$sum=['blocked'=>0,'held'=>0];
    if(is_array($stats))foreach($stats as $day)if(is_array($day))foreach($sum as $k=>$v)$sum[$k]+=(int)($day[$k]??0);
    return $sum;
}

/** Aufräumen: Spam-Verdacht nach 30 Tagen, benutzte Tickets nach 3 Tagen. */
function yw_spam_prune(): void {
    if(!yw_spam_schema_ready())return;
    try{
        $ids=db()->query("SELECT id FROM inquiries WHERE status='spam' AND created_at<DATE_SUB(NOW(),INTERVAL ".YW_SPAM_KEEP_DAYS." DAY) LIMIT 5000")->fetchAll(PDO::FETCH_COLUMN);
        foreach($ids as $id){
            // Von Hand als Spam markierte Anfragen können Probeshop und Artikelliste haben.
            if(function_exists('yw_probeshop_delete')){$q=db()->prepare('SELECT id FROM yw_probeshops WHERE inquiry_id=?');$q->execute([(int)$id]);foreach($q->fetchAll(PDO::FETCH_COLUMN) as $p)yw_probeshop_delete((int)$p,false);}
            if(function_exists('yw_inquiry_attachment_delete'))yw_inquiry_attachment_delete((int)$id);
            db()->prepare("DELETE FROM inquiries WHERE id=? AND status='spam'")->execute([(int)$id]);
        }
        db()->exec('DELETE FROM yw_form_guard_used WHERE used_at<DATE_SUB(NOW(),INTERVAL 3 DAY)');
    }catch(Throwable $e){error_log('WorkwearCenter spam prune: '.$e->getMessage());}
}
