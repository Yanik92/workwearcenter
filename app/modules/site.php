<?php
declare(strict_types=1);

/*
 * Öffentliche Website ab 10.20.0 („Werkstatt-Premium“): Navy, Orange und die gestrichelte Naht.
 * Die Seiten nutzen nur assets/web.css und assets/web.js (w-Klassen). public_header() und
 * global_footer() im alten Stil bleiben für Mustershop, Probeshop und Verwaltung erhalten.
 */

require_once __DIR__.'/legal.php';
require_once __DIR__.'/site_topics.php';

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
    echo '<details class="s-menu"><summary aria-label="Menü"><span></span><span></span><span></span></summary><nav aria-label="Mobile Navigation"><a href="/">Startseite</a>'.$nav.($path==='/'?'<a href="/leistungen">Kleidung & Veredelung</a><a href="/so-funktionierts">So funktioniert’s</a><a href="/ueber-uns">Über uns</a>':'').'<a href="/zugang">Kundenportal</a><a class="s-btn s-btn-orange" href="/anfrage">Shop anfragen</a></nav></details>';
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
        echo '<div><h2>WorkwearCenter</h2><nav aria-label="WorkwearCenter"><a href="/#was-ist">Was ist das WorkwearCenter?</a><a href="/#vorteile">Vorteile für dein Unternehmen</a><a href="/#bestellwege">Bestellwege</a><a href="/#ablauf">Auftragsweg</a><a href="/#faq">Häufige Fragen</a><a href="/mustershop">Mustershop</a></nav></div>';
        echo '<div><h2>Kontakt & Zugang</h2><nav aria-label="Kontakt und Zugang"><a href="/anfrage">Shop anfragen</a><a href="/kontakt">Kontakt</a><a href="/zugang">Kundenportal</a></nav></div>';
        // 10.19.1: Von der Startseite waren Leistungen, Ablauf und Über uns nicht erreichbar (Impressum/Datenschutz stehen unten).
        echo '<div><h2>Yanik Textildruck</h2><nav aria-label="Unternehmen"><a href="/leistungen">Kleidung & Veredelung</a><a href="/so-funktionierts">So funktioniert’s</a><a href="/ueber-uns">Über uns</a></nav></div>';
        echo '</div><div class="s-footer-bottom"><span>© '.date('Y').' WorkwearCenter</span><span><a href="/impressum">Impressum</a><a href="/datenschutz">Datenschutz</a></span></div></div></footer>';
        return;
    }
    $company=current_site_setting('imprint_company','Yanik Textildruck');
    $street=current_site_setting('imprint_street','Geseker Straße 19');$city=current_site_setting('imprint_city','33154 Salzkotten');
    $mail=s_mail();
    echo '<footer class="s-footer"><div class="s-wrap"><div class="s-footer-grid">';
    echo '<div class="s-footer-brand">'.s_public_brand_html(false).'<p>Firmenshop für Arbeitskleidung mit Stick und Druck aus eigener Produktion in Salzkotten.</p></div>';
    echo '<div><h2>WorkwearCenter</h2><nav aria-label="WorkwearCenter"><a href="/leistungen">Leistungen</a><a href="/so-funktionierts">Firmenshop & Ablauf</a><a href="/mustershop">Mustershop ansehen</a><a href="/anfrage">Shop anfragen</a></nav></div>';
    echo '<div><h2>Yanik Textildruck</h2><nav aria-label="Unternehmen"><a href="/ueber-uns">Über uns</a><a href="/kontakt">Kontakt</a><a href="/zugang">Kundenportal</a></nav></div>';
    echo '<div><h2>Kontakt</h2><p>'.e($company).'<br>'.e($street).'<br>'.e($city).'</p><p><a href="mailto:'.e($mail).'">'.e($mail).'</a></p></div>';
    echo '</div><div class="s-footer-bottom"><span>© '.date('Y').' '.e(yw_text('footer_text')).'</span><span><a href="/impressum">Impressum</a><a href="/datenschutz">Datenschutz</a></span></div></div></footer>';
}


/* ---------- Bausteine der Website (10.20, überarbeitet 10.23) ---------- */

/** Linien-Symbole der Website (aria-hidden, Farbe über currentColor). */
function w_icon(string $name): string {
    $paths=[
        'arrow'=>'<path d="M4 12h15M13 6l6 6-6 6"/>',
        'play'=>'<path d="M8 5v14l11-7z" fill="currentColor" stroke="none"/>',
        'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
        'shield'=>'<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'factory'=>'<path d="M3 21h18M5 21V9l7-5 7 5v12M9 21v-6h6v6"/>',
        'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'check'=>'<path d="m5 12 5 5 9-10"/>',
        'x'=>'<path d="M6 6l12 12M18 6 6 18"/>',
        'needle'=>'<path d="M4 20 20 4M14 4h6v6"/><path d="M6 14c2 0 2 4 4 4" stroke-dasharray="2 3"/>',
        'print'=>'<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M4 14h16"/>',
        'transfer'=>'<path d="M12 3v18M3 12h18"/><circle cx="12" cy="12" r="4"/>',
        'flex'=>'<path d="M5 19 19 5"/><path d="M7 5h12v12"/>',
        'pin'=>'<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'chat'=>'<path d="M4 4h16v12H8l-4 4z"/>',
        'plus'=>'<circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/>',
        'cart'=>'<path d="M6 6h15l-2 9H8L6 3H3"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/>',
        'truck'=>'<path d="M3 7h11v10H3zM14 10h4l3 3v4h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'globe'=>'<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18"/>',
        'team'=>'<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c1-3.5 3.6-5.5 6.5-5.5s5.5 2 6.5 5.5"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.5c2 .7 3 2.6 3.5 5.5"/>',
        'clipboard'=>'<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1M9 10h6M9 14h6M9 18h3"/>',
        'stack'=>'<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
        'mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'phone'=>'<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
        'pause'=>'<rect x="6" y="4" width="4" height="16" fill="currentColor" stroke="none"/><rect x="14" y="4" width="4" height="16" fill="currentColor" stroke="none"/>',
        'swap'=>'<path d="M8 7 4 12l4 5M16 7l4 5-4 5"/>',
        'lock'=>'<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'wallet'=>'<rect x="3" y="6" width="18" height="14" rx="2"/><path d="M3 10h18M16 15h2"/><path d="M6 6V4h12v2"/>',
        'refresh'=>'<path d="M20 11a8 8 0 0 0-14.5-4.5L4 8M4 4v4h4M4 13a8 8 0 0 0 14.5 4.5L20 16M20 20v-4h-4"/>',
        'chart'=>'<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'tag'=>'<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1.5"/>',
        'hammer'=>'<path d="m14 6 4 4M3 21l9-9M12 4l3-1 6 6-1 3-3-1-5-5z"/>',
        'gear'=>'<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/>',
        'heart'=>'<path d="M12 20s-8-5-8-11a4.5 4.5 0 0 1 8-2.8A4.5 4.5 0 0 1 20 9c0 6-8 11-8 11z"/>',
        'flame'=>'<path d="M12 21c-4 0-7-2.7-7-6.5 0-4 4-6 3.5-10.5 3 1.5 4.5 4 4.5 6.5 1-1 1.5-2.5 1.5-3.5C17 9 19 12 19 14.5 19 18.3 16 21 12 21z"/>',
        'building'=>'<path d="M4 21V5l8-2v18M12 9h8v12M7 8h2M7 12h2M7 16h2M15 13h2M15 17h2M2 21h20"/>',
        'briefcase'=>'<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V4h6v3M3 13h18"/>',
        'upload'=>'<path d="M12 16V4M7 9l5-5 5 5M4 16v4h16v-4"/>',
        'link'=>'<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7L11.5 6.8M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1.5-1.5"/>',
        'euro'=>'<path d="M17 6.5A7 7 0 1 0 17 17.5M4 10h9M4 14h9"/>',
        'box'=>'<path d="M3 7l9-4 9 4v10l-9 4-9-4z"/><path d="M3 7l9 4 9-4M12 11v10"/>',
        'shop'=>'<path d="M4 9l1.5-5h13L20 9M4 9h16v11H4zM9 20v-6h6v6"/>',
        'shirt'=>'<path d="M8 3 3 6l2 5 3-1v11h8V10l3 1 2-5-5-3a4 4 0 0 1-8 0z"/>',
        'doc'=>'<path d="M6 3h9l4 4v14H6zM14 3v5h5M9 13h7M9 17h7"/>',
        'eye'=>'<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??'').'</svg>';
}

/** Logo für helle Flächen (ab 10.26 sind Kopf und Fuß der Website hell): dunkles „Workwear“, oranges „Center“. */
function w_logo_path(): string {
    $logo=yw_site_logo_path(false);
    $defaults=['assets/media/workwearcenter-logo.png','assets/media/workwearcenter-logo-dunkel.png','assets/media/workwearcenter-logo-anthrazit.png','assets/media/workwearcenter-logo-ice.svg','assets/media/workwearcenter-logo-warm.svg'];
    return in_array($logo,$defaults,true)?'assets/media/workwearcenter-logo-dunkel.png':$logo;
}

/** Ort aus der Impressumsanschrift („33154 Salzkotten“ → „Salzkotten“). */
function w_city(): string {
    $city=trim((string)current_site_setting('imprint_city','33154 Salzkotten'));
    return trim((string)preg_replace('/^\d{4,5}\s+/u','',$city))?:'Salzkotten';
}

/** Die beiden Handlungsaufforderungen der ganzen Website (10.26: „Unverbindlich Shop anfragen“ und Beispiel-Shop). */
function w_cta_buttons(bool $onDark=false,string $class=''): string {
    return '<div class="w-cta'.($class!==''?' '.$class:'').'"><a class="w-btn w-btn-o" href="/anfrage">Unverbindlich Shop anfragen'.w_icon('arrow').'</a><a class="w-btn '.($onDark?'w-btn-ghost':'w-btn-line').'" href="/mustershop">Beispiel-Shop ansehen</a></div>';
}

/** Kopf der Website (10.26): hell, wenige Punkte, Kunden-Login rechts abgetrennt. Keine Telefonnummer. */
function w_header(): void {
    $path=path_only();$home=$path==='/';
    $links=['/so-funktionierts'=>'So funktioniert’s','/leistungen'=>'Leistungen','/#referenzen'=>'Referenzen','/#faq'=>'FAQ'];
    $nav='';foreach($links as $href=>$label)$nav.='<li><a href="'.$href.'"'.($path===$href?' aria-current="page"':'').'>'.e($label).'</a></li>';
    $ask=$home?'#anfrage':'/anfrage';
    echo '<a class="w-skip" href="#inhalt">Zum Inhalt springen</a>';
    echo '<header class="w-nav"><div class="w-wrap">';
    echo '<a class="w-brand" href="/" aria-label="WorkwearCenter Startseite"><img src="/'.e(w_logo_path()).'" alt="WorkwearCenter" width="252" height="32"></a>';
    echo '<nav aria-label="Hauptnavigation" class="w-menu-nav"><ul class="w-menu">'.$nav.'</ul></nav>';
    echo '<div class="w-nav-end"><a class="w-login" href="/zugang"'.($path==='/zugang'?' aria-current="page"':'').'>'.w_icon('user').'Kunden-Login</a>';
    echo '<a class="w-btn w-btn-o w-btn-sm" href="'.$ask.'">Shop anfragen</a></div>';
    echo '<details class="w-burger"><summary aria-label="Menü öffnen"><span></span><span></span><span></span></summary><nav aria-label="Mobile Navigation"><a href="/">Startseite</a>';
    foreach($links+['/branchen'=>'Branchen','/mustershop'=>'Beispiel-Shop','/ueber-uns'=>'Über uns','/kontakt'=>'Kontakt'] as $href=>$label)echo '<a href="'.$href.'"'.($path===$href?' aria-current="page"':'').'>'.e($label).'</a>';
    echo '<a class="w-btn w-btn-o" href="'.$ask.'">Unverbindlich Shop anfragen</a><a class="w-burger-login" href="/zugang">'.w_icon('user').'Kunden-Login für bestehende Kunden</a></nav></details>';
    echo '</div></header>';
}

function w_footer(): void {
    $o=yw_operator();$mail=s_mail();
    echo '<footer class="w-footer"><div class="w-wrap"><div class="w-fgrid">';
    echo '<div><img src="/'.e(w_logo_path()).'" alt="WorkwearCenter" width="220" height="28" loading="lazy"><p>Der Firmenshop für Arbeitskleidung. Ein Angebot von '.e($o['company']).', mit Stick und Druck aus eigener Produktion.</p>';
    echo '<address class="w-faddr">'.e($o['company']).'<br>'.e($o['street']).'<br>'.e($o['city']).'<br><a href="mailto:'.e($mail).'">'.e($mail).'</a></address></div>';
    echo '<div><h2>WorkwearCenter</h2><nav aria-label="WorkwearCenter"><a href="/so-funktionierts">So funktioniert’s</a><a href="/leistungen">Leistungen</a><a href="/#referenzen">Referenzen</a><a href="/mustershop">Beispiel-Shop</a><a href="/#faq">Häufige Fragen</a></nav></div>';
    echo '<div><h2>Themen</h2><nav aria-label="Themen"><a href="/branchen">Branchen</a><a href="/arbeitskleidung-bestellsystem">Bestellsystem &amp; Freigaben</a><a href="/mitarbeitershop-arbeitskleidung">Mitarbeitershop</a><a href="/workwear-management">Workwear Management</a></nav></div>';
    echo '<div><h2>Unternehmen</h2><nav aria-label="Unternehmen"><a href="/ueber-uns">Über uns</a><a href="/kontakt">Kontakt</a><a href="/anfrage">Shop anfragen</a><a href="/zugang">Kunden-Login</a></nav></div>';
    echo '</div><div class="w-fbottom"><span>© '.date('Y').' WorkwearCenter · '.e($o['company']).', '.e(w_city()).'</span><span><a href="/impressum">Impressum</a><a href="/datenschutz">Datenschutz</a></span></div></div></footer>';
}

function hp_check_icon(): string { return w_icon('check'); }

/** Seitenanfang: Titel kommt aus yw_public_pages() (SEO-Titel), sonst der übergebene Text. */
function hp_page_start(string $documentTitle=''): void {
    render_head(yw_page_title(path_only(),$documentTitle!==''?$documentTitle:'WorkwearCenter'));
    w_header();
    echo '<main id="inhalt">';
}

function hp_page_end(): void { echo '</main>'; w_footer(); render_foot(); }

/** Kopf einer Unterseite: heller Bereich mit Brotkrumen. $actions=true zeigt die zwei Standard-Knöpfe. */
function hp_page_hero(string $label,string $title,string $introHtml,array|bool $actions=[],bool $compact=false,string $eyebrow=''): void {
    echo '<section class="w-pagehead'.($compact?' compact':'').'"><div class="w-wrap"><nav class="w-crumbs" aria-label="Brotkrumen"><a href="/">Start</a><span aria-hidden="true">/</span><span aria-current="page">'.e($label).'</span></nav>';
    if($eyebrow!=='')echo '<span class="w-eyebrow w-pagehead-eyebrow">'.e($eyebrow).'</span>';
    echo '<h1>'.e($title).'</h1>';
    if($introHtml!=='')echo '<div class="w-lead">'.$introHtml.'</div>';
    if($actions===true)echo w_cta_buttons();
    elseif(is_array($actions)&&$actions){echo '<div class="w-cta">';foreach($actions as [$text,$href,$primary])echo '<a class="w-btn '.($primary?'w-btn-o':'w-btn-line').'" href="'.e($href).'">'.e($text).($primary?w_icon('arrow'):'').'</a>';echo '</div>';}
    echo '</div></section>';
}

/** Abschnittskopf: Augenbraue, Überschrift und kurzer Text. */
function s_head(string $title,string $text='',string $eyebrow='',string $id=''): void {
    echo '<div class="w-head w-reveal"><div>'.($eyebrow!==''?'<span class="w-eyebrow">'.e($eyebrow).'</span>':'').'<h2'.($id!==''?' id="'.e($id).'"':'').'>'.e($title).'</h2></div>'.($text!==''?'<p>'.e($text).'</p>':'').'</div>';
}

/** Abschluss jeder Unterseite: navyblauer Kasten mit den zwei Standard-Knöpfen. */
function hp_cta_band(string $title='Dein Firmenshop für Arbeitskleidung.',string $text='Schick uns die Eckdaten, gern mit Logo. Wir melden uns per E-Mail mit einem Vorschlag für Kollektion und Veredelung. Der Shop ist für dein Unternehmen kostenfrei.'): void {
    $mail=s_mail();
    echo '<section class="w-final"><div class="w-wrap"><div class="box"><div><h2>'.e($title).'</h2><p>'.e($text).'</p></div>';
    echo '<div class="actions"><a class="w-btn w-btn-o" href="/anfrage">Unverbindlich Shop anfragen'.w_icon('arrow').'</a><a class="w-btn w-btn-ghost" href="/mustershop">Beispiel-Shop ansehen</a><small>Kostenfrei und unverbindlich · <a class="mail" href="mailto:'.e($mail).'">'.e($mail).'</a></small></div>';
    echo '</div></div></section>';
}

/** Kompatibilität: frühere Seiten riefen diesen Kopf auf. */
function marketing_page_header(string $titleKey,string $introKey,string $fallbackTitle,string $fallbackIntro): void {
    hp_page_start(yw_text($titleKey).' – '.current_site_setting('site_title','WorkwearCenter'));
    hp_page_hero('WorkwearCenter',yw_text($titleKey)?:$fallbackTitle,rich_text(yw_text($introKey)?:$fallbackIntro));
}

/** FAQ-Liste (Frage, Antwort). Die gleichen Einträge gehen vorher per yw_jsonld_faq() in die strukturierten Daten. */
function w_faq_list(array $faqs): void {
    echo '<div class="w-faq">';
    foreach($faqs as $i=>[$q,$a])echo '<details'.($i===0?' open':'').'><summary>'.e($q).'</summary><div class="a"><p>'.e($a).'</p></div></details>';
    echo '</div>';
}

/** FAQ-Abschnitt mit Einleitung links. */
function w_faq_section(array $faqs,string $title,string $intro='Deine Frage ist nicht dabei? Schreib uns eine E-Mail.',bool $white=true): void {
    echo '<section class="w-sec'.($white?' w-white':'').'" id="faq" aria-labelledby="w-faq-title"><div class="w-wrap w-faq-grid"><div class="w-faq-intro"><h2 id="w-faq-title">'.e($title).'</h2><p>'.e($intro).'</p><a class="w-btn w-btn-o w-btn-sm" href="'.(path_only()==='/'?'#anfrage':'/anfrage').'">Shop anfragen</a></div>';
    w_faq_list($faqs);
    echo '</div></section>';
}

/** Ältere Aufrufer: FAQ aus Admin → Inhalte. Seit 10.23 nicht mehr auf der Website genutzt (Texte stehen fest im Code, in Du-Form). */
function s_faq(array $extra=[]): void { w_faq_list($extra); }

/** Kundenstimme (nur wenn gepflegt) und Ansprechpartner (Standard: Inhaber aus dem Impressum). */
function w_voice_section(): void {
    $c=yw_trust_content();$mail=s_mail();$o=yw_operator();
    $p=$c['contact'];
    $name=$p['name']!==''?$p['name']:$o['owner'];
    $role=$p['role']!==''?$p['role']:'Inhaber, '.$o['company'];
    $text=$p['text']!==''?$p['text']:'Ich berate dich persönlich, vom ersten Muster bis zur Lieferung. Kein Callcenter, keine Warteschleife.';
    echo '<section class="w-sec w-sec-flush"><div class="w-wrap"><div class="w-voice'.($c['has_quote']?'':' single').'">';
    if($c['has_quote']){
        $q=$c['quote'];
        $initials=implode('',array_map(fn($w)=>mb_strtoupper(mb_substr($w,0,1)),array_slice(preg_split('/\s+/u',trim($q['name']))?:[],0,2)));
        echo '<figure class="w-quote"><blockquote><p>„'.nl2br(e(trim($q['text'],"„“\"' "))).'“</p></blockquote>';
        if($q['name']!==''||$q['company']!=='')echo '<figcaption>'.($initials!==''?'<i aria-hidden="true">'.e($initials).'</i>':'').'<span>'.e($q['name']).($q['company']!==''?'<small>'.e($q['company']).'</small>':'').'</span></figcaption>';
        echo '</figure>';
    }
    echo '<aside class="w-person"><div class="av">'.($p['photo']!==''?'<img src="/'.e($p['photo']).'" alt="'.e($name).'" width="96" height="96" loading="lazy" decoding="async">':e(mb_strtoupper(mb_substr($name,0,1)))).'</div>';
    echo '<div class="w-person-copy"><span class="w-eyebrow">Dein Ansprechpartner</span><h3>'.e($name).'</h3><p class="role">'.e($role).' · '.e(w_city()).'</p><p>'.e($text).'</p></div>';
    echo '<div class="row"><a class="w-btn w-btn-o w-btn-sm" href="/anfrage">Shop anfragen</a><a class="w-btn w-btn-line w-btn-sm" href="mailto:'.e($mail).'">'.w_icon('mail').'E-Mail</a></div></aside>';
    echo '</div></div></section>';
}

/** Kundenlogos (nur freigegebene). $embedded: ohne eigenen dunklen Abschnitt. */
function s_trust_section(bool $embedded=false): void {
    $c=yw_trust_content();
    if(!$c['logos'])return;
    $loop=count($c['logos'])>=3;
    echo '<section class="w-trust'.($embedded?' embedded':'').'" aria-labelledby="w-trust-title"><div class="'.($embedded?'':'w-wrap').'"><div class="w-trust-head"><h'.($embedded?'3':'2').' id="w-trust-title">'.e($c['title']).'</h'.($embedded?'3':'2').'>';
    if($loop)echo '<button class="w-trust-toggle" type="button" data-w-trust-toggle aria-pressed="false">'.w_icon('pause').'<span>Anhalten</span></button>';
    echo '</div><div class="w-marquee '.($loop?'is-moving':'is-static').'" style="--dur:'.max(24,count($c['logos'])*7).'s"><ul class="w-lane" aria-label="Logos von Kundenunternehmen">';
    $copies=$loop?2:1;
    for($copy=0;$copy<$copies;$copy++){
        foreach($c['logos'] as $logo){
            $alt=$logo['name']!==''?'Logo '.$logo['name']:'Kundenlogo';
            echo '<li class="w-lg"'.($copy>0?' aria-hidden="true"':'').'><img src="/'.e($logo['path']).'" alt="'.($copy>0?'':e($alt)).'" loading="lazy" decoding="async"></li>';
        }
    }
    echo '</ul></div></div></section>';
}

/** Browserrahmen mit einem echten Bildschirmfoto aus einem Firmenshop. */
function w_shot(string $file,string $alt,int $w,int $h,string $url='workwearcenter.de/hanseatic',bool $eager=false,string $class=''): string {
    return '<figure class="w-shot'.($class!==''?' '.$class:'').'"><div class="bar" aria-hidden="true"><i></i><i></i><i></i><span>'.e($url).'</span></div><img src="/assets/media/screens/'.e($file).'.webp" alt="'.e($alt).'" width="'.$w.'" height="'.$h.'"'.($eager?' fetchpriority="high"':' loading="lazy"').' decoding="async"></figure>';
}

/** Handyrahmen mit echtem Bildschirmfoto. */
function w_phone(string $file,string $alt,bool $lazy=true): string {
    return '<figure class="w-phone"><img src="/assets/media/screens/'.e($file).'.webp" alt="'.e($alt).'" width="390" height="844"'.($lazy?' loading="lazy"':'').' decoding="async"></figure>';
}

/** Ansichten aus dem Firmenshop (echte Oberfläche mit Beispieldaten des Musterunternehmens Hanseatic Haustechnik). */
function w_shop_views(): array {
    return [
        ['start','Start & Budget','shop-start',950,'Startseite eines Firmenshops: persönliche Begrüßung, verfügbares Jahresbudget, letzte Bestellung mit Status und die Firmenkollektion','Jede Person sieht nach der Anmeldung ihr verfügbares Budget, die letzte Bestellung und die freigegebene Kollektion.','workwearcenter.de/hanseatic'],
        ['artikel','Artikel & Größe','shop-artikel',900,'Produktansicht im Firmenshop: Hoodie mit Größenwahl, Menge, Stick Brust links und Aufteilung des Preises in Firmenanteil und Eigenanteil','Größe wählen, Menge festlegen, fertig. Logo-Position und Veredelung sind fest hinterlegt, der Preis zeigt Firmen- und Eigenanteil.','workwearcenter.de/hanseatic/produkte'],
        ['freigabe','Freigabe','shop-freigabe',1549,'Freigabeseite für Verantwortliche: offene Bestellungen mit Artikeln, Größen und Beträgen, Knöpfe zum Freigeben oder Ablehnen','Verantwortliche sehen alle offenen Bestellungen mit Person, Größen und Betrag und geben mit einem Klick frei oder lehnen mit Hinweis ab.','workwearcenter.de/hanseatic/freigaben'],
        ['status','Bestellstatus','shop-status',766,'Bestellübersicht mit Statusleiste: freigegeben, in Produktion, fertig, versendet','Freigegeben, in Produktion, fertig, versendet: Der Status wird aus unserer Produktion heraus aktualisiert.','workwearcenter.de/hanseatic/bestellungen'],
        ['spind','Mein Spind','shop-spind',830,'Mein Spind: erhaltene Kleidung mit Größe und Datum, Knöpfe zum Nachbestellen und Größe tauschen','Im Spind steht, was jemand schon bekommen hat. Nachbestellen in der bekannten Größe oder Umtausch beantragen geht von hier aus.','workwearcenter.de/hanseatic/spind'],
    ];
}

/** Bildergalerie „So sieht dein Firmenshop aus“ mit Reitern (ohne JavaScript als waagerechte Bildreihe). */
function w_showcase(bool $withPhone=true): void {
    $views=w_shop_views();
    echo '<div class="w-showcase" data-showcase><div class="w-tabs" role="tablist" aria-label="Ansichten aus dem Firmenshop" hidden>';
    foreach($views as $i=>[$id,$label])echo '<button type="button" role="tab" id="w-tab-'.e($id).'" aria-controls="w-view-'.e($id).'" aria-selected="'.($i===0?'true':'false').'"'.($i===0?'':' tabindex="-1"').'>'.e($label).'</button>';
    echo '</div><div class="w-views">';
    foreach($views as $i=>[$id,$label,$file,$h,$alt,$text,$url]){
        echo '<div class="w-view" id="w-view-'.e($id).'" role="tabpanel" aria-labelledby="w-tab-'.e($id).'"><div class="w-view-shot">'.w_shot($file,$alt,1440,$h,$url).'</div><p class="w-view-cap"><b>'.e($label).'</b> '.e($text).'</p></div>';
    }
    echo '</div></div>';
}

/* ---------- Startseite ---------- */

/** Häufige Fragen der Startseite (auch als FAQPage in den strukturierten Daten). 10.26: Reihenfolge und Antworten nach Jens’ Briefing. */
function w_home_faqs(): array {
    return [
        ['Was kostet der Shop?','Nichts. Einrichtung und Betrieb sind für dein Unternehmen kostenfrei. Du bezahlst nur die Kleidung, die bestellt wird.'],
        ['Gibt es Mindestmengen?','Nein, bei Standardware nicht. Auch ein einzelnes Teil kann nachbestellt werden. Bei Sonderanfertigungen oder individuellen Artikeln stimmen wir den Ablauf vorher mit dir ab.'],
        ['Wie lange dauert die Einrichtung?','Sobald du die Designs freigegeben hast, steht dein Shop innerhalb eines Werktags.'],
        ['Wer bezahlt, Mitarbeiter oder Firma?','Das legst du fest. Meist zahlt die Firma und bekommt eine Rechnung. Du kannst pro Person ein Jahresbudget vergeben und auf Wunsch einen Eigenanteil festlegen, der über die Lohnabrechnung läuft.'],
        ['Wie läuft die Freigabe der Designs ab?','Bevor wir den Shop einrichten, bekommst du für jeden Artikel einen Entwurf mit Logo, Position und Verfahren. Erst nach deiner Freigabe kommt der Artikel in den Shop. Auf Wunsch schicken wir dir vorher ein Musterteil.'],
        ['Wie lange sind die Lieferzeiten?','Das hängt von Artikel, Veredelung und Menge ab. Den voraussichtlichen Liefertermin nennen wir dir bei jedem Auftrag, den Stand siehst du jederzeit im Shop.'],
    ];
}

/** Startseite (10.26): hell und sachlich, in der Reihenfolge der Fragen eines Entscheiders. */
function landing_page(): void {
    $faqs=w_home_faqs();
    yw_jsonld_faq($faqs);
    yw_jsonld_service('Firmenshop für Arbeitskleidung','Eigener, kostenfreier Firmenshop für die Arbeitskleidung eines Unternehmens: mit Firmenlogo, festen Artikeln, Mitarbeiterzugängen und zentraler Bestellung. Veredelung (Stick, Druck, Transfer) und Lieferung durch Yanik Textildruck.','Firmenshop für Arbeitskleidung');
    hp_page_start();
    $city=w_city();$o=yw_operator();$mail=s_mail();

    // 1. Was ist WorkwearCenter?
    echo '<section class="w-hh"><div class="w-wrap w-hh-grid"><div class="w-hh-copy">';
    echo '<span class="w-tag"><i aria-hidden="true"></i>Firmenshop für Arbeitskleidung</span>';
    echo '<h1>Ein eigener Online-Shop für die Arbeitskleidung deines Unternehmens.</h1>';
    echo '<p class="w-hh-lead">Mit eurem Logo und euren festen Artikeln. Deine Mitarbeiter bestellen selbst, oder der Einkauf bestellt zentral für alle. Druck, Stick und Versand übernehmen wir in '.e($city).'.</p>';
    echo '<ul class="w-hh-facts"><li>'.w_icon('check').'Kostenfrei für dein Unternehmen</li><li>'.w_icon('check').'Keine Mindestmengen bei Standardware</li></ul>';
    echo '<div class="w-cta"><a class="w-btn w-btn-o" href="#anfrage">Unverbindlich Shop anfragen'.w_icon('arrow').'</a><a class="w-btn w-btn-line" href="#beispiel">Beispiel-Shop ansehen</a></div>';
    echo '<p class="w-hh-meta"><span>'.w_icon('clock').'Shop in 1 Werktag eingerichtet</span><span>'.w_icon('pin').'Produktion in '.e($city).'</span></p>';
    echo '</div><div class="w-hh-stage">';
    echo w_shot('shop-start','Startseite eines Firmenshops im WorkwearCenter mit Jahresbudget, letzter Bestellung und eigener Kleidung (Beispieldaten)',1440,950,'workwearcenter.de/hanseatic',true,'w-hh-desk');
    echo '<figure class="w-hh-phone"><img src="/assets/media/screens/shop-handy-start.webp" alt="Derselbe Firmenshop auf dem Handy" width="390" height="844" decoding="async"></figure>';
    echo '<figure class="w-hh-photo"><img src="/assets/media/work/jacke-brust.webp" alt="Arbeitsjacke mit gedrucktem Firmenlogo auf der Brust" width="800" height="800" loading="lazy" decoding="async"><figcaption>Logo im Druck</figcaption></figure>';
    echo '</div></div></section>';

    // Vorteilsleiste: die zwei häufigsten Einwände zuerst
    echo '<section class="w-facts" aria-label="Das Wichtigste in Kürze"><div class="w-wrap"><ul>';
    foreach([
        ['euro','Kostenfrei für dein Unternehmen','Keine Einrichtungs- oder Monatsgebühr',true],
        ['box','Keine Mindestmengen bei Standardware','Auch ein einzelnes Polo nachbestellen',true],
        ['needle','Druck und Stick im eigenen Haus','Produktion in '.$city,false],
        ['user','Ein fester Ansprechpartner','Von der Anfrage bis zur Rechnung',false],
    ] as [$ic,$t,$x,$key])echo '<li'.($key?' class="key"':'').'><span class="w-icb">'.w_icon($ic).'</span><div><b>'.e($t).'</b><span>'.e($x).'</span></div></li>';
    echo '</ul></div></section>';

    // 2. Für wen?
    echo '<section class="w-sec" id="fuer-wen"><div class="w-wrap">';
    w_sec_head('Für Unternehmen, die ihr Team einheitlich einkleiden','Vom Handwerksbetrieb mit zehn Leuten bis zum Mittelständler mit mehreren Standorten.');
    echo '<div class="w-who-grid">';
    foreach([
        ['handwerk','hammer','Handwerk','Arbeitshosen, Jacken und Polos für Baustelle und Werkstatt.'],
        ['zeitarbeit','swap','Zeitarbeit','Viele neue Mitarbeiter, wechselnde Größen, schnelle Nachbestellung.'],
        ['industrie','factory','Industrie und Produktion','Feste Kollektion je Abteilung, Bestellung über den Einkauf.'],
        ['pflege','heart','Pflege und soziale Dienste','Einheitliche Kleidung für Teams im Schichtdienst.'],
        ['dienstleister','briefcase','Dienstleister','Gebäudeservice, Logistik, Gastronomie, Kundendienst.'],
        ['kommunen','building','Kommunen und Feuerwehren','Bauhof, Verwaltung, Mannschaftsbekleidung mit Wappen.'],
    ] as [$id,$ic,$t,$x])echo '<a class="w-card w-whocard" href="/branchen#'.e($id).'"><span class="w-icb">'.w_icon($ic).'</span><div><h3>'.e($t).'</h3><p>'.e($x).'</p></div></a>';
    echo '</div></div></section>';

    // 3. So funktioniert's
    echo '<section class="w-sec w-alt" id="ablauf"><div class="w-wrap">';
    w_sec_head('So funktioniert’s','Vier Schritte. Bevor der Shop online geht, gibst du jedes Design frei.');
    echo '<ol class="w-steps4">';
    foreach([
        ['Abstimmung','Artikel und Veredelung festlegen','Wir stellen mit dir die Kollektion zusammen und legen fest, wo dein Logo sitzt und ob gedruckt oder gestickt wird.'],
        ['1 Werktag','Shop wird eingerichtet','Du gibst die Entwürfe frei. Danach steht dein Shop mit Logo, Artikeln und Preisen innerhalb eines Werktags.'],
        ['Bestellung','Mitarbeiter oder Einkauf bestellen','Jeder bestellt mit eigenem Zugang, oder eine Person bestellt zentral für das ganze Team.'],
        ['Lieferung','Produktion, Veredelung, Versand','Wir veredeln in '.$city.', packen nach Personen und liefern an deine Adresse.'],
    ] as $i=>[$k,$t,$x])echo '<li class="w-card"><div class="n"><b>'.($i+1).'</b><small>'.e($k).'</small></div><h3>'.e($t).'</h3><p>'.e($x).'</p></li>';
    echo '</ol><p class="w-more"><a class="w-link" href="/so-funktionierts">Ablauf im Detail'.w_icon('arrow').'</a></p></div></section>';

    // 4. Was ist enthalten?
    echo '<section class="w-sec" id="leistungen"><div class="w-wrap w-incl"><div>';
    w_sec_head('Was enthalten ist','Alles, was du für die Arbeitskleidung deines Teams brauchst, bei einem Anbieter.');
    echo '<ul class="w-incl-list">';
    foreach([
        ['shop','Eigener Firmenshop','Geschützt, mit eurem Logo und nur euren Artikeln. Kostenfrei.'],
        ['shirt','Textilien namhafter Marken','Polos, Shirts, Hoodies, Jacken, Hosen und Warnschutz.'],
        ['needle','Veredelung','Stick, DTF-Druck, Textildruck und Flex, in eigener Produktion.'],
        ['team','Mitarbeiter und Größen','Eigene Zugänge, gespeicherte Größen, neue Leute per E-Mail einladen.'],
        ['refresh','Einfache Nachbestellung','Jeder sieht, was er schon hat, und bestellt in seiner Größe nach.'],
        ['doc','Rechnung pro Firma','Eine Rechnung an das Unternehmen, auf Wunsch nach Kostenstelle.'],
        ['shield','Budgets und Freigaben','Optional: Jahresbudget pro Person, Freigabe durch Teamleitung oder Einkauf.'],
        ['globe','In sechs Sprachen','Deutsch, Englisch, Polnisch, Türkisch, Russisch, Rumänisch.'],
    ] as [$ic,$t,$x])echo '<li><span class="w-icb">'.w_icon($ic).'</span><div><h3>'.e($t).'</h3><p>'.e($x).'</p></div></li>';
    echo '</ul><p class="w-more"><a class="w-link" href="/leistungen">Alle Leistungen'.w_icon('arrow').'</a></p></div>';
    echo '<div class="w-incl-media"><img class="big" src="/assets/media/work/jacke-gesamt.webp" alt="Arbeitsjacke mit Firmenlogo im Druck" width="1100" height="825" loading="lazy" decoding="async">';
    echo '<img src="/assets/media/work/polo-wappen.webp" alt="Polo mit Wappen und Namen" width="800" height="800" loading="lazy" decoding="async">';
    echo '<img src="'.e(s_img('about_image','/assets/media/work/stick-detail.webp')).'" alt="Firmenlogo im Detail aus unserer Produktion" width="800" height="800" loading="lazy" decoding="async"></div>';
    echo '</div></section>';

    // 5. Vorteile
    echo '<section class="w-sec w-alt" id="vorteile"><div class="w-wrap">';
    w_sec_head('Was dein Unternehmen davon hat');
    echo '<div class="w-adv-main">';
    echo '<div class="w-card w-adv"><span class="w-icb o">'.w_icon('euro').'</span><div><h3>Der Shop kostet dich nichts</h3><p>Keine Einrichtungsgebühr, keine monatlichen Kosten. Du bezahlst nur die Kleidung, die bestellt wird.</p></div></div>';
    echo '<div class="w-card w-adv"><span class="w-icb o">'.w_icon('box').'</span><div><h3>Keine Mindestmengen bei Standardware</h3><p>Fängt nächste Woche jemand neu an, bestellst du ein einzelnes Poloshirt nach. Mit Logo, in seiner Größe.</p></div></div>';
    echo '</div><div class="w-adv-more">';
    foreach([
        ['tag','Einheitliches Auftreten','Dieselben Artikel, dieselbe Farbe, das Logo an derselben Stelle.'],
        ['clock','Weniger Aufwand','Keine Größenlisten mehr einsammeln, abtippen und nachfragen.'],
        ['link','Alles aus einer Hand','Shop, Ware, Veredelung, Versand und Rechnung von einem Anbieter.'],
        ['pin','Ansprechpartner vor Ort','Wir sitzen in '.$city.' und kennen deinen Auftrag.'],
    ] as [$ic,$t,$x])echo '<article class="w-card"><span class="w-icb">'.w_icon($ic).'</span><h3>'.e($t).'</h3><p>'.e($x).'</p></article>';
    echo '</div></div></section>';

    // 6. Vertrauen
    $trust=yw_trust_content();$case=yw_case_study();
    echo '<section class="w-sec" id="referenzen"><div class="w-wrap">';
    if($trust['logos']){
        w_sec_head('Diese Unternehmen bestellen über WorkwearCenter');
        echo '<ul class="w-logos" aria-label="Logos von Kundenunternehmen">';
        foreach($trust['logos'] as $logo)echo '<li class="w-card"><img src="/'.e($logo['path']).'" alt="'.e($logo['name']!==''?'Logo '.$logo['name']:'Kundenlogo').'" loading="lazy" decoding="async"></li>';
        echo '</ul>';
    }else w_sec_head('Referenzen','Unternehmen aus der Region bestellen ihre Arbeitskleidung bereits über einen eigenen Firmenshop.');
    if($case['show']){
        echo '<article class="w-card w-case2"><div><span class="w-tag">'.e($case['industry']).'</span><h3>'.e($case['title']).'</h3></div><dl><div><dt>Ausgangslage</dt><dd>'.e($case['situation']).'</dd></div><div><dt>Lösung</dt><dd>'.e($case['solution']).'</dd></div><div><dt>Ergebnis</dt><dd>'.e($case['result']).'</dd></div></dl></article>';
    }
    if($trust['has_quote']){
        $q=$trust['quote'];
        echo '<figure class="w-card w-quote2"><blockquote><p>„'.nl2br(e(trim($q['text'],"„“\"' "))).'“</p></blockquote>'.($q['name']!==''||$q['company']!==''?'<figcaption><b>'.e($q['name']).'</b>'.($q['company']!==''?' · '.e($q['company']):'').'</figcaption>':'').'</figure>';
    }
    // Ohne Ansprechpartner-Foto (Admin → Inhalte) bleibt die Karte einspaltig, damit kein Werkstattbild doppelt erscheint.
    $p=$trust['contact'];
    echo '<div class="w-card w-about'.($p['photo']===''?' no-img':'').'">'.($p['photo']!==''?'<img src="/'.e($p['photo']).'" alt="'.e($p['name']?:$o['owner']).'" width="600" height="600" loading="lazy" decoding="async">':'').'<div class="w-about-copy">';
    echo '<h3>Hinter WorkwearCenter steht '.e($o['company']).'</h3><ul class="w-pills"><li>Seit 2023</li><li>Eigene Stick- und Druckproduktion</li><li>'.e($city).'</li></ul>';
    echo '<p>Seit 2023 besticken und bedrucken wir Kleidung für Unternehmen aus '.e($city).', Paderborn und ganz OWL. WorkwearCenter haben wir selbst entwickelt, weil wir Größenlisten und Nachbestellungen lange genug von Hand sortiert haben.</p>';
    echo '<p class="sig">'.e($o['owner']).', Inhaber · '.e($o['company']).', '.e($o['street']).', '.e($o['city']).'</p><a class="w-link" href="/ueber-uns">Mehr über uns'.w_icon('arrow').'</a></div></div>';
    echo '</div></section>';

    // 7. Beispiel-Shop
    echo '<section class="w-sec w-alt" id="beispiel"><div class="w-wrap">';
    w_sec_head('So sieht dein Shop aus','Ansichten aus einem echten Firmenshop mit Beispieldaten. Den Beispiel-Shop kannst du ohne Anmeldung selbst ausprobieren.',true);
    echo '<div class="w-shots">';
    echo '<figure>'.w_shot('shop-start','Startseite eines Firmenshops mit Budget und letzter Bestellung',1440,950).'<figcaption><b>Startseite</b>Budget, letzte Bestellung und die eigene Kleidung.</figcaption></figure>';
    echo '<figure>'.w_shot('shop-artikel','Artikelseite mit Größenwahl und hinterlegter Veredelung',1440,900,'workwearcenter.de/hanseatic/produkte').'<figcaption><b>Artikel</b>Größe wählen, Logo und Veredelung sind hinterlegt.</figcaption></figure>';
    echo '<figure>'.w_phone('shop-handy-start','Firmenshop auf dem Handy').'<figcaption><b>Auf dem Handy</b>Bestellen ohne App.</figcaption></figure>';
    echo '</div><div class="w-center"><a class="w-btn w-btn-line" href="/mustershop">Beispiel-Shop öffnen'.w_icon('arrow').'</a></div></div></section>';

    // 8. FAQ
    w_faq_section($faqs,'Häufige Fragen','Deine Frage ist nicht dabei? Schreib uns an '.$mail.'.');

    // 9. Anfrage
    w_home_inquiry();
    hp_page_end();
}

/** Abschnittskopf ohne Augenbraue (10.26). */
function w_sec_head(string $title,string $text='',bool $center=false): void {
    echo '<div class="w-sh'.($center?' c':'').'"><h2>'.e($title).'</h2>'.($text!==''?'<p>'.e($text).'</p>':'').'</div>';
}

/** Kurzes Anfrageformular der Startseite (Firma, Ansprechpartner, Mitarbeiterzahl, E-Mail). Verarbeitung wie /anfrage. */
function w_home_inquiry(): void {
    $mail=s_mail();
    echo '<section class="w-ask" id="anfrage"><div class="w-wrap"><div class="w-ask-box"><div>';
    echo '<h2>Unverbindlich Shop anfragen</h2><p class="w-ask-lead">Schick uns vier Angaben. Wir melden uns per E-Mail mit einem Vorschlag für Kollektion und Veredelung.</p>';
    echo '<ul><li>'.w_icon('check').'Kostenfrei und unverbindlich</li><li>'.w_icon('check').'Logo und Artikelliste kannst du später nachreichen</li><li>'.w_icon('check').'<span>Oder direkt an <a href="mailto:'.e($mail).'">'.e($mail).'</a></span></li></ul></div>';
    echo '<form method="post" action="/anfrage" class="w-form w-ask-form">'.csrf_field();
    echo '<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>';
    echo '<div class="row"><label>Firma<input name="company" autocomplete="organization" maxlength="190" required placeholder="Name des Unternehmens"></label><label>Ansprechpartner<input name="name" autocomplete="name" maxlength="190" required placeholder="Vor- und Nachname"></label></div>';
    echo '<div class="row"><label><span>Anzahl Mitarbeiter <small>ungefähr</small></span><input type="number" name="employees" min="1" max="100000" inputmode="numeric" placeholder="z. B. 25"></label><label>E-Mail<input type="email" name="email" autocomplete="email" maxlength="190" required placeholder="name@firma.de"></label></div>';
    echo '<label class="w-check"><input type="checkbox" name="privacy" value="1" required><span>Ich habe die <a href="/datenschutz#anfrage" target="_blank" rel="noopener">Datenschutzerklärung</a> gelesen. Meine Angaben werden nur zur Bearbeitung dieser Anfrage verwendet.</span></label>';
    echo '<button class="w-btn w-btn-o" type="submit">Anfrage senden'.w_icon('arrow').'</button>';
    echo '<p class="w-form-note">Mehr Angaben, Logo oder Artikelliste? <a href="/anfrage">Zum ausführlichen Formular</a></p></form>';
    echo '</div></div></section>';
}

/** Funktionen: jeweils das Problem und die Lösung. */
function w_feature_list(): array {
    return [
        ['user','Mitarbeiterzugänge','Statt Sammellisten','Jede Person meldet sich mit eigenem Zugang an und sieht nur die Kleidung, die für sie freigegeben ist. Neue Leute lädst du per E-Mail ein, wer das Unternehmen verlässt, wird mit einem Klick deaktiviert.'],
        ['wallet','Budgets pro Mitarbeiter','Statt Nachrechnen','Lege fest, wie viel jeder Mitarbeiter pro Jahr für Arbeitskleidung verwenden darf. So bleiben Kosten planbar und Bestellungen kontrolliert. Auf Wunsch mit Eigenanteil und Stücklimits je Artikel.'],
        ['shield','Freigaben','Statt Zuruf','Bestellungen gehen erst an die verantwortliche Person, zum Beispiel Teamleitung oder Einkauf. Freigeben geht im Shop oder direkt aus der E-Mail, große Bestellungen auf Wunsch mit zweiter Freigabe.'],
        ['tag','Produktfreigaben','Statt falscher Artikel','Im Shop stehen nur die Artikel, Farben und Größen, die ihr abgestimmt habt. Mit Ausstattungsprofilen bekommt jede Abteilung oder Tätigkeit genau die passende Kleidung.'],
        ['needle','Logo und Veredelung hinterlegt','Statt falscher Logo-Position','Für jeden Artikel ist festgelegt, welches Logo wo sitzt und ob gestickt oder gedruckt wird. Dein Team wählt nur Größe und Menge, wir halten uns an die Vorgabe.'],
        ['truck','Statusverfolgung','Statt Nachfragen','Bestellt, freigegeben, in Produktion, versendet: Jede Person sieht, wo ihre Kleidung gerade ist, mit Sendungsnummer, sobald das Paket unterwegs ist.'],
        ['team','Team-Bestellungen','Statt Zetteln','Nicht jeder sitzt am Rechner. Eine berechtigte Person erfasst Größen und Mengen für mehrere Kolleginnen und Kollegen. Jede Auswahl bleibt der richtigen Person und ihrem Budget zugeordnet.'],
        ['refresh','Nachbestellen und Umtausch','Statt Neuanfang','Im „Spind“ sieht jede Person, was sie schon bekommen hat. Nachbestellen geht mit einem Klick in der bekannten Größe, passt etwas nicht, wird der Umtausch direkt im Shop beantragt.'],
        ['chart','Übersicht und Export','Statt Belegsuche','Verantwortliche sehen Bestellungen nach Person, Abteilung und Kostenstelle und exportieren sie für Buchhaltung oder Lohnabrechnung.'],
    ];
}

function w_features(array $only=[]): void {
    echo '<div class="w-feats">';
    foreach(w_feature_list() as [$ic,$t,$instead,$x]){
        if($only&&!in_array($t,$only,true))continue;
        echo '<article class="w-feat w-reveal"><span class="ic">'.w_icon($ic).'</span><small>'.e($instead).'</small><h3>'.e($t).'</h3><p>'.e($x).'</p></article>';
    }
    echo '</div>';
}

/** Branchenübersicht (Startseite und Leistungen) mit Sprung zur Branchenseite. */
function w_industry_list(): array {
    return [
        ['handwerk','hammer','Handwerk','Arbeitshosen, Arbeitsjacken, Polos, Shirts und Warnschutz für Baustelle und Werkstatt.'],
        ['industrie','factory','Industrie','Einheitliche Kollektionen für mehrere Abteilungen, feste Produktfreigaben und Budgets.'],
        ['zeitarbeit','briefcase','Zeitarbeit','Viele Mitarbeitende, häufige Größenwechsel, schnelle Nachbestellungen, zentral verwaltet.'],
        ['pflege','heart','Pflege','Einheitliche Kleidung für Teams im Schichtdienst, mit Bestellung vom Handy aus.'],
        ['kommunen','building','Kommunen','Verschiedene Teams, festgelegte Bekleidungsstandards und eine zentrale Freigabe.'],
        ['feuerwehr','flame','Feuerwehr','Mannschaftsbekleidung, Polos, Hoodies und Jacken mit Wappen und Namensstick.'],
        ['dienstleister','team','Dienstleister','Einheitliches Erscheinungsbild, eigene Zugänge und kontrollierte Bestellungen.'],
        ['mittelstand','stack','Mittelstand','Mehrere Standorte, Kostenstellen und Ausstattungsprofile in einem Shop.'],
    ];
}

function w_industries_grid(): void {
    echo '<div class="w-inds">';
    foreach(w_industry_list() as [$id,$ic,$t,$x])echo '<a class="w-ind w-reveal" href="/branchen#'.e($id).'"><span class="ic">'.w_icon($ic).'</span><h3>'.e($t).'</h3><p>'.e($x).'</p><span class="go">Mehr erfahren'.w_icon('arrow').'</span></a>';
    echo '</div>';
}

/** Fallbeispiel aus Admin → Inhalte. Ohne Haken „Kunde hat zugestimmt“ erscheint es ohne Firmennamen. */
function yw_case_study(): array {
    $s=fn(string $k,string $d)=>trim((string)current_site_setting($k,''))?:$d;
    $named=current_site_setting('case_named','')==='1';
    $features=array_values(array_filter(array_map('trim',explode("\n",$s('case_features',"Mitarbeiterzugänge\nProduktfreigaben\nBudgets\nZentrale Bestellung")))));
    return [
        'show'=>current_site_setting('case_show','1')!=='0',
        'named'=>$named,
        'title'=>$named?$s('case_company','Pader Personal GmbH'):$s('case_anon','Ein Personaldienstleister aus der Region'),
        'industry'=>$s('case_industry','Zeitarbeit und Personaldienstleistung'),
        'situation'=>$s('case_situation','Viele Mitarbeitende benötigen regelmäßig Arbeitskleidung, oft kurzfristig und in wechselnden Größen.'),
        'solution'=>$s('case_solution','Ein eigener WorkwearCenter-Shop mit der abgestimmten Kollektion und dem Firmenlogo.'),
        'features'=>$features,
        'result'=>$s('case_result','Einfachere Nachbestellung und weniger Verwaltungsaufwand.'),
    ];
}

/* ---------- Leistungen ---------- */
function services_page(): void {
    $city=w_city();
    yw_jsonld_service('Arbeitskleidung mit Logo und Textilveredelung','Auswahl von Arbeits- und Firmenkleidung namhafter Hersteller, Veredelung durch Stick, Druck, DTF-Transfer und Flex in eigener Produktion in '.$city.', Bestellung über einen eigenen Firmenshop.','Textilveredelung und Firmenbekleidung');
    hp_page_start();
    hp_page_hero('Leistungen','Arbeitskleidung, Veredelung und Firmenshop aus einer Hand.','<p>Wir stellen deine Kollektion zusammen, veredeln in unserer eigenen Produktion in '.e($city).' und organisieren die Bestellungen deines Teams über einen eigenen Firmenshop. Keine Trennung zwischen Shopsoftware, Händler, Druckerei und Stickerei.</p>',true);

    echo '<section class="w-sec"><div class="w-wrap"><div class="w-rows">';
    $rows=[
        ['/assets/media/products/workwear-hoodie.webp',true,'Arbeits- & Firmenkleidung','Wir stellen deine Kollektion aus Markenware namhafter Workwear-Hersteller zusammen, passend zu Tätigkeit, Einsatzbereich und Budget.',['T-Shirts, Polos und Hemden','Sweater, Hoodies und Fleece','Softshell- und Arbeitsjacken','Arbeitshosen, Caps und Zubehör','Sicherheits- und Warnbekleidung']],
        ['/assets/media/work/stick-detail.webp',false,'Textilveredelung','Dein Logo wird in unserer eigenen Produktion in '.$city.' aufgebracht. Welches Verfahren passt, entscheiden wir gemeinsam nach Stoff, Motiv und Menge. Auch große und detailreiche Stickmotive setzen wir um.',['Stick, wertig und sehr langlebig','DTF-Transfer, vollfarbig, auch in kleinen Mengen','Textildruck für Rückenmotive und größere Stückzahlen','Flex für Namen und Nummern']],
        ['shot',false,'Dein Firmenshop','Ein geschützter Online-Shop nur für dein Unternehmen. Mitarbeitende bestellen selbst oder eine verantwortliche Person erfasst die Ausstattung für mehrere Beschäftigte. Budgets, Freigaben und Status inklusive.',['Persönliche Zugänge für jede Person','Team-Bestellung durch Verantwortliche','Jahresbudgets und Stücklimits','Freigaben und Bestellstatus']],
    ];
    foreach($rows as $i=>[$img,$product,$title,$text,$items]){
        echo '<article class="w-row'.($i%2?' rev':'').' w-reveal"><div class="w-row-img'.($product?' product':'').($img==='shot'?' shot':'').'">';
        if($img==='shot')echo w_shot('shop-artikel','Produktansicht im Firmenshop mit Größenwahl, hinterlegter Veredelung und Preisaufteilung',1440,900,'workwearcenter.de/hanseatic/produkte');
        else echo '<img src="'.e($img).'" loading="lazy" decoding="async" alt="'.e($product?'Graphitfarbener Hoodie, Beispiel aus der Kollektion':'Gedrucktes Firmenlogo im Detail').'">';
        echo '</div><div class="w-row-copy"><small>0'.($i+1).'</small><h2>'.e($title).'</h2><p>'.e($text).'</p><ul class="w-ticks">';
        foreach($items as $item)echo '<li>'.e($item).'</li>';
        echo '</ul>'.($img==='shot'?'<a class="w-link" href="/so-funktionierts">So funktioniert der Firmenshop'.w_icon('arrow').'</a>':'').'</div></article>';
    }
    echo '</div></div></section>';

    echo '<section class="w-sec w-dark"><div class="w-wrap">';
    s_head('Welches Verfahren passt zu deinem Logo?','Das hängt von Stoff, Motiv und Stückzahl ab. Wir beraten dich ehrlich, auf Wunsch mit Muster vorab.','Veredelung');
    echo '<div class="w-methods">';
    foreach([['needle','Stick','Polos, Jacken, Softshells, Caps','Hochwertige Optik, sehr waschbeständig'],['transfer','DTF-Transfer','Farbige Logos mit feinen Details','Alle Farben und Verläufe, auch kleine Mengen'],['print','Textildruck','Rückendrucke und größere Serien','Große Flächen, kräftige Farben'],['flex','Flex','Namen, Nummern, Schriftzüge','Einzeln personalisierbar, sehr präzise']] as [$ic,$a,$b,$c])echo '<article class="w-method w-reveal"><span class="ic">'.w_icon($ic).'</span><h3>'.e($a).'</h3><dl><dt>Geeignet für</dt><dd>'.e($b).'</dd><dt>Stärken</dt><dd>'.e($c).'</dd></dl></article>';
    echo '</div></div></section>';

    echo '<section class="w-sec"><div class="w-wrap">';
    s_head('Arbeitskleidung für deine Branche','Welche Kleidung typisch ist und welche Shop-Funktionen in deiner Branche am meisten helfen.','Branchen');
    w_industries_grid();
    echo '</div></section>';

    hp_cta_band('Welche Kleidung braucht dein Team?','Schreib uns kurz, wofür die Kleidung gedacht ist. Wir stellen dir eine passende Auswahl zusammen und zeigen dir, wie sie im Firmenshop aussieht.');
    hp_page_end();
}

/* ---------- So funktioniert’s ---------- */
function how_it_works_page(): void {
    $faqs=[
        ['Müssen wir etwas installieren?','Nein. Der Shop läuft im Browser, am Computer, Tablet und Smartphone. Deine Mitarbeitenden erhalten eine Einladung per E-Mail und legen ihr Passwort selbst fest oder melden sich mit einem Link aus der E-Mail an.'],
        ['Wie kommen neue Mitarbeitende in den Shop?','Du lädst sie einzeln oder per Liste ein. Alternativ registrieren sie sich selbst über einen Firmenlink, auf Wunsch mit PIN oder mit Bestätigung durch eine verantwortliche Person.'],
        ['Was passiert, wenn jemand das Unternehmen verlässt?','Das Konto wird mit einem Klick deaktiviert. Auf Wunsch werden die persönlichen Daten gelöscht, die Bestellungen bleiben ohne Namen für deine Buchhaltung erhalten.'],
        ['Können wir Bestellungen sammeln, statt jede einzeln zu produzieren?','Ja. Auf Wunsch gehen freigegebene Bestellungen gesammelt zu einem festen Termin in die Produktion, zum Beispiel einmal im Monat.'],
        ['Was ist, wenn eine Größe nicht passt?','Nach dem Versand beantragt die Person den Umtausch direkt im Shop. Die verantwortliche Person entscheidet, wir tauschen.'],
    ];
    yw_jsonld_faq($faqs);
    hp_page_start();
    hp_page_hero('So funktioniert’s','So funktioniert dein Firmenshop für Arbeitskleidung.','<p>Du legst Kollektion, Regeln und Zuständigkeiten einmal fest. Danach bestellt dein Team selbst, Verantwortliche geben frei und wir veredeln und liefern.</p>',true);
    $steps=[
        ['Anfrage','Du schickst uns die Eckdaten, gern direkt mit Logo und vorhandener Artikelliste.','Du nennst Einsatzbereich, Wunschartikel und eine Kontaktperson.','Wir melden uns persönlich und zeigen dir auf Wunsch sofort eine Logo-Vorschau.'],
        ['Kollektion abstimmen','Gemeinsam legen wir fest, welche Kleidung dein Team bekommt und wie dein Logo darauf aussieht.','Du sagst uns, was dein Team tragen soll.','Wir schlagen passende Artikel vor, beraten zu Verfahren und Position und fertigen auf Wunsch ein Muster.'],
        ['Freigeben und einrichten','Dein Firmenshop bekommt dein Logo, deine Kollektion und deine Regeln.','Du gibst den Entwurf frei und bestimmst Budget, Lieferadresse und wer Bestellungen freigibt.','Wir richten den Shop vollständig ein und laden dein Team per E-Mail ein.'],
        ['Team bestellt','Jede Person bestellt selbst oder eine verantwortliche Person erfasst mehrere Bestellungen gesammelt.','Verantwortliche geben Bestellungen frei, im Shop oder direkt aus der E-Mail.','Der Shop prüft Budget und Stücklimits und übergibt freigegebene Bestellungen an unsere Auftragsverwaltung.'],
        ['Veredeln und liefern','Wir besorgen die Ware, veredeln jedes Teil und liefern an deine Adresse.','Du erhältst die Kleidung nach Personen sortiert und eine Rechnung wie gewohnt.','Wir prüfen jedes Teil vor dem Versand und zeigen den Status im Shop an.'],
    ];
    echo '<section class="w-sec"><div class="w-wrap"><ol class="w-timeline" data-fill>';
    foreach($steps as $i=>[$title,$text,$you,$we]){
        echo '<li class="w-tl w-reveal" data-fill-item><span class="n">'.($i+1).'</span><div class="w-tl-card"><h2>'.e($title).'</h2><p>'.e($text).'</p><div class="w-who"><div><h3>Dein Teil</h3><p>'.e($you).'</p></div><div><h3>Unser Teil</h3><p>'.e($we).'</p></div></div></div></li>';
    }
    echo '</ol></div></section>';

    echo '<section class="w-sec w-dark w-demo-sec"><div class="w-wrap">';
    s_head('So sieht dein Firmenshop aus','Echte Ansichten aus dem Shop, mit Beispieldaten. Oder gleich selbst im Mustershop ausprobieren.','Die Oberfläche');
    w_showcase();
    echo '<div class="w-demo-cta w-reveal"><div><b>Lieber selbst klicken?</b><span>Im Mustershop wählst du Artikel, Größe und Menge wie dein Team, ohne Anmeldung.</span></div>'.w_cta_buttons(true,'w-cta-tight').'</div>';
    echo '</div></section>';

    echo '<section class="w-sec w-white"><div class="w-wrap">';
    s_head('Jede Bestellung bleibt nachvollziehbar.','Alle Beteiligten sehen genau das, was sie brauchen.','Wer sieht was');
    echo '<div class="w-roles">';
    foreach([['user','Mitarbeitende','sehen ihre Kollektion, ihr verfügbares Budget, ihren Spind und den Stand ihrer Bestellungen.','/mitarbeitershop-arbeitskleidung','Mitarbeitershop'],['team','Verantwortliche','verwalten das Team, geben Bestellungen frei, erfassen Team-Bestellungen und exportieren Auswertungen.','/workwear-management','Workwear Management'],['clipboard','Unsere Auftragsverwaltung','erhält jede freigegebene Bestellung mit Person, Größen, Mengen und Veredelung. Produktion, Lieferschein und Rechnung laufen dort.','/arbeitskleidung-bestellsystem','Bestellsystem']] as [$ic,$t,$x,$href,$link])echo '<article class="w-role w-reveal"><span class="ic">'.w_icon($ic).'</span><h3>'.e($t).'</h3><p>'.e($x).'</p><a class="w-link" href="'.e($href).'">'.e($link).w_icon('arrow').'</a></article>';
    echo '</div></div></section>';

    echo '<section class="w-sec"><div class="w-wrap">';
    s_head('Alles, was dein Unternehmen für Arbeitskleidung braucht','Die Funktionen im Überblick, jeweils mit dem Problem, das sie lösen.','Funktionen');
    w_features();
    echo '</div></section>';

    w_faq_section($faqs,'Noch Fragen zum Ablauf?','Schreib uns, wenn deine Frage hier fehlt: '.s_mail());
    hp_cta_band('Bereit für deinen eigenen Firmenshop?','Wir richten alles für dich ein. Du sagst uns nur, was dein Team tragen soll.');
    hp_page_end();
}

/* ---------- Über uns ---------- */
function about_page(): void {
    $o=yw_operator();$city=w_city();
    hp_page_start();
    hp_page_hero('Über uns','WorkwearCenter ist unsere Eigenentwicklung, entstanden in der Werkstatt.','<p>Hinter WorkwearCenter steht '.e($o['company']).' aus '.e($city).': ein inhabergeführter Textilveredler, der Kleidung für Unternehmen, Vereine und Teams bestickt und bedruckt. Den Firmenshop haben wir selbst entwickelt, weil wir die Probleme unserer Kunden jeden Tag auf dem Tisch hatten.</p>',true);

    echo '<section class="w-sec"><div class="w-wrap w-story"><div class="w-reveal"><span class="w-eyebrow">Unsere Geschichte</span><h2>Warum es WorkwearCenter gibt</h2>';
    echo '<ol class="w-chapters">';
    echo '<li><h3>Das Problem</h3><p>Firmen bestellen Arbeitskleidung oft über Excel-Listen, E-Mails, WhatsApp und Papierlisten. Größen werden eingesammelt, abgetippt und nachgefragt. Nachbestellungen gehen unter, und niemand weiß genau, wie viel vom Budget schon verbraucht ist.</p></li>';
    echo '<li><h3>Die Idee</h3><p>Genau diese Listen landeten bei uns. Statt sie weiter von Hand zu sortieren, wollten wir einen Weg, auf dem jede Person selbst bestellt und die Bestellung sauber bei uns ankommt.</p></li>';
    echo '<li><h3>Die Entwicklung</h3><p>WorkwearCenter wurde direkt aus der Praxis von '.e($o['company']).' entwickelt: für Kunden mit vielen Mitarbeitenden, unterschiedlichen Artikeln, Budgets und Freigaben. Jede Funktion beantwortet eine Frage, die uns Kunden gestellt haben.</p></li>';
    echo '<li><h3>Heute</h3><p>WorkwearCenter verbindet Shop, Mitarbeitende, Budgets, Freigaben, Arbeitskleidung, Textilveredelung und Produktion. Im Hintergrund arbeitet unsere eigene Auftragsverwaltung, die Anfrage, Auftrag, Korrekturabzug, Produktion und Versand zusammenführt.</p></li>';
    echo '</ol>';
    echo '<div class="w-owner"><i aria-hidden="true">'.e(mb_strtoupper(mb_substr($o['owner'],0,1))).'</i><div><b>'.e($o['owner']).'</b><span>Inhaber, '.e($o['company']).'</span></div></div></div>';
    echo '<div class="w-mosaic w-reveal"><figure><img src="/assets/media/work/jacke-brust.webp" width="800" height="800" loading="lazy" decoding="async" alt="Arbeitsjacke mit bedrucktem Firmenlogo auf der Brust"></figure><figure><img src="'.e(s_img('about_image','assets/media/work/polo-wappen.webp')).'" loading="lazy" decoding="async" alt="Poloshirt mit gesticktem Wappen und Namensstick"></figure><figure><img src="'.e(s_img('section3_image','assets/media/work/stick-detail.webp')).'" loading="lazy" decoding="async" alt="Gedrucktes Firmenlogo im Detail"></figure></div></div></section>';

    echo '<section class="w-sec w-white"><div class="w-wrap">';
    s_head('Worauf du dich verlassen kannst','Hinter dem System steht ein echter Textilveredler mit eigener Produktion, keine anonyme Softwarefirma.','Unser Versprechen');
    echo '<div class="w-values">';
    foreach([['Ein Ansprechpartner','Vom ersten Muster bis zur Lieferung sprichst du mit uns, nicht mit einer Hotline.'],['Ehrliche Beratung','Wir beurteilen dein Logo an Stickmaschine und Presse und sagen offen, was auf welchem Stoff gut aussieht.'],['Geprüfte Qualität','Jedes Teil geht vor der Auslieferung durch unsere Hände. Absprachen und Termine halten wir ein.']] as [$t,$x])echo '<article class="w-value w-reveal"><h3>'.e($t).'</h3><p>'.e($x).'</p></article>';
    echo '</div></div></section>';

    echo '<section class="w-sec"><div class="w-wrap w-visit"><div class="w-reveal"><span class="w-eyebrow">Standort</span><h2>Unsere Werkstatt in '.e($city).'.</h2><p>Hier sticken und bedrucken wir, hier sitzt dein Ansprechpartner. Wir betreuen Unternehmen aus Salzkotten, Paderborn und ganz OWL. Dein Logo besprechen wir per E-Mail, Muster schicken wir dir auf Wunsch zu.</p></div><address class="w-address w-reveal"><strong>'.e($o['company']).'</strong><span>WorkwearCenter</span><span>'.e($o['street']).'</span><span>'.e($o['city']).'</span><a href="mailto:'.e(s_mail()).'">'.e(s_mail()).'</a></address></div></section>';
    hp_cta_band();
    hp_page_end();
}

/* ---------- Kontakt ---------- */
function contact_page(): void {
    $o=yw_operator();$mail=s_mail();$city=w_city();
    hp_page_start();
    hp_page_hero('Kontakt','Sprich mit uns über deinen Firmenshop.','<p>Du möchtest einen Shop für dein Team einrichten oder hast eine Frage zu einer bestehenden Bestellung? Schreib uns über das Anfrageformular oder per E-Mail. Wir sitzen in '.e($city).' und betreuen Unternehmen aus Salzkotten, Paderborn und ganz OWL.</p>');
    echo '<section class="w-sec"><div class="w-wrap"><div class="w-contact">';
    echo '<article class="w-ccard main w-reveal"><span class="w-eyebrow on-dark">Neu hier</span><h2>Du möchtest einen Firmenshop?</h2><p>Über das Anfrageformular sendest du uns die wichtigsten Eckdaten, gern mit Logo und Artikelliste. Wir melden uns persönlich per E-Mail.</p><div class="w-cta w-cta-tight"><a class="w-btn w-btn-o" href="/anfrage">Shop anfragen'.w_icon('arrow').'</a><a class="w-btn w-btn-ghost" href="/mustershop">'.'Beispiel-Shop ansehen</a></div></article>';
    echo '<article class="w-ccard w-reveal"><span class="w-eyebrow">Direkt erreichen</span><a class="big" href="mailto:'.e($mail).'">'.e($mail).'</a><p>Kontaktperson: '.e($o['owner']).'. Logo oder Wunschartikel kannst du direkt an die E-Mail hängen.</p></article>';
    echo '<article class="w-ccard w-reveal"><span class="w-eyebrow">Anschrift &amp; Produktion</span><h2>'.e($o['company']).'</h2><address class="w-plain">'.e($o['street']).'<br>'.e($o['city']).'</address><p>Firmensitz und Produktion in '.e($city).'. Wir beraten dich per E-Mail, Muster schicken wir dir auf Wunsch zu.</p></article>';
    echo '<article class="w-ccard w-reveal"><span class="w-eyebrow">Schon Kunde?</span><h2>Zum eigenen Firmenshop</h2><p>Mitarbeitende und Verantwortliche melden sich direkt im eigenen Firmenshop an.</p><a class="w-btn w-btn-dark" href="/zugang">Zum Kundenlogin'.w_icon('arrow').'</a></article>';
    echo '</div></div></section>';
    hp_page_end();
}

function imprint_page(): void {
    hp_page_start();
    hp_page_hero('Impressum','Impressum','',[],true);
    echo '<section class="w-sec w-sec-tight"><div class="w-wrap"><article class="w-legal">'.yw_imprint_html().'</article></div></section>';
    hp_page_end();
}

/** 10.22: Standard ist die aus dem System erzeugte Erklärung (app/modules/legal.php); ein eigener Text ist in Admin → Inhalte wählbar. */
function privacy_page(): void {
    hp_page_start();
    hp_page_hero('Datenschutz','Datenschutzerklärung','',[],true);
    echo '<section class="w-sec w-sec-tight"><div class="w-wrap"><article class="w-legal">'.(yw_privacy_uses_custom_text()?rich_text(current_site_setting('privacy_body','')):yw_privacy_html()).'</article></div></section>';
    hp_page_end();
}

/** 10.23: Auswahllisten des Anfrageformulars (Werte werden so in die Anfrage übernommen). */
function yw_inquiry_options(): array {
    return [
        'industry'=>['Handwerk','Industrie / Produktion','Zeitarbeit / Personaldienstleistung','Pflege / Gesundheit','Kommune / öffentliche Einrichtung','Feuerwehr / Hilfsorganisation','Dienstleistung','Handel / Logistik','Sonstige'],
        'current'=>['Ja, wir haben schon einheitliche Kleidung','Teilweise','Nein, wir starten neu'],
        'features'=>['Mitarbeiter bestellen selbst','Team-Bestellung durch Verantwortliche','Budgets pro Person','Freigaben','Mehrere Abteilungen oder Standorte','Mehrsprachiger Shop','Abrechnung mit Eigenanteil'],
    ];
}

/** Darstellung der Anfrageseite (Verarbeitung in inquiry_page). */
function site_inquiry_render(bool $success,string $error,?string $probeshopToken=null,bool $confirmationSent=true): void {
    hp_page_start();
    hp_page_hero('Anfrage','Firmenshop anfragen','<p>Ein paar Eckdaten reichen. Wir melden uns persönlich mit einem Vorschlag für Kollektion, Veredelung und Shop. Mit deinem Logo siehst du sofort eine unverbindliche Vorschau.</p>',[],true);
    echo '<section class="w-sec w-sec-tight"><div class="w-wrap w-inquiry"><div class="w-formcard">';
    if($success){
        echo '<div class="w-success"><span class="mark">'.hp_check_icon().'</span><h2>Danke für deine Anfrage.</h2><p>Wir haben deine Angaben erhalten und melden uns so schnell wie möglich per E-Mail.</p>';
        if($probeshopToken!==null)echo '<div class="w-probe"><strong>Deine unverbindliche Vorschau ist bereit.</strong><p>Sie zeigt dein Logo auf Musterartikeln und ist 30 Tage über diesen Link erreichbar. Sie enthält noch nicht die abgestimmte Firmenkollektion.</p><a class="w-btn w-btn-o" href="/probeshop/'.e($probeshopToken).'" rel="noreferrer">Vorschau ansehen'.w_icon('arrow').'</a></div>';
        else echo '<a class="w-btn w-btn-dark" href="/mustershop">Beispiel-Shop ansehen</a>';
        echo '</div>';
    }else{
        $opt=yw_inquiry_options();$post=fn(string $k)=>e((string)($_POST[$k]??''));
        $sel=function(string $name,array $values,string $placeholder): string{$cur=(string)($_POST[$name]??'');$h='<select name="'.$name.'"><option value="">'.e($placeholder).'</option>';foreach($values as $v)$h.='<option'.($cur===$v?' selected':'').'>'.e($v).'</option>';return $h.'</select>';};
        if($error)echo '<div class="flash error" role="alert">'.e($error).'</div>';
        echo '<form method="post" enctype="multipart/form-data" class="w-form">'.csrf_field();
        echo '<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>';
        echo '<fieldset><legend>Kontakt</legend><div class="row"><label>Firmenname<input name="company" autocomplete="organization" maxlength="190" value="'.$post('company').'" required></label><label>Ansprechpartner<input name="name" autocomplete="name" maxlength="190" value="'.$post('name').'" required></label></div>';
        echo '<div class="row"><label>E-Mail<input type="email" name="email" autocomplete="email" maxlength="190" value="'.$post('email').'" required></label><label><span>Telefon <small>optional</small></span><input type="tel" name="phone" autocomplete="tel" maxlength="80" value="'.$post('phone').'"></label></div></fieldset>';
        echo '<fieldset><legend>Dein Unternehmen</legend><div class="row"><label><span>Anzahl Mitarbeiter <small>ungefähr</small></span><input type="number" min="1" max="100000" inputmode="numeric" name="employees" value="'.$post('employees').'"></label><label>Branche'.$sel('industry',$opt['industry'],'Bitte wählen').'</label></div>';
        echo '<label>Gibt es schon einheitliche Arbeitskleidung?'.$sel('current_wear',$opt['current'],'Bitte wählen').'</label>';
        echo '<div class="w-checks" role="group" aria-labelledby="w-feat-label"><span id="w-feat-label" class="w-checks-label">Was soll der Shop können? <small>optional</small></span>';
        $chosen=array_map('strval',(array)($_POST['features']??[]));
        foreach($opt['features'] as $f)echo '<label class="w-check-pill"><input type="checkbox" name="features[]" value="'.e($f).'"'.(in_array($f,$chosen,true)?' checked':'').'><span>'.e($f).'</span></label>';
        echo '</div></fieldset>';
        echo '<label><span>Nachricht <small>optional</small></span><textarea name="message" rows="4" maxlength="10000" placeholder="Zum Beispiel: Arbeitsjacken und Polos mit Stick, drei Abteilungen, die Teamleitung soll freigeben.">'.$post('message').'</textarea></label>';
        echo '<div class="w-upload"><label>'.w_icon('upload').'Firmenlogo <small>optional · PNG, JPG, WebP oder SVG, max. 4 MB</small><input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg,image/png,image/jpeg,image/webp,image/svg+xml"></label><p>Mit deinem Logo erstellen wir sofort eine <strong>unverbindliche Vorschau</strong> auf Musterartikeln. Der Link ist 30 Tage gültig.</p>';
        echo '<label>'.w_icon('clipboard').'Bestehende Artikelliste <small>optional · PDF, Excel, CSV, Word oder Bild, max. 4 MB</small><input type="file" name="article_list" accept=".pdf,.xlsx,.xls,.csv,.docx,.doc,.png,.jpg,.jpeg,.webp"></label></div>';
        echo '<label class="w-check"><input type="checkbox" name="privacy" value="1" required><span>Ich habe die <a href="/datenschutz#anfrage" target="_blank" rel="noopener">Datenschutzerklärung</a> gelesen. Meine Angaben und hochgeladene Dateien werden nur zur Bearbeitung der Anfrage verwendet.</span></label>';
        echo '<button class="w-btn w-btn-o" type="submit">Anfrage senden'.w_icon('arrow').'</button><p class="w-form-note">Pflichtfelder: Firmenname, Ansprechpartner, E-Mail. Unverbindlich und kostenlos.</p></form>';
    }
    echo '</div><aside class="w-side"><h2>Was nach deiner Anfrage passiert</h2><ol><li><div><strong>Wir prüfen deine Angaben</strong><span>Wir melden uns so schnell wie möglich persönlich per E-Mail.</span></div></li><li><div><strong>Wir stimmen den Entwurf ab</strong><span>Artikel, Logo, Position und Veredelung legen wir gemeinsam fest.</span></div></li><li><div><strong>Du gibst die Gestaltung frei</strong><span>Erst danach richten wir deinen Firmenshop ein und laden dein Team ein.</span></div></li></ol><p class="mail">Lieber direkt?<br><a href="mailto:'.e(s_mail()).'">'.e(s_mail()).'</a></p><p class="mail">Erst ausprobieren? <a href="/mustershop">Beispiel-Shop ansehen</a></p></aside></div></section>';
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
        elseif(!slug_ok($value))$error='Bitte gib den Kurznamen deines Firmenshops ein, z. B. „musterbau“.';
        else{
            $q=db()->prepare('SELECT slug FROM companies WHERE slug=? AND active=1 LIMIT 1');$q->execute([$value]);
            if($slug=$q->fetchColumn())redirect('/'.$slug.'/login');
            record_login_failure('customer-access');
            $error='Zu diesem Kurznamen haben wir keinen aktiven Firmenshop gefunden. Den Link findest du in deiner Einladungs-E-Mail, oder frag deine verantwortliche Person.';
        }
    }
    hp_page_start();
    echo '<section class="w-access"><div class="w-wrap"><div class="w-access-card"><span class="w-eyebrow">Kundenlogin</span><h1>Zu deinem Firmenshop</h1><p>Jedes Unternehmen hat seinen eigenen, geschützten Shop. Gib den Kurznamen deines Shops ein, er steht in deiner Einladungs-E-Mail.</p>';
    echo '<form method="post" class="w-form">'.csrf_field();
    if($error)echo '<div class="flash error" role="alert">'.e($error).'</div>';
    echo '<label>Kurzname oder Shop-Adresse<input name="shop" value="'.e($value).'" placeholder="z. B. musterbau" autocomplete="organization" required autofocus></label><button class="w-btn w-btn-o" type="submit">Weiter zur Anmeldung'.w_icon('arrow').'</button></form><p class="w-access-foot">Noch kein Firmenshop? <a href="/anfrage">Shop anfragen</a> oder <a href="/mustershop">Beispiel-Shop ansehen</a></p></div></div></section>';
    hp_page_end();
}

/** 10.23: Nicht gefundene Seite im Website-Design, mit Wegen zurück (statt der schlichten Systemmeldung). */
function website_not_found_page(bool $shop=false): void {
    $GLOBALS['yw_website_404']=true;
    render_head(($shop?'Firmenshop nicht gefunden':'Seite nicht gefunden').' – WorkwearCenter');
    w_header();
    echo '<main id="inhalt"><section class="w-access"><div class="w-wrap"><div class="w-access-card"><span class="w-eyebrow">Fehler 404</span><h1>'.($shop?'Diesen Firmenshop finden wir nicht.':'Diese Seite gibt es nicht.').'</h1><p>'.($shop?'Prüf bitte die Shop-Adresse aus deiner Einladung oder such deinen Shop über den Kundenlogin.':'Vielleicht hat sich die Adresse geändert. Hier geht es weiter:').'</p><div class="w-cta w-cta-tight"><a class="w-btn w-btn-o" href="/">Zur Startseite'.w_icon('arrow').'</a><a class="w-btn w-btn-line" href="/mustershop">Beispiel-Shop ansehen</a></div><p class="w-access-foot">Du suchst deinen Firmenshop? <a href="/zugang">Zum Kundenlogin</a></p></div></div></section></main>';
    w_footer();render_foot();
}
