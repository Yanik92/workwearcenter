<?php
declare(strict_types=1);

/*
 * 10.23: Branchenseite und drei Themenseiten mit eigener Suchabsicht.
 *  /arbeitskleidung-bestellsystem    – Einkauf/Verwaltung: Bestellweg, Regeln, Freigaben (Prozess)
 *  /mitarbeitershop-arbeitskleidung  – Sicht der Mitarbeitenden: selbst bestellen, Handy, Spind (Nutzung)
 *  /workwear-management              – Verantwortliche: Personen, Profile, Ein-/Austritt, Auswertung (Verwaltung)
 * Startseite = „Firmenshop Arbeitskleidung“. Bewusst keine Seiten für „Firmenbekleidung“ oder
 * „Arbeitskleidung mit Logo“: Dafür rankt yanik-textildruck.de, die Domains sollen sich nicht Konkurrenz machen.
 */

/** Gemeinsamer Aufbau: Kopf, Inhalt (Callback), Querverweise, FAQ, Abschluss. */
function topic_page(string $path,string $label,string $h1,string $lead,array $faqs,callable $body,string $ctaTitle,string $ctaText,string $serviceType='Firmenshop für Arbeitskleidung'): void {
    yw_jsonld_faq($faqs);
    yw_jsonld_service($h1,strip_tags($lead),$serviceType);
    hp_page_start();
    hp_page_hero($label,$h1,'<p>'.$lead.'</p>',true);
    $body();
    topic_related($path);
    w_faq_section($faqs,'Häufige Fragen: '.$label);
    hp_cta_band($ctaTitle,$ctaText);
    hp_page_end();
}

/** Querverweise auf die anderen Themenseiten (interne Verlinkung). */
function topic_related(string $current): void {
    $all=[
        '/so-funktionierts'=>['Ablauf','So funktioniert der Firmenshop','Von der Anfrage bis zur Lieferung in fünf Schritten.'],
        '/arbeitskleidung-bestellsystem'=>['Prozess','Bestellsystem mit Freigabe','Wie eine Bestellung geprüft, freigegeben und produziert wird.'],
        '/mitarbeitershop-arbeitskleidung'=>['Mitarbeitende','Mitarbeitershop','Wie dein Team selbst bestellt, auch am Handy.'],
        '/workwear-management'=>['Verwaltung','Workwear Management','Personen, Profile, Budgets und Auswertungen verwalten.'],
        '/branchen'=>['Branchen','Für deine Branche','Typische Kleidung und Funktionen je Branche.'],
    ];
    unset($all[$current]);
    echo '<section class="w-sec w-sec-related"><div class="w-wrap"><h2 class="w-related-title">Mehr zum Firmenshop</h2><div class="w-related">';
    foreach($all as $href=>[$tag,$t,$x])echo '<a class="w-rel w-reveal" href="'.e($href).'"><small>'.e($tag).'</small><b>'.e($t).'</b><span>'.e($x).'</span>'.w_icon('arrow').'</a>';
    echo '</div></div></section>';
}

/** Zweispaltiger Textblock mit Bildschirmfoto. */
function topic_split(string $eyebrow,string $title,string $textHtml,string $media,bool $rev=false,bool $white=false,string $id=''): void {
    echo '<section class="w-sec'.($white?' w-white':'').'"'.($id!==''?' id="'.e($id).'"':'').'><div class="w-wrap"><div class="w-split'.($rev?' rev':'').'"><div class="w-split-copy w-reveal"><span class="w-eyebrow">'.e($eyebrow).'</span><h2>'.e($title).'</h2>'.$textHtml.'</div><div class="w-split-media w-reveal">'.$media.'</div></div></div></section>';
}

function topic_ticks(array $items): string { $h='<ul class="w-ticks">';foreach($items as $i)$h.='<li>'.e($i).'</li>';return $h.'</ul>'; }

/* ---------- Bestellsystem ---------- */
function topic_ordering_system_page(): void {
    $faqs=[
        ['Was ist ein Bestellsystem für Arbeitskleidung?','Ein Bestellsystem bündelt alle Bestellungen von Arbeitskleidung an einem Ort: Mitarbeitende wählen aus einer festgelegten Kollektion, das System prüft Budgets und Stücklimits, Verantwortliche geben frei und die Bestellung geht ohne Abtippen in die Produktion.'],
        ['Kann eine Bestellung auch ohne Freigabe durchlaufen?','Ja. Ihr legt fest, ob jede Bestellung freigegeben werden muss oder ob Bestellungen innerhalb des Budgets direkt weiterlaufen. Für größere Bestellungen kann zusätzlich eine zweite Freigabe verlangt werden.'],
        ['Wie geben Verantwortliche frei?','Im Firmenshop unter „Freigaben“ oder direkt aus der Benachrichtigungs-E-Mail. Wer ablehnt, schreibt einen kurzen Hinweis, den die bestellende Person sieht. Offene Freigaben werden auf Wunsch per Erinnerung gemeldet.'],
        ['Was passiert nach der Freigabe?','Die Bestellung geht verschlüsselt an unsere eigene Auftragsverwaltung. Dort laufen Beschaffung, Veredelung, Lieferschein und Rechnung. Den Status sieht die bestellende Person im Shop.'],
        ['Können Bestellungen gesammelt werden?','Ja. Freigegebene Bestellungen können bis zu einem festen Sammeltermin zurückgehalten und dann gemeinsam produziert und geliefert werden.'],
        ['Gibt es eine Schnittstelle zu unseren Systemen?','WorkwearCenter hat eine dokumentierte Schnittstelle für Aufträge. Ob und wie sie zu euren Systemen passt, klären wir im Gespräch. Für die Lohnabrechnung gibt es außerdem einen Export der Eigenanteile.'],
    ];
    topic_page('/arbeitskleidung-bestellsystem','Bestellsystem','Bestellsystem für Arbeitskleidung: bestellen, prüfen, freigeben.','Statt Größenlisten, E-Mails und Rückfragen läuft jede Bestellung über einen festen Weg: Mitarbeitende bestellen, der Shop prüft Budget und Limits, Verantwortliche geben frei, wir produzieren. Ohne Abtippen, ohne Medienbruch.',$faqs,function(): void{
        // Der Bestellweg
        echo '<section class="w-sec"><div class="w-wrap">';
        s_head('Der Weg einer Bestellung','Jede Station ist im System hinterlegt. Niemand muss Daten weitergeben oder abschreiben.','Der Prozess');
        echo '<ol class="w-flow">';
        foreach([
            ['cart','Bestellung','Eine Person wählt Artikel, Größe und Menge aus ihrer Kollektion, oder die Teamleitung erfasst eine Team-Bestellung.'],
            ['shield','Prüfung','Der Shop prüft Jahresbudget, Stücklimits je Artikel und einen etwaigen Eigenanteil, bevor die Bestellung abgeschickt wird.'],
            ['check','Freigabe','Die verantwortliche Person gibt im Shop oder per E-Mail frei, große Bestellungen auf Wunsch mit zweiter Freigabe.'],
            ['link','Übergabe','Die freigegebene Bestellung geht direkt in unsere Auftragsverwaltung, mit Person, Größen und hinterlegter Veredelung.'],
            ['needle','Produktion','Wir beschaffen die Ware, sticken oder drucken und prüfen jedes Teil. Der Status wird im Shop sichtbar.'],
            ['truck','Lieferung','Versand an die Firmenadresse oder Abholung, nach Personen gepackt, mit Lieferschein und Rechnung.'],
        ] as $i=>[$ic,$t,$x])echo '<li class="w-reveal"><span class="ic">'.w_icon($ic).'</span><small>'.($i+1).'</small><h3>'.e($t).'</h3><p>'.e($x).'</p></li>';
        echo '</ol></div></section>';

        topic_split('Freigabesystem','Freigaben, die dokumentiert sind.','<p>Bestellungen landen bei den Personen, die ihr festlegt: Teamleitung, Einkauf oder Geschäftsführung. Sie sehen Person, Artikel, Größen und Beträge auf einen Blick und entscheiden mit einem Klick. Abgelehnte Bestellungen bekommen einen Hinweis, damit niemand nachfragen muss.</p>'.topic_ticks(['Freigabe im Shop oder direkt aus der E-Mail','Vier-Augen-Prinzip für größere Bestellungen','Erinnerung an offene Freigaben','Jede Entscheidung mit Zeitpunkt und Person gespeichert']),w_shot('shop-freigabe','Freigabeseite im Firmenshop: offene Bestellungen mit Person, Größen und Betrag sowie Knöpfe zum Freigeben und Ablehnen',1440,1549,'workwearcenter.de/kantenwerk/freigaben',false,'w-shot-crop'),false,true);

        echo '<section class="w-sec"><div class="w-wrap">';
        s_head('Regeln, die der Shop für dich prüft','Einmal bei der Einrichtung festgelegt, bei jeder Bestellung automatisch angewendet.','Budget und Limits');
        echo '<div class="w-rules">';
        foreach([
            ['wallet','Jahresbudget pro Person','Lege fest, wie viel jede Person pro Jahr für Arbeitskleidung verwenden darf, auf Wunsch individuell. Das Budget wird beim Bestellen angezeigt und reserviert, bis entschieden ist.'],
            ['tag','Stücklimits je Artikel','Zum Beispiel höchstens fünf T-Shirts und eine Winterjacke pro Jahr. So bleibt die Ausstattung einheitlich und fair.'],
            ['briefcase','Eigenanteil','Trägt euer Team einen Anteil selbst, zeigt der Shop bei jedem Artikel, was die Firma übernimmt und was die Person zahlt. Für die Lohnabrechnung gibt es einen Export.'],
            ['clock','Sammeltermine','Freigegebene Bestellungen gehen sofort oder gesammelt zu einem festen Termin in die Produktion.'],
        ] as [$ic,$t,$x])echo '<article class="w-rule w-reveal"><span class="ic">'.w_icon($ic).'</span><h3>'.e($t).'</h3><p>'.e($x).'</p></article>';
        echo '</div></div></section>';

        echo '<section class="w-sec w-white"><div class="w-wrap">';
        s_head('Excel und E-Mail im Vergleich','Was sich ändert, wenn Arbeitskleidung über ein Bestellsystem läuft.','Vergleich');
        echo '<div class="w-table-wrap w-reveal"><table class="w-compare-table"><thead><tr><th scope="col">Aufgabe</th><th scope="col">Liste, E-Mail, WhatsApp</th><th scope="col">Bestellsystem WorkwearCenter</th></tr></thead><tbody>';
        foreach([
            ['Größen sammeln','Rundmail, Nachfragen, Abtippen','Jede Person wählt selbst'],
            ['Richtige Artikel','Freitext, Verwechslungen','Nur freigegebene Artikel'],
            ['Logo und Position','Muss jedes Mal mitgeteilt werden','Je Artikel fest hinterlegt'],
            ['Budget im Blick','Nachrechnen in Tabellen','Automatisch geprüft und angezeigt'],
            ['Freigabe','Per Zuruf, nicht dokumentiert','Per Klick, gespeichert'],
            ['Status','„Wann kommt meine Jacke?“','Im Shop sichtbar, mit Sendungsnummer'],
            ['Nachbestellung','Alles von vorne','Ein Klick in der bekannten Größe'],
        ] as [$a,$b,$c])echo '<tr><th scope="row">'.e($a).'</th><td>'.w_icon('x').e($b).'</td><td>'.w_icon('check').e($c).'</td></tr>';
        echo '</tbody></table></div></div></section>';
    },'Bestellungen ohne Listen und Rückfragen.','Wir richten dir das Bestellsystem mit deiner Kollektion, deinen Budgets und deinen Freigaben ein. Probier vorher den Mustershop aus.','Bestellsystem für Arbeitskleidung');
}

/* ---------- Mitarbeitershop ---------- */
function topic_employee_shop_page(): void {
    $faqs=[
        ['Was ist ein Mitarbeitershop für Arbeitskleidung?','Ein geschützter Online-Shop nur für die Mitarbeitenden eines Unternehmens. Jede Person meldet sich mit eigenem Zugang an und bestellt die Arbeitskleidung, die das Unternehmen für sie freigegeben hat, mit Firmenlogo und im Rahmen ihres Budgets.'],
        ['Brauchen Mitarbeitende eine App?','Nein. Der Shop läuft im Browser auf Smartphone, Tablet und Computer. Anmelden geht mit Passwort oder mit einem Link per E-Mail.'],
        ['Was ist mit Mitarbeitenden ohne E-Mail-Adresse?','Für sie erfasst eine berechtigte Person die Bestellung als Team-Bestellung, zum Beispiel die Schicht- oder Teamleitung. Die Auswahl bleibt trotzdem der richtigen Person zugeordnet.'],
        ['In welchen Sprachen gibt es den Shop?','Auf Deutsch, Englisch, Polnisch, Türkisch, Russisch und Rumänisch. Jede Person wählt ihre Sprache selbst, E-Mails kommen in derselben Sprache.'],
        ['Können Mitarbeitende Größen umtauschen?','Ja. Nach dem Versand wird der Umtausch direkt im Shop beantragt, die verantwortliche Person entscheidet darüber. Wie lange ein Umtausch möglich ist, legt ihr fest.'],
        ['Wer sieht die Daten der Mitarbeitenden?','Nur die Verantwortlichen eures Unternehmens und wir für die Produktion. Wir verarbeiten die Daten im Auftrag eures Unternehmens nach Art. 28 DSGVO, die Server stehen in Deutschland.'],
    ];
    topic_page('/mitarbeitershop-arbeitskleidung','Mitarbeitershop','Mitarbeitershop für Arbeitskleidung: Dein Team bestellt selbst.','Jede Person bekommt einen eigenen Zugang zum Firmenshop, sieht nur ihre freigegebene Kleidung, wählt die Größe selbst und weiß jederzeit, wo die Bestellung steht. Ohne App, auch am Handy.',$faqs,function(): void{
        topic_split('Selbst bestellen','Größe wählen statt Größe weitersagen.','<p>Im Mitarbeitershop sieht jede Person die Kollektion, die für sie freigegeben ist, mit Firmenlogo an der richtigen Stelle. Größe und Menge wählt sie selbst. Beim nächsten Mal schlägt der Shop die zuletzt bestellte Größe vor.</p>'.topic_ticks(['Nur freigegebene Artikel mit hinterlegter Veredelung','Größenvorschlag aus der letzten Bestellung','Preis mit Firmen- und Eigenanteil auf einen Blick','Budget immer sichtbar']),w_shot('shop-artikel','Produktansicht im Mitarbeitershop: Hoodie mit Größenwahl, Menge, Stick Brust links und Preisaufteilung',1440,900,'workwearcenter.de/kantenwerk/produkte'),false,true);

        echo '<section class="w-sec w-dark"><div class="w-wrap"><div class="w-split rev"><div class="w-split-copy w-reveal"><span class="w-eyebrow">Am Handy</span><h2>Bestellen in der Pause, in der eigenen Sprache.</h2><p>Viele Mitarbeitende haben keinen Rechner am Arbeitsplatz. Deshalb ist der Shop für das Smartphone gebaut: Anmelden per Link aus der E-Mail, Größe tippen, abschicken. Shop und E-Mails gibt es auf Deutsch, Englisch, Polnisch, Türkisch, Russisch und Rumänisch.</p>'.topic_ticks(['Keine App, kein Passwort nötig (Anmeldelink)','Sechs Sprachen, frei wählbar','Status und Freigabe per E-Mail']).'</div><div class="w-split-media w-phones w-reveal">'.w_phone('shop-handy-start','Startseite des Mitarbeitershops auf dem Smartphone mit Budget und Kollektion').w_phone('shop-handy-artikel','Artikelansicht auf dem Smartphone mit Größenwahl und Preis').'</div></div></div></section>';

        topic_split('Mein Spind','Was habe ich schon? Was kommt noch?','<p>Im Spind sieht jede Person, welche Kleidung sie bekommen hat, mit Größe und Datum. Von dort aus geht die Nachbestellung mit einem Klick, oder der Umtausch wird beantragt, wenn etwas nicht passt. Unter „Bestellungen“ steht der Status jeder Bestellung, vom Freigeben bis zum Versand.</p>'.topic_ticks(['Erhaltene Kleidung mit Größe und Datum','Nachbestellen mit einem Klick','Umtausch und Rückgabe im Shop beantragen','Status mit Sendungsnummer']),w_shot('shop-spind','Mein Spind im Mitarbeitershop: erhaltene Kleidung mit Größe, Datum und Knöpfen zum Nachbestellen und Tauschen',1440,830,'workwearcenter.de/kantenwerk/spind'),true,false);

        echo '<section class="w-sec w-white"><div class="w-wrap">';
        s_head('So kommt dein Team in den Shop','Du entscheidest, wie neue Leute Zugang bekommen.','Zugänge');
        echo '<div class="w-rules">';
        foreach([
            ['mail','Einladung per E-Mail','Du lädst Personen einzeln oder per Liste ein. Sie legen ihr Passwort selbst fest.'],
            ['link','Registrierung über Firmenlink','Mitarbeitende registrieren sich selbst, auf Wunsch mit Firmen-PIN oder Bestätigung durch eine verantwortliche Person.'],
            ['team','Team-Bestellung ohne Zugang','Wer keine E-Mail hat, bekommt die Kleidung über eine Team-Bestellung der Teamleitung.'],
            ['lock','Abmelden und Sperren','Wer das Unternehmen verlässt, wird mit einem Klick deaktiviert. Bestellungen bleiben für die Buchhaltung erhalten.'],
        ] as [$ic,$t,$x])echo '<article class="w-rule w-reveal"><span class="ic">'.w_icon($ic).'</span><h3>'.e($t).'</h3><p>'.e($x).'</p></article>';
        echo '</div></div></section>';
    },'Ein Shop, den dein Team gern benutzt.','Wir richten den Mitarbeitershop mit deiner Kollektion ein und laden dein Team ein. Im Mustershop siehst du vorher, wie er sich anfühlt.','Mitarbeitershop für Arbeitskleidung');
}

/* ---------- Workwear Management ---------- */
function topic_management_page(): void {
    $faqs=[
        ['Was bedeutet Workwear Management?','Workwear Management heißt, die Arbeitskleidung eines Unternehmens planbar zu organisieren: Wer bekommt welche Kleidung, wie viel darf es kosten, wer gibt frei, wer hat was bekommen und was passiert bei Ein- und Austritten. WorkwearCenter bündelt das in einem Firmenshop.'],
        ['Können wir Mitarbeitende per Liste anlegen?','Ja. Verantwortliche laden eine CSV-Liste mit Name, E-Mail und optional Abteilung, Kostenstelle, Standort, Personalnummer und Ausstattungsprofil hoch.'],
        ['Was ist ein Ausstattungsprofil?','Ein Profil legt fest, welche Artikel eine Gruppe bestellen darf, zum Beispiel „Montage“, „Lager“ oder „Büro“. Neue Mitarbeitende bekommen das Profil und sehen sofort die richtige Kleidung.'],
        ['Wie funktioniert der Jahreswechsel beim Budget?','Das Budget gilt für ein Budgetjahr, dessen Startmonat ihr festlegt. Zum neuen Jahr steht das Budget wieder zur Verfügung, auf Wunsch mit einer E-Mail an das Team.'],
        ['Welche Auswertungen gibt es?','Verantwortliche exportieren Bestellungen und Personen als CSV, zum Beispiel nach Abteilung oder Kostenstelle, und auf Wunsch die Eigenanteile für die Lohnabrechnung.'],
        ['Was passiert mit den Daten bei Austritt?','Das Konto wird deaktiviert und auf Wunsch anonymisiert. Bestellungen bleiben ohne Namen für die gesetzlichen Aufbewahrungsfristen erhalten.'],
    ];
    topic_page('/workwear-management','Workwear Management','Workwear Management: Arbeitskleidung im Unternehmen verwalten.','Wer bekommt welche Kleidung, wie viel darf sie kosten, wer hat was bekommen und was passiert bei Ein- und Austritten? WorkwearCenter hält Mitarbeitende, Ausstattung, Budgets und Bestellungen an einem Ort zusammen.',$faqs,function(): void{
        echo '<section class="w-sec"><div class="w-wrap">';
        s_head('Die Verwaltung an einem Ort','Für Personalabteilung, Einkauf und Teamleitung: alles, was sonst in Tabellen verteilt ist.','Was du verwaltest');
        echo '<div class="w-rules three">';
        foreach([
            ['team','Mitarbeitende','Anlegen per Einladung, Registrierung oder CSV-Liste, mit Abteilung, Kostenstelle, Standort und Personalnummer.'],
            ['stack','Ausstattungsprofile','Je Tätigkeit oder Abteilung eine eigene Auswahl, zum Beispiel Montage, Lager oder Büro.'],
            ['wallet','Budgets','Jahresbudget pro Person mit eigenem Startmonat, individuelle Ausnahmen und Stücklimits je Artikel.'],
            ['shield','Rollen und Freigaben','Wer bestellt, wer freigibt, wer für andere bestellen darf, auf Wunsch mit Vier-Augen-Prinzip.'],
            ['refresh','Umtausch und Rückgabe','Anträge laufen im Shop ein, die Frist beginnt mit dem Versand, ihr entscheidet.'],
            ['chart','Auswertungen','Bestellungen und Personen als CSV, Eigenanteile für die Lohnabrechnung.'],
        ] as [$ic,$t,$x])echo '<article class="w-rule w-reveal"><span class="ic">'.w_icon($ic).'</span><h3>'.e($t).'</h3><p>'.e($x).'</p></article>';
        echo '</div></div></section>';

        topic_split('Ein- und Austritte','Neue Leute ausstatten, ohne Liste.','<p>Kommt jemand neu ins Team, legst du die Person an oder sie registriert sich über euren Firmenlink. Mit dem Ausstattungsprofil sieht sie sofort die passende Kleidung und kann im Rahmen ihres Budgets bestellen. Verlässt jemand das Unternehmen, wird das Konto deaktiviert und auf Wunsch anonymisiert.</p>'.topic_ticks(['Eintritts- und Austrittsdatum je Person','Offene Registrierungen direkt auf der Startseite der Verantwortlichen','Anonymisierung nach DSGVO, Bestellungen bleiben erhalten']),w_shot('shop-personen','Personenliste für Verantwortliche im Firmenshop: aktive Konten, offene Freigaben, Registrierungen und Mitarbeitende mit Abteilung und Kostenstelle',1440,900,'workwearcenter.de/kantenwerk/mitarbeiter'),false,true);

        topic_split('Überblick','Wer hat was bekommen?','<p>Jede Bestellung ist einer Person, Abteilung und Kostenstelle zugeordnet. Im Spind jeder Person steht, was sie bekommen hat. Für die Buchhaltung exportierst du Bestellungen nach Zeitraum, für die Lohnabrechnung die Eigenanteile pro Monat.</p>'.topic_ticks(['Auswertung nach Person, Abteilung, Kostenstelle','Export für Buchhaltung und Lohnabrechnung','Bestellstatus aus unserer Produktion, live im Shop']),w_shot('shop-status','Bestellübersicht mit Statusleiste von Freigegeben über In Produktion bis Versendet',1440,766,'workwearcenter.de/kantenwerk/bestellungen'),true,false);
    },'Arbeitskleidung, die sich selbst verwaltet.','Wir richten Personen, Profile, Budgets und Freigaben für dich ein. Du behältst den Überblick, wir kümmern uns um Kleidung und Veredelung.','Workwear Management');
}

/* ---------- Branchen ---------- */
function industries_page(): void {
    $faqs=[
        ['Für welche Branchen eignet sich ein Firmenshop für Arbeitskleidung?','Für alle Unternehmen, in denen mehrere Menschen einheitliche Kleidung tragen: Handwerk, Industrie, Zeitarbeit, Pflege, Kommunen, Feuerwehren und Dienstleister, vom kleinen Betrieb bis zum Mittelständler mit mehreren Standorten.'],
        ['Ab wie vielen Mitarbeitenden lohnt sich ein eigener Shop?','Sobald regelmäßig bestellt wird und jemand Größen, Budgets oder Freigaben im Blick behalten muss. Ob sich der Shop für euch lohnt, besprechen wir gern unverbindlich.'],
        ['Bietet ihr auch Kleidung für Feuerwehren an?','Ja, Mannschaftsbekleidung wie Poloshirts, Hoodies und Jacken mit Wappen und Namensstick. Was genau infrage kommt, stimmen wir mit euch ab.'],
    ];
    yw_jsonld_faq($faqs);
    hp_page_start();
    hp_page_hero('Branchen','Firmenshop für Arbeitskleidung, passend zu deiner Branche.','<p>Ein Handwerksbetrieb braucht anderes als ein Personaldienstleister oder eine Kommune. Hier siehst du, welche Kleidung typisch ist und welche Funktionen des Firmenshops in deiner Branche am meisten helfen.</p>',true);
    echo '<nav class="w-jump" aria-label="Branchen auf dieser Seite"><div class="w-wrap">';
    foreach(w_industry_list() as [$id,$ic,$t])echo '<a href="#'.e($id).'">'.w_icon($ic).e($t).'</a>';
    echo '</div></nav>';
    $data=[
        'handwerk'=>['Handwerk','Kleidung für Baustelle, Werkstatt und Kundentermin','Im Handwerk ist Arbeitskleidung Werkzeug und Visitenkarte zugleich. Gleichzeitig hat niemand Zeit, Größenlisten zu pflegen.',['Arbeitshosen','Arbeitsjacken','Poloshirts','T-Shirts','Sweatshirts','Sicherheits- und Warnbekleidung'],['Stücklimits je Artikel, damit die Ausstattung fair bleibt','Team-Bestellung durch den Meister für die ganze Kolonne','Nachbestellen mit einem Klick, wenn die Hose durch ist']],
        'industrie'=>['Industrie','Einheitliche Kollektionen für viele Abteilungen','In der Industrie arbeiten viele Abteilungen mit unterschiedlicher Ausstattung. Der Einkauf will feste Artikel, feste Preise und klare Regeln.',['Einheitliche Mitarbeiterkollektionen','Bekleidung je Abteilung','Polos und Shirts für die Fertigung','Jacken für Lager und Versand'],['Ausstattungsprofile je Abteilung','Feste Produktfreigaben','Budgets und Kostenstellen','Vier-Augen-Freigabe für große Bestellungen']],
        'zeitarbeit'=>['Zeitarbeit','Viele Mitarbeitende, häufige Wechsel','Personaldienstleister statten laufend neue Mitarbeitende aus, oft kurzfristig und für unterschiedliche Einsätze.',['Shirts und Polos mit Firmenlogo','Jacken und Softshells','Arbeitshosen','Warnbekleidung je nach Einsatz'],['Schnelle Nachbestellungen','Häufige Größenwechsel per Umtausch','Zentrale Verwaltung vieler Personen','Mehrsprachiger Shop für internationale Teams']],
        'pflege'=>['Pflege','Einheitlich auftreten, im Schichtdienst bestellen','Pflegedienste und Einrichtungen arbeiten im Schichtdienst, mit vielen Teilzeitkräften. Bestellen muss schnell gehen, am besten vom Handy.',['Poloshirts und Shirts','Sweatjacken','Jacken für den Außendienst'],['Bestellung am Smartphone, ohne App','Budget pro Person, auch für Teilzeitkräfte individuell','Freigabe durch die Pflegedienstleitung']],
        'kommunen'=>['Kommunen','Verschiedene Teams, ein Standard','Bauhof, Grünpflege, Hausmeisterei und Verwaltung: Kommunen haben viele Teams mit festgelegten Bekleidungsstandards und einer zentralen Freigabe.',['Arbeitskleidung für Bauhof und Grünpflege','Warnbekleidung','Poloshirts und Jacken mit Wappen'],['Ausstattungsprofile je Team','Zentrale Freigabe mit Vier-Augen-Prinzip','Nachvollziehbare Bestellungen für die Verwaltung','Auswertung nach Kostenstelle']],
        'feuerwehr'=>['Feuerwehr','Mannschaftsbekleidung mit Wappen und Namen','Feuerwehren und Löschzüge brauchen einheitliche Mannschaftsbekleidung, oft mit Wappen und Namensstick, und bestellen in Wellen.',['Mannschaftsbekleidung','Poloshirts','Hoodies','Jacken','Wappen','Namensstick'],['Sammelbestellung zu einem festen Termin','Team-Bestellung durch die Wehrführung','Wappen und Namen fest hinterlegt']],
        'dienstleister'=>['Dienstleister','Einheitliches Erscheinungsbild beim Kunden','Wer beim Kunden auftritt, soll wiedererkannt werden: Gebäudereinigung, Sicherheitsdienst, Service und Technik.',['Poloshirts und Hemden','Softshell- und Fleecejacken','Caps und Accessoires'],['Mitarbeiterzugänge mit eigener Kollektion','Kontrollierte Bestellungen mit Freigabe','Budget pro Person']],
        'mittelstand'=>['Mittelstand','Mehrere Standorte, ein Shop','Größere Unternehmen und Mittelständler verwalten Arbeitskleidung über mehrere Standorte, Abteilungen und Kostenstellen.',['Komplette Kollektionen je Bereich','Firmenkleidung für Büro und Außendienst'],['Standorte, Abteilungen und Kostenstellen','Personen per CSV-Liste anlegen','Export für Buchhaltung und Lohnabrechnung','Sammeltermine für die Produktion']],
    ];
    $icons=[];foreach(w_industry_list() as [$id,$ic])$icons[$id]=$ic;
    $i=0;
    foreach($data as $id=>[$t,$sub,$text,$items,$features]){
        echo '<section class="w-sec w-ind-sec'.($i++%2?' w-white':'').'" id="'.e($id).'"><div class="w-wrap"><div class="w-ind-row w-reveal"><div class="w-ind-title"><span class="ic">'.w_icon($icons[$id]).'</span><span class="w-eyebrow">'.e($sub).'</span><h2>'.e($t).'</h2><p>'.e($text).'</p></div>';
        echo '<div class="w-ind-box"><h3>Typische Kleidung</h3><ul class="w-chips">';foreach($items as $x)echo '<li>'.e($x).'</li>';echo '</ul></div>';
        echo '<div class="w-ind-box"><h3>Was im Firmenshop hilft</h3>'.topic_ticks($features).'</div></div></div></section>';
    }
    topic_related('/branchen');
    w_faq_section($faqs,'Fragen zu Branchen');
    hp_cta_band('Deine Branche ist nicht dabei?','Schreib uns, wofür die Kleidung gedacht ist. Ein Firmenshop passt überall, wo mehrere Menschen einheitlich auftreten.');
    hp_page_end();
}
