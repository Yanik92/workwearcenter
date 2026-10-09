<?php
declare(strict_types=1);

/**
 * Public, read-only showcase. It deliberately uses no customer or order data.
 * The purpose is to let prospects experience the product before requesting a shop.
 */
function mustershop_page(): void {
    render_head('Mustershop: Firmenshop live ausprobieren – WorkwearCenter');
    echo site_brand_style();
    public_header();

    $products=[
        ['Premium Workwear T-Shirt','T-Shirts','Brust links · DTF-Transfer','24,90 €',2490,'YW-TS01','Navy','XS–5XL','assets/media/products/workwear-tshirt.webp'],
        ['Heavy Workwear Hoodie','Hoodies','Brust links · Stick','54,90 €',5490,'YW-HD02','Graphit','XS–4XL','assets/media/products/workwear-hoodie.webp'],
        ['Softshelljacke Performance','Jacken','Brust links · Stick','84,90 €',8490,'YW-SJ03','Schwarz','S–5XL','assets/media/products/workwear-softshell.webp'],
    ];

    echo <<<'HTML'
<style>
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
@media(max-width:760px){.demo-shell{width:min(100% - 24px,1380px);padding-top:18px}.demo-app{grid-template-columns:1fr}.demo-side{padding:16px}.demo-company{padding-bottom:14px}.demo-nav{display:flex;overflow:auto;margin-top:13px}.demo-nav button{white-space:nowrap}.demo-person{display:none}.demo-content{padding:20px 15px}.demo-welcome{display:block}.demo-budget{margin-top:18px;min-width:0}.demo-tools{align-items:flex-start}.demo-products{grid-template-columns:1fr 1fr}.demo-visual{height:230px}}
@media(max-width:500px){.demo-notice{display:block}.demo-notice a{display:block;margin-top:7px}.demo-products{grid-template-columns:1fr}.demo-top{padding:0 17px}.demo-filters{max-height:76px;overflow:hidden}.demo-tools{display:block}.demo-cart{margin-top:12px;width:100%}}
</style>
<main class="demo"><div class="demo-shell">
  <div class="demo-notice"><span><strong>Interaktiver Mustershop</strong> · Alle Inhalte und Bestellungen auf dieser Seite sind reine Beispieldaten.</span><a href="/anfrage">Eigenen Unternehmensshop anfragen →</a></div>
  <section class="demo-app" aria-label="Mustershop für Musterbau GmbH">
    <aside class="demo-side">
      <div class="demo-company"><div class="demo-company-mark">M</div><div><strong>Musterbau GmbH</strong><small>Mitarbeiter-Shop</small></div></div>
      <nav class="demo-nav" aria-label="Mustershop Navigation">
        <button class="active" type="button" data-demo-nav="Übersicht"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10M9 20v-6h6v6"/></svg><span>Übersicht</span></button>
        <button type="button" data-demo-nav="Produkte"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m8 3-5 3 2 5 3-1v11h8V10l3 1 2-5-5-3a4 4 0 0 1-8 0Z"/></svg><span>Produkte</span></button>
        <button type="button" data-demo-nav="Meine Bestellungen"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21 8-9 5-9-5 9-5 9 5Z"/><path d="m3 8 9 5 9-5v9l-9 5-9-5V8Z"/><path d="M12 13v9"/></svg><span>Meine Bestellungen</span></button>
        <button type="button" data-demo-nav="Mein Profil"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg><span>Mein Profil</span></button>
      </nav>
      <div class="demo-person"><strong>Max Mustermann</strong><small>Produktion · Kostenstelle 120</small></div>
    </aside>
    <div class="demo-main">
      <header class="demo-top"><div class="demo-top-title"><strong>Ihre freigegebene Firmenkollektion</strong><small>WorkwearCenter by Yanik Textildruck</small></div><div class="demo-avatar">MM</div></header>
      <div class="demo-content">
        <div class="demo-welcome"><div><h1>Guten Morgen, Max.</h1><p>Wählen Sie aus den für Ihr Team freigegebenen Artikeln.</p></div><div class="demo-budget"><div class="demo-budget-row"><span>Verfügbares Jahresbudget</span><strong id="demo-budget-value">218,70 €</strong></div><div class="demo-progress"><span id="demo-progress"></span></div></div></div>
        <div class="demo-tools"><div class="demo-filters"><button class="demo-filter active" data-filter="Alle">Alle Artikel</button><button class="demo-filter" data-filter="T-Shirts">T-Shirts</button><button class="demo-filter" data-filter="Hoodies">Hoodies</button><button class="demo-filter" data-filter="Jacken">Jacken</button></div><button class="demo-cart" type="button">Auswahl · <span id="demo-count">0</span></button></div>
        <div class="demo-products">
HTML;
    foreach($products as $index=>[$name,$category,$detail,$price,$cents,$sku,$color,$sizes,$image]){
        $dimensions=$index===0?'width="1226" height="1283"':($index===1?'width="1312" height="1199"':'width="1222" height="1287"');
        echo '<article class="demo-product" data-category="'.e($category).'"><div class="demo-visual"><span class="demo-product-code">'.e($sku).'</span><img src="/'.e($image).'" '.$dimensions.' alt="'.e($name).' in '.e($color).'" loading="eager" decoding="async"'.($index===0?' fetchpriority="high"':'').'></div><div class="demo-product-body"><span class="demo-cat">'.e($category).'</span><h2>'.e($name).'</h2><p>'.e($detail).'</p><div class="demo-meta"><span>'.e($color).'</span><span>Größen '.e($sizes).'</span></div><div class="demo-buy"><strong>'.e($price).'</strong><button class="demo-add" type="button" data-price="'.(int)$cents.'" data-name="'.e($name).'">Auswählen</button></div></div></article>';
    }
    echo <<<'HTML'
        </div>
      </div>
    </div>
  </section>
</div><div class="demo-toast" id="demo-toast" role="status"></div></main>
HTML;
    global_footer();
    render_foot();
}
