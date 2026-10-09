<?php
declare(strict_types=1);

/*
 * Öffentliche Website (ab 10.5): klares Raster in Navyblau, Orange und Weiß.
 * Regeln: ein Container, linksbündige Abschnittsköpfe, einheitliche Karten und Bildformate,
 * keine Telefonnummer. Texte bleiben unter Admin → Inhalte pflegbar (yw_text).
 */

function s_mail(): string { return current_site_setting('imprint_email','info@yanik-textildruck.de'); }
function s_arrow(): string { return '<svg class="s-arrow" viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M11 5l5 5-5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'; }
function s_img(string $key,string $fallback): string { return yw_site_image_url($key,$fallback); }

function public_header(): void {
    $path=path_only();
    $links=['/leistungen'=>'Leistungen','/so-funktionierts'=>'So funktioniert’s','/mustershop'=>'Mustershop','/ueber-uns'=>'Über uns','/kontakt'=>'Kontakt'];
    $nav='';foreach($links as $href=>$label)$nav.='<a href="'.$href.'"'.($path===$href?' aria-current="page"':'').'>'.e($label).'</a>';
    echo '<a class="s-skip" href="#inhalt">Zum Inhalt springen</a>';
    echo '<header class="s-header"><div class="s-wrap s-header-row">'.site_brand_html(false);
    echo '<nav class="s-nav" aria-label="Hauptnavigation">'.$nav.'</nav>';
    echo '<div class="s-header-end"><a class="s-header-login" href="/zugang"'.($path==='/zugang'?' aria-current="page"':'').'>Kunden-Login</a><a class="s-btn s-btn-orange s-btn-sm" href="/anfrage">Shop anfragen</a></div>';
    echo '<details class="s-menu"><summary aria-label="Menü"><span></span><span></span><span></span></summary><nav aria-label="Mobile Navigation"><a href="/">Startseite</a>'.$nav.'<a href="/zugang">Kunden-Login</a><a class="s-btn s-btn-orange" href="/anfrage">Firmenshop anfragen</a></nav></details>';
    echo '</div></header>';
}

function global_footer(): void {
    // Verwaltung, Anmeldeseiten und Systemseiten: schlichte Fußzeile im Admin-Stil (site.css wird dort nicht geladen).
    $path=path_only();
    if(str_starts_with($path,'/admin')||str_starts_with($path,'/update')||$path==='/install.php'||$path==='/update.php'){
        echo '<footer class="admin-footer"><div class="wrap"><span>'.e(yw_text('footer_text')).' · © '.date('Y').'</span><span><a href="/" target="_blank" rel="noopener">Website</a> · <a href="/impressum" target="_blank" rel="noopener">Impressum</a> · <a href="/datenschutz" target="_blank" rel="noopener">Datenschutz</a></span></div></footer>';
        return;
    }
    $company=current_site_setting('imprint_company','Yanik Textildruck');
    $street=current_site_setting('imprint_street','Geseker Straße 19');$city=current_site_setting('imprint_city','33154 Salzkotten');
    $mail=s_mail();
    echo '<footer class="s-footer"><div class="s-wrap"><div class="s-footer-grid">';
    echo '<div class="s-footer-brand">'.site_brand_html(false).'<p>Firmenkleidung, Textilveredelung und Unternehmensshops aus Salzkotten.</p></div>';
    echo '<div><h2>Website</h2><nav aria-label="Seiten"><a href="/leistungen">Leistungen</a><a href="/so-funktionierts">So funktioniert’s</a><a href="/mustershop">Mustershop</a><a href="/ueber-uns">Über uns</a></nav></div>';
    echo '<div><h2>Service</h2><nav aria-label="Service"><a href="/anfrage">Shop anfragen</a><a href="/zugang">Kunden-Login</a><a href="/kontakt">Kontakt</a></nav></div>';
    echo '<div><h2>Kontakt</h2><p>'.e($company).'<br>'.e($street).'<br>'.e($city).'</p><p><a href="mailto:'.e($mail).'">'.e($mail).'</a></p></div>';
    echo '</div><div class="s-footer-bottom"><span>© '.date('Y').' '.e(yw_text('footer_text')).'</span><span><a href="/impressum">Impressum</a><a href="/datenschutz">Datenschutz</a></span></div></div></footer>';
}

/* ---------- Bausteine ---------- */

function hp_check_icon(): string { return '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 10.5l3.2 3.2L15 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'; }

function hp_page_start(string $documentTitle): void {
    render_head($documentTitle);
    echo site_brand_style();
    public_header();
    echo '<main class="s-main" id="inhalt">';
}

function hp_page_end(): void { echo '</main>'; global_footer(); render_foot(); }

/** Kopf einer Unterseite (Navy-Band). */
function hp_page_hero(string $label,string $title,string $introHtml,array $actions=[],bool $compact=false): void {
    echo '<section class="s-pagehead'.($compact?' s-pagehead-compact':'').'"><div class="s-wrap"><nav class="s-crumbs" aria-label="Brotkrumen"><a href="/">Start</a><span aria-hidden="true">/</span><span>'.e($label).'</span></nav><h1>'.e($title).'</h1>';
    if($introHtml!=='')echo '<div class="s-pagehead-lead">'.$introHtml.'</div>';
    if($actions){echo '<div class="s-actions">';foreach($actions as [$text,$href,$primary])echo '<a class="s-btn '.($primary?'s-btn-orange':'s-btn-outline-light').'" href="'.e($href).'">'.e($text).'</a>';echo '</div>';}
    echo '</div></section>';
}

/** Abschnittskopf: immer linksbündig, gleicher Aufbau. */
function s_head(string $title,string $text='',string $link='',string $linkText=''): void {
    echo '<div class="s-head"><div><h2>'.e($title).'</h2>'.($text!==''?'<p>'.e($text).'</p>':'').'</div>';
    if($link!=='')echo '<a class="s-more" href="'.e($link).'">'.e($linkText).s_arrow().'</a>';
    echo '</div>';
}

/** Abschluss jeder Seite. */
function hp_cta_band(string $title='Lassen Sie uns über Ihre Firmenkleidung sprechen.',string $text='Unverbindlich und persönlich – schreiben Sie uns, wir melden uns zeitnah bei Ihnen.'): void {
    $mail=s_mail();
    echo '<section class="s-section s-cta-section"><div class="s-wrap"><div class="s-cta"><div class="s-cta-copy"><h2>'.e($title).'</h2><p>'.e($text).'</p></div><div class="s-cta-actions"><a class="s-btn s-btn-orange" href="/anfrage">Firmenshop anfragen</a><a class="s-cta-mail" href="mailto:'.e($mail).'">'.e($mail).'</a></div></div></div></section>';
}

/** Kompatibilität: frühere Seiten riefen diesen Kopf auf. */
function marketing_page_header(string $titleKey,string $introKey,string $fallbackTitle,string $fallbackIntro): void {
    hp_page_start(yw_text($titleKey).' – '.current_site_setting('site_title','WorkwearCenter'));
    hp_page_hero('WorkwearCenter',yw_text($titleKey)?:$fallbackTitle,rich_text(yw_text($introKey)?:$fallbackIntro));
}

function s_steps(): array {
    $steps=[];
    for($n=1;$n<=4;$n++)$steps[$n]=[(string)preg_replace('/^\s*\d+[.)]\s*/u','',yw_text('process_step'.$n.'_title')),yw_text('process_step'.$n.'_text')];
    return $steps;
}

function s_faq(array $extra=[]): void {
    echo '<div class="s-faq">';
    $i=0;
    for($n=1;$n<=4;$n++){echo '<details'.($i++===0?' open':'').'><summary>'.e(yw_text('faq'.$n.'_question')).'</summary><div class="s-faq-a">'.rich_text(yw_text('faq'.$n.'_answer')).'</div></details>';}
    foreach($extra as [$q,$a])echo '<details><summary>'.e($q).'</summary><div class="s-faq-a"><p>'.e($a).'</p></div></details>';
    echo '</div>';
}

function s_browser(string $class=''): string {
    return '<div class="s-browser '.$class.'"><div class="s-browser-bar"><i></i><i></i><i></i><span>workwearcenter.de/ihre-firma</span></div><img src="/assets/media/screens/shop-desktop.webp" width="1600" height="1000" alt="Mitarbeiter-Shop mit Firmenkollektion, Budget und Bestellungen"></div>';
}

function s_phone_mock(): string {
    return '<div class="s-phone"><img src="/assets/media/screens/shop-mobile.webp" width="560" height="1211" loading="lazy" alt="Der Firmenshop auf dem Smartphone"></div>';
}

/* ---------- Startseite ---------- */
function landing_page(): void {
    render_head('Firmenkleidung mit eigenem Online-Shop | Yanik Textildruck Salzkotten');
    echo site_brand_style();
    public_header();
    $primaryText=trim(yw_text('hero_button_text'))?:'Unternehmensshop anfragen';
    $primaryLink=yw_public_link(yw_text('hero_button_link'),'/anfrage');
    $secondaryText=trim(yw_text('hero_secondary_text'))?:'Mustershop ansehen';
    $secondaryLink=yw_public_link(yw_text('hero_secondary_link'),'/mustershop');
    $mail=s_mail();

    echo '<main class="s-main" id="inhalt">';

    // 1 · Einstieg
    echo '<section class="s-hero"><div class="s-wrap s-hero-grid"><div class="s-hero-copy">';
    echo '<p class="s-hero-kicker">Yanik Textildruck · Salzkotten</p>';
    echo '<h1>'.e(yw_text('hero_title')).'</h1><div class="s-hero-lead">'.rich_text(yw_text('hero_text')).'</div>';
    echo '<div class="s-actions">';
    if($primaryLink!=='')echo '<a class="s-btn s-btn-orange" href="'.e($primaryLink).'">'.e($primaryText).'</a>';
    if($secondaryLink!=='')echo '<a class="s-btn s-btn-outline-light" href="'.e($secondaryLink).'">'.e($secondaryText).'</a>';
    echo '</div></div><div class="s-hero-visual">'.s_browser().'</div></div>';
    echo '<div class="s-wrap"><ul class="s-hero-facts"><li><strong>Eigene Produktion</strong><span>Stick, Druck und Transfer in Salzkotten</span></li><li><strong>Ihr eigener Shop</strong><span>Mit Logo, Kollektion und Regeln</span></li><li><strong>Budgets & Freigaben</strong><span>Automatisch geprüft bei jeder Bestellung</span></li><li><strong>Persönlich betreut</strong><span>Ein fester Ansprechpartner</span></li></ul></div></section>';

    // 2 · Leistungen
    echo '<section class="s-section"><div class="s-wrap">';
    s_head('Alles aus einer Hand','Kleidung auswählen, Logo veredeln, Bestellungen organisieren – wir übernehmen den ganzen Weg.','/leistungen','Alle Leistungen');
    echo '<div class="s-cards">';
    foreach([
        ['/assets/media/products/workwear-softshell.webp','s-card-img-product','Arbeits- & Firmenkleidung','Markenware namhafter Hersteller – vom T-Shirt bis zur Softshelljacke, passend zu Einsatz und Budget.','/leistungen'],
        [s_img('section1_image','assets/media/work/stick-detail.jpg'),'','Textilveredelung','Stick, DTF-Transfer und Textildruck aus unserer eigenen Werkstatt – sauber, langlebig und farbtreu.','/leistungen'],
        ['/assets/media/screens/shop-desktop.webp','s-card-img-screen','Ihr Unternehmensshop','Ihr Team bestellt selbst im eigenen Firmenshop. Sie legen Kollektion, Budgets und Freigaben fest.','/so-funktionierts'],
    ] as [$img,$imgClass,$title,$text,$href]){
        echo '<a class="s-card" href="'.e($href).'"><div class="s-card-img '.$imgClass.'"><img src="'.e($img).'" loading="lazy" alt=""></div><div class="s-card-body"><h3>'.e($title).'</h3><p>'.e($text).'</p><span class="s-card-link">Mehr erfahren'.s_arrow().'</span></div></a>';
    }
    echo '</div></div></section>';

    // 3 · Der Shop im Alltag
    echo '<section class="s-section s-section-grey"><div class="s-wrap s-feature">';
    echo '<div class="s-feature-copy"><h2>Ihr Firmenshop – einfach für alle Beteiligten.</h2><p class="s-intro">Schluss mit Größenlisten und Nachtelefonieren: Jede Person bestellt selbst, Sie behalten den Überblick.</p><div class="s-benefits">';
    $benefits=[[yw_text('section1_title'),yw_text('section1_text')],[yw_text('section2_title'),yw_text('section2_text')],[yw_text('section3_title'),yw_text('section3_text')],['Nachbestellen & Status','Die letzte Größe ist gespeichert, Nachbestellungen gehen mit einem Klick. Den Stand ihrer Bestellung sehen alle jederzeit.']];
    foreach($benefits as $i=>[$t,$x])echo '<div class="s-benefit"><span class="s-num">0'.($i+1).'</span><div><h3>'.e($t).'</h3><div class="s-benefit-text">'.rich_text($x).'</div></div></div>';
    echo '</div><a class="s-btn s-btn-navy" href="'.e($secondaryLink?:'/mustershop').'">Mustershop ausprobieren</a></div>';
    echo '<div class="s-feature-visual">'.s_phone_mock().'</div></div></section>';

    // 4 · Ablauf
    echo '<section class="s-section s-section-navy"><div class="s-wrap">';
    s_head(yw_text('process_title'),'Einmal gemeinsam einrichten – danach läuft die Bestellung Ihrer Firmenkleidung von selbst.','/so-funktionierts','Ablauf im Detail');
    echo '<ol class="s-steps">';
    foreach(s_steps() as $n=>[$title,$text])echo '<li><span class="s-step-num">0'.$n.'</span><h3>'.e($title).'</h3><div>'.rich_text($text).'</div></li>';
    echo '</ol></div></section>';

    // 5 · Werkstatt
    echo '<section class="s-section"><div class="s-wrap s-workshop">';
    echo '<div class="s-mosaic"><figure class="s-mosaic-a"><img src="'.e(s_img('hero_image','assets/media/work/jacke-gesamt.jpg')).'" loading="lazy" alt="Arbeitsjacke mit gesticktem Firmenlogo"></figure><figure class="s-mosaic-b"><img src="'.e(s_img('section2_image','assets/media/work/polo-wappen.jpg')).'" loading="lazy" alt="Poloshirt mit Wappen und Namensstick"></figure><figure class="s-mosaic-c"><img src="'.e(s_img('section3_image','assets/media/work/stick-detail.jpg')).'" loading="lazy" alt="Gesticktes Logo im Detail"></figure></div>';
    echo '<div class="s-workshop-copy"><h2>'.e(yw_text('intro_title')).'</h2><div class="s-intro">'.rich_text(yw_text('intro_text')).'</div>';
    echo '<ul class="s-ticks"><li>Stick, DTF-Transfer und Textildruck</li><li>Beratung zu Logo-Größe und Position</li><li>Qualitätskontrolle vor jeder Auslieferung</li></ul>';
    echo '<a class="s-btn s-btn-navy" href="/ueber-uns">Mehr über uns</a></div></div></section>';

    // 6 · Fragen
    echo '<section class="s-section s-section-grey"><div class="s-wrap s-faq-grid"><div class="s-faq-intro"><h2>Häufige Fragen</h2><p>Ihre Frage ist nicht dabei? Schreiben Sie uns an <a href="mailto:'.e($mail).'">'.e($mail).'</a> – wir antworten persönlich.</p></div>';
    s_faq();
    echo '</div></section>';

    // 7 · Abschluss
    hp_cta_band(yw_text('final_cta_title'),strip_tags(yw_text('final_cta_text')));

    echo '</main>';
    global_footer();
    render_foot();
}

/* ---------- Leistungen ---------- */
function services_page(): void {
    hp_page_start('Leistungen: Firmenkleidung mit Logo, Stick & Druck – WorkwearCenter');
    hp_page_hero('Leistungen','Firmenkleidung mit Ihrem Logo – aus einer Hand.','<p>Wir beraten Sie bei der Auswahl, veredeln in unserer Werkstatt in Salzkotten und organisieren die Bestellungen Ihres Teams über einen eigenen Firmenshop.</p>',[['Firmenshop anfragen','/anfrage',true],['Mustershop ansehen','/mustershop',false]]);

    echo '<section class="s-section"><div class="s-wrap"><div class="s-rows">';
    $rows=[
        ['/assets/media/products/workwear-hoodie.webp','s-row-img-product','Arbeits- & Firmenkleidung','Wir stellen Ihre Kollektion aus Markenware namhafter Workwear-Hersteller zusammen – passend zu Tätigkeit, Einsatzbereich und Budget.',['T-Shirts, Polos und Hemden','Sweater, Hoodies und Fleece','Softshell- und Arbeitsjacken','Hosen, Caps und Zubehör']],
        [s_img('services_image','assets/media/work/jacke-gesamt.jpg'),'','Textilveredelung','Ihr Logo wird in unserer eigenen Werkstatt aufgebracht. Welches Verfahren passt, entscheiden wir gemeinsam nach Stoff, Motiv und Menge.',['Stick – wertig und sehr langlebig','DTF-Transfer – vollfarbig, auch in kleinen Mengen','Textildruck – für Rückenmotive und größere Stückzahlen','Flex – für Namen und Nummern']],
        ['/assets/media/screens/shop-desktop.webp','s-row-img-screen','Ihr Firmenshop','Ein geschützter Online-Shop nur für Ihr Unternehmen. Ihr Team bestellt selbst – nach den Regeln, die Sie festlegen.',['Persönliche Zugänge für jede Person','Jahresbudgets und Stücklimits','Freigabe durch Verantwortliche','Auswertung nach Kostenstelle']],
    ];
    foreach($rows as $i=>[$img,$imgClass,$title,$text,$items]){
        echo '<article class="s-row'.($i%2?' s-row-reverse':'').'"><div class="s-row-img '.$imgClass.'"><img src="'.e($img).'" loading="lazy" alt="'.e($title).'"></div><div class="s-row-copy"><span class="s-num">0'.($i+1).'</span><h2>'.e($title).'</h2><p>'.e($text).'</p><ul class="s-ticks">';
        foreach($items as $item)echo '<li>'.e($item).'</li>';
        echo '</ul></div></article>';
    }
    echo '</div></div></section>';

    echo '<section class="s-section s-section-grey"><div class="s-wrap">';
    s_head('Welches Verfahren passt zu Ihrem Logo?','Das hängt von Stoff, Motiv und Stückzahl ab. Wir beraten Sie ehrlich – auf Wunsch mit Muster vorab.');
    echo '<div class="s-methods">';
    foreach([['Stick','Polos, Jacken, Softshells, Caps','Hochwertige Optik, extrem waschbeständig'],['DTF-Transfer','Farbige Logos mit feinen Details','Alle Farben und Verläufe, auch kleine Mengen'],['Textildruck','Rückendrucke und größere Serien','Große Flächen, kräftige Farben'],['Flex','Namen, Nummern, Schriftzüge','Einzeln personalisierbar, sehr präzise']] as [$a,$b,$c])echo '<article class="s-method"><h3>'.e($a).'</h3><dl><dt>Geeignet für</dt><dd>'.e($b).'</dd><dt>Stärken</dt><dd>'.e($c).'</dd></dl></article>';
    echo '</div></div></section>';

    hp_cta_band('Welche Kleidung braucht Ihr Team?','Schreiben Sie uns kurz, wofür die Kleidung gedacht ist. Wir stellen Ihnen eine passende Auswahl zusammen.');
    hp_page_end();
}

/* ---------- So funktioniert’s ---------- */
function how_it_works_page(): void {
    hp_page_start('So funktioniert der Unternehmensshop für Firmenkleidung – WorkwearCenter');
    hp_page_hero('So funktioniert’s','In vier Schritten zu Ihrem Firmenshop.','<p>Sie legen einmal fest, was Ihr Team tragen darf. Danach bestellen Ihre Mitarbeitenden selbst – und wir kümmern uns um Veredelung und Lieferung.</p>',[['Firmenshop anfragen','/anfrage',true],['Mustershop testen','/mustershop',false]]);
    $steps=[
        ['Kollektion abstimmen','Gemeinsam legen wir fest, welche Kleidung Ihr Team bekommt und wie Ihr Logo darauf aussieht.','Sie nennen Einsatzbereich, Wunschartikel und schicken uns Ihr Logo.','Wir schlagen passende Artikel vor, beraten zu Verfahren und Position und fertigen auf Wunsch ein Muster.'],
        ['Shop einrichten','Ihr Firmenshop bekommt Ihr Logo, Ihre Kollektion und Ihre Regeln.','Sie bestimmen Budget, Lieferadresse und wer Bestellungen freigibt.','Wir richten den Shop vollständig ein und laden Ihr Team per E-Mail ein.'],
        ['Team bestellt','Jede Person bestellt selbst in ihrer Größe – am Computer oder am Smartphone.','Verantwortliche prüfen die Bestellungen und geben sie mit einem Klick frei.','Freigegebene Bestellungen gehen direkt in unsere Produktion.'],
        ['Veredeln & liefern','Wir besorgen die Ware, veredeln jedes Teil und liefern an Ihre Adresse.','Sie erhalten die Kleidung nach Personen sortiert – und eine Rechnung wie gewohnt.','Wir prüfen jedes Teil vor dem Versand und zeigen den Versandstatus im Shop an.'],
    ];
    echo '<section class="s-section"><div class="s-wrap"><ol class="s-timeline">';
    foreach($steps as $i=>[$title,$text,$you,$we]){
        echo '<li class="s-timeline-item"><span class="s-timeline-num">'.($i+1).'</span><div class="s-timeline-card"><h2>'.e($title).'</h2><p class="s-timeline-text">'.e($text).'</p><div class="s-who"><div><h3>Ihr Teil</h3><p>'.e($you).'</p></div><div><h3>Unser Teil</h3><p>'.e($we).'</p></div></div></div></li>';
    }
    echo '</ol></div></section>';

    echo '<section class="s-section s-section-grey"><div class="s-wrap s-feature"><div class="s-feature-copy"><h2>Jede Person sieht genau, was sie braucht.</h2><div class="s-benefits s-benefits-one">';
    foreach([['Mitarbeitende','sehen ihre Kollektion, ihr verfügbares Budget und den Stand ihrer Bestellungen – bis zur Sendungsnummer.'],['Verantwortliche','geben Bestellungen frei, verwalten ihr Team und sehen den Verbrauch nach Kostenstelle.'],['Yanik Textildruck','erhält jede Bestellung vollständig mit Größen, Mengen und Veredelung – ohne Rückfragen.']] as $i=>[$t,$x])echo '<div class="s-benefit"><span class="s-num">0'.($i+1).'</span><div><h3>'.e($t).'</h3><div class="s-benefit-text"><p>'.e($x).'</p></div></div></div>';
    echo '</div></div><div class="s-feature-visual">'.s_phone_mock().'</div></div></section>';

    echo '<section class="s-section"><div class="s-wrap s-faq-grid"><div class="s-faq-intro"><h2>Häufige Fragen</h2><p>Weitere Fragen? <a href="mailto:'.e(s_mail()).'">'.e(s_mail()).'</a></p></div>';
    s_faq([['Müssen wir etwas installieren?','Nein. Der Shop läuft im Browser – am Computer, Tablet und Smartphone. Ihre Mitarbeitenden erhalten eine Einladung per E-Mail und legen ihr Passwort selbst fest.'],['Was passiert, wenn jemand das Unternehmen verlässt?','Das Konto wird mit einem Klick deaktiviert. Auf Wunsch werden die persönlichen Daten gelöscht – die Bestellungen bleiben für Ihre Buchhaltung erhalten.']]);
    echo '</div></section>';
    hp_cta_band('Bereit für Ihren eigenen Firmenshop?','Wir richten alles für Sie ein – Sie sagen uns nur, was Ihr Team tragen soll.');
    hp_page_end();
}

/* ---------- Über uns ---------- */
function about_page(): void {
    hp_page_start('Über uns: Yanik Textildruck aus Salzkotten – WorkwearCenter');
    hp_page_hero('Über uns','Textilveredelung aus Salzkotten – persönlich und zuverlässig.','<p>Hinter dem WorkwearCenter steht Yanik Textildruck: ein inhabergeführter Betrieb, der Kleidung für Unternehmen, Vereine und Teams bestickt und bedruckt.</p>');
    $owner=current_site_setting('imprint_owner','Jens Yanik');$company=current_site_setting('imprint_company','Yanik Textildruck');
    $street=current_site_setting('imprint_street','Geseker Straße 19');$city=current_site_setting('imprint_city','33154 Salzkotten');

    echo '<section class="s-section"><div class="s-wrap s-workshop s-workshop-reverse"><div class="s-workshop-copy"><h2>Warum es das WorkwearCenter gibt</h2><div class="s-intro"><p>Bei Firmenkunden sieht die Bestellung von Arbeitskleidung oft gleich aus: Größen werden auf Zetteln gesammelt, Nachbestellungen gehen unter, und niemand weiß genau, wie viel vom Budget schon verbraucht ist.</p><p>Deshalb haben wir das WorkwearCenter entwickelt – einen Firmenshop, in dem Mitarbeitende selbst bestellen und Sie trotzdem die Kontrolle behalten. Die Veredelung bleibt Handarbeit: Wir sticken, drucken und prüfen jedes Teil selbst.</p></div><p class="s-owner"><strong>'.e($owner).'</strong><span>Inhaber, '.e($company).'</span></p></div>';
    echo '<div class="s-mosaic"><figure class="s-mosaic-a"><img src="/assets/media/work/jacke-gesamt.jpg" loading="lazy" alt="Arbeitsjacke mit gesticktem Firmenlogo"></figure><figure class="s-mosaic-b"><img src="'.e(s_img('about_image','assets/media/work/polo-wappen.jpg')).'" loading="lazy" alt="Poloshirt mit Wappen und Namensstick"></figure><figure class="s-mosaic-c"><img src="'.e(s_img('section3_image','assets/media/work/stick-detail.jpg')).'" loading="lazy" alt="Gesticktes Logo im Detail"></figure></div></div></section>';

    echo '<section class="s-section s-section-grey"><div class="s-wrap">';
    s_head('Worauf Sie sich verlassen können');
    echo '<div class="s-values">';
    foreach([['Ein Ansprechpartner','Vom ersten Muster bis zur Lieferung sprechen Sie mit uns – nicht mit einer Hotline.'],['Ehrliche Beratung','Wir beurteilen Ihr Logo an Stickmaschine und Presse und sagen offen, was auf welchem Stoff gut aussieht.'],['Geprüfte Qualität','Jedes Teil geht vor der Auslieferung durch unsere Hände. Absprachen und Termine halten wir ein.']] as [$t,$x])echo '<article class="s-value"><h3>'.e($t).'</h3><p>'.e($x).'</p></article>';
    echo '</div></div></section>';

    echo '<section class="s-section"><div class="s-wrap s-visit"><div><h2>Besuchen Sie uns</h2><p>Sie möchten Stoffe anfassen, Muster ansehen oder Ihr Logo direkt mit uns besprechen? Kommen Sie gern vorbei – nach kurzer Terminabsprache per E-Mail.</p></div><address class="s-address-card"><strong>'.e($company).'</strong><span>'.e($street).'</span><span>'.e($city).'</span><a href="mailto:'.e(s_mail()).'">'.e(s_mail()).'</a></address></div></section>';
    hp_cta_band();
    hp_page_end();
}

/* ---------- Kontakt ---------- */
function contact_page(): void {
    hp_page_start('Kontakt – WorkwearCenter by Yanik Textildruck, Salzkotten');
    hp_page_hero('Kontakt','Wir freuen uns auf Ihre Nachricht.','<p>Ob neuer Firmenshop oder eine Frage zu Ihrer Kleidung – schreiben Sie uns. Sie bekommen eine persönliche Antwort.</p>');
    $mail=s_mail();$owner=current_site_setting('imprint_owner','Jens Yanik');
    $street=current_site_setting('imprint_street','Geseker Straße 19');$city=current_site_setting('imprint_city','33154 Salzkotten');$company=current_site_setting('imprint_company','Yanik Textildruck');
    echo '<section class="s-section"><div class="s-wrap"><div class="s-contact-grid">';
    echo '<article class="s-contact-card s-contact-main"><h2>Sie interessieren sich für einen Firmenshop?</h2><p>Am schnellsten geht es über das Anfrageformular. Dort fragen wir die wichtigsten Eckdaten ab – so können wir uns gut vorbereitet bei Ihnen melden.</p><a class="s-btn s-btn-orange" href="/anfrage">Zum Anfrageformular</a></article>';
    echo '<article class="s-contact-card"><h2>E-Mail</h2><p><a class="s-contact-big" href="mailto:'.e($mail).'">'.e($mail).'</a></p><p class="s-muted">Gern mit Logo oder Wunschartikeln im Anhang.</p></article>';
    echo '<article class="s-contact-card"><h2>Anschrift</h2><p>'.e($company).'<br>'.e($street).'<br>'.e($city).'</p><p class="s-muted">Ansprechpartner: '.e($owner).'<br>Besuche nach Terminabsprache.</p></article>';
    echo '<article class="s-contact-card"><h2>Schon Kunde?</h2><p>Mitarbeitende und Verantwortliche melden sich direkt im eigenen Firmenshop an.</p><a class="s-more" href="/zugang">Zum Kunden-Login'.s_arrow().'</a></article>';
    echo '</div></div></section>';
    hp_page_end();
}

function imprint_page(): void {
    hp_page_start('Impressum – '.current_site_setting('site_title','WorkwearCenter'));
    hp_page_hero('Rechtliches','Impressum','',[],true);
    echo '<section class="s-section"><div class="s-wrap"><article class="s-legal">';
    echo '<h2>Angaben gemäß § 5 DDG</h2><p><strong>'.e(current_site_setting('imprint_company','Yanik Textildruck')).'</strong><br>Inhaber: '.e(current_site_setting('imprint_owner','Jens Yanik')).'<br>'.e(current_site_setting('imprint_street','Geseker Straße 19')).'<br>'.e(current_site_setting('imprint_city','33154 Salzkotten')).'</p>';
    echo '<h2>Kontakt</h2><p>E-Mail: <a href="mailto:'.e(s_mail()).'">'.e(s_mail()).'</a><br>Kontaktformular: <a href="/anfrage">workwearcenter.de/anfrage</a></p>';
    $vat=trim(current_site_setting('imprint_vat_id',''));$register=trim(current_site_setting('imprint_register',''));
    if($vat!=='')echo '<h2>Umsatzsteuer</h2><p>Umsatzsteuer-Identifikationsnummer gemäß § 27a Umsatzsteuergesetz: '.e($vat).'</p>';
    if($register!=='')echo '<h2>Registereintrag</h2><p>'.e($register).'</p>';
    echo '<h2>Verantwortlich für den Inhalt</h2><p>'.e(current_site_setting('imprint_owner','Jens Yanik')).', Anschrift wie oben.</p>';
    echo '</article></div></section>';
    hp_page_end();
}

function privacy_page(): void {
    hp_page_start('Datenschutz – '.current_site_setting('site_title','WorkwearCenter'));
    hp_page_hero('Rechtliches','Datenschutz','',[],true);
    echo '<section class="s-section"><div class="s-wrap"><article class="s-legal">'.rich_text(current_site_setting('privacy_body','Bitte hinterlege hier die rechtlich geprüfte Datenschutzerklärung für WorkwearCenter.de.')).'</article></div></section>';
    hp_page_end();
}

/** Darstellung der Anfrageseite (Verarbeitung in inquiry_page). */
function site_inquiry_render(bool $success,string $error,?string $probeshopToken=null): void {
    hp_page_start('Unternehmensshop anfragen – '.current_site_setting('site_title','WorkwearCenter'));
    hp_page_hero('Anfrage','Firmenshop anfragen','<p>Beschreiben Sie kurz, was Ihr Team braucht. Wir melden uns persönlich – unverbindlich und kostenlos.</p>',[],true);
    echo '<section class="s-section"><div class="s-wrap s-inquiry"><div class="s-form-card">';
    if($success){
        echo '<div class="s-success"><span class="s-success-mark">'.hp_check_icon().'</span><h2>Vielen Dank für Ihre Anfrage!</h2><p>Wir haben Ihre Angaben erhalten und melden uns zeitnah persönlich bei Ihnen.</p>';
        if($probeshopToken!==null)echo '<div class="s-probe-ready"><strong>Ihr persönlicher Probeshop ist fertig.</strong><p>So könnte Ihr Firmenshop mit Ihrem Logo aussehen. Den Link haben wir Ihnen auch per E-Mail geschickt; er ist 30 Tage gültig.</p><a class="s-btn s-btn-orange" href="/probeshop/'.e($probeshopToken).'" rel="noreferrer">Probeshop ansehen</a></div>';
        else echo '<a class="s-btn s-btn-navy" href="/mustershop">In der Zwischenzeit: Mustershop ansehen</a>';
        echo '</div>';
    }else{
        if($error)echo '<div class="flash error">'.e($error).'</div>';
        echo '<form method="post" enctype="multipart/form-data" class="s-form">'.csrf_field();
        echo '<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>';
        echo '<div class="s-form-row"><label>Unternehmen<input name="company" autocomplete="organization" value="'.e((string)($_POST['company']??'')).'" required></label><label>Ansprechpartner<input name="name" autocomplete="name" value="'.e((string)($_POST['name']??'')).'" required></label></div>';
        echo '<div class="s-form-row"><label>E-Mail<input type="email" name="email" autocomplete="email" value="'.e((string)($_POST['email']??'')).'" required></label><label>Telefon <small>optional, für Rückfragen</small><input name="phone" type="tel" autocomplete="tel" value="'.e((string)($_POST['phone']??'')).'"></label></div>';
        echo '<label>Anzahl Mitarbeitende <small>ungefähr, optional</small><input type="number" min="0" name="employees" value="'.e((string)($_POST['employees']??'')).'"></label>';
        echo '<label>Was benötigen Sie?<textarea name="message" rows="6" placeholder="z. B. ca. 30 Mitarbeitende, Polos und Softshelljacken, Stick Brust links, Rückendruck …" required>'.e((string)($_POST['message']??'')).'</textarea></label>';
        echo '<div class="s-upload"><label>Ihr Logo <small>optional · PNG, JPG, WebP oder SVG, max. 4 MB</small><input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg,image/png,image/jpeg,image/webp,image/svg+xml"></label><p>Mit Logo erstellen wir Ihnen sofort einen <strong>persönlichen Probeshop</strong>: Ihre Firmenkleidung mit Ihrem Logo – zum Ansehen und Weiterleiten im Team. Der Link ist 30 Tage gültig, danach löschen wir Shop und Logo automatisch.</p></div>';
        echo '<label class="s-check"><input type="checkbox" name="privacy" value="1" required><span>Ich bin einverstanden, dass meine Angaben (und ein ggf. hochgeladenes Logo für den Probeshop) zur Bearbeitung der Anfrage verwendet werden. <a href="/datenschutz" target="_blank" rel="noopener">Datenschutzhinweise</a></span></label>';
        echo '<button class="s-btn s-btn-orange" type="submit">Anfrage absenden</button></form>';
    }
    echo '</div><aside class="s-inquiry-side"><h2>So geht es weiter</h2><ol class="s-mini-steps"><li><strong>Wir melden uns</strong><span>persönlich, meist innerhalb eines Werktags.</span></li><li><strong>Wir stimmen ab</strong><span>Artikel, Logo, Veredelung und Regeln – auf Wunsch mit Mustern.</span></li><li><strong>Ihr Shop startet</strong><span>Wir richten alles ein und laden Ihr Team ein.</span></li></ol><p class="s-inquiry-mail">Lieber per E-Mail?<br><a href="mailto:'.e(s_mail()).'">'.e(s_mail()).'</a></p></aside></div></section>';
    hp_page_end();
}

/** Kunden-Login: führt über den Firmen-Kurznamen zum richtigen Shop. */
function customer_access_page(): void {
    $error='';$value='';
    if(is_post()){
        verify_csrf();
        $value=strtolower(trim((string)($_POST['shop']??'')));
        $value=(string)preg_replace('#^(?:https?://)?(?:www\.)?workwearcenter\.de/#','',$value);
        $value=trim($value,'/ ');
        if(login_throttled('customer-access',30))$error='Zu viele Versuche. Bitte in 15 Minuten erneut versuchen.';
        elseif(!slug_ok($value))$error='Bitte den Kurznamen Ihres Firmenshops eingeben, z. B. „musterbau“.';
        else{
            $q=db()->prepare('SELECT slug FROM companies WHERE slug=? AND active=1 LIMIT 1');$q->execute([$value]);
            if($slug=$q->fetchColumn())redirect('/'.$slug.'/login');
            record_login_failure('customer-access');
            $error='Zu diesem Kurznamen haben wir keinen aktiven Firmenshop gefunden. Den Link finden Sie in Ihrer Einladungs-E-Mail – oder fragen Sie Ihre verantwortliche Person.';
        }
    }
    hp_page_start('Kunden-Login – '.current_site_setting('site_title','WorkwearCenter'));
    echo '<section class="s-access"><div class="s-wrap"><div class="s-access-card"><h1>Kunden-Login</h1><p>Jedes Unternehmen hat seinen eigenen, geschützten Shop. Geben Sie den Kurznamen Ihres Shops ein – er steht in Ihrer Einladungs-E-Mail.</p>';
    echo '<form method="post" class="s-form">'.csrf_field();
    if($error)echo '<div class="flash error">'.e($error).'</div>';
    echo '<label>Kurzname oder Shop-Adresse<input name="shop" value="'.e($value).'" placeholder="z. B. musterbau" autocomplete="organization" required autofocus></label><button class="s-btn s-btn-orange" type="submit">Weiter zur Anmeldung</button></form><p class="s-access-foot">Noch kein Firmenshop? <a href="/anfrage">Jetzt anfragen</a></p></div></div></section>';
    hp_page_end();
}
