<?php
declare(strict_types=1);

/*
 * Öffentliche Website: ruhiges Eisblau-Anthrazit-Design mit klarer Typohierarchie.
 * Die WorkwearCenter-Startseite erklärt den Firmen-Shop, Bestellwege und Auftragsübergabe;
 * Unternehmensshops behalten ihre individuelle Gestaltung.
 */

function s_mail(): string { return current_site_setting('imprint_email','info@yanik-textildruck.de'); }
function s_arrow(): string { return '<svg class="s-arrow" viewBox="0 0 20 20" aria-hidden="true"><path d="M4 10h11M11 5l5 5-5 5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'; }
function s_img(string $key,string $fallback): string { return yw_site_image_url($key,$fallback); }

/** Markenlogo der öffentlichen Website; Kundenportal und Anmeldung nutzen ihre eigene Variante. */
function s_public_brand_html(bool $darkSurface=false): string {
    $logo=yw_site_logo_path($darkSurface);
    $defaultLogos=['assets/media/workwearcenter-logo.png','assets/media/workwearcenter-logo-dunkel.png','assets/media/workwearcenter-logo-anthrazit.png','assets/media/workwearcenter-logo-ice.svg','assets/media/workwearcenter-logo-warm.svg'];
    if(in_array($logo,$defaultLogos,true))$logo=$darkSurface?'assets/media/workwearcenter-logo.png':'assets/media/workwearcenter-logo-dunkel.png';
    return '<div class="site-brand-wrap"><a href="/" aria-label="WorkwearCenter"><img class="main-site-logo" src="/'.e($logo).'" alt="WorkwearCenter"></a></div>';
}

function public_header(): void {
    $path=path_only();
    $links=$path==='/'
        ? ['/#was-ist'=>'Das WorkwearCenter','/#vorteile'=>'Vorteile','/#bestellwege'=>'Bestellwege','/#ablauf'=>'Auftragsweg','/#faq'=>'FAQ','/mustershop'=>'Mustershop','/kontakt'=>'Kontakt']
        : ['/leistungen'=>'Kleidung & Veredelung','/so-funktionierts'=>'Firmenshop','/ueber-uns'=>'Über uns','/kontakt'=>'Kontakt'];
    $nav='';foreach($links as $href=>$label)$nav.='<a href="'.$href.'"'.($path===$href?' aria-current="page"':'').'>'.e($label).'</a>';
    echo '<a class="s-skip" href="#inhalt">Zum Inhalt springen</a>';
    echo '<header class="s-header"><div class="s-wrap s-header-row">'.s_public_brand_html(false);
    echo '<nav class="s-nav" aria-label="Hauptnavigation">'.$nav.'</nav>';
    echo '<div class="s-header-end"><a class="s-header-login" href="/zugang"'.($path==='/zugang'?' aria-current="page"':'').'>Kundenportal</a><a class="s-btn s-btn-orange s-btn-sm" href="/anfrage">Shop anfragen</a></div>';
    echo '<details class="s-menu"><summary aria-label="Menü"><span></span><span></span><span></span></summary><nav aria-label="Mobile Navigation"><a href="/">Startseite</a>'.$nav.'<a href="/zugang">Kundenportal</a><a class="s-btn s-btn-orange" href="/anfrage">Shop anfragen</a></nav></details>';
    echo '</div></header>';
}

function global_footer(): void {
    // Verwaltung, Anmeldeseiten und Systemseiten: schlichte Fußzeile im Admin-Stil (site.css wird dort nicht geladen).
    $path=path_only();
    if(str_starts_with($path,'/admin')||str_starts_with($path,'/update')||$path==='/install.php'||$path==='/update.php'){
        echo '<footer class="admin-footer"><div class="wrap"><span>'.e(yw_text('footer_text')).' · © '.date('Y').'</span><span><a href="/" target="_blank" rel="noopener">Website</a> · <a href="/impressum" target="_blank" rel="noopener">Impressum</a> · <a href="/datenschutz" target="_blank" rel="noopener">Datenschutz</a></span></div></footer>';
        return;
    }
    if($path==='/'){
        echo '<footer class="s-footer wcm-footer"><div class="s-wrap"><div class="wcm-footer-grid">';
        echo '<div class="wcm-footer-brand">'.s_public_brand_html(false).'<p>Der digitale Bestellshop für Firmenkleidung und Teamwear.</p></div>';
        echo '<div><h2>WorkwearCenter</h2><nav aria-label="WorkwearCenter"><a href="/#was-ist">Was ist das WorkwearCenter?</a><a href="/#vorteile">Vorteile für Ihr Unternehmen</a><a href="/#bestellwege">Bestellwege</a><a href="/#ablauf">Auftragsweg</a><a href="/#faq">Häufige Fragen</a><a href="/mustershop">Mustershop</a></nav></div>';
        echo '<div><h2>Kontakt & Zugang</h2><nav aria-label="Kontakt und Zugang"><a href="/anfrage">Shop anfragen</a><a href="/kontakt">Kontakt</a><a href="/zugang">Kundenportal</a></nav></div>';
        echo '<div><h2>Informationen</h2><nav aria-label="Rechtliche Informationen"><a href="/impressum">Impressum</a><a href="/datenschutz">Datenschutz</a></nav></div>';
        echo '</div><div class="s-footer-bottom"><span>© '.date('Y').' WorkwearCenter</span><span><a href="/impressum">Impressum</a><a href="/datenschutz">Datenschutz</a></span></div></div></footer>';
        return;
    }
    $company=current_site_setting('imprint_company','Yanik Textildruck');
    $street=current_site_setting('imprint_street','Geseker Straße 19');$city=current_site_setting('imprint_city','33154 Salzkotten');
    $mail=s_mail();
    echo '<footer class="s-footer"><div class="s-wrap"><div class="s-footer-grid">';
    echo '<div class="s-footer-brand">'.s_public_brand_html(false).'<p>Firmenkleidung und Textilveredelung – persönlich aus Salzkotten.</p></div>';
    echo '<div><h2>WorkwearCenter</h2><nav aria-label="WorkwearCenter"><a href="/leistungen">Leistungen</a><a href="/so-funktionierts">Firmenshop & Ablauf</a><a href="/mustershop">Mustershop ansehen</a><a href="/anfrage">Firmenshop anfragen</a></nav></div>';
    echo '<div><h2>Yanik Textildruck</h2><nav aria-label="Unternehmen"><a href="/ueber-uns">Über uns</a><a href="/kontakt">Kontakt</a><a href="/zugang">Kundenportal</a></nav></div>';
    echo '<div><h2>Besuchen Sie uns</h2><p>'.e($company).'<br>'.e($street).'<br>'.e($city).'</p><p><a href="mailto:'.e($mail).'">'.e($mail).'</a></p></div>';
    echo '</div><div class="s-footer-bottom"><span>© '.date('Y').' '.e(yw_text('footer_text')).'</span><span><a href="/impressum">Impressum</a><a href="/datenschutz">Datenschutz</a></span></div></div></footer>';
}

/* ---------- Bausteine ---------- */

function hp_check_icon(): string { return '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 10.5l3.2 3.2L15 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>'; }

function hp_page_start(string $documentTitle): void {
    render_head($documentTitle);
    echo site_brand_style();
    echo '<div class="wcm-public-page">';
    public_header();
    echo '<main class="s-main" id="inhalt">';
}

function hp_page_end(): void { echo '</main>'; global_footer(); echo '</div>'; render_foot(); }

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
    return '<figure class="s-service-proof '.e($class).'" aria-label="Echte Textilveredelung aus unserer Werkstatt">'
        .'<img src="/'.e(s_img('services_image','assets/media/work/jacke-gesamt.jpg')).'" loading="lazy" alt="Arbeitsjacke mit sauber gesticktem Firmenlogo">'
        .'<figcaption><span>Aus unserer Werkstatt in Salzkotten</span><strong>Textilien, die zu Ihrem Betrieb passen.</strong><a href="/mustershop">Firmenshop ansehen '.s_arrow().'</a></figcaption>'
        .'</figure>';
}

/** Referenzen, Kundenstimme und Ansprechpartner – erscheint nur, wenn etwas gepflegt ist (10.11). */
function s_trust_section(): void {
    $c=yw_trust_content();
    if($c['empty']){
        echo '<section class="s-section s-trust s-trust-empty wcm-trust-empty" aria-labelledby="s-trust-title"><div class="s-wrap"><div class="s-trust-head"><div><span class="s-eyebrow">REFERENZEN</span><h2 id="s-trust-title">'.e($c['title']).'</h2></div></div>';
        echo '<div class="s-trust-empty-row"><p>[FREIGEGEBENE KUNDENLOGOS EINFÜGEN]</p><a class="s-more" href="/kontakt">Kontakt aufnehmen '.s_arrow().'</a></div></div></section>';
        return;
    }
    echo '<section class="s-section s-trust" aria-labelledby="s-trust-title"><div class="s-wrap">';
    echo '<div class="s-trust-head"><h2 id="s-trust-title">'.e($c['title']).'</h2>';
    if($c['logos']){
        $loop=count($c['logos'])>1;
        if($loop)echo '<button class="s-trust-toggle" type="button" data-trust-toggle aria-pressed="false">Durchlauf pausieren</button>';
    }
    echo '</div>';
    if($c['logos']){
        $loop=count($c['logos'])>1;
        echo '<div class="s-trust-marquee'.($loop?' s-trust-marquee-moving':'').'"'.($loop?' data-trust-marquee':'').'><div class="s-trust-window" role="group" aria-label="Logos von Kundenunternehmen"><div class="s-trust-track">';
        $copies=$loop?3:1;
        for($copy=0;$copy<$copies;$copy++){
            echo '<ul class="s-trust-group"'.($copy>0?' aria-hidden="true"':'').'>';
            foreach($c['logos'] as $logo){
                $name=$logo['name']!==''?'Logo von '.$logo['name']:'Kundenlogo';
                echo '<li><img src="/'.e($logo['path']).'" alt="'.($copy>0?'':e($name)).'" decoding="async"></li>';
            }
            echo '</ul>';
        }
        echo '</div></div></div>';
    }
    if($c['has_quote']||$c['has_contact']){
        echo '<div class="s-trust-grid'.($c['has_quote']&&$c['has_contact']?'':' s-trust-single').'">';
        if($c['has_quote']){
            $q=$c['quote'];$by=implode(', ',array_filter([$q['name'],$q['company']]));
            echo '<figure class="s-quote"><blockquote><p>'.nl2br(e($q['text'])).'</p></blockquote>'.($by!==''?'<figcaption>'.($q['name']!==''?'<strong>'.e($q['name']).'</strong>':'').($q['company']!==''?'<span>'.e($q['company']).'</span>':'').'</figcaption>':'').'</figure>';
        }
        if($c['has_contact']){
            $p=$c['contact'];$mail=s_mail();
            echo '<div class="s-person">'.($p['photo']!==''?'<img src="/'.e($p['photo']).'" alt="'.e($p['name']).'" width="104" height="104" decoding="async">':'<span class="s-person-initial" aria-hidden="true">'.e(mb_strtoupper(mb_substr($p['name'],0,1))).'</span>').'<div><p class="s-person-kicker">Ihr Ansprechpartner</p><strong>'.e($p['name']).'</strong>'.($p['role']!==''?'<span class="s-person-role">'.e($p['role']).'</span>':'').($p['text']!==''?'<p>'.e($p['text']).'</p>':'').'<a class="s-more" href="mailto:'.e($mail).'">'.e($mail).s_arrow().'</a></div></div>';
        }
        echo '</div>';
    }
    echo '</div></section>';
}

/* ---------- Startseite ---------- */
function landing_page(): void {
    render_head('WorkwearCenter – Der digitale Firmenshop für Arbeitskleidung');
    echo site_brand_style();
    echo '<div class="wcm-public-page wcm-public-home">';
    public_header();
    echo '<main class="s-main wcm-home" id="inhalt">';
    echo '<section class="wcm-hero"><div class="wcm-container wcm-hero-grid"><div class="wcm-hero-copy">';
    echo '<p class="wcm-eyebrow">Für Unternehmen in Handwerk, Industrie, Dienstleistung und Zeitarbeit</p>';
    echo '<h1>Dein Firmenshop für Arbeitskleidung mit Logo</h1>';
    echo '<p class="wcm-hero-lead">Du bekommst einen eigenen Shop mit Arbeitskleidung, die wir vorher mit dir und deinem Logo abstimmen. Erst nach deiner Freigabe kommen die Artikel in den Shop. Mitarbeitende bestellen selbst oder eine zuständige Person bestellt für das Team.</p>';
    echo '<div class="wcm-actions"><a class="wcm-button" href="/anfrage">Shop anfragen</a><a class="wcm-text-link" href="/mustershop">Mustershop ansehen <span aria-hidden="true">↗</span></a></div>';
    echo '</div><div class="wcm-hero-brief" aria-label="Bestandteile deines Firmenshops">';
    echo '<p class="wcm-brief-label">DEIN SHOP ENTHÄLT</p><dl class="wcm-hero-facts">';
    echo '<div><dt>Abgestimmte Kollektion</dt><dd>Artikel und Veredelung werden vor dem Start mit dir festgelegt.</dd></div>';
    echo '<div><dt>Dein Firmenauftritt</dt><dd>Logo und Firmenfarben prägen den eigenen Shopzugang.</dd></div>';
    echo '<div><dt>Bestellen und freigeben</dt><dd>Beschäftigte oder eine zuständige Person erfassen den Bedarf.</dd></div>';
    echo '</dl><a class="wcm-brief-link" href="/mustershop">Mustershop öffnen '.s_arrow().'</a>';
    echo '</div></div></section>';

    echo '<section class="wcm-purpose" id="was-ist"><div class="wcm-container"><p class="wcm-eyebrow">Was ist das WorkwearCenter?</p><div class="wcm-purpose-grid"><h2>Ein geschützter Shop für die Arbeitskleidung deines Unternehmens.</h2><div><p>Das WorkwearCenter ist ein B2B-Shopsystem für Unternehmen. Du erhältst einen eigenen Firmenshop mit den Kleidungsstücken, die zu euren Arbeitsbereichen passen. Logo, Veredelung und Gestaltung stimmen wir vorher mit dir ab.</p><p>Nach deiner Freigabe stellen wir die abgestimmten Artikel in den Shop. Deine Mitarbeitenden können dort selbst bestellen. Alternativ erfasst eine zuständige Person die Ausstattung für mehrere Beschäftigte. Bestellungen werden geprüft und gehen anschließend in die Auftragsverwaltung.</p><p class="wcm-origin"><strong>Warum es das WorkwearCenter gibt:</strong> Firmenkleidung wird oft über Listen, E-Mails und einzelne Rückfragen organisiert. Wir haben das Portal gegründet, damit Kollektion, Bestellung und Freigabe an einem Ort zusammenlaufen und dein Team denselben klaren Bestellweg nutzt.</p></div></div></div></section>';

    echo '<section class="wcm-benefits" id="vorteile"><div class="wcm-container"><div class="wcm-benefits-head"><div><p class="wcm-eyebrow">Was dein Firmenshop übernimmt</p><h2>Von der abgestimmten Kleidung bis zum Auftrag.</h2></div><p>Du legst mit uns fest, was dein Team bestellen kann und wie Bestellungen freigegeben werden. Im Portal bleiben Artikel und Mengen den jeweiligen Beschäftigten zugeordnet.</p></div><div class="wcm-benefits-grid">';
    $benefits=[
        ['01','Kleidung mit freigegebenem Logo','Vor dem Shopstart stimmen wir Artikel, Logo und Veredelung mit dir ab. Die vorgesehenen Kleidungsstücke kommen erst nach deiner Freigabe in den Firmenshop.'],
        ['02','Bestellungen pro Beschäftigtem','Beschäftigte bestellen selbst oder eine berechtigte Person erfasst die Ausstattung fürs Team. Artikel, Größen und Mengen bleiben den jeweiligen Personen zugeordnet.'],
        ['03','Freigabe und Auftragsverwaltung','Bestellungen laufen nach den vereinbarten Freigaberegeln. Danach übernehmen wir Produktion und Lieferung. Abrechnung, Lieferscheine und weitere Auftragsunterlagen laufen über die Auftragsverwaltung.'],
    ];
    foreach($benefits as [$number,$title,$text])echo '<article class="wcm-benefit"><span>'.$number.'</span><h3>'.e($title).'</h3><p>'.e($text).'</p></article>';
    echo '</div></div></section>';

    echo '<section class="wcm-order" id="bestellwege"><div class="wcm-container"><div class="wcm-order-heading"><p class="wcm-eyebrow">Zwei Bestellwege</p><h2>Dein Team bestellt passend zum Arbeitsalltag.</h2><p>Du legst bei der Einrichtung fest, wer Bestellungen erfasst und wer sie freigibt.</p></div><div class="wcm-order-grid">';
    echo '<article class="wcm-order-option"><span class="wcm-order-index">01</span><div><p class="wcm-order-kicker">MITARBEITENDE BESTELLEN SELBST</p><h3>Jede Person wählt die eigene Ausstattung.</h3><p>Beschäftigte melden sich im Firmenshop an und wählen die für sie freigegebenen Artikel, Größen und Mengen.</p><a href="/mustershop">Mustershop ansehen <span aria-hidden="true">↗</span></a></div></article>';
    echo '<article class="wcm-order-option"><span class="wcm-order-index">02</span><div><p class="wcm-order-kicker">BESTELLUNG FÜR DAS TEAM</p><h3>Eine Person erfasst die Kleidung der Belegschaft.</h3><p>Eine berechtigte Person trägt Artikel, Größen und Mengen für Beschäftigte ein. Das Portal ordnet jede Auswahl der richtigen Person zu.</p><a href="/so-funktionierts">Ablauf ansehen <span aria-hidden="true">↗</span></a></div></article>';
    echo '</div></div></section>';

    echo '<section class="wcm-handoff" id="ablauf"><div class="wcm-container wcm-handoff-grid"><div><p class="wcm-eyebrow">Von der Anfrage bis zur Lieferung</p><h2>Fünf Schritte bis zur ersten Lieferung.</h2><p>Die Artikel werden erst nach deiner Designfreigabe in den Shop aufgenommen. Sobald Bestellungen freigegeben sind, produzieren und liefern wir die Kleidung.</p></div><ol class="wcm-flow"><li><span>1</span><div><strong>Du fragst den Shop an</strong><small>Logo, gewünschte Artikel und eine Kontaktperson reichen für den Start.</small></div></li><li><span>2</span><div><strong>Wir stimmen Kollektion und Gestaltung ab</strong><small>Wir besprechen Kleidung, Logo und Veredelung mit dir.</small></div></li><li><span>3</span><div><strong>Du gibst den Entwurf frei</strong><small>Erst danach stellen wir die abgestimmten Artikel in deinen Firmenshop.</small></div></li><li><span>4</span><div><strong>Dein Team bestellt</strong><small>Mitarbeitende oder eine zuständige Person erfassen den Bedarf; Bestellungen werden nach den vereinbarten Regeln freigegeben.</small></div></li><li><span>5</span><div><strong>Wir produzieren und liefern</strong><small>Abrechnung, Lieferscheine und weitere Auftragsunterlagen laufen über die Auftragsverwaltung.</small></div></li></ol></div></section>';

    echo '<section class="wcm-faq" id="faq" aria-labelledby="wcm-faq-title"><div class="wcm-container wcm-faq-grid"><div class="wcm-faq-intro"><p class="wcm-eyebrow">Häufige Fragen</p><h2 id="wcm-faq-title">Was du vor der Anfrage wissen solltest.</h2><p>Hier stehen die Angaben, die für den Start wichtig sind. Offene Konditionen und Zeitangaben sind kenntlich gemacht, damit nichts versprochen wird, was noch nicht bestätigt ist.</p><a class="wcm-text-link" href="/anfrage">Shop anfragen <span aria-hidden="true">↗</span></a></div><div class="wcm-faq-list">';
    $faqs=[
        ['Für welche Unternehmen ist das WorkwearCenter gedacht?','Für Unternehmen aus Handwerk, Industrie, Dienstleistung und Zeitarbeit, die Arbeitskleidung über einen eigenen Firmenshop organisieren wollen. Der Shop zeigt die Kollektion, die du vorher mit uns abstimmst.'],
        ['Was bekomme ich konkret?','Einen geschützten Shop mit eurem Namen, Logo und Firmenfarben sowie der vereinbarten Arbeitskleidung. Wir stimmen die Gestaltung mit dir ab. Die Artikel kommen erst nach deiner Freigabe in den Shop.'],
        ['Was brauchst du von mir für den Start?','Dein Firmenlogo, die gewünschten Artikel oder Einsatzbereiche und eine Kontaktperson. Hilfreich sind außerdem die ungefähre Teamgröße und die Person, die Bestellungen freigibt.'],
        ['Kann eine Person für die ganze Belegschaft bestellen?','Ja. Eine berechtigte Person kann Artikel, Größen und Mengen für Beschäftigte erfassen. Ein Sammelvorgang umfasst bis zu 20 Personen; größere Belegschaften werden in mehreren Durchgängen erfasst. Jede Auswahl bleibt der jeweiligen Person zugeordnet.'],
        ['Wer gibt die Bestellungen frei?','Die für deinen Shop hinterlegte Rolle: [FREIGEBENDE ROLLE EINTRAGEN]. Wir legen den Bestellweg bei der Einrichtung gemeinsam fest.'],
        ['Was kostet der Firmenshop und die Kleidung?','[PREISMODELL UND PREISFAKTOREN EINTRAGEN]'],
        ['Wie lange dauert die Einrichtung?','[SHOP-EINRICHTUNGSDAUER EINTRAGEN]'],
        ['Wie lange dauert die Lieferung?','[LIEFERZEIT AB FREIGABE EINTRAGEN]'],
        ['Gibt es eine Mindestbestellmenge?','[MINDESTBESTELLMENGE EINTRAGEN]'],
        ['Wann bekomme ich nach meiner Anfrage eine Antwort?','[ANTWORTZEIT BESTÄTIGEN]. Nach deiner Anfrage prüfen wir die Angaben und melden uns per E-Mail bei dir.'],
        ['Wie laufen Abrechnung und Lieferscheine?','Nach der Freigabe gehen die Bestellungen in die Auftragsverwaltung. Dort laufen Abrechnung, Lieferscheine und weitere Auftragsunterlagen. Wir produzieren und liefern die bestellte Kleidung.'],
        ['Brauchen Beschäftigte eine App?','Nein. Der Firmenshop läuft im Browser auf Computer, Tablet und Smartphone. Für die Bestellung ist keine zusätzliche App erforderlich.'],
        ['Was sehe ich im Mustershop?','Der Mustershop zeigt beispielhaft, wie ein Firmenshop aufgebaut ist. Wenn du bei der Anfrage dein Logo hochlädst, kann zusätzlich eine unverbindliche Vorschau mit deinem Firmenlogo entstehen. Das ist noch nicht der freigegebene Shop mit deiner abgestimmten Kollektion.'],
    ];
    foreach($faqs as $i=>[$question,$answer])echo '<details class="wcm-faq-item"'.($i===0?' open':'').'><summary>'.e($question).'</summary><div class="wcm-faq-answer"><p>'.e($answer).'</p></div></details>';
    echo '</div></div></section>';

    s_trust_section();

    echo '<section class="wcm-contact"><div class="wcm-container wcm-contact-inner"><div><p class="wcm-eyebrow">Nächster Schritt</p><h2>Firmenshop anfragen.</h2><p>Schick uns dein Logo, die gewünschten Artikel und eine Kontaktperson. Den Ablauf stimmen wir danach mit dir ab.</p></div><div class="wcm-contact-actions"><a class="wcm-button wcm-button-light" href="/anfrage">Shop anfragen</a><a href="/kontakt">Kontakt</a></div></div></section>';

    echo '</main>';
    global_footer();
    echo '</div>';
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
        ['live-demo','s-row-img-live','Ihr Firmenshop','Ein geschützter Online-Shop nur für Ihr Unternehmen. Mitarbeitende bestellen selbst oder eine verantwortliche Person erfasst die Ausstattung für mehrere Beschäftigte.',['Persönliche Zugänge für jede Person','Team-Bestellung durch Verantwortliche','Jahresbudgets und Stücklimits','Freigaben und Auftragsstatus']],
    ];
    foreach($rows as $i=>[$img,$imgClass,$title,$text,$items]){
        echo '<article class="s-row'.($i%2?' s-row-reverse':'').'"><div class="s-row-img '.e($imgClass).'">';
        if($img==='live-demo')echo s_browser('s-browser-row');else echo '<img src="'.e($img).'" loading="lazy" alt="'.e($title).'">';
        echo '</div><div class="s-row-copy"><span class="s-num">0'.($i+1).'</span><h2>'.e($title).'</h2><p>'.e($text).'</p><ul class="s-ticks">';
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
    hp_page_hero('So funktioniert’s','In vier Schritten zu Ihrem Firmenshop.','<p>Sie legen Kollektion, Regeln und Zuständigkeiten fest. Mitarbeitende können selbst bestellen – oder eine verantwortliche Person bündelt die Ausstattung für das Team.</p>',[['Firmenshop anfragen','/anfrage',true],['Mustershop testen','/mustershop',false]]);
    $steps=[
        ['Kollektion abstimmen','Gemeinsam legen wir fest, welche Kleidung Ihr Team bekommt und wie Ihr Logo darauf aussieht.','Sie nennen Einsatzbereich, Wunschartikel und schicken uns Ihr Logo.','Wir schlagen passende Artikel vor, beraten zu Verfahren und Position und fertigen auf Wunsch ein Muster.'],
        ['Shop einrichten','Ihr Firmenshop bekommt Ihr Logo, Ihre Kollektion und Ihre Regeln.','Sie bestimmen Budget, Lieferadresse und wer Bestellungen freigibt.','Wir richten den Shop vollständig ein und laden Ihr Team per E-Mail ein.'],
        ['Team bestellt','Jede Person bestellt selbst – oder eine verantwortliche Person erfasst mehrere Mitarbeiteraufträge gesammelt.','Sie bestimmen, ob Mitarbeitende selbst bestellen oder wer Teamaufträge erfasst. Budgets, Stücklimits und Freigaben bleiben je Person zugeordnet.','Das Portal prüft jeden Auftrag einzeln und übergibt freigegebene Bestellungen an die Auftragsverwaltung.'],
        ['Veredeln & liefern','Wir besorgen die Ware, veredeln jedes Teil und liefern an Ihre Adresse.','Sie erhalten die Kleidung nach Personen sortiert – und eine Rechnung wie gewohnt.','Wir prüfen jedes Teil vor dem Versand und zeigen den Versandstatus im Shop an.'],
    ];
    echo '<section class="s-section"><div class="s-wrap"><ol class="s-timeline">';
    foreach($steps as $i=>[$title,$text,$you,$we]){
        echo '<li class="s-timeline-item"><span class="s-timeline-num">'.($i+1).'</span><div class="s-timeline-card"><h2>'.e($title).'</h2><p class="s-timeline-text">'.e($text).'</p><div class="s-who"><div><h3>Ihr Teil</h3><p>'.e($you).'</p></div><div><h3>Unser Teil</h3><p>'.e($we).'</p></div></div></div></li>';
    }
    echo '</ol></div></section>';

    echo '<section class="s-section s-section-grey"><div class="s-wrap s-feature"><div class="s-feature-copy"><h2>Jede Bestellung bleibt transparent.</h2><div class="s-benefits s-benefits-one">';
    foreach([['Mitarbeitende','sehen ihre Kollektion, ihr verfügbares Budget und den Stand ihrer Aufträge.'],['Verantwortliche','verwalten das Team, geben Bestellungen frei und können Kleidung für mehrere Personen gesammelt erfassen.'],['Auftragsverwaltung','erhält jeden Auftrag mit Mitarbeiter, Größen, Mengen und Veredelung. Abrechnung und Lieferscheine laufen über den bestehenden Ablauf.']] as $i=>[$t,$x])echo '<div class="s-benefit"><span class="s-num">0'.($i+1).'</span><div><h3>'.e($t).'</h3><div class="s-benefit-text"><p>'.e($x).'</p></div></div></div>';
    echo '</div></div><figure class="s-how-photo"><img src="'.e(s_img('section2_image','assets/media/work/polo-wappen.jpg')).'" loading="lazy" alt="Firmenpoloshirt mit gesticktem Wappen"><figcaption>Veredelt und geprüft in Salzkotten</figcaption></figure></div></section>';

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

    echo '<section class="s-section"><div class="s-wrap s-workshop s-workshop-reverse"><div class="s-workshop-copy"><h2>Warum es das WorkwearCenter gibt</h2><div class="s-intro"><p>Bei Firmenkunden sieht die Bestellung von Arbeitskleidung oft gleich aus: Größen werden auf Zetteln gesammelt, Nachbestellungen gehen unter, und niemand weiß genau, wie viel vom Budget schon verbraucht ist.</p><p>Deshalb haben wir das WorkwearCenter entwickelt: Mitarbeitende können selbst bestellen, Verantwortliche können Teamaufträge gesammelt erfassen. So bleiben Größen, Budgets und Freigaben nachvollziehbar. Die Veredelung bleibt Handarbeit: Wir sticken, drucken und prüfen jedes Teil selbst.</p></div><p class="s-owner"><strong>'.e($owner).'</strong><span>Inhaber, '.e($company).'</span></p></div>';
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
    hp_page_hero('Kontakt','Schreib uns zum Firmenshop.','<p>Du möchtest einen Shop für dein Team einrichten oder hast eine Frage zu einer bestehenden Bestellung? Schreib uns per Anfrageformular oder E-Mail.</p>');
    $mail=s_mail();$owner=current_site_setting('imprint_owner','Jens Yanik');
    $street=current_site_setting('imprint_street','Geseker Straße 19');$city=current_site_setting('imprint_city','33154 Salzkotten');$company=current_site_setting('imprint_company','Yanik Textildruck');
    echo '<section class="s-section"><div class="s-wrap"><div class="s-contact-grid">';
    echo '<article class="s-contact-card s-contact-main"><h2>Du möchtest einen Firmenshop?</h2><p>Über das Anfrageformular sendest du uns die wichtigsten Eckdaten. Wir sehen uns deine Angaben an und melden uns per E-Mail.</p><a class="s-btn s-btn-orange" href="/anfrage">Shop anfragen</a></article>';
    echo '<article class="s-contact-card"><h2>E-Mail</h2><p><a class="s-contact-big" href="mailto:'.e($mail).'">'.e($mail).'</a></p><p class="s-muted">Du kannst dein Logo oder deine Wunschartikel anhängen.</p></article>';
    echo '<article class="s-contact-card"><h2>Anschrift</h2><p>'.e($company).'<br>'.e($street).'<br>'.e($city).'</p><p class="s-muted">Kontaktperson: '.e($owner).'<br>Besuche nach Terminabsprache.</p></article>';
    echo '<article class="s-contact-card"><h2>Schon Kunde?</h2><p>Mitarbeitende und Verantwortliche melden sich direkt im eigenen Firmenshop an.</p><a class="s-more" href="/zugang">Zum Kundenportal'.s_arrow().'</a></article>';
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
function site_inquiry_render(bool $success,string $error,?string $probeshopToken=null,bool $confirmationSent=true): void {
    hp_page_start('Unternehmensshop anfragen – '.current_site_setting('site_title','WorkwearCenter'));
    hp_page_hero('Anfrage','Firmenshop anfragen','<p>Nenn uns deine gewünschten Artikel, die ungefähre Teamgröße und eine Kontaktperson. Preise, Einrichtung und Lieferzeit bestätigen wir nach der Abstimmung.</p>',[],true);
    echo '<section class="s-section"><div class="s-wrap s-inquiry"><div class="s-form-card">';
    if($success){
        echo '<div class="s-success"><span class="s-success-mark">'.hp_check_icon().'</span><h2>Danke für deine Anfrage.</h2><p>Wir haben deine Angaben erhalten und melden uns innerhalb von [ANTWORTZEIT BESTÄTIGEN] per E-Mail.</p>';
        if($probeshopToken!==null)echo '<div class="s-probe-ready"><strong>Deine unverbindliche Vorschau ist bereit.</strong><p>Sie zeigt dein Logo auf Musterartikeln und ist 30 Tage über diesen Link erreichbar. Sie enthält noch nicht die freigegebene Firmenkollektion.</p><a class="s-btn s-btn-orange" href="/probeshop/'.e($probeshopToken).'" rel="noreferrer">Vorschau ansehen</a></div>';
        else echo '<a class="s-btn s-btn-navy" href="/mustershop">Mustershop ansehen</a>';
        echo '</div>';
    }else{
        if($error)echo '<div class="flash error">'.e($error).'</div>';
        echo '<form method="post" enctype="multipart/form-data" class="s-form">'.csrf_field();
        echo '<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>';
        echo '<div class="s-form-row"><label>Unternehmen<input name="company" autocomplete="organization" value="'.e((string)($_POST['company']??'')).'" required></label><label>Dein Name<input name="name" autocomplete="name" value="'.e((string)($_POST['name']??'')).'" required></label></div>';
        echo '<div class="s-form-row"><label>E-Mail<input type="email" name="email" autocomplete="email" value="'.e((string)($_POST['email']??'')).'" required></label><label>Teamgröße <small>ungefähr, optional</small><input type="number" min="0" name="employees" value="'.e((string)($_POST['employees']??'')).'"></label></div>';
        echo '<label>Welche Artikel und Bestellwege brauchst du?<textarea name="message" rows="6" placeholder="Zum Beispiel Arbeitsjacken und Polos; Mitarbeitende bestellen selbst oder eine Person sammelt die Ausstattung fürs Team." required>'.e((string)($_POST['message']??'')).'</textarea></label>';
        echo '<div class="s-upload"><label>Dein Firmenlogo <small>optional · PNG, JPG, WebP oder SVG, max. 4 MB</small><input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg,image/png,image/jpeg,image/webp,image/svg+xml"></label><p>Mit deinem Logo erstellen wir eine <strong>unverbindliche Vorschau</strong> mit Musterartikeln. Der Link ist 30 Tage gültig. Die abgestimmte Kollektion kommt erst nach deiner Freigabe in den Firmenshop.</p></div>';
        echo '<label class="s-check"><input type="checkbox" name="privacy" value="1" required><span>Ich bin einverstanden, dass meine Angaben und ein gegebenenfalls hochgeladenes Logo zur Bearbeitung der Anfrage verwendet werden. <a href="/datenschutz" target="_blank" rel="noopener">Datenschutzhinweise</a></span></label>';
        echo '<button class="s-btn s-btn-orange" type="submit">Anfrage senden</button></form>';
    }
    echo '</div><aside class="s-inquiry-side"><h2>Was nach deiner Anfrage passiert</h2><ol class="s-mini-steps"><li><strong>Wir prüfen deine Angaben</strong><span>Antwort innerhalb von [ANTWORTZEIT BESTÄTIGEN].</span></li><li><strong>Wir stimmen den Entwurf ab</strong><span>Artikel, Logo und Veredelung legen wir gemeinsam fest.</span></li><li><strong>Du gibst die Gestaltung frei</strong><span>Erst danach kommen die abgestimmten Artikel in den Firmenshop.</span></li></ol><p class="s-inquiry-mail">Lieber per E-Mail?<br><a href="mailto:'.e(s_mail()).'">'.e(s_mail()).'</a></p></aside></div></section>';
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
