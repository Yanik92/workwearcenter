<?php
declare(strict_types=1);

/*
 * 10.22: Impressum und Datenschutzerklärung.
 * Die Datenschutzerklärung wird aus dem tatsächlichen Verhalten des Systems erzeugt (Cookies, Fristen,
 * Schnittstellen) und ändert sich mit jedem Update mit. Betreiberangaben kommen aus Admin → Inhalte.
 * Wer einen eigenen, z. B. anwaltlich erstellten Text verwenden will, stellt dort auf „eigener Text“ um.
 */

const YW_PRIVACY_VERSION_DATE = '16.10.2026'; // 10.23: Du-Form, neue Felder im Anfrageformular, Artikelliste
const YW_DEFAULT_HOSTER = 'Greenmark IT GmbH (do.de), Leinstraße 3, 31061 Alfeld (Leine), Deutschland';

/** Betreiberangaben aus Admin → Inhalte (mit den bisherigen Standardwerten). */
function yw_operator(): array {
    $s=fn(string $k,string $d='')=>trim(current_site_setting($k,$d));
    return [
        'company'=>$s('imprint_company','Yanik Textildruck'),
        'owner'=>$s('imprint_owner','Jens Yanik'),
        'street'=>$s('imprint_street','Geseker Straße 19'),
        'city'=>$s('imprint_city','33154 Salzkotten'),
        'phone'=>$s('imprint_phone','05258 / 2090004'),
        'email'=>$s('imprint_email','info@yanik-textildruck.de'),
        'vat'=>$s('imprint_vat_id',''),
        'wid'=>$s('imprint_wid',''),
        'register'=>$s('imprint_register',''),
    ];
}

/** Telefonnummer als tel:-Ziel (nur Ziffern und führendes +). */
function yw_tel_href(string $phone): string {
    $digits=preg_replace('/[^\d+]/','',$phone)??'';
    return $digits!==''?'tel:'.$digits:'';
}

function yw_operator_address_html(array $o): string {
    return '<strong>'.e($o['company']).'</strong>'.($o['owner']!==''?'<br>Inhaber: '.e($o['owner']):'').'<br>'.e($o['street']).'<br>'.e($o['city']).'<br>Deutschland';
}

function yw_operator_contact_html(array $o): string {
    $lines=[];
    if($o['phone']!==''){$tel=yw_tel_href($o['phone']);$lines[]='Telefon: '.($tel!==''?'<a href="'.e($tel).'">'.e($o['phone']).'</a>':e($o['phone']));}
    if($o['email']!=='')$lines[]='E-Mail: <a href="mailto:'.e($o['email']).'">'.e($o['email']).'</a>';
    return implode('<br>',$lines);
}

/** Inhalt des Impressums (§ 5 DDG, § 18 Abs. 2 MStV, § 36 VSBG). */
function yw_imprint_html(): string {
    $o=yw_operator();
    $h='<p class="w-legal-lead">WorkwearCenter (workwearcenter.de) ist ein Angebot von '.e($o['company']).'.</p>';
    $h.='<h2>Angaben gemäß § 5 DDG</h2><p>'.yw_operator_address_html($o).'</p>';
    $h.='<h2>Kontakt</h2><p>'.yw_operator_contact_html($o).'<br>Anfrageformular: <a href="/anfrage">workwearcenter.de/anfrage</a></p>';
    if($o['vat']!=='')$h.='<h2>Umsatzsteuer-Identifikationsnummer</h2><p>Umsatzsteuer-Identifikationsnummer gemäß § 27a Umsatzsteuergesetz: '.e($o['vat']).'</p>';
    if($o['wid']!=='')$h.='<h2>Wirtschafts-Identifikationsnummer</h2><p>Wirtschafts-Identifikationsnummer gemäß § 139c Abgabenordnung: '.e($o['wid']).'</p>';
    if($o['register']!=='')$h.='<h2>Registereintrag</h2><p>'.e($o['register']).'</p>';
    $h.='<h2>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h2><p>'.e($o['owner']!==''?$o['owner']:$o['company']).', Anschrift wie oben.</p>';
    $h.='<h2>Verbraucherstreitbeilegung</h2><p>Das WorkwearCenter richtet sich ausschließlich an Unternehmen. Wir sind nicht bereit und nicht verpflichtet, an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle teilzunehmen.</p>';
    $h.='<h2>Haftung für Inhalte und Links</h2><p>Wir erstellen die Inhalte dieser Website mit Sorgfalt. Für Inhalte externer Websites, auf die wir verlinken (zum Beispiel Sendungsverfolgung der Paketdienste), sind ausschließlich deren Betreiber verantwortlich. Werden uns Rechtsverletzungen bekannt, entfernen wir die betroffenen Inhalte oder Links umgehend.</p>';
    $h.='<h2>Urheberrecht</h2><p>Texte, Fotos und Gestaltung dieser Website sind urheberrechtlich geschützt. Produktfotos stammen teilweise von den jeweiligen Herstellern. Firmenlogos in Kundenshops gehören den jeweiligen Unternehmen und werden nur in deren Auftrag verwendet.</p>';
    return $h;
}

/** Auswahl: automatisch erzeugte Erklärung oder eigener Text aus Admin → Inhalte. */
function yw_privacy_uses_custom_text(): bool {
    return current_site_setting('privacy_mode','auto')==='custom' && mb_strlen(trim(strip_tags(current_site_setting('privacy_body',''))))>250;
}

/** Monate, nach denen das Aktivitätsprotokoll gelöscht wird (ohne Abrechnungseinträge). */
function yw_privacy_audit_months(): int {
    return function_exists('yw_audit_retention_months')?yw_audit_retention_months():24;
}

/** Datenschutzerklärung nach Art. 13 und 14 DSGVO, passend zu den Funktionen dieser Version. */
function yw_privacy_html(): string {
    $o=yw_operator();
    $hoster=trim(current_site_setting('privacy_hoster',YW_DEFAULT_HOSTER))?:YW_DEFAULT_HOSTER;
    $mailProvider=trim(current_site_setting('privacy_mail_provider',''));
    $logRetention=trim(current_site_setting('privacy_log_retention',''));
    $dpo=trim(current_site_setting('privacy_dpo',''));
    $lexware=function_exists('yw_lexware_enabled')&&yw_lexware_enabled();
    $auditMonths=yw_privacy_audit_months();
    $mailText=$mailProvider!==''?e($mailProvider):'unseren Hoster ('.e($hoster).')';

    $sections=[];
    $sections['ueberblick']=['Das Wichtigste in Kürze',
        '<ul><li>Wir setzen <strong>keine Analyse-, Tracking- oder Werbedienste</strong> ein und binden keine Inhalte anderer Anbieter ein (keine Google-Dienste, keine Social-Media-Plugins, keine Videos oder Karten). Schriftarten liegen auf unserem eigenen Server.</li>'
        .'<li>Es gibt nur technisch notwendige Cookies. Deshalb fragen wir keine Einwilligung per Cookie-Banner ab.</li>'
        .'<li>Website und Shops laufen bei einem Hoster in Deutschland. Wir übermitteln keine personenbezogenen Daten in Länder außerhalb der EU.</li>'
        .'<li>Die Firmenshops für Mitarbeitende betreiben wir im Auftrag des jeweiligen Arbeitgebers. Für diese Daten ist der Arbeitgeber verantwortlich (Abschnitt 8).</li></ul>'];

    $sections['verantwortlicher']=['Verantwortlicher',
        '<p>Verantwortlich für die Datenverarbeitung auf dieser Website ist:</p><p>'.yw_operator_address_html($o).'<br>'.yw_operator_contact_html($o).'</p>'
        .($dpo!==''?'<p>Unseren Datenschutzbeauftragten erreichst du unter: '.e($dpo).'</p>':'<p>Für Fragen zum Datenschutz schreib uns an <a href="mailto:'.e($o['email']).'">'.e($o['email']).'</a>. Einen Datenschutzbeauftragten haben wir nicht benannt, weil dafür bei unserer Betriebsgröße keine gesetzliche Pflicht besteht.</p>')];

    $sections['hosting']=['Hosting und Server-Protokolle',
        '<p>Diese Website und alle Firmenshops werden auf Servern von '.e($hoster).' betrieben. Mit dem Hoster haben wir einen Vertrag zur Auftragsverarbeitung nach Art. 28 DSGVO geschlossen.</p>'
        .'<p>Bei jedem Aufruf verarbeitet der Webserver automatisch technische Daten, die dein Browser übermittelt: IP-Adresse, Datum und Uhrzeit, aufgerufene Adresse, übertragene Datenmenge, Statuscode, verweisende Seite sowie Browser- und Betriebssystemangaben. Das ist nötig, um die Seiten auszuliefern und Angriffe zu erkennen.</p>'
        .'<p>Rechtsgrundlage ist unser berechtigtes Interesse an einem sicheren und stabilen Betrieb (Art. 6 Abs. 1 lit. f DSGVO). '.($logRetention!==''?'Die Server-Protokolle werden '.e($logRetention).' gelöscht.':'Die Server-Protokolle werden vom Hoster nach kurzer Zeit automatisch gelöscht.').'</p>'
        .'<p>Alle Verbindungen sind per TLS (https) verschlüsselt.</p>'];

    $sections['cookies']=['Cookies und Speicherung im Browser',
        '<p>Wir verwenden nur Cookies, die für die angeforderten Funktionen technisch unbedingt erforderlich sind (§ 25 Abs. 2 Nr. 2 TDDDG). Eine Einwilligung ist dafür nicht nötig.</p>'
        .'<table><thead><tr><th>Name</th><th>Zweck</th><th>Speicherdauer</th></tr></thead><tbody>'
        .'<tr><td>yanik_workwear_session</td><td>Sitzungskennung für Anmeldung, Warenkorb und Schutz von Formularen vor Missbrauch</td><td>bis zum Schließen des Browsers; Abmeldung spätestens nach 4 Stunden ohne Aktivität</td></tr>'
        .'<tr><td>yw_lang</td><td>merkt sich die Sprache, die du in einem Firmenshop ausgewählt hast (wird nur bei einer Sprachwahl gesetzt)</td><td>12 Monate</td></tr>'
        .'</tbody></table><p>Weitere Cookies, Local Storage, Fingerprinting oder vergleichbare Techniken setzen wir nicht ein. Die zugehörige Verarbeitung stützen wir auf Art. 6 Abs. 1 lit. b DSGVO (Bereitstellung der angefragten Funktion) und lit. f DSGVO (Sicherheit).</p>'];

    $sections['kontakt']=['Kontakt per E-Mail oder Telefon',
        '<p>Wenn du uns per E-Mail oder Telefon kontaktierst, verarbeiten wir deine Angaben (Name, Kontaktdaten, Inhalt der Nachricht), um dein Anliegen zu bearbeiten. Rechtsgrundlage ist Art. 6 Abs. 1 lit. b DSGVO, wenn es um einen Vertrag oder dessen Anbahnung geht, sonst Art. 6 Abs. 1 lit. f DSGVO (Beantwortung von Anfragen). Wir löschen die Daten, wenn das Anliegen erledigt ist und keine Aufbewahrungspflicht besteht.</p>'];

    $sections['anfrage']=['Anfrageformular und Probeshop',
        '<p>Über das <a href="/anfrage">Anfrageformular</a> erhalten wir Firmenname, deinen Namen als Ansprechpartner und deine E-Mail-Adresse, optional deine Telefonnummer, die ungefähre Zahl der Mitarbeitenden, Branche, ob es schon einheitliche Arbeitskleidung gibt, gewünschte Shop-Funktionen, deine Nachricht, ein Firmenlogo und eine bestehende Artikelliste. Pflichtfelder sind gekennzeichnet; ohne sie können wir die Anfrage nicht bearbeiten.</p>'
        .'<p>Wir verwenden die Angaben, um deine Anfrage zu beantworten und ein Angebot vorzubereiten (Art. 6 Abs. 1 lit. b DSGVO). Die Anfrage geht per E-Mail an uns, Du erhältst eine kurze Eingangsbestätigung ohne Wiederholung deiner Angaben.</p>'
        .'<p>Lädst du ein Logo hoch, erstellen wir daraus einen Probeshop: eine Vorschau mit deinem Firmennamen und Logo auf Musterartikeln. Er ist nur über einen zufällig erzeugten Link erreichbar, wird von Suchmaschinen nicht erfasst und läuft nach 30 Tagen ab. Danach löschen wir Probeshop und Logo automatisch. Gespeichert werden nur Firmenname, Logo, Farbwahl, Ablaufdatum und die Zahl der Aufrufe.</p>'
        .'<p>Eine hochgeladene Artikelliste speichern wir in einem geschützten, nicht öffentlich erreichbaren Bereich unseres Servers. Nur wir können sie abrufen, und wir löschen sie zusammen mit der Anfrage.</p>'
        .'<p>Zum Schutz vor automatisierten Massenanfragen begrenzen wir die Zahl der Anfragen je Anschluss. Dafür speichern wir einen nicht umkehrbaren Prüfwert (Hash) der IP-Adresse für höchstens 24 Stunden (Art. 6 Abs. 1 lit. f DSGVO).</p>'
        .'<p>Anfragen löschen wir automatisch spätestens 24 Monate nach Eingang. Wird aus der Anfrage ein Auftrag, gelten die Fristen aus Abschnitt 7.</p>'];

    $sections['geschaeftskunden']=['Geschäftskunden und Vertragsabwicklung',
        '<p>Für die Zusammenarbeit mit Unternehmen, die bei uns einen Firmenshop betreiben, verarbeiten wir die Daten der Ansprechpartner und Verantwortlichen (Name, dienstliche Kontaktdaten, Funktion) sowie Rechnungs- und Lieferdaten des Unternehmens. Das dient der Durchführung des Vertrags (Art. 6 Abs. 1 lit. b DSGVO) bzw. bei Ansprechpartnern unserem berechtigten Interesse an der Kommunikation mit unseren Kunden (Art. 6 Abs. 1 lit. f DSGVO).</p>'
        .'<p>Freigegebene Bestellungen übertragen wir verschlüsselt in unsere eigene Auftragsverwaltung (Yanik Auftragszentrale). Dort werden sie für Beschaffung, Veredelung (Druck, Stick), Versand und Abrechnung bearbeitet. Ausgeliefert wird an die Firmenadresse oder zur Abholung, nicht an Privatadressen.</p>'
        .($lexware
            ?'<p>Rechnungen erstellen wir mit Lexware Office der Haufe-Lexware GmbH &amp; Co. KG, Munzinger Straße 9, 79111 Freiburg, Deutschland, als unserem Auftragsverarbeiter. Übermittelt werden Rechnungsanschrift, Steuernummern, die bestellten Artikel und Bestellnummern, je nach Einstellung des Kunden auch Namen der Besteller und Kostenstellen.</p>'
            :'<p>Rechnungen erstellen wir mit unserer Buchhaltungssoftware.</p>')
        .'<p>Rechnungen, Buchungsbelege und Geschäftsbriefe bewahren wir nach Handels- und Steuerrecht (§ 257 HGB, § 147 AO) bis zu zehn Jahre auf (Art. 6 Abs. 1 lit. c DSGVO).</p>'];

    $sections['firmenshops']=['Firmenshops für Mitarbeitende',
        '<p><strong>Wer ist verantwortlich?</strong> Die Firmenshops betreiben wir im Auftrag des jeweiligen Arbeitgebers. Für die Daten der Mitarbeitenden ist der Arbeitgeber verantwortlich, wir verarbeiten sie als Auftragsverarbeiter nach Art. 28 DSGVO auf Grundlage eines Vertrags zur Auftragsverarbeitung. Fragen und Anträge zu deinen Rechten richtest du bitte an deinen Arbeitgeber; erreichen sie uns, leiten wir sie weiter und unterstützen ihn bei der Bearbeitung. Diese Hinweise dienen deiner Information.</p>'
        .'<p><strong>Welche Daten?</strong> Je nachdem, was dein Arbeitgeber nutzt: Name, dienstliche E-Mail-Adresse, Abteilung, Kostenstelle, Standort, Personalnummer, Eintritts- und Austrittsdatum, Rolle (Mitarbeitende oder Verantwortliche), Ausstattungsprofil, Budget, gewählte Sprache, Bestellungen mit Artikeln, Größen, Mengen, Preisen und einem etwaigen Eigenanteil, Hinweise zur Bestellung, Umtausch- und Rückgabewünsche mit Begründung, Freigabeentscheidungen sowie der Zeitpunkt der letzten Anmeldung.</p>'
        .'<p><strong>Wofür?</strong> Damit du deine Arbeitskleidung bestellen kannst, Verantwortliche Bestellungen freigeben, wir die Kleidung herstellen und liefern und dein Arbeitgeber die Kosten abrechnen kann. Die rechtliche Grundlage legt dein Arbeitgeber fest, in der Regel § 26 BDSG bzw. Art. 88 DSGVO in Verbindung mit Art. 6 Abs. 1 lit. b DSGVO.</p>'
        .'<p><strong>Wer sieht die Daten?</strong> Die Verantwortlichen deines Arbeitgebers für die Firmenkleidung, unsere Mitarbeitenden in Verwaltung und Produktion sowie die in Abschnitt 9 genannten Dienstleister. Auf Wunsch deines Arbeitgebers stellen wir eine Übersicht der Eigenanteile für die Lohnabrechnung bereit (Monat, Personalnummer, Name, Kostenstelle, Betrag).</p>'
        .'<p><strong>Sicherheit:</strong> Passwörter speichern wir nur als sicheren Hash. Anmelde-Links per E-Mail gelten 15 Minuten und nur einmal, Links zum Zurücksetzen des Passworts höchstens 60 Minuten, Freigabe-Links für Verantwortliche 7 Tage. Wiederholte Fehlversuche werden gebremst.</p>'
        .'<p><strong>Löschung:</strong> Scheidest du aus dem Unternehmen aus oder endet der Vertrag mit deinem Arbeitgeber, wird dein Konto auf dessen Veranlassung anonymisiert: Name, E-Mail-Adresse, Personalnummer, Standort, Beschäftigungsdaten und Anmeldedaten werden entfernt, auch in gespeicherten Bestellübersichten und noch nicht versendeten E-Mails. Bestellungen selbst bleiben ohne Namen für die gesetzlichen Aufbewahrungsfristen erhalten.</p>'];

    $sections['empfaenger']=['Dienstleister und Empfänger',
        '<p>Wir geben personenbezogene Daten nur weiter, wenn das für die genannten Zwecke nötig ist. Alle Dienstleister sind vertraglich zur Vertraulichkeit verpflichtet und arbeiten als Auftragsverarbeiter nach unseren Weisungen:</p><ul>'
        .'<li>Hosting und Server: '.e($hoster).'</li>'
        .'<li>E-Mail-Versand: '.$mailText.'</li>'
        .($lexware?'<li>Rechnungsstellung: Haufe-Lexware GmbH &amp; Co. KG, Freiburg</li>':'')
        .'<li>Versand: der jeweilige Paketdienst erhält Firmenanschrift und Sendungsdaten, keine Mitarbeiterdaten.</li>'
        .'<li>Arbeiten Partnerbetriebe an einem Auftrag mit (zum Beispiel bei Veredelungen), erhalten sie nur die für den Auftrag nötigen Angaben und sind ebenfalls als Auftragsverarbeiter verpflichtet.</li></ul>'
        .'<p>Produktbilder von Lieferanten laden wir vorab auf unseren eigenen Server; dabei werden keine Daten der Besucher übertragen. Eine Übermittlung in Drittländer außerhalb der EU und des EWR findet nicht statt. Daten werden nicht verkauft und nicht zu Werbezwecken weitergegeben.</p>'];

    $sections['emails']=['E-Mails aus dem System',
        '<p>Das System versendet E-Mails, die für die Nutzung nötig sind: Zugangsdaten und Anmelde-Links, Bestellbestätigungen, Freigabeanfragen, Status- und Versandmeldungen, Umtausch, Erinnerungen an offene Freigaben und die Eingangsbestätigung zu Anfragen. Einen Newsletter oder Werbe-Mails versenden wir nicht. Versandt wird über '.$mailText.'.</p>'
        .'<p>Kopien versendeter Systemnachrichten werden nach 90 Tagen gelöscht, nicht zustellbare nach 180 Tagen.</p>'];

    $sections['protokoll']=['Sicherheits- und Änderungsprotokoll',
        '<p>Wichtige Aktionen im System (zum Beispiel Anmeldungen, Freigaben, Änderungen an Konten und Einstellungen) protokollieren wir, um Missbrauch zu erkennen und Änderungen nachvollziehen zu können (Art. 6 Abs. 1 lit. f DSGVO). Gespeichert werden Zeitpunkt, handelnde Person, Art der Aktion und ein nicht umkehrbarer Prüfwert der IP-Adresse, nicht die IP-Adresse selbst. Die Einträge werden nach '.$auditMonths.' Monaten gelöscht; Einträge zu Rechnungen und Lohnabrechnung bewahren wir als Abrechnungsnachweis bis zu zehn Jahre auf.</p>'];

    $sections['speicherdauer']=['Speicherdauer im Überblick',
        '<table><thead><tr><th>Daten</th><th>Löschung</th></tr></thead><tbody>'
        .'<tr><td>Sitzungs-Cookie</td><td>beim Schließen des Browsers</td></tr>'
        .'<tr><td>Sprach-Cookie</td><td>nach 12 Monaten</td></tr>'
        .'<tr><td>Schutz vor Fehlversuchen (Hash der IP-Adresse)</td><td>nach 24 Stunden</td></tr>'
        .'<tr><td>Anmelde- und Passwort-Links</td><td>nach 2 bzw. 7 Tagen (ungültig bereits nach 15 bzw. 60 Minuten)</td></tr>'
        .'<tr><td>Probeshop mit Logo</td><td>30 Tage nach Erstellung</td></tr>'
        .'<tr><td>Anfragen mit Artikelliste</td><td>24 Monate nach Eingang</td></tr>'
        .'<tr><td>Kopien von System-E-Mails</td><td>90 Tage, nicht zustellbare 180 Tage</td></tr>'
        .'<tr><td>Sicherheits- und Änderungsprotokoll</td><td>'.$auditMonths.' Monate, Abrechnungseinträge 10 Jahre</td></tr>'
        .'<tr><td>Konten von Mitarbeitenden</td><td>Anonymisierung auf Veranlassung des Arbeitgebers, spätestens nach Vertragsende</td></tr>'
        .'<tr><td>Bestellungen, Rechnungen, Belege</td><td>nach Ablauf der gesetzlichen Aufbewahrungsfristen (bis zu 10 Jahre)</td></tr>'
        .'</tbody></table>'];

    $sections['rechte']=['Deine Rechte',
        '<p>Du hast jederzeit das Recht auf Auskunft über deine gespeicherten Daten (Art. 15 DSGVO), auf Berichtigung (Art. 16), Löschung (Art. 17), Einschränkung der Verarbeitung (Art. 18) und Datenübertragbarkeit (Art. 20). Hast du eine Einwilligung erteilt, kannst du sie jederzeit mit Wirkung für die Zukunft widerrufen (Art. 7 Abs. 3 DSGVO). Eine formlose Nachricht an <a href="mailto:'.e($o['email']).'">'.e($o['email']).'</a> genügt.</p>'
        .'<div class="w-legal-note"><p><strong>Widerspruchsrecht (Art. 21 DSGVO):</strong> Verarbeiten wir Daten auf Grundlage berechtigter Interessen (Art. 6 Abs. 1 lit. f DSGVO), kannst du dieser Verarbeitung aus Gründen, die sich aus deiner besonderen Situation ergeben, jederzeit widersprechen. Wir verarbeiten die Daten dann nicht mehr, es sei denn, wir können zwingende schutzwürdige Gründe nachweisen, die deine Interessen überwiegen, oder die Verarbeitung dient der Geltendmachung, Ausübung oder Verteidigung von Rechtsansprüchen.</p></div>'
        .'<p>Du hast außerdem das Recht, dich bei einer Datenschutz-Aufsichtsbehörde zu beschweren (Art. 77 DSGVO). Für uns zuständig ist: Landesbeauftragte für Datenschutz und Informationsfreiheit Nordrhein-Westfalen, Kavalleriestraße 2–4, 40213 Düsseldorf, <a href="https://www.ldi.nrw.de" rel="noopener">www.ldi.nrw.de</a>.</p>'];

    $sections['sonstiges']=['Automatisierte Entscheidungen und Änderungen',
        '<p>Wir treffen keine ausschließlich automatisierten Entscheidungen im Sinne von Art. 22 DSGVO und erstellen keine Profile. Budget- und Stückzahlgrenzen in den Firmenshops legt dein Arbeitgeber fest; über Bestellungen entscheiden seine Verantwortlichen.</p>'
        .'<p>Wir passen diese Datenschutzerklärung an, wenn sich unsere Angebote oder die Rechtslage ändern. Es gilt die jeweils hier veröffentlichte Fassung.</p>'];

    $extra=trim(current_site_setting('privacy_extra',''));
    if(trim(strip_tags($extra))!=='')$sections['ergaenzungen']=['Ergänzende Hinweise',rich_text($extra)];

    $h='<p class="w-legal-lead">Wir nehmen den Schutz deiner Daten ernst und verarbeiten sie nur im Rahmen der Datenschutz-Grundverordnung (DSGVO), des Bundesdatenschutzgesetzes (BDSG) und des Telekommunikation-Digitale-Dienste-Datenschutz-Gesetzes (TDDDG). Hier erfährst du, welche Daten wir beim WorkwearCenter wofür verarbeiten.</p>';
    $h.='<nav class="w-legal-toc" aria-label="Inhalt"><strong>Inhalt</strong><ol>';
    foreach($sections as $id=>[$title])$h.='<li><a href="#'.e($id).'">'.e($title).'</a></li>';
    $h.='</ol></nav>';
    $n=0;
    foreach($sections as $id=>[$title,$body]){$n++;$h.='<h2 id="'.e($id).'">'.$n.'. '.e($title).'</h2>'.$body;}
    $h.='<p class="w-legal-date">Stand: '.YW_PRIVACY_VERSION_DATE.'</p>';
    return $h;
}
