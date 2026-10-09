<?php
declare(strict_types=1);
require_once __DIR__.'/probeshop.php';

/**
 * Public, read-only showcase. It deliberately uses no customer or order data.
 * The purpose is to let prospects experience the product before requesting a shop.
 */
function mustershop_page(): void {
    render_head('Mustershop: Firmenshop live ausprobieren – WorkwearCenter');
    echo site_brand_style();
    public_header();
    $products=[];
    foreach([
        ['Premium Workwear T-Shirt','T-Shirts','Brust links · DTF-Transfer',2490,'YW-TS01','Navy','XS–5XL','assets/media/products/workwear-tshirt.webp','width="1226" height="1283"'],
        ['Heavy Workwear Hoodie','Hoodies','Brust links · Stick',5490,'YW-HD02','Graphit','XS–4XL','assets/media/products/workwear-hoodie.webp','width="1312" height="1199"'],
        ['Softshelljacke Performance','Jacken','Brust links · Stick',8490,'YW-SJ03','Schwarz','S–5XL','assets/media/products/workwear-softshell.webp','width="1222" height="1287"'],
    ] as $index=>[$name,$category,$detail,$cents,$sku,$color,$sizes,$image,$dimensions]){
        $products[]=['name'=>$name,'category'=>$category,'detail'=>$detail,'cents'=>$cents,'sku'=>$sku,'color'=>$color,'sizes'=>$sizes,
            'visual'=>'<img src="/'.e($image).'" '.$dimensions.' alt="'.e($name).' in '.e($color).'" loading="eager" decoding="async"'.($index===0?' fetchpriority="high"':'').'>'];
    }
    yw_demo_shop_render([
        'company'=>'Musterbau GmbH',
        'mark'=>'<div class="demo-company-mark">M</div>',
        'notice'=>'<span><strong>Interaktiver Mustershop</strong> · Alle Inhalte und Bestellungen auf dieser Seite sind reine Beispieldaten.</span><a href="/anfrage">Eigenen Unternehmensshop anfragen →</a>',
        'products'=>$products,
    ]);
    global_footer();
    render_foot();
}

/**
 * Gemeinsame Darstellung von Mustershop und Probeshop (10.11).
 * $o: company, mark (HTML), notice (HTML), products (name, category, detail, cents, sku, color, sizes, visual-HTML,
 * optional color_light), optional probe (bool), tone ('dark'|'light'), primary (#rrggbb).
 */
function yw_demo_shop_render(array $o): void {
    $probe=!empty($o['probe']);
    $tone=($o['tone']??'dark')==='light'?'light':'dark';
    $style=!empty($o['primary'])&&color_ok((string)$o['primary'])?' style="--demo-navy:'.e((string)$o['primary']).'"':'';
    echo '<style>'.yw_demo_shop_css().'</style>';
    echo '<main class="demo'.($probe?' demo-probe tone-'.$tone.(!empty($o['logo_dark'])?' logo-dark':''):'').'"'.$style.' id="inhalt"><div class="demo-shell">';
    echo '<div class="demo-notice">'.$o['notice'].'</div>';
    echo '<section class="demo-app" aria-label="'.($probe?'Probeshop':'Mustershop').' für '.e((string)$o['company']).'">';
    echo '<aside class="demo-side"><div class="demo-company">'.$o['mark'].'<div><strong>'.e((string)$o['company']).'</strong><small>Mitarbeiter-Shop</small></div></div>';
    echo '<nav class="demo-nav" aria-label="'.($probe?'Probeshop':'Mustershop').' Navigation">';
    foreach([['Übersicht','home'],['Produkte','shirt'],['Meine Bestellungen','box'],['Mein Profil','user']] as $i=>[$label,$icon])echo '<button'.($i===0?' class="active"':'').' type="button" data-demo-nav="'.e($label).'">'.wc_public_icon($icon).'<span>'.e($label).'</span></button>';
    echo '</nav><div class="demo-person"><strong>Max Mustermann</strong><small>Produktion · Kostenstelle 120</small></div></aside>';
    echo '<div class="demo-main"><header class="demo-top"><div class="demo-top-title"><strong>Ihre freigegebene Firmenkollektion</strong><small>'.($probe?e((string)$o['company']).' · ':'').'WorkwearCenter by Yanik Textildruck</small></div><div class="demo-avatar">MM</div></header>';
    // 10.12: Begrüßung nach Tageszeit (wie im echten Firmenshop) statt fest „Guten Morgen“
    echo '<div class="demo-content"><div class="demo-welcome"><div><h1>'.e(yw_shop_greeting('Max')).'</h1><p>Wählen Sie aus den für Ihr Team freigegebenen Artikeln.</p></div><div class="demo-budget"><div class="demo-budget-row"><span>Verfügbares Jahresbudget</span><strong id="demo-budget-value">218,70 €</strong></div><div class="demo-progress"><span id="demo-progress"></span></div></div></div>';
    $categories=array_values(array_unique(array_column($o['products'],'category')));
    echo '<div class="demo-tools"><div class="demo-filters"><button class="demo-filter active" data-filter="Alle">Alle Artikel</button>';
    foreach($categories as $category)echo '<button class="demo-filter" data-filter="'.e($category).'">'.e($category).'</button>';
    echo '</div>';
    if($probe)echo '<div class="demo-tone" role="group" aria-label="Textilfarbe der Vorschau"><span>Textilfarbe</span><button type="button" data-probe-tone="dark" aria-pressed="'.($tone==='dark'?'true':'false').'">Dunkel</button><button type="button" data-probe-tone="light" aria-pressed="'.($tone==='light'?'true':'false').'">Hell</button></div>';
    echo '<button class="demo-cart" type="button">Auswahl · <span id="demo-count">0</span></button></div><div class="demo-products">';
    foreach($o['products'] as $p){
        $color=e((string)$p['color']);
        if($probe&&!empty($p['color_light']))$color='<i class="tone-dark-only">'.$color.'</i><i class="tone-light-only">'.e((string)$p['color_light']).'</i>';
        echo '<article class="demo-product" data-category="'.e((string)$p['category']).'"><div class="demo-visual"><span class="demo-product-code">'.e((string)$p['sku']).'</span>'.$p['visual'].'</div><div class="demo-product-body"><span class="demo-cat">'.e((string)$p['category']).'</span><h2>'.e((string)$p['name']).'</h2><p>'.e((string)$p['detail']).'</p><div class="demo-meta"><span>'.$color.'</span><span>Größen '.e((string)$p['sizes']).'</span></div><div class="demo-buy"><strong>'.money((int)$p['cents']).'</strong><button class="demo-add" type="button" data-price="'.(int)$p['cents'].'" data-name="'.e((string)$p['name']).'">Auswählen</button></div></div></article>';
    }
    echo '</div></div></div></section></div><div class="demo-toast" id="demo-toast" role="status"></div></main>';
}

function yw_demo_shop_css(): string {
    return <<<'CSS'
.demo{--demo-navy:#10283f;--demo-orange:#f47721;--demo-ink:#18283a;--demo-line:#dfe5ea;background:#efede8;color:var(--demo-ink);min-height:100vh}
.demo *{box-sizing:border-box}.demo-shell{width:min(1380px,calc(100% - 40px));margin:0 auto;padding:34px 0 70px}.demo-notice{display:flex;justify-content:space-between;gap:20px;align-items:center;padding:13px 17px;background:#fff4eb;border:1px solid #ffd5b8;border-radius:10px;margin-bottom:18px;font-size:.86rem}.demo-notice strong{color:#a34405}.demo-notice a{font-weight:850;color:#8d3900}
.demo-app{display:grid;grid-template-columns:235px minmax(0,1fr);background:#fff;border:1px solid #d9e0e5;min-height:760px;overflow:hidden;box-shadow:0 24px 70px rgba(16,40,63,.12)}
.demo-side{background:var(--demo-navy);color:#fff;padding:27px 20px;display:flex;flex-direction:column}.demo-company{display:flex;gap:11px;align-items:center;padding:0 4px 25px;border-bottom:1px solid rgba(255,255,255,.12)}.demo-company-mark{width:42px;height:42px;display:grid;place-items:center;background:var(--demo-orange);font-weight:950;font-size:1.05rem}.demo-company strong,.demo-company small{display:block}.demo-company strong{font-size:.92rem}.demo-company small{color:rgba(255,255,255,.56);font-size:.7rem;margin-top:3px}
.demo-nav{display:grid;gap:5px;margin-top:24px}.demo-nav button{all:unset;display:flex;align-items:center;gap:11px;padding:12px 13px;border-radius:8px;color:rgba(255,255,255,.63);font-size:.85rem;font-weight:750;cursor:pointer}.demo-nav button.active{background:rgba(255,255,255,.1);color:#fff}.demo-nav svg{width:18px;height:18px}.demo-person{margin-top:auto;border-top:1px solid rgba(255,255,255,.12);padding:19px 5px 0}.demo-person strong,.demo-person small{display:block}.demo-person small{color:rgba(255,255,255,.56);margin-top:4px;font-size:.72rem}
.demo-main{background:#f7f8f9}.demo-top{height:78px;padding:0 29px;background:#fff;border-bottom:1px solid var(--demo-line);display:flex;align-items:center;justify-content:space-between;gap:15px}.demo-top-title strong,.demo-top-title small{display:block}.demo-top-title strong{font-size:.92rem}.demo-top-title small{font-size:.72rem;color:#728092;margin-top:3px}.demo-avatar{width:38px;height:38px;border-radius:50%;background:#ffe7d5;color:#a54505;display:grid;place-items:center;font-weight:900}
.demo-content{padding:29px}.demo-welcome{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:22px}.demo-welcome h1{font-size:clamp(1.75rem,3vw,2.35rem);letter-spacing:-.04em;margin:0 0 7px}.demo-welcome p{margin:0;color:#697789}.demo-budget{min-width:280px;background:var(--demo-navy);color:#fff;padding:17px 19px}.demo-budget-row{display:flex;justify-content:space-between;gap:15px;font-size:.75rem}.demo-budget-row strong{font-size:1rem}.demo-progress{height:7px;background:rgba(255,255,255,.18);margin-top:11px;overflow:hidden}.demo-progress span{display:block;height:100%;width:73%;background:var(--demo-orange);transition:width .25s}
.demo-tools{display:flex;justify-content:space-between;gap:14px;align-items:center;margin:26px 0 17px}.demo-filters{display:flex;gap:7px;flex-wrap:wrap}.demo-filter{border:1px solid var(--demo-line);background:#fff;border-radius:999px;padding:8px 12px;font-weight:750;font-size:.75rem;cursor:pointer}.demo-filter.active{background:var(--demo-navy);border-color:var(--demo-navy);color:#fff}.demo-cart{border:0;background:var(--demo-orange);color:#fff;border-radius:8px;padding:10px 13px;font-weight:850;white-space:nowrap}
.demo-products{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px}.demo-product{background:#fff;border:1px solid var(--demo-line);overflow:hidden;transition:.22s ease}.demo-product:hover{border-color:#aebbc5;transform:translateY(-4px);box-shadow:0 18px 35px rgba(20,38,55,.1)}.demo-visual{height:285px;position:relative;display:grid;place-items:center;background:#ebe9e4;overflow:hidden}.demo-visual:before{content:"";position:absolute;width:190px;height:190px;border:1px solid rgba(16,40,63,.12);border-radius:50%}.demo-visual img{position:relative;z-index:1;width:92%;height:92%;object-fit:contain;filter:drop-shadow(0 18px 18px rgba(21,36,50,.14));transition:transform .28s ease}.demo-product:hover .demo-visual img{transform:scale(1.035)}.demo-product-code{position:absolute;z-index:2;left:13px;top:13px;background:rgba(255,255,255,.9);padding:6px 8px;font-size:.58rem;font-weight:900;letter-spacing:.1em}.demo-product-body{padding:18px}.demo-cat{font-size:.63rem;text-transform:uppercase;letter-spacing:.12em;color:#85909c;font-weight:850}.demo-product h2{font-size:1.04rem;margin:8px 0 6px;line-height:1.25}.demo-product p{font-size:.76rem;color:#6e7b89;margin:0}.demo-meta{display:flex;gap:7px;flex-wrap:wrap;margin-top:13px}.demo-meta span{border:1px solid #dce2e7;padding:5px 7px;font-size:.63rem;color:#536273;background:#fafafa}.demo-buy{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-top:17px;padding-top:15px;border-top:1px solid #e7ebee}.demo-buy strong{font-size:1rem}.demo-add{border:1px solid var(--demo-navy);background:var(--demo-navy);color:#fff;padding:9px 11px;font-size:.72rem;font-weight:850;cursor:pointer}.demo-add:hover{background:var(--demo-orange);border-color:var(--demo-orange)}.demo-toast{position:fixed;right:24px;bottom:24px;background:#10283f;color:#fff;padding:14px 18px;box-shadow:0 16px 40px rgba(0,0,0,.22);opacity:0;transform:translateY(10px);pointer-events:none;transition:.2s;z-index:99}.demo-toast.show{opacity:1;transform:none}
@media(max-width:1050px){.demo-products{grid-template-columns:repeat(2,1fr)}.demo-app{grid-template-columns:190px minmax(0,1fr)}}
@media(max-width:760px){.demo-shell{width:min(100% - 24px,1380px);padding-top:18px}.demo-app{grid-template-columns:minmax(0,1fr)}.demo-side,.demo-main{min-width:0}.demo-side{padding:16px}.demo-company{padding-bottom:14px}.demo-nav{display:flex;overflow:auto;margin-top:13px}.demo-nav button{white-space:nowrap}.demo-person{display:none}.demo-content{padding:20px 15px}.demo-welcome{display:block}.demo-budget{margin-top:18px;min-width:0}.demo-tools{align-items:flex-start}.demo-products{grid-template-columns:1fr 1fr}.demo-visual{height:230px}}
@media(max-width:500px){.demo-notice{display:block}.demo-notice a{display:block;margin-top:7px}.demo-products{grid-template-columns:1fr}.demo-top{padding:0 17px}.demo-filters{max-height:76px;overflow:hidden}.demo-tools{display:block}.demo-cart{margin-top:12px;width:100%}}
/* 10.11 Probeshop: Logo-Ebene, Textilfarbe, Abschluss */
.demo-probe .demo-company{flex-direction:column;align-items:flex-start;gap:12px}.demo-company-logo{width:auto;height:auto;max-width:100%;background:none;padding:0;display:block}.demo-company-logo img{max-width:min(190px,100%);max-height:56px;object-fit:contain;display:block}.demo-probe.logo-dark .demo-company-logo{background:#fff;padding:8px 10px;border-radius:6px}
@media(max-width:760px){.demo-probe .demo-company{flex-direction:row;align-items:center}.demo-company-logo img{max-height:40px;max-width:120px}}
.demo-visual .company-product-visual.demo-mockup{position:relative;z-index:1;width:92%;height:92%;transition:transform .28s ease}
.demo-visual .demo-mockup .mockup-view{width:100%;height:100%}
.demo-visual .demo-mockup img.mockup-base{position:static;width:100%;height:100%;object-fit:contain;transition:filter .25s ease}
.demo-visual .mockup-logo img{position:static;width:100%;height:auto;object-fit:initial;transform:none!important;filter:drop-shadow(0 .3px .3px rgba(0,0,0,.18))}
.demo-visual .mockup-embroidery img{filter:drop-shadow(0 0 .6px rgba(0,0,0,.45)) drop-shadow(.4px .6px .4px rgba(0,0,0,.35)) contrast(1.05)}
.demo-probe .demo-product:hover .demo-visual img{transform:none}.demo-probe .demo-product:hover .demo-mockup{transform:scale(1.035)}
.demo.tone-light .demo-mockup img.mockup-base{filter:grayscale(1) brightness(3.3) contrast(.82) drop-shadow(0 18px 18px rgba(21,36,50,.14))}
.demo .tone-light-only,.demo.tone-light .tone-dark-only{display:none}.demo.tone-light .tone-light-only{display:inline}.demo-meta i{font-style:normal}
.demo-tone{display:flex;align-items:center;gap:4px;margin-left:auto;margin-right:10px;font-size:.75rem;font-weight:750;color:#536273}.demo-tone span{margin-right:4px}.demo-tone button{border:1px solid var(--demo-line);background:#fff;border-radius:999px;padding:7px 11px;font-weight:750;font-size:.75rem;cursor:pointer}.demo-tone button[aria-pressed="true"]{background:var(--demo-navy);border-color:var(--demo-navy);color:#fff}
.demo-probe-next{background:#efede8;padding:0 0 64px}.demo-probe-next-inner{display:flex;justify-content:space-between;align-items:center;gap:28px;padding:30px 34px;background:#fff;border:1px solid #d9e0e5}.demo-probe-next h2{margin:0 0 8px;font-size:1.5rem;letter-spacing:-.02em}.demo-probe-next p{margin:0;color:#5f6d7c;max-width:640px;line-height:1.6}.demo-probe-actions{display:flex;gap:10px;flex-wrap:wrap;flex:none}
@media(max-width:760px){.demo-tone{margin:0}.demo-probe-next-inner{display:block;padding:22px 18px}.demo-probe-actions{margin-top:16px}}
@media(max-width:500px){.demo-tone{margin-top:12px}}
CSS;
}
