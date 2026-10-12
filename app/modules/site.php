<?php
declare(strict_types=1);

/*
 * Öffentliche Website ab 10.20.0 („Werkstatt-Premium“): Navy, Orange und die gestrichelte Naht.
 * Die Seiten nutzen nur assets/web.css und assets/web.js (w-Klassen). public_header() und
 * global_footer() im alten Stil bleiben für Mustershop, Probeshop und Verwaltung erhalten.
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
    echo '<div class="s-footer-brand">'.s_public_brand_html(false).'<p>Firmenkleidung und Textilveredelung mit eigener Produktion in Paderborn.</p></div>';
    echo '<div><h2>WorkwearCenter</h2><nav aria-label="WorkwearCenter"><a href="/leistungen">Leistungen</a><a href="/so-funktionierts">Firmenshop & Ablauf</a><a href="/mustershop">Mustershop ansehen</a><a href="/anfrage">Firmenshop anfragen</a></nav></div>';
    echo '<div><h2>Yanik Textildruck</h2><nav aria-label="Unternehmen"><a href="/ueber-uns">Über uns</a><a href="/kontakt">Kontakt</a><a href="/zugang">Kundenportal</a></nav></div>';
    echo '<div><h2>Besuch uns</h2><p>'.e($company).'<br>'.e($street).'<br>'.e($city).'</p><p><a href="mailto:'.e($mail).'">'.e($mail).'</a></p></div>';
    echo '</div><div class="s-footer-bottom"><span>© '.date('Y').' '.e(yw_text('footer_text')).'</span><span><a href="/impressum">Impressum</a><a href="/datenschutz">Datenschutz</a></span></div></div></footer>';
}

/* ---------- Bausteine der neuen Website (10.20) ---------- */

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
        'pause'=>'<rect x="6" y="4" width="4" height="16" fill="currentColor" stroke="none"/><rect x="14" y="4" width="4" height="16" fill="currentColor" stroke="none"/>',
        'swap'=>'<path d="M8 7 4 12l4 5M16 7l4 5-4 5"/>',
        'lock'=>'<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??'').'</svg>';
}

/** Logo für dunkle Flächen (Kopf und Fuß der Website). */
function w_logo_path(): string {
    $logo=yw_site_logo_path(true);
    $defaults=['assets/media/workwearcenter-logo.png','assets/media/workwearcenter-logo-dunkel.png','assets/media/workwearcenter-logo-anthrazit.png','assets/media/workwearcenter-logo-ice.svg','assets/media/workwearcenter-logo-warm.svg'];
    return in_array($logo,$defaults,true)?'assets/media/workwearcenter-logo.png':$logo;
}

function w_header(): void {
    $path=path_only();$mail=s_mail();
    $links=['/leistungen'=>'Leistungen','/so-funktionierts'=>'So funktioniert’s','/mustershop'=>'Mustershop','/ueber-uns'=>'Über uns','/kontakt'=>'Kontakt'];
    $nav='';foreach($links as $href=>$label)$nav.='<li><a href="'.$href.'"'.($path===$href?' aria-current="page"':'').'>'.e($label).'</a></li>';
    echo '<a class="w-skip" href="#inhalt">Zum Inhalt springen</a>';
    echo '<div class="w-topline"><div class="w-wrap"><span><i class="dot"></i>Veredelung im eigenen Haus · Produktion Paderborn</span><span>Persönlich erreichbar: <a href="mailto:'.e($mail).'">'.e($mail).'</a></span></div></div>';
    echo '<header class="w-nav"><div class="w-wrap">';
    echo '<a class="w-brand" href="/" aria-label="WorkwearCenter Startseite"><img src="/'.e(w_logo_path()).'" alt="WorkwearCenter" width="200" height="30"></a>';
    echo '<nav aria-label="Hauptnavigation" style="display:contents"><ul class="w-menu">'.$nav.'</ul></nav>';
    echo '<a class="w-login" href="/zugang"'.($path==='/zugang'?' aria-current="page"':'').'>'.w_icon('user').'Kundenlogin</a>';
    echo '<a class="w-btn w-btn-o w-btn-sm" href="/anfrage">Shop anfragen</a>';
    echo '<details class="w-burger"><summary aria-label="Menü öffnen"><span></span><span></span><span></span></summary><nav aria-label="Mobile Navigation"><a href="/">Startseite</a>';
    foreach($links as $href=>$label)echo '<a href="'.$href.'"'.($path===$href?' aria-current="page"':'').'>'.e($label).'</a>';
    echo '<a href="/zugang">Kundenlogin</a><a class="w-btn w-btn-o" href="/anfrage">Firmenshop anfragen</a></nav></details>';
    echo '</div></header>';
}

function w_footer(): void {
    $company=current_site_setting('imprint_company','Yanik Textildruck');$mail=s_mail();
    echo '<footer class="w-footer"><div class="w-wrap"><div class="w-fgrid">';
    echo '<div><img src="/'.e(w_logo_path()).'" alt="WorkwearCenter" width="190" height="28"><p>Der Firmenshop für Arbeitskleidung mit Logo. Ein Service von '.e($company).', mit Stick und Druck aus eigener Produktion in Paderborn.</p><p><a href="mailto:'.e($mail).'">'.e($mail).'</a></p></div>';
    echo '<div><h2>Firmenshop</h2><nav aria-label="Firmenshop"><a href="/so-funktionierts">So funktioniert’s</a><a href="/mustershop">Mustershop</a><a href="/leistungen">Kleidung &amp; Veredelung</a><a href="/#faq">Häufige Fragen</a></nav></div>';
    echo '<div><h2>Unternehmen</h2><nav aria-label="Unternehmen"><a href="/ueber-uns">Über uns</a><a href="/kontakt">Kontakt</a><a href="/anfrage">Shop anfragen</a></nav></div>';
    echo '<div><h2>Service</h2><nav aria-label="Service"><a href="/zugang">Kundenlogin</a><a href="/impressum">Impressum</a><a href="/datenschutz">Datenschutz</a></nav></div>';
    echo '</div><div class="w-fbottom"><span>© '.date('Y').' '.e(yw_text('footer_text')?:'WorkwearCenter').'</span><span><a href="/impressum">Impressum</a><a href="/datenschutz">Datenschutz</a></span></div></div></footer>';
}

function hp_check_icon(): string { return w_icon('check'); }

function hp_page_start(string $documentTitle): void {
    render_head($documentTitle);
    w_header();
    echo '<main id="inhalt">';
}

function hp_page_end(): void { echo '</main>'; w_footer(); render_foot(); }

/** Kopf einer Unterseite: Navy-Band mit Brotkrumen und Nahtkante. */
function hp_page_hero(string $label,string $title,string $introHtml,array $actions=[],bool $compact=false): void {
    echo '<section class="w-pagehead'.($compact?' compact':'').'"><div class="w-wrap"><nav class="w-crumbs" aria-label="Brotkrumen"><a href="/">Start</a><span aria-hidden="true">/</span><span>'.e($label).'</span></nav><h1>'.e($title).'</h1>';
    if($introHtml!=='')echo '<div class="w-lead">'.$introHtml.'</div>';
    if($actions){echo '<div class="w-cta">';foreach($actions as [$text,$href,$primary])echo '<a class="w-btn '.($primary?'w-btn-o':'w-btn-ghost').'" href="'.e($href).'">'.e($text).($primary?w_icon('arrow'):'').'</a>';echo '</div>';}
    echo '</div><span class="seam" aria-hidden="true"></span></section>';
}

/** Abschnittskopf: Augenbraue, Überschrift und kurzer Text. */
function s_head(string $title,string $text='',string $eyebrow=''): void {
    echo '<div class="w-head w-reveal"><div>'.($eyebrow!==''?'<span class="w-eyebrow">'.e($eyebrow).'</span>':'').'<h2>'.e($title).'</h2></div>'.($text!==''?'<p>'.e($text).'</p>':'').'</div>';
}

/** Abschluss jeder Seite: dunkle Karte mit Anfrage. */
function hp_cta_band(string $title='Zeig uns dein Logo. Wir zeigen dir deinen Shop.',string $text='Lade bei der Anfrage dein Logo hoch und sieh direkt eine unverbindliche Vorschau auf Musterartikeln.'): void {
    $mail=s_mail();
    echo '<section class="w-final"><div class="w-wrap"><div class="box w-reveal"><div style="position:relative;z-index:1"><span class="w-eyebrow on-dark">Nächster Schritt</span><h2>'.e($title).'</h2><p>'.e($text).'</p></div>';
    echo '<div class="actions"><a class="w-btn w-btn-o" href="/anfrage">Firmenshop anfragen'.w_icon('arrow').'</a><a class="w-btn w-btn-ghost" href="/mustershop">'.w_icon('play').'Erst den Mustershop ansehen</a><small>Antwort persönlich per E-Mail · <a class="mail" href="mailto:'.e($mail).'">'.e($mail).'</a></small></div>';
    echo '</div></div></section>';
}

/** Kompatibilität: frühere Seiten riefen diesen Kopf auf. */
function marketing_page_header(string $titleKey,string $introKey,string $fallbackTitle,string $fallbackIntro): void {
    hp_page_start(yw_text($titleKey).' – '.current_site_setting('site_title','WorkwearCenter'));
    hp_page_hero('WorkwearCenter',yw_text($titleKey)?:$fallbackTitle,rich_text(yw_text($introKey)?:$fallbackIntro));
}

/** Häufige Fragen aus Admin → Inhalte (faq1–4) plus feste Ergänzungen. */
function s_faq(array $extra=[]): void {
    echo '<div class="w-faq">';
    $i=0;
    for($n=1;$n<=4;$n++){
        $q=trim(yw_text('faq'.$n.'_question'));if($q==='')continue;
        echo '<details'.($i++===0?' open':'').'><summary>'.e($q).'</summary><div class="a">'.rich_text(yw_text('faq'.$n.'_answer')).'</div></details>';
    }
    foreach($extra as [$q,$a])echo '<details'.($i++===0?' open':'').'><summary>'.e($q).'</summary><div class="a"><p>'.e($a).'</p></div></details>';
    echo '</div>';
}

/** Kundenstimme (nur wenn gepflegt) und Ansprechpartner (Standard: Inhaber aus dem Impressum). */
function w_voice_section(): void {
    $c=yw_trust_content();$mail=s_mail();
    $p=$c['contact'];
    $name=$p['name']!==''?$p['name']:current_site_setting('imprint_owner','Jens Yanik');
    $role=$p['role']!==''?$p['role']:'Inhaber, '.current_site_setting('imprint_company','Yanik Textildruck');
    $text=$p['text']!==''?$p['text']:'Ich berate dich persönlich, vom ersten Muster bis zur Lieferung. Kein Callcenter, keine Warteschleife.';
    echo '<section class="w-sec" style="padding-top:0"><div class="w-wrap"><div class="w-voice'.($c['has_quote']?'':' single').' w-reveal">';
    if($c['has_quote']){
        $q=$c['quote'];
        $initials=implode('',array_map(fn($w)=>mb_strtoupper(mb_substr($w,0,1)),array_slice(preg_split('/\s+/u',trim($q['name']))?:[],0,2)));
        echo '<figure class="w-quote"><blockquote><p>„'.nl2br(e(trim($q['text'],"„“\"' "))).'“</p></blockquote>';
        if($q['name']!==''||$q['company']!=='')echo '<figcaption>'.($initials!==''?'<i aria-hidden="true">'.e($initials).'</i>':'').'<span>'.e($q['name']).($q['company']!==''?'<small>'.e($q['company']).'</small>':'').'</span></figcaption>';
        echo '</figure>';
    }
    echo '<aside class="w-person"><div class="av">'.($p['photo']!==''?'<img src="/'.e($p['photo']).'" alt="'.e($name).'" width="96" height="96" decoding="async">':e(mb_strtoupper(mb_substr($name,0,1)))).'</div>';
    echo '<div class="w-person-copy"><span class="w-eyebrow">Dein Ansprechpartner</span><h3>'.e($name).'</h3><p style="font-weight:700;color:var(--ink)">'.e($role).'</p><p>'.e($text).'</p></div>';
    echo '<div class="row"><a class="w-btn w-btn-dark w-btn-sm" href="/anfrage">Shop anfragen</a><a class="w-btn w-btn-line w-btn-sm" href="mailto:'.e($mail).'">Mail schreiben</a></div></aside>';
    echo '</div></div></section>';
}

/** Kundenleiste „Diese Kunden vertrauen uns“: erscheint nur mit freigegebenen Logos. */
function s_trust_section(): void {
    $c=yw_trust_content();
    if(!$c['logos'])return;
    $loop=count($c['logos'])>=3;
    echo '<section class="w-trust" aria-labelledby="w-trust-title"><div class="w-wrap"><div class="w-trust-head"><h2 id="w-trust-title">'.e($c['title']).'</h2>';
    if($loop)echo '<button class="w-trust-toggle" type="button" data-w-trust-toggle aria-pressed="false">'.w_icon('pause').'<span>Anhalten</span></button>';
    echo '</div><div class="w-marquee '.($loop?'is-moving':'is-static').'" style="--dur:'.max(24,count($c['logos'])*7).'s"><ul class="w-lane" aria-label="Logos von Kundenunternehmen">';
    $copies=$loop?2:1;
    for($copy=0;$copy<$copies;$copy++){
        foreach($c['logos'] as $logo){
            $alt=$logo['name']!==''?$logo['name']:'Kundenlogo';
            echo '<li class="w-lg"'.($copy>0?' aria-hidden="true"':'').'><img src="/'.e($logo['path']).'" alt="'.($copy>0?'':e($alt)).'" loading="lazy" decoding="async"></li>';
        }
    }
    echo '</ul></div></div></section>';
}

/** Mini-Browser mit Beispiel-Firmenshop (nur Darstellung). */
function w_browser_demo(): string {
    $items=[['workwear-softshell','Softshelljacke','Stick Brust links'],['workwear-hoodie','Hoodie','Druck Rücken'],['workwear-tshirt','T-Shirt','Druck Brust'],['workwear-softshell','Weste','Stick Brust']];
    $h='<div class="w-browser" aria-hidden="true"><div class="bar"><i></i><i></i><i></i><span>workwearcenter.de/kantenwerk</span></div><div class="w-mini-head"><b>KANTENWERK</b><span>Budget frei</span></div><div class="w-mini-grid">';
    foreach($items as [$img,$name,$sub])$h.='<div class="w-mini-p"><div class="im"><img src="/assets/media/products/'.$img.'.webp" alt="" loading="lazy" width="96" height="96"></div><b>'.e($name).'</b><small>'.e($sub).'</small></div>';
    return $h.'</div></div>';
}

/* ---------- Startseite ---------- */
function landing_page(): void {
    hp_page_start('WorkwearCenter – Der Firmenshop für Arbeitskleidung mit Logo');

    // Kopfbereich
    echo '<section class="w-hero w-dark"><div class="w-wrap w-hero-grid"><div>';
    echo '<span class="w-badge"><b>NEU</b>Logo hochladen und sofort die Vorschau sehen</span>';
    echo '<h1>Arbeitskleidung, die dein Team <span class="w-stitch-word">stolz<svg viewBox="0 0 200 20" preserveAspectRatio="none" aria-hidden="true"><path d="M2 12 C 50 2, 150 2, 198 12" fill="none" stroke="#ff6a13" stroke-width="4" stroke-linecap="round"/></svg></span> trägt.</h1>';
    echo '<p class="w-lead">Dein eigener Firmenshop: abgestimmte Kollektion mit deinem Logo, feste Budgets und Freigaben per Klick. Wir besticken und bedrucken alles selbst und liefern direkt zu dir.</p>';
    echo '<div class="w-cta"><a class="w-btn w-btn-o" href="/anfrage">Firmenshop anfragen'.w_icon('arrow').'</a><a class="w-btn w-btn-ghost" href="/mustershop">'.w_icon('play').'Mustershop live testen</a></div>';
    echo '<div class="w-proof"><div>'.w_icon('shield').'Geschützter Shop pro Firma</div><div>'.w_icon('factory').'Stick &amp; Druck aus Paderborn</div><div>'.w_icon('clock').'Bestellstatus jederzeit sichtbar</div></div>';
    echo '</div><div class="w-stage" aria-hidden="true">';
    echo '<div class="w-photo"><img src="/assets/media/work/jacke-gesamt.jpg" alt="" width="470" height="560" fetchpriority="high"></div>';
    echo '<i class="w-hotspot" style="right:110px;top:300px"></i>';
    echo '<div class="w-float w-f-approve"><span class="ic">'.w_icon('check').'</span><div><small>Freigabe erteilt</small><strong>Teamleitung Montage</strong><span style="font-size:13px;color:var(--body)">Bestellung von Lukas Becker</span></div></div>';
    echo '<div class="w-float w-f-stitch"><small>Veredelung</small><div class="row"><span>Verfahren</span><b>Stick</b></div><div class="row"><span>Position</span><b>Brust links</b></div><div class="w-threads"><i style="background:#f4f4f2"></i><i style="background:#ff6a13"></i><i style="background:#9aa3ab"></i></div></div>';
    echo '<div class="w-float w-f-budget"><div class="w-ring" style="--p:71"><span>71%</span></div><div><small>Budget frei</small><strong>212 €</strong><div style="font-size:12.5px;color:var(--body)">Beispiel aus dem Shop</div></div></div>';
    echo '<div class="w-float w-f-truck"><small>Deine Bestellung</small><strong>In Produktion</strong><div class="w-track"><i></i><em></em><i></i><em></em><i></i><em class="off"></em><i class="off"></i></div><div class="w-track-l"><span>Bestellt</span><span>Frei</span><span>Stick</span><span>Versand</span></div></div>';
    echo '</div></div><div class="w-scrollcue" aria-hidden="true"><i></i>SCROLLEN</div></section>';

    // Faktenband ohne Zahlen
    echo '<section class="w-facts" aria-label="Das Wichtigste in Kürze"><div class="w-wrap">';
    foreach([
        ['factory','Eigene Veredelung','Stick, Druck und Transfer in unserer Produktion in Paderborn.'],
        ['shield','Ein Shop pro Firma','Geschützt, mit deinem Logo und deinen Farben.'],
        ['globe','Mehrsprachig','Dein Team bestellt auf Deutsch, Englisch, Polnisch, Türkisch, Russisch oder Rumänisch.'],
        ['user','Persönlich erreichbar','Ein fester Ansprechpartner statt Hotline.'],
    ] as [$ic,$t,$x])echo '<div class="w-fact"><span class="ic">'.w_icon($ic).'</span><div><b>'.e($t).'</b><span>'.e($x).'</span></div></div>';
    echo '</div></section>';

    // Alles in einem Shop
    echo '<section class="w-sec" id="firmenshop"><div class="w-wrap">';
    s_head('Dein Firmenauftritt, vom Lager bis zur Baustelle.','Keine Größenlisten per Mail. Jede Person bestellt selbst oder die Teamleitung bestellt für alle, und du behältst Budgets und Freigaben im Blick.','Alles in einem Shop');
    echo '<div class="w-bento">';
    echo '<article class="w-tile w-t-shop w-reveal"><span class="w-eyebrow">Der Firmenshop</span><h3 style="margin-top:14px">Dein Logo, deine Farben, deine Kollektion.</h3><p>Jede Firma bekommt eine eigene Adresse wie workwearcenter.de/kantenwerk, nur für eure Leute.</p>'.w_browser_demo().'</article>';
    echo '<article class="w-tile w-t-lang w-reveal"><h3>Bestellen in deiner Sprache.</h3><p>Dein Team wählt die eigene Sprache, die Mails kommen genauso an.</p><div class="w-langs" aria-label="Sprachen im Firmenshop"><span class="on">DE</span><span>EN</span><span>PL</span><span>TR</span><span>RU</span><span>RO</span></div><div class="w-lang-note"><span class="w-pulse"></span><span lang="pl"><b>PL</b> · Twoje zamówienie zostało zatwierdzone.</span></div></article>';
    echo '<article class="w-tile w-t-logo w-reveal"><h3>Logo-Vorschau sofort.</h3><p>Logo bei der Anfrage hochladen und auf Musterartikeln sehen, noch vor dem ersten Gespräch.</p><div class="w-logo-demo" aria-hidden="true"><img src="/assets/media/products/workwear-softshell.webp" alt="" loading="lazy"><span class="mark"></span></div></article>';
    echo '<article class="w-tile w-t-third w-reveal"><h3>Freigabe per Klick</h3><p>Verantwortliche geben Bestellungen frei, auf Wunsch mit Vier-Augen-Prinzip.</p><div class="w-mini-flow" aria-hidden="true"><div><i>✓</i>Lukas Becker bestellt</div><div><i>✓</i>Teamleitung gibt frei</div><div class="wait"><i>…</i>Zweite Freigabe bei großen Bestellungen</div></div></article>';
    echo '<article class="w-tile w-t-third w-reveal"><h3>Budgets, die mitrechnen</h3><p>Jahresbudget pro Person, Firmenanteil und Eigenanteil sauber getrennt.</p><div class="w-bars" aria-hidden="true"><i style="height:40%"></i><i style="height:65%"></i><i style="height:52%"></i><i style="height:88%"></i><i style="height:70%"></i><i style="height:96%"></i><i style="height:60%"></i></div></article>';
    echo '<article class="w-tile w-t-third w-reveal"><h3>Status wie beim Paketdienst</h3><p>Bestellt, freigegeben, in Produktion, verschickt. Jeder sieht, wo seine Kleidung ist.</p><div aria-hidden="true" style="margin-top:34px"><div class="w-track"><i></i><em></em><i></i><em></em><i></i><em class="off"></em><i class="off"></i></div><div class="w-track-l"><span>Bestellt</span><span>Frei</span><span>Stick</span><span>Versand</span></div></div></article>';
    echo '</div></div></section>';

    // Zwei Bestellwege
    echo '<section class="w-sec w-white" id="bestellwege"><div class="w-wrap">';
    s_head('Dein Team bestellt so, wie es zum Arbeitsalltag passt.','Bei der Einrichtung legst du fest, wer bestellt und wer freigibt. Beide Wege lassen sich kombinieren.','Zwei Bestellwege');
    echo '<div class="w-ways">';
    echo '<article class="w-way w-reveal"><span class="n">'.w_icon('user').'</span><div><small>MITARBEITENDE BESTELLEN SELBST</small><h3>Jede Person wählt die eigene Ausstattung.</h3><p>Beschäftigte melden sich im Firmenshop an und wählen die für sie freigegebenen Artikel, Größen und Mengen. Das Budget sehen sie dabei immer.</p><a class="w-link" href="/mustershop">Mustershop ansehen'.w_icon('arrow').'</a></div></article>';
    echo '<article class="w-way w-reveal"><span class="n">'.w_icon('team').'</span><div><small>BESTELLUNG FÜR DAS TEAM</small><h3>Eine Person erfasst die Kleidung für alle.</h3><p>Eine berechtigte Person trägt Artikel, Größen und Mengen für die Beschäftigten ein. Jede Auswahl bleibt der richtigen Person zugeordnet.</p><a class="w-link" href="/so-funktionierts">Ablauf ansehen'.w_icon('arrow').'</a></div></article>';
    echo '</div></div></section>';

    // Werkstatt mit Vorher-nachher-Regler
    echo '<section class="w-sec w-dark" id="veredelung"><div class="w-wrap">';
    s_head('Wir veredeln selbst. Darum passt es.','Zieh den Regler: links die Logo-Vorschau aus dem Shop, rechts das gestickte Original. Die Platzierung stimmen wir vorher mit dir ab.','Aus unserer Werkstatt');
    echo '<div class="w-craft"><div class="w-compare w-reveal" data-compare><img src="/assets/media/work/jacke-gesamt.jpg" alt="Gesticktes Firmenlogo auf einer Arbeitsjacke" loading="lazy"><div class="before" aria-hidden="true"><div class="pv"><img src="/assets/media/products/workwear-softshell.webp" alt="" loading="lazy"><img class="pv-logo" src="/assets/media/work/stollhans-logo.png" alt="" loading="lazy"></div></div><span class="lbl" style="left:20px">VORSCHAU IM SHOP</span><span class="lbl" style="right:20px">ECHT GESTICKT</span><div class="handle"><b>'.w_icon('swap').'</b></div><input type="range" min="0" max="100" value="48" aria-label="Vergleich zwischen Shop-Vorschau und gesticktem Original verschieben"></div>';
    echo '<div class="w-techs">';
    foreach([['needle','Stick','Hochwertig und waschfest, ideal für Jacken, Polos und Caps.',true],['print','Druck','Große Motive auf Rücken und T-Shirts, auch mehrfarbig.',false],['transfer','Transfer & Flex','Für feine Details, Namen, Nummern und kleine Auflagen.',false]] as [$ic,$t,$x,$on])echo '<div class="w-tech'.($on?' on':'').' w-reveal"><span class="ic">'.w_icon($ic).'</span><div><h3>'.e($t).'</h3><p>'.e($x).'</p></div></div>';
    echo '<div class="w-place w-reveal">'.w_icon('pin').'<div><b>Produktion Paderborn</b><p>Muster anfassen? Komm nach Absprache vorbei oder wir schicken sie dir.</p></div></div>';
    echo '</div></div></div></section>';

    // Ablauf
    echo '<section class="w-sec" id="ablauf"><div class="w-wrap">';
    s_head('In fünf Schritten zum eigenen Shop.','Erst nach deiner Freigabe kommen die Artikel in den Shop. Danach produzieren und liefern wir.','So funktioniert’s');
    echo '<ol class="w-steps" data-fill>';
    foreach([['chat','Anfrage','Logo hochladen, Artikel nennen, Kontakt angeben.'],['plus','Abstimmung','Kollektion, Logo-Platzierung und Preise gemeinsam festlegen.'],['check','Freigabe','Du gibst den Entwurf frei, wir richten den Shop ein.'],['cart','Team bestellt','Selbst oder als Team-Bestellung, mit Freigabe.'],['truck','Lieferung','Wir veredeln, packen pro Person und liefern.']] as $i=>[$ic,$t,$x])echo '<li class="w-step" data-fill-item><div class="n">'.w_icon($ic).'</div><small>SCHRITT '.($i+1).'</small><h3>'.e($t).'</h3><p>'.e($x).'</p></li>';
    echo '</ol></div></section>';

    w_voice_section();

    // Häufige Fragen
    echo '<section class="w-sec w-white" id="faq" aria-labelledby="w-faq-title"><div class="w-wrap w-faq-grid"><div class="w-faq-intro"><span class="w-eyebrow">Häufige Fragen</span><h2 id="w-faq-title">Was du vor der Anfrage wissen solltest.</h2><p>Preise, Mengen und Liefertermine nennen wir dir verbindlich im Angebot.</p><a class="w-btn w-btn-dark w-btn-sm" href="/anfrage">Shop anfragen</a></div><div class="w-faq">';
    $faqs=[
        ['Für welche Unternehmen ist das WorkwearCenter gedacht?','Für Unternehmen aus Handwerk, Industrie, Dienstleistung, Pflege und Zeitarbeit, die Arbeitskleidung über einen eigenen Firmenshop organisieren wollen. Der Shop zeigt die Kollektion, die du vorher mit uns abstimmst.'],
        ['Was bekomme ich konkret?','Einen geschützten Shop mit eurem Namen, Logo und Firmenfarben sowie der vereinbarten Arbeitskleidung. Wir stimmen die Gestaltung mit dir ab. Die Artikel kommen erst nach deiner Freigabe in den Shop.'],
        ['Was brauchst du von mir für den Start?','Dein Firmenlogo, die gewünschten Artikel oder Einsatzbereiche und eine Kontaktperson. Hilfreich sind außerdem die ungefähre Teamgröße und die Person, die Bestellungen freigibt.'],
        ['Kann eine Person für die ganze Belegschaft bestellen?','Ja. Eine berechtigte Person kann Artikel, Größen und Mengen für Beschäftigte erfassen. Ein Sammelvorgang umfasst bis zu 20 Personen; größere Belegschaften werden in mehreren Durchgängen erfasst. Jede Auswahl bleibt der jeweiligen Person zugeordnet.'],
        ['Wer gibt die Bestellungen frei?','Die Personen, die ihr dafür festlegt, zum Beispiel Teamleitung, Einkauf oder Geschäftsführung. Den Bestellweg legen wir bei der Einrichtung gemeinsam fest.'],
        ['Was kosten der Firmenshop und die Kleidung?','Die Preise hängen von Artikel, Veredelung und Menge ab. Nach deiner Anfrage bekommst du ein konkretes Angebot mit allen Preisen.'],
        ['Wie lange dauern Einrichtung und Lieferung?','Das hängt vom Umfang der Kollektion, der Abstimmung der Entwürfe, der Verfügbarkeit und der Veredelung ab. Den Zeitplan und den voraussichtlichen Liefertermin nennen wir dir im Angebot und bei jedem Auftrag.'],
        ['Gibt es eine Mindestbestellmenge?','Das stimmen wir je Artikel und Veredelung im Angebot mit dir ab.'],
        ['Wie laufen Abrechnung und Lieferscheine?','Nach der Freigabe gehen die Bestellungen in unsere Auftragsverwaltung. Dort laufen Abrechnung, Lieferscheine und weitere Auftragsunterlagen. Wir produzieren und liefern die bestellte Kleidung.'],
        ['Brauchen Beschäftigte eine App?','Nein. Der Firmenshop läuft im Browser auf Computer, Tablet und Smartphone.'],
        ['Was sehe ich im Mustershop?','Der Mustershop zeigt beispielhaft, wie ein Firmenshop aufgebaut ist. Wenn du bei der Anfrage dein Logo hochlädst, entsteht zusätzlich eine unverbindliche Vorschau mit deinem Firmenlogo. Das ist noch nicht der freigegebene Shop mit deiner abgestimmten Kollektion.'],
    ];
    foreach($faqs as $i=>[$q,$a])echo '<details'.($i===0?' open':'').'><summary>'.e($q).'</summary><div class="a"><p>'.e($a).'</p></div></details>';
    echo '</div></div></section>';

    hp_cta_band();
    s_trust_section();
    hp_page_end();
}

/* ---------- Leistungen ---------- */
function services_page(): void {
    hp_page_start('Leistungen: Firmenkleidung mit Logo, Stick & Druck – WorkwearCenter');
    hp_page_hero('Leistungen','Firmenkleidung mit deinem Logo, aus einer Hand.','<p>Wir beraten dich bei der Auswahl, veredeln in unserer Produktion in Paderborn und organisieren die Bestellungen deines Teams über einen eigenen Firmenshop.</p>',[['Firmenshop anfragen','/anfrage',true],['Mustershop ansehen','/mustershop',false]]);

    echo '<section class="w-sec"><div class="w-wrap"><div class="w-rows">';
    $rows=[
        ['/assets/media/products/workwear-hoodie.webp',true,'Arbeits- & Firmenkleidung','Wir stellen deine Kollektion aus Markenware namhafter Workwear-Hersteller zusammen, passend zu Tätigkeit, Einsatzbereich und Budget.',['T-Shirts, Polos und Hemden','Sweater, Hoodies und Fleece','Softshell- und Arbeitsjacken','Hosen, Caps und Zubehör']],
        [s_img('services_image','assets/media/work/jacke-gesamt.jpg'),false,'Textilveredelung','Dein Logo wird in unserer eigenen Produktion aufgebracht. Welches Verfahren passt, entscheiden wir gemeinsam nach Stoff, Motiv und Menge. Auch große und detailreiche Stickmotive setzen wir um.',['Stick, wertig und sehr langlebig','DTF-Transfer, vollfarbig, auch in kleinen Mengen','Textildruck für Rückenmotive und größere Stückzahlen','Flex für Namen und Nummern']],
        ['browser',false,'Dein Firmenshop','Ein geschützter Online-Shop nur für dein Unternehmen. Mitarbeitende bestellen selbst oder eine verantwortliche Person erfasst die Ausstattung für mehrere Beschäftigte.',['Persönliche Zugänge für jede Person','Team-Bestellung durch Verantwortliche','Jahresbudgets und Stücklimits','Freigaben und Auftragsstatus']],
    ];
    foreach($rows as $i=>[$img,$product,$title,$text,$items]){
        echo '<article class="w-row'.($i%2?' rev':'').' w-reveal"><div class="w-row-img'.($product?' product':'').'"'.($img==='browser'?' style="background:linear-gradient(160deg,#fff,var(--orange-soft))"':'').'>';
        if($img==='browser')echo w_browser_demo();else echo '<img src="'.e($img).'" loading="lazy" alt="'.e($title).'">';
        echo '</div><div class="w-row-copy"><small>0'.($i+1).'</small><h2>'.e($title).'</h2><p>'.e($text).'</p><ul class="w-ticks">';
        foreach($items as $item)echo '<li>'.e($item).'</li>';
        echo '</ul></div></article>';
    }
    echo '</div></div></section>';

    echo '<section class="w-sec w-dark"><div class="w-wrap">';
    s_head('Welches Verfahren passt zu deinem Logo?','Das hängt von Stoff, Motiv und Stückzahl ab. Wir beraten dich ehrlich, auf Wunsch mit Muster vorab.','Veredelung');
    echo '<div class="w-methods">';
    foreach([['needle','Stick','Polos, Jacken, Softshells, Caps','Hochwertige Optik, sehr waschbeständig'],['transfer','DTF-Transfer','Farbige Logos mit feinen Details','Alle Farben und Verläufe, auch kleine Mengen'],['print','Textildruck','Rückendrucke und größere Serien','Große Flächen, kräftige Farben'],['flex','Flex','Namen, Nummern, Schriftzüge','Einzeln personalisierbar, sehr präzise']] as [$ic,$a,$b,$c])echo '<article class="w-method w-reveal"><span class="ic">'.w_icon($ic).'</span><h3>'.e($a).'</h3><dl><dt>Geeignet für</dt><dd>'.e($b).'</dd><dt>Stärken</dt><dd>'.e($c).'</dd></dl></article>';
    echo '</div></div></section>';

    hp_cta_band('Welche Kleidung braucht dein Team?','Schreib uns kurz, wofür die Kleidung gedacht ist. Wir stellen dir eine passende Auswahl zusammen.');
    hp_page_end();
}

/* ---------- So funktioniert’s ---------- */
function how_it_works_page(): void {
    hp_page_start('So funktioniert der Firmenshop für Arbeitskleidung – WorkwearCenter');
    hp_page_hero('So funktioniert’s','In fünf Schritten zu deinem Firmenshop.','<p>Du legst Kollektion, Regeln und Zuständigkeiten fest. Mitarbeitende bestellen selbst oder eine verantwortliche Person bündelt die Ausstattung für das Team.</p>',[['Firmenshop anfragen','/anfrage',true],['Mustershop testen','/mustershop',false]]);
    $steps=[
        ['Anfrage','Du schickst uns die Eckdaten, gern direkt mit Logo.','Du nennst Einsatzbereich, Wunschartikel und eine Kontaktperson.','Wir melden uns persönlich und erstellen auf Wunsch eine Logo-Vorschau.'],
        ['Kollektion abstimmen','Gemeinsam legen wir fest, welche Kleidung dein Team bekommt und wie dein Logo darauf aussieht.','Du sagst uns, was dein Team tragen soll.','Wir schlagen passende Artikel vor, beraten zu Verfahren und Position und fertigen auf Wunsch ein Muster.'],
        ['Freigeben und einrichten','Dein Firmenshop bekommt dein Logo, deine Kollektion und deine Regeln.','Du gibst den Entwurf frei und bestimmst Budget, Lieferadresse und wer Bestellungen freigibt.','Wir richten den Shop vollständig ein und laden dein Team per E-Mail ein.'],
        ['Team bestellt','Jede Person bestellt selbst oder eine verantwortliche Person erfasst mehrere Bestellungen gesammelt.','Verantwortliche geben Bestellungen frei. Budgets, Stücklimits und Freigaben bleiben je Person zugeordnet.','Das Portal prüft jede Bestellung einzeln und übergibt freigegebene Bestellungen an unsere Auftragsverwaltung.'],
        ['Veredeln und liefern','Wir besorgen die Ware, veredeln jedes Teil und liefern an deine Adresse.','Du erhältst die Kleidung nach Personen sortiert und eine Rechnung wie gewohnt.','Wir prüfen jedes Teil vor dem Versand und zeigen den Status im Shop an.'],
    ];
    echo '<section class="w-sec"><div class="w-wrap"><ol class="w-timeline" data-fill>';
    foreach($steps as $i=>[$title,$text,$you,$we]){
        echo '<li class="w-tl w-reveal" data-fill-item><span class="n">'.($i+1).'</span><div class="w-tl-card"><h2>'.e($title).'</h2><p>'.e($text).'</p><div class="w-who"><div><h3>Dein Teil</h3><p>'.e($you).'</p></div><div><h3>Unser Teil</h3><p>'.e($we).'</p></div></div></div></li>';
    }
    echo '</ol></div></section>';

    echo '<section class="w-sec w-white"><div class="w-wrap">';
    s_head('Jede Bestellung bleibt nachvollziehbar.','Alle Beteiligten sehen genau das, was sie brauchen.','Wer sieht was');
    echo '<div class="w-roles">';
    foreach([['user','Mitarbeitende','sehen ihre Kollektion, ihr verfügbares Budget und den Stand ihrer Bestellungen.'],['team','Verantwortliche','verwalten das Team, geben Bestellungen frei und können Kleidung für mehrere Personen gesammelt erfassen.'],['clipboard','Unsere Auftragsverwaltung','erhält jede Bestellung mit Person, Größen, Mengen und Veredelung. Abrechnung und Lieferscheine laufen über den gewohnten Ablauf.']] as [$ic,$t,$x])echo '<article class="w-role w-reveal"><span class="ic">'.w_icon($ic).'</span><h3>'.e($t).'</h3><p>'.e($x).'</p></article>';
    echo '</div></div></section>';

    echo '<section class="w-sec"><div class="w-wrap w-faq-grid"><div class="w-faq-intro"><span class="w-eyebrow">Häufige Fragen</span><h2>Noch Fragen?</h2><p>Schreib uns: <a class="w-link" href="mailto:'.e(s_mail()).'">'.e(s_mail()).'</a></p></div>';
    s_faq([['Müssen wir etwas installieren?','Nein. Der Shop läuft im Browser, am Computer, Tablet und Smartphone. Deine Mitarbeitenden erhalten eine Einladung per E-Mail und legen ihr Passwort selbst fest.'],['Was passiert, wenn jemand das Unternehmen verlässt?','Das Konto wird mit einem Klick deaktiviert. Auf Wunsch werden die persönlichen Daten gelöscht, die Bestellungen bleiben für deine Buchhaltung erhalten.']]);
    echo '</div></section>';
    hp_cta_band('Bereit für deinen eigenen Firmenshop?','Wir richten alles für dich ein. Du sagst uns nur, was dein Team tragen soll.');
    hp_page_end();
}

/* ---------- Über uns ---------- */
function about_page(): void {
    hp_page_start('Über uns: Yanik Textildruck – WorkwearCenter');
    hp_page_hero('Über uns','Textilveredelung mit eigener Produktion, persönlich und zuverlässig.','<p>Hinter dem WorkwearCenter steht Yanik Textildruck: ein inhabergeführter Betrieb, der Kleidung für Unternehmen, Vereine und Teams bestickt und bedruckt, in der eigenen Produktion in Paderborn.</p>');
    $owner=current_site_setting('imprint_owner','Jens Yanik');$company=current_site_setting('imprint_company','Yanik Textildruck');
    $street=current_site_setting('imprint_street','Geseker Straße 19');$city=current_site_setting('imprint_city','33154 Salzkotten');

    echo '<section class="w-sec"><div class="w-wrap w-story"><div class="w-reveal"><span class="w-eyebrow">Unsere Geschichte</span><h2>Warum es das WorkwearCenter gibt</h2><p>Bei Firmenkunden sieht die Bestellung von Arbeitskleidung oft gleich aus: Größen werden auf Zetteln gesammelt, Nachbestellungen gehen unter, und niemand weiß genau, wie viel vom Budget schon verbraucht ist.</p><p>Deshalb haben wir das WorkwearCenter entwickelt: Mitarbeitende bestellen selbst, Verantwortliche erfassen Teambestellungen gesammelt. So bleiben Größen, Budgets und Freigaben nachvollziehbar. Die Veredelung bleibt Handarbeit: Wir sticken, drucken und prüfen jedes Teil selbst.</p>';
    echo '<div class="w-owner"><i aria-hidden="true">'.e(mb_strtoupper(mb_substr($owner,0,1))).'</i><div><b>'.e($owner).'</b><span>Inhaber, '.e($company).'</span></div></div></div>';
    echo '<div class="w-mosaic w-reveal"><figure><img src="/assets/media/work/jacke-gesamt.jpg" loading="lazy" alt="Arbeitsjacke mit gesticktem Firmenlogo"></figure><figure><img src="'.e(s_img('about_image','assets/media/work/polo-wappen.jpg')).'" loading="lazy" alt="Poloshirt mit Wappen und Namensstick"></figure><figure><img src="'.e(s_img('section3_image','assets/media/work/stick-detail.jpg')).'" loading="lazy" alt="Gesticktes Logo im Detail"></figure></div></div></section>';

    echo '<section class="w-sec w-white"><div class="w-wrap">';
    s_head('Worauf du dich verlassen kannst','','Unser Versprechen');
    echo '<div class="w-values">';
    foreach([['Ein Ansprechpartner','Vom ersten Muster bis zur Lieferung sprichst du mit uns, nicht mit einer Hotline.'],['Ehrliche Beratung','Wir beurteilen dein Logo an Stickmaschine und Presse und sagen offen, was auf welchem Stoff gut aussieht.'],['Geprüfte Qualität','Jedes Teil geht vor der Auslieferung durch unsere Hände. Absprachen und Termine halten wir ein.']] as [$t,$x])echo '<article class="w-value w-reveal"><h3>'.e($t).'</h3><p>'.e($x).'</p></article>';
    echo '</div></div></section>';

    echo '<section class="w-sec"><div class="w-wrap w-visit"><div class="w-reveal"><span class="w-eyebrow">Besuch uns</span><h2>Stoffe anfassen, Muster ansehen.</h2><p>Du möchtest dein Logo direkt mit uns besprechen? Komm gern vorbei, nach kurzer Terminabsprache per E-Mail. Produziert wird in Paderborn.</p></div><address class="w-address w-reveal"><strong>'.e($company).'</strong><span>'.e($street).'</span><span>'.e($city).'</span><a href="mailto:'.e(s_mail()).'">'.e(s_mail()).'</a></address></div></section>';
    hp_cta_band();
    hp_page_end();
}

/* ---------- Kontakt ---------- */
function contact_page(): void {
    hp_page_start('Kontakt – WorkwearCenter by Yanik Textildruck');
    hp_page_hero('Kontakt','Schreib uns zum Firmenshop.','<p>Du möchtest einen Shop für dein Team einrichten oder hast eine Frage zu einer bestehenden Bestellung? Schreib uns per Anfrageformular oder E-Mail.</p>');
    $mail=s_mail();$owner=current_site_setting('imprint_owner','Jens Yanik');
    $street=current_site_setting('imprint_street','Geseker Straße 19');$city=current_site_setting('imprint_city','33154 Salzkotten');$company=current_site_setting('imprint_company','Yanik Textildruck');
    echo '<section class="w-sec"><div class="w-wrap"><div class="w-contact">';
    echo '<article class="w-ccard main w-reveal"><span class="w-eyebrow on-dark">Neu hier</span><h2>Du möchtest einen Firmenshop?</h2><p>Über das Anfrageformular sendest du uns die wichtigsten Eckdaten, gern mit Logo. Wir sehen uns alles an und melden uns per E-Mail.</p><a class="w-btn w-btn-o" href="/anfrage">Shop anfragen'.w_icon('arrow').'</a></article>';
    echo '<article class="w-ccard w-reveal"><span class="w-eyebrow">E-Mail</span><a class="big" href="mailto:'.e($mail).'">'.e($mail).'</a><p>Du kannst dein Logo oder deine Wunschartikel direkt anhängen.</p></article>';
    echo '<article class="w-ccard w-reveal"><span class="w-eyebrow">Anschrift</span><h2>'.e($company).'</h2><p>'.e($street).'<br>'.e($city).'</p><p>Kontaktperson: '.e($owner).'<br>Produktion in Paderborn, Besuche nach Terminabsprache.</p></article>';
    echo '<article class="w-ccard w-reveal"><span class="w-eyebrow">Schon Kunde?</span><h2>Zum eigenen Firmenshop</h2><p>Mitarbeitende und Verantwortliche melden sich direkt im eigenen Firmenshop an.</p><a class="w-btn w-btn-dark" href="/zugang">Zum Kundenlogin'.w_icon('arrow').'</a></article>';
    echo '</div></div></section>';
    hp_page_end();
}

function imprint_page(): void {
    hp_page_start('Impressum – '.current_site_setting('site_title','WorkwearCenter'));
    hp_page_hero('Rechtliches','Impressum','',[],true);
    echo '<section class="w-sec w-sec-tight"><div class="w-wrap"><article class="w-legal">';
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
    echo '<section class="w-sec w-sec-tight"><div class="w-wrap"><article class="w-legal">'.rich_text(current_site_setting('privacy_body','Bitte hinterlege hier die rechtlich geprüfte Datenschutzerklärung für WorkwearCenter.de.')).'</article></div></section>';
    hp_page_end();
}

/** Darstellung der Anfrageseite (Verarbeitung in inquiry_page). */
function site_inquiry_render(bool $success,string $error,?string $probeshopToken=null,bool $confirmationSent=true): void {
    hp_page_start('Firmenshop anfragen – '.current_site_setting('site_title','WorkwearCenter'));
    hp_page_hero('Anfrage','Firmenshop anfragen','<p>Nenn uns deine gewünschten Artikel, die ungefähre Teamgröße und eine Kontaktperson. Mit deinem Logo siehst du direkt eine unverbindliche Vorschau.</p>',[],true);
    echo '<section class="w-sec w-sec-tight"><div class="w-wrap w-inquiry"><div class="w-formcard">';
    if($success){
        echo '<div class="w-success"><span class="mark">'.hp_check_icon().'</span><h2>Danke für deine Anfrage.</h2><p>Wir haben deine Angaben erhalten und melden uns so schnell wie möglich per E-Mail.</p>';
        if($probeshopToken!==null)echo '<div class="w-probe"><strong>Deine unverbindliche Vorschau ist bereit.</strong><p>Sie zeigt dein Logo auf Musterartikeln und ist 30 Tage über diesen Link erreichbar. Sie enthält noch nicht die freigegebene Firmenkollektion.</p><a class="w-btn w-btn-o" href="/probeshop/'.e($probeshopToken).'" rel="noreferrer">Vorschau ansehen'.w_icon('arrow').'</a></div>';
        else echo '<a class="w-btn w-btn-dark" href="/mustershop">Mustershop ansehen</a>';
        echo '</div>';
    }else{
        if($error)echo '<div class="flash error" role="alert">'.e($error).'</div>';
        echo '<form method="post" enctype="multipart/form-data" class="w-form">'.csrf_field();
        echo '<div style="position:absolute;left:-9999px" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>';
        echo '<div class="row"><label>Unternehmen<input name="company" autocomplete="organization" value="'.e((string)($_POST['company']??'')).'" required></label><label>Dein Name<input name="name" autocomplete="name" value="'.e((string)($_POST['name']??'')).'" required></label></div>';
        echo '<div class="row"><label>E-Mail<input type="email" name="email" autocomplete="email" value="'.e((string)($_POST['email']??'')).'" required></label><label>Teamgröße <small>ungefähr, optional</small><input type="number" min="0" name="employees" value="'.e((string)($_POST['employees']??'')).'"></label></div>';
        echo '<label>Welche Artikel und Bestellwege brauchst du?<textarea name="message" rows="6" placeholder="Zum Beispiel Arbeitsjacken und Polos; Mitarbeitende bestellen selbst oder eine Person sammelt die Ausstattung fürs Team." required>'.e((string)($_POST['message']??'')).'</textarea></label>';
        echo '<div class="w-upload"><label>Dein Firmenlogo <small>optional · PNG, JPG, WebP oder SVG, max. 4 MB</small><input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg,image/png,image/jpeg,image/webp,image/svg+xml"></label><p>Mit deinem Logo erstellen wir eine <strong>unverbindliche Vorschau</strong> mit Musterartikeln. Der Link ist 30 Tage gültig. Die abgestimmte Kollektion kommt erst nach deiner Freigabe in den Firmenshop.</p></div>';
        echo '<label class="w-check"><input type="checkbox" name="privacy" value="1" required><span>Ich bin einverstanden, dass meine Angaben und ein gegebenenfalls hochgeladenes Logo zur Bearbeitung der Anfrage verwendet werden. <a href="/datenschutz" target="_blank" rel="noopener">Datenschutzhinweise</a></span></label>';
        echo '<button class="w-btn w-btn-o" type="submit">Anfrage senden'.w_icon('arrow').'</button></form>';
    }
    echo '</div><aside class="w-side"><h2>Was nach deiner Anfrage passiert</h2><ol><li><div><strong>Wir prüfen deine Angaben</strong><span>Wir melden uns so schnell wie möglich per E-Mail.</span></div></li><li><div><strong>Wir stimmen den Entwurf ab</strong><span>Artikel, Logo und Veredelung legen wir gemeinsam fest.</span></div></li><li><div><strong>Du gibst die Gestaltung frei</strong><span>Erst danach kommen die abgestimmten Artikel in den Firmenshop.</span></div></li></ol><p class="mail">Lieber per E-Mail?<br><a href="mailto:'.e(s_mail()).'">'.e(s_mail()).'</a></p></aside></div></section>';
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
    hp_page_start('Kundenlogin – '.current_site_setting('site_title','WorkwearCenter'));
    echo '<section class="w-access"><div class="w-wrap"><div class="w-access-card"><span class="w-eyebrow">Kundenlogin</span><h1>Zu deinem Firmenshop</h1><p>Jedes Unternehmen hat seinen eigenen, geschützten Shop. Gib den Kurznamen deines Shops ein, er steht in deiner Einladungs-E-Mail.</p>';
    echo '<form method="post" class="w-form">'.csrf_field();
    if($error)echo '<div class="flash error" role="alert">'.e($error).'</div>';
    echo '<label>Kurzname oder Shop-Adresse<input name="shop" value="'.e($value).'" placeholder="z. B. musterbau" autocomplete="organization" required autofocus></label><button class="w-btn w-btn-o" type="submit">Weiter zur Anmeldung'.w_icon('arrow').'</button></form><p class="w-access-foot">Noch kein Firmenshop? <a href="/anfrage">Jetzt anfragen</a></p></div></div></section>';
    hp_page_end();
}
