<?php
declare(strict_types=1);
require_once __DIR__.'/probeshop.php';

/**
 * Public, read-only showcase. It deliberately uses no customer or order data.
 * The purpose is to let prospects experience the product before requesting a shop.
 */
function mustershop_page(): void {
    $embedded=(string)($_GET['embed']??'')==='1';
    if($embedded)header('X-Robots-Tag: noindex, nofollow');
    render_head('KANTENWERK Mustershop – Firmenshop live ausprobieren | WorkwearCenter');
    if(!$embedded)w_header(); // 10.21: gleicher Kopf wie die Website
    // 10.23.2: Artikel aus dem eigenen Produktkatalog (Verwaltung → Produkte); nur ohne Katalog die drei Beispielartikel.
    $products=yw_demo_catalog_products();
    if(!$products)foreach([
        ['T-Shirt Classic','T-Shirts','Ein schlichtes Teamshirt für Arbeit und Alltag.',2490,'WC-TS01','Navy','XS–5XL',['XS','S','M','L','XL','2XL','3XL','4XL','5XL'],'assets/media/products/workwear-tshirt.webp',['x'=>60,'y'=>27,'w'=>9],'T-SHIRT'],
        ['Hoodie Team','Hoodies','Eine zusätzliche Schicht für kühle Arbeitstage.',5490,'WC-HD02','Graphit','XS–4XL',['XS','S','M','L','XL','2XL','3XL','4XL'],'assets/media/products/workwear-hoodie.webp',['x'=>63,'y'=>30,'w'=>9],'HOODIE'],
        ['Softshelljacke','Jacken','Eine Jacke für den Einsatz im Freien.',8490,'WC-SJ03','Schwarz','S–5XL',['S','M','L','XL','2XL','3XL','4XL','5XL'],'assets/media/products/workwear-softshell.webp',['x'=>61,'y'=>36,'w'=>9],'SOFTSHELL'],
    ] as [$name,$category,$detail,$cents,$sku,$color,$sizes,$sizeOptions,$image,$zone,$code]){
        $views=[['view'=>'front','label'=>'Vorderseite','image'=>$image,'layers'=>[['src'=>'assets/media/demo/kantenwerk-mark.svg','x'=>$zone['x'],'y'=>$zone['y'],'w'=>$zone['w'],'method'=>'embroidery','label'=>'KANTENWERK · Brust links']]]];
        $visual=yw_mockup_html($views,$name.' in '.$color.' mit Logo-Vorschau',false,'demo-mockup');
        $products[]=['name'=>$name,'category'=>$category,'detail'=>$detail,'cents'=>$cents,'sku'=>$sku,'color'=>$color,'sizes'=>$sizes,'size_options'=>$sizeOptions,'code'=>$code,'visual'=>$visual];
    }
    $notice=$embedded
        ?'<span><strong>KANTENWERK · Musterunternehmen</strong> · Preise, Budget und Auswahl sind Beispieldaten. Es wird keine Bestellung übertragen.</span>'
        :'<span><strong>KANTENWERK · Musterunternehmen</strong> · Preise, Budget und Auswahl sind Beispieldaten. Es wird keine Bestellung übertragen.</span><a href="/anfrage">Eigenen Firmenshop besprechen →</a>';
    yw_demo_shop_render([
        'company'=>'KANTENWERK',
        'mark'=>'<img src="/assets/media/demo/kantenwerk-logo.svg" alt="KANTENWERK – Industrie und Service">',
        'notice'=>$notice,
        'products'=>$products,
    ]);
    if(!$embedded)w_footer();
    render_foot();
}

/** 10.23.2: Im Mustershop gezeigte Katalogartikel (Verwaltung → Produkte → „Im Mustershop zeigen“). */
function yw_demo_product_ids(): array {
    $ids=json_decode(current_site_setting('demo_product_ids','[]'),true);
    return is_array($ids)?array_values(array_unique(array_filter(array_map('intval',$ids),fn($i)=>$i>0))):[];
}

/**
 * 10.23.2: Artikel für den Mustershop aus dem Stammkatalog. Angehakte Artikel in ihrer Reihenfolge,
 * sonst automatisch bis zu neun aktive Artikel mit Foto, abwechselnd aus den Kategorien.
 * Das KANTENWERK-Zeichen sitzt in der erkannten bzw. von Hand gesetzten Druckzone „Brust links“ –
 * dieselbe Zone, die auch in den echten Firmenshops gilt.
 */
function yw_demo_catalog_products(): array {
    try{
        $chosen=yw_demo_product_ids();
        if($chosen){
            $in=implode(',',array_fill(0,count($chosen),'?'));
            $q=db()->prepare('SELECT * FROM products WHERE active=1 AND id IN ('.$in.')');$q->execute($chosen);
            $byId=[];foreach($q->fetchAll() as $r)$byId[(int)$r['id']]=$r;
            $rows=[];foreach($chosen as $id)if(isset($byId[$id]))$rows[]=$byId[$id];
        }else{
            $all=db()->query("SELECT * FROM products WHERE active=1 AND image_path<>'' ORDER BY id DESC LIMIT 400")->fetchAll();
            $groups=[];foreach($all as $r)$groups[(string)($r['category']??'')][]=$r;
            $rows=[];
            while(count($rows)<9&&$groups){
                foreach($groups as $k=>&$list){if(count($rows)>=9)break;$rows[]=array_shift($list);if(!$list)unset($groups[$k]);}
                unset($list);
            }
        }
    }catch(Throwable $e){return [];}
    $out=[];
    foreach($rows as $p){
        if(count($out)>=12)break;
        $views=yw_product_views($p);
        if(!isset($views['front']))continue;
        $zones=yw_product_zones($p);
        $found=yw_zone_for($zones,'chest_left',['front']);
        $type=yw_zone_garment_type($p);
        $layers=[];
        if($found){$z=$found['zone'];$layers[]=['src'=>'assets/media/demo/kantenwerk-mark.svg','x'=>$z['x'],'y'=>$z['y'],'w'=>round(max(5.0,min(14.0,(float)$z['w']*0.8)),1),'method'=>'embroidery','label'=>'KANTENWERK'];}
        $sizes=json_decode((string)($p['sizes']??''),true);$sizes=is_array($sizes)?array_values(array_filter(array_map('strval',$sizes),'strlen')):[];
        $color=trim((string)($p['color']??''));
        $name=(string)$p['name'];
        $lines=preg_split('/\R/u',trim(strip_tags((string)($p['description']??''))))?:[];
        $detail=trim(preg_replace('/\s+/u',' ',(string)($lines[0]??'')));
        if(mb_strlen($detail)>110)$detail=rtrim(mb_substr($detail,0,107)).' …';
        $out[]=[
            'name'=>$name,
            'category'=>(string)($p['category']??'')!==''?(string)$p['category']:'Kollektion',
            'detail'=>$detail,
            'cents'=>gross_from_net((int)$p['price_cents'],product_vat_rate($p)),
            'sku'=>(string)($p['sku']??'')!==''?(string)$p['sku']:'P'.(int)$p['id'],
            'color'=>$color!==''?$color:'Wie abgebildet',
            'sizes'=>$sizes?($sizes[0].(count($sizes)>1?'–'.$sizes[count($sizes)-1]:'')):'Einheitsgröße',
            'size_options'=>$sizes?:['Einheitsgröße'],
            'code'=>mb_strtoupper((string)($p['category']??'Artikel')),
            'logo_caption'=>['head'=>'Logo · vorne','legs'=>'Logo · Bein oben','flat'=>'Logo · mittig'][$type]??'Logo · Brust links',
            'visual'=>yw_mockup_html([['view'=>'front','label'=>'Vorderseite','image'=>$views['front'],'layers'=>$layers]],$name.($color!==''?' in '.$color:'').' mit Logo-Vorschau',false,'demo-mockup'),
        ];
    }
    return $out;
}

/**
 * Gemeinsame Darstellung von Mustershop und Probeshop (10.11).
 * $o: company, mark (HTML), notice (HTML), products (name, category, detail, cents, sku, color, sizes, visual-HTML,
 * optional color_light), optional probe (bool), tone ('dark'|'light'), primary (#rrggbb).
 */
function yw_demo_shop_render(array $o): void {
    $probe=!empty($o['probe']);
    $tone=($o['tone']??'dark')==='light'?'light':'dark';
    $style='';
    if(!empty($o['primary'])&&color_ok((string)$o['primary'])){
        $brand=strtolower((string)$o['primary']);
        $deep=yw_shop_ink($brand);
        $style=' style="--demo-navy:'.e($brand).';--demo-forest:'.e($brand).';--demo-forest-deep:'.e($deep).';--demo-on-brand:'.e(yw_shop_on_color($brand)).'"';
    }
    echo '<style>'.yw_demo_shop_css().yw_demo_pro_css().'</style>';
    echo '<main class="demo'.($probe?' demo-probe tone-'.$tone.(!empty($o['logo_dark'])?' logo-dark':''):'').'"'.$style.' id="inhalt"><div class="demo-shell">';
    echo '<div class="demo-notice">'.$o['notice'].'</div>';
    if(!$probe){
        $categories=array_values(array_unique(array_column($o['products'],'category')));
        echo '<section class="demo-app" aria-label="KANTENWERK Muster-Firmenshop"><header class="demo-shopbar"><a class="demo-brand" href="#demo-kollektion" aria-label="KANTENWERK Firmenshop">'.$o['mark'].'</a><div class="demo-shopbar-actions"><span class="demo-shopbar-label"><i aria-hidden="true"></i> MUSTER-FIRMENSHOP</span><button class="demo-cart" type="button" aria-controls="demo-selection" aria-expanded="false">Deine Auswahl <span id="demo-count" aria-live="polite">0</span></button></div></header><div class="demo-main">';
        echo '<section class="demo-hero" aria-labelledby="demo-hero-title"><div class="demo-hero-copy"><span class="demo-eyebrow">KANTENWERK · INDUSTRIE &amp; SERVICE</span><h1 id="demo-hero-title">Arbeitskleidung für unser Team.</h1><p>Wähle die passende Kleidung, Größe und Menge aus der KANTENWERK-Kollektion.</p><a class="demo-hero-link" href="#demo-kollektion">Kollektion ansehen <span aria-hidden="true">→</span></a><div class="demo-assurances"><span><i aria-hidden="true">✓</i> KANTENWERK-Logo auf der Kleidung</span><span><i aria-hidden="true">✓</i> Auswahl nach Größe und Menge</span></div></div><aside class="demo-budget" aria-label="Beispielbudget"><span class="demo-budget-kicker">MUSTERBUDGET</span><span class="demo-budget-title">Verfügbar für die Ausstattung</span><strong id="demo-budget-value">218,70&nbsp;€</strong><div class="demo-progress" role="progressbar" aria-label="Verbleibendes Beispielbudget" aria-valuemin="0" aria-valuemax="30000" aria-valuenow="21870"><span id="demo-progress"></span></div><small>von 300,00&nbsp;€ · Beispieldaten</small><div class="demo-budget-foot"><span aria-hidden="true">●</span> Nur Vorschau – keine Bestellung</div></aside><span class="demo-hero-mark" aria-hidden="true"></span></section>';
        echo '<div class="demo-content" id="demo-kollektion"><section class="demo-tools" aria-label="Kollektion filtern"><div class="demo-tools-title"><div><span class="demo-eyebrow">DIE KOLLEKTION</span><h2>Für jeden Einsatz.</h2></div><span class="demo-item-count">'.count($o['products']).' Artikel in der Kollektion</span></div><div class="demo-filters"><button class="demo-filter active" data-filter="Alle" type="button" aria-pressed="true">Alle Artikel</button>';
        foreach($categories as $category)echo '<button class="demo-filter" data-filter="'.e($category).'" type="button" aria-pressed="false">'.e($category).'</button>';
        echo '</div></section><section class="demo-selection" id="demo-selection" hidden aria-labelledby="demo-selection-title"><div class="demo-selection-head"><div><span class="demo-eyebrow">NUR IN DIESER VORSCHAU</span><h2 id="demo-selection-title">Deine Auswahl</h2></div><button class="demo-clear" id="demo-clear" type="button">Auswahl leeren</button></div><ul id="demo-selection-items"></ul><p id="demo-selection-empty">Noch keine Artikel ausgewählt.</p><div class="demo-selection-total"><span>Verbleibendes Beispielbudget</span><strong id="demo-selection-budget">218,70&nbsp;€</strong></div></section><div class="demo-products" id="demo-produkte">';
        foreach($o['products'] as $p){
            $sizeOptions='';
            foreach(($p['size_options']??[]) as $size)$sizeOptions.='<option value="'.e((string)$size).'">'.e((string)$size).'</option>';
            echo '<article class="demo-product" data-category="'.e((string)$p['category']).'"><div class="demo-visual"><span class="demo-product-code">'.e((string)($p['code']??$p['category'])).'</span>'.$p['visual'].'<span class="demo-logo-caption"><span class="demo-mark-dot" aria-hidden="true"></span> '.e((string)($p['logo_caption']??'Logo · Brust links')).'</span></div><div class="demo-product-body"><span class="demo-cat">'.e((string)$p['category']).'</span><h3>'.e((string)$p['name']).'</h3><p>'.e((string)$p['detail']).'</p><div class="demo-product-foot"><span><i class="demo-color-dot" aria-hidden="true"></i> '.e((string)$p['color']).'</span><span>Größen <b>'.e((string)$p['sizes']).'</b></span></div><div class="demo-buy"><strong>'.money((int)$p['cents']).'</strong><span>Beispielpreis <b>je Stück</b></span></div><div class="demo-order-row"><label class="demo-size-label">Größe<select class="demo-size" aria-label="Größe für '.e((string)$p['name']).'"><option value="">Größe wählen</option>'.$sizeOptions.'</select></label><label class="demo-size-label">Anzahl<input class="demo-quantity" type="number" min="1" max="20" value="1" aria-label="Anzahl für '.e((string)$p['name']).'"></label><button class="demo-add" type="button" data-price="'.(int)$p['cents'].'" data-sku="'.e((string)$p['sku']).'" data-name="'.e((string)$p['name']).'" disabled><span>Größe wählen</span><span aria-hidden="true">+</span></button></div></div></article>';
        }
        echo '</div><p class="demo-sample-note">Alle Artikel, Preise und das Guthaben dienen nur zur Demonstration. Dein echter Firmenshop wird mit deinen Produkten, Farben und Regeln gestaltet.</p></div></div></section><div class="demo-toast" id="demo-toast" role="status"></div></main>';
        return;
    }
    echo '<section class="demo-app" aria-label="Probeshop für '.e((string)$o['company']).'">';
    echo '<aside class="demo-side"><div class="demo-company">'.$o['mark'].'<div><strong>'.e((string)$o['company']).'</strong><small>Mitarbeiter-Shop</small></div></div>';
    echo '<nav class="demo-nav" aria-label="Probeshop Navigation"><a class="active" href="#demo-kollektion">Kollektion</a></nav>';
    echo '</aside>';
    echo '<div class="demo-main"><header class="demo-top"><div class="demo-top-title"><strong>Deine Firmenkollektion</strong><small>'.e((string)$o['company']).' · WorkwearCenter</small></div></header>';
    echo '<div class="demo-content" id="demo-kollektion"><div class="demo-welcome"><div><h1>Arbeitskleidung für '.e((string)$o['company']).'</h1><p>Entdecke deine Auswahl mit Firmenlogo in dieser persönlichen Vorschau.</p></div><div class="demo-budget"><div class="demo-budget-row"><span>Beispielbudget</span><strong id="demo-budget-value">218,70 €</strong></div><div class="demo-progress"><span id="demo-progress"></span></div></div></div>';
    $categories=array_values(array_unique(array_column($o['products'],'category')));
    echo '<div class="demo-tools"><div class="demo-filters"><button class="demo-filter active" data-filter="Alle">Alle Artikel</button>';
    foreach($categories as $category)echo '<button class="demo-filter" data-filter="'.e($category).'">'.e($category).'</button>';
    echo '</div>';
    echo '<div class="demo-tone" role="group" aria-label="Textilfarbe der Vorschau"><span>Textilfarbe</span><button type="button" data-probe-tone="dark" aria-pressed="'.($tone==='dark'?'true':'false').'">Dunkel</button><button type="button" data-probe-tone="light" aria-pressed="'.($tone==='light'?'true':'false').'">Hell</button></div>';
    echo '<button class="demo-cart" type="button">Auswahl · <span id="demo-count">0</span></button></div><div class="demo-products">';
    foreach($o['products'] as $p){
        $color=e((string)$p['color']);
        if(!empty($p['color_light']))$color='<i class="tone-dark-only">'.$color.'</i><i class="tone-light-only">'.e((string)$p['color_light']).'</i>';
        echo '<article class="demo-product" data-category="'.e((string)$p['category']).'"><div class="demo-visual"><span class="demo-product-code">'.e((string)$p['sku']).'</span>'.$p['visual'].'</div><div class="demo-product-body"><span class="demo-cat">'.e((string)$p['category']).'</span><h2>'.e((string)$p['name']).'</h2><p>'.e((string)$p['detail']).'</p><div class="demo-meta"><span>'.$color.'</span><span>Größen '.e((string)$p['sizes']).'</span></div><div class="demo-buy"><strong>'.money((int)$p['cents']).'</strong><button class="demo-add" type="button" data-price="'.(int)$p['cents'].'" data-name="'.e((string)$p['name']).'">Auswählen</button></div></div></article>';
    }
    echo '</div></div></div></section></div><div class="demo-toast" id="demo-toast" role="status"></div></main>';
}

/**
 * 10.21: Mustershop und Probeshop im Design der Firmenshops („Werkstatt-Premium“):
 * Begrüßung in Firmenfarbe mit Naht, runde Karten, Orange nur für „In die Auswahl“.
 * Präfix body.w-body main.demo überstimmt die älteren .demo:not(.demo-probe)-Regeln.
 */
function yw_demo_pro_css(): string {
    return <<<'CSS'
body.w-body main.demo{--demo-ink:#121b24;--demo-line:#e0e3de;--demo-hero:color-mix(in srgb,var(--demo-forest,#294238) 62%,#0c1a2a);--demo-hero-2:color-mix(in srgb,var(--demo-forest,#294238) 30%,#08121d);background:var(--paper,#f4f5f2);font-family:var(--f-body)}
body.w-body main.demo h1,body.w-body main.demo h2,body.w-body main.demo h3{font-family:var(--f-display);font-stretch:86%}
body.w-body main.demo .demo-notice{border-radius:14px;border:1px dashed #ffb27f;background:#fff4eb;padding:12px 20px;margin:0 0 18px;line-height:1.45}
body.w-body main.demo .demo-app{border-radius:28px;border:1px solid var(--demo-line);box-shadow:0 2px 6px rgba(8,18,29,.08),0 30px 60px -20px rgba(8,18,29,.3)}
body.w-body main.demo .demo-shopbar{border-bottom:1px solid var(--demo-line);border-radius:28px 28px 0 0;box-shadow:none}
body.w-body main.demo .demo-shopbar-label{font-family:var(--f-mono);color:var(--orange-deep,#c94c00)}
body.w-body main.demo .demo-cart{border-radius:999px;background:var(--navy-900,#0c1a2a);color:#fff;border:0;padding:10px 16px;font-weight:800}
body.w-body main.demo .demo-cart span{display:inline-block;min-width:22px;padding:2px 7px;background:var(--orange,#ff6a13);color:#fff;border-radius:999px;text-align:center}
body.w-body main.demo .demo-hero{position:relative;margin:18px;border-radius:24px;background:radial-gradient(120% 140% at 100% 0%,rgba(182,139,91,.28),transparent 55%),linear-gradient(135deg,var(--demo-hero),var(--demo-hero-2));color:#fff;padding:clamp(34px,5vw,56px) clamp(24px,5vw,56px) clamp(44px,5vw,64px)}
body.w-body main.demo .demo-hero::after{content:"";position:absolute;left:0;right:0;bottom:12px;border-top:3px dashed rgba(214,180,140,.85)}
body.w-body main.demo .demo-hero h1{color:#fff;font-size:clamp(2.3rem,4.6vw,3.8rem);font-weight:850;line-height:.98}
body.w-body main.demo .demo-hero .demo-eyebrow{font-family:var(--f-mono);color:rgba(255,255,255,.72)}
body.w-body main.demo .demo-hero-copy>p{color:rgba(255,255,255,.82)}
body.w-body main.demo .demo-hero-link{min-height:52px;padding:0 24px;border:0;border-radius:999px;background:linear-gradient(180deg,var(--orange-2,#ff8f45),var(--orange,#ff6a13));color:#fff;font-size:.95rem;font-weight:800;box-shadow:0 10px 26px -8px rgba(255,106,19,.6)}
body.w-body main.demo .demo-assurances span{color:rgba(255,255,255,.85)}
body.w-body main.demo .demo-hero-mark{opacity:.08}
body.w-body main.demo .demo-hero .demo-budget{padding:22px 24px;border:1px solid rgba(255,255,255,.16);border-radius:20px;background:rgba(255,255,255,.07);color:#fff;backdrop-filter:blur(6px)}
body.w-body main.demo .demo-hero .demo-budget-kicker{font-family:var(--f-mono);color:rgba(255,255,255,.7)}
body.w-body main.demo .demo-hero .demo-budget-title,body.w-body main.demo .demo-hero .demo-budget small,body.w-body main.demo .demo-hero .demo-budget-foot{color:rgba(255,255,255,.75);border-color:rgba(255,255,255,.14)}
body.w-body main.demo .demo-hero .demo-budget strong{color:#fff;font-family:var(--f-display);font-stretch:86%;font-size:2.8rem}
body.w-body main.demo .demo-progress{height:8px;border-radius:999px;background:rgba(255,255,255,.15)}
body.w-body main.demo .demo-progress span{border-radius:999px;background:#d6b48c}
body.w-body main.demo .demo-filter{border-radius:999px;padding:10px 18px;font-weight:750;font-size:.86rem;background:#fff;color:var(--demo-ink);border:1px solid var(--demo-line)}
body.w-body main.demo .demo-filter.active{background:var(--demo-ink);border-color:var(--demo-ink);color:#fff}
body.w-body main.demo .demo-product{background:#fff;border:1px solid var(--demo-line);border-radius:22px;padding:10px;overflow:hidden;transition:transform .2s,box-shadow .2s}
body.w-body main.demo .demo-product:hover{transform:translateY(-4px);box-shadow:0 30px 60px -20px rgba(8,18,29,.3)}
body.w-body main.demo .demo-visual{border-radius:15px;overflow:hidden;background:#fff;box-shadow:inset 0 0 0 1px #eceee9}
body.w-body main.demo:not(.demo-probe) .demo-visual .company-product-visual,body.w-body main.demo:not(.demo-probe) .demo-visual .mockup-view{background:#fff}
body.w-body main.demo .demo-product-body{padding:16px 8px 6px}
body.w-body main.demo .demo-cat{font-family:var(--f-mono);letter-spacing:.12em}
body.w-body main.demo .demo-product h2,body.w-body main.demo .demo-product h3{font-family:var(--f-body);font-stretch:100%;font-weight:800}
body.w-body main.demo .demo-buy strong{font-family:var(--f-display);font-stretch:90%;font-weight:850;font-size:1.4rem}
body.w-body main.demo .demo-size,body.w-body main.demo .demo-qty,body.w-body main.demo .demo-order-row select,body.w-body main.demo .demo-order-row input{border-radius:12px}
body.w-body main.demo .demo-add{min-height:46px;border:0;border-radius:999px;background:linear-gradient(180deg,var(--orange-2,#ff8f45),var(--orange,#ff6a13));color:#fff;font-weight:800;box-shadow:0 8px 20px -8px rgba(255,106,19,.6)}
body.w-body main.demo .demo-add:disabled{background:#e3e6e1;color:#6b747c;box-shadow:none}
body.w-body main.demo .demo-selection{border-radius:20px}
body.w-body main.demo .demo-toast{border-radius:16px;background:var(--navy-900,#0c1a2a);border-bottom:3px solid var(--orange,#ff6a13)}
body.w-body main.demo.demo-probe .demo-welcome{position:relative;overflow:hidden;padding:32px 34px 42px;border:0;border-radius:24px;background:linear-gradient(135deg,var(--demo-hero),var(--demo-hero-2));color:#fff}
body.w-body main.demo.demo-probe .demo-welcome::after{content:"";position:absolute;left:0;right:0;bottom:10px;border-top:3px dashed rgba(214,180,140,.85)}
body.w-body main.demo.demo-probe .demo-welcome h1{color:#fff;font-weight:850}
body.w-body main.demo.demo-probe .demo-welcome p{color:rgba(255,255,255,.82)}
body.w-body main.demo.demo-probe .demo-budget{border:1px solid rgba(255,255,255,.16);border-radius:18px;background:rgba(255,255,255,.07);color:#fff;box-shadow:none}
body.w-body main.demo.demo-probe .demo-budget-row,body.w-body main.demo.demo-probe .demo-budget-row strong{color:#fff}
body.w-body main.demo .demo-company-logo{width:auto;min-width:42px;max-width:130px;height:42px;padding:4px 8px;border-radius:10px;background:#fff;overflow:hidden}
body.w-body main.demo .demo-company-logo img{display:block;width:auto;max-width:100%;max-height:34px;object-fit:contain}
body.w-body .demo-probe-next{background:var(--paper,#f4f5f2)}
body.w-body .demo-probe-next-inner{border-radius:24px;border:1px solid var(--demo-line,#e0e3de);background:#fff}
body.w-body .demo-probe-actions{display:flex;flex-wrap:wrap;gap:10px}
@media(max-width:760px){
  body.w-body main.demo .demo-hero{margin:10px;border-radius:20px}
  body.w-body main.demo .demo-hero .demo-budget strong{font-size:2.2rem}
  body.w-body main.demo .demo-product{border-radius:18px;padding:7px}
}
@media(prefers-reduced-motion:reduce){body.w-body main.demo .demo-product{transition:none}}
CSS;
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
/* 10.16: gleiches Erscheinungsbild wie der neue Firmenshop – helle Kopfzeile, Produkte ohne Rahmen */
.demo-app{grid-template-columns:minmax(0,1fr);grid-template-rows:auto 1fr;border-radius:18px;border-color:#e3e6eb;box-shadow:0 24px 70px rgba(16,24,40,.12)}
.demo-side{flex-direction:row;align-items:center;gap:26px;padding:0 28px;height:72px;background:#fff;color:#101828;border-bottom:1px solid #e3e6eb;box-shadow:inset 0 3px 0 var(--demo-navy)}
.demo-company{padding:0;border:0}.demo-company-mark{border-radius:10px;width:38px;height:38px;background:var(--demo-navy);color:#fff}.demo-company small{color:#5b6678}
.demo-nav{display:flex;gap:2px;margin:0;align-self:stretch}.demo-nav button{position:relative;padding:0 12px;border-radius:0;color:#5b6678;font-weight:650;font-size:.88rem}.demo-nav button svg{display:none}.demo-nav button.active{background:none;color:#101828}.demo-nav button.active:after{content:"";position:absolute;left:12px;right:12px;bottom:-1px;height:2px;background:var(--demo-navy);border-radius:2px}
.demo-person{margin:0 0 0 auto;border:0;padding:0;text-align:right}.demo-person small{color:#5b6678}
.demo-top{display:none}.demo-main{background:#f5f6f8}.demo-content{padding:30px}
.demo-welcome{align-items:center;padding:28px 30px;border-radius:18px;background:linear-gradient(120deg,color-mix(in srgb,var(--demo-navy) 7%,#fff),#fff 72%);border:1px solid #eceef2}
.demo-budget{background:#fff;color:#101828;border:1px solid #e3e6eb;border-radius:14px;box-shadow:0 1px 3px rgba(16,24,40,.06)}.demo-budget-row{color:#5b6678;font-weight:600}.demo-budget-row strong{color:#101828;font-size:1.35rem;white-space:nowrap}.demo-progress{background:#f2f4f7;border-radius:999px;height:8px}.demo-progress span{background:var(--demo-navy);border-radius:999px}
.demo-filter{padding:9px 15px;font-weight:650;font-size:.84rem}.demo-filter.active{background:#101828;border-color:#101828}.demo-cart{background:var(--demo-navy);border-radius:999px;padding:10px 16px;font-weight:700}
.demo-products{gap:28px 22px}.demo-product{background:none;border:0;overflow:visible}.demo-product:hover{transform:none;box-shadow:none}
.demo-visual{height:auto;aspect-ratio:1/1.08;background:#eef0f3;border-radius:14px;isolation:isolate}.demo-visual:before{display:none}.demo-visual img{filter:none;mix-blend-mode:multiply}.demo-product:hover .demo-visual{background:#e7e9ed}.demo-product:hover .demo-visual img{transform:none}
.demo-product-code{border-radius:999px;font-size:.6rem}.demo-product-body{padding:14px 2px 0}.demo-cat{font-size:.7rem;letter-spacing:.09em;color:#5b6678;font-weight:700}.demo-product h2{font-size:1rem;font-weight:650;margin:5px 0 3px}.demo-meta span{border-radius:999px;background:#fff;border-color:#e3e6eb}
.demo-buy{border-top:0;padding-top:12px;margin-top:10px}.demo-add{background:#fff;color:#101828;border:1px solid #e3e6eb;border-radius:999px;padding:9px 14px;font-weight:700;font-size:.82rem}.demo-add:hover,.demo-product:hover .demo-add{background:var(--demo-navy);border-color:var(--demo-navy);color:#fff}
.demo-toast{left:50%;right:auto;transform:translate(-50%,10px);border-radius:12px;background:#101828}.demo-toast.show{transform:translate(-50%,0)}
.demo-probe .demo-company{flex-direction:row;align-items:center}.demo-probe.logo-dark .demo-company-logo{background:none;padding:0}.demo-company-logo img{max-height:40px}
.demo-probe .demo-product:hover .demo-mockup{transform:none}.demo-tone button[aria-pressed="true"]{background:#101828;border-color:#101828}
@media(max-width:1050px){.demo-person{display:none}.demo-nav button{padding:0 8px;font-size:.82rem}}
@media(max-width:760px){.demo-side{height:auto;flex-wrap:wrap;gap:6px;padding:12px 16px 10px}.demo-nav{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:6px;width:100%;height:auto;overflow:visible;align-self:auto;margin:8px 0 0}.demo-nav button{justify-content:center;min-height:42px;padding:0 10px;border-radius:8px;white-space:normal}.demo-nav button.active{background:#eef2f6;color:#101828}.demo-nav button.active:after{left:14px;right:14px;bottom:2px}.demo-content{padding:18px 14px}.demo-welcome{padding:20px}.demo-products{gap:20px 12px}.demo-visual{height:auto}}
@media(max-width:500px){.demo-products{grid-template-columns:1fr 1fr}.demo-buy{flex-direction:column;align-items:stretch;gap:8px}.demo-product p{display:none}}
/* KANTENWERK: ruhige, eigenständige Markenwelt mit klarer Bestelloberfläche */
.demo:not(.demo-probe){
  --demo-forest:#294238;
  --demo-forest-deep:#20352d;
  --demo-lime:#b68b5b;
  --demo-ink:#24322d;
  --demo-muted:#65736b;
  --demo-paper:#f4f2ed;
  --demo-line:#dedfd9;
  background:#f2f1ec;
  color:var(--demo-ink);
  min-height:100vh;
  font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;
}
.demo:not(.demo-probe) *{box-sizing:border-box}
.demo:not(.demo-probe) .demo-shell{width:min(1320px,calc(100% - 48px));margin:0 auto;padding:18px 0 52px}
.demo:not(.demo-probe) .demo-notice{display:flex;justify-content:space-between;align-items:center;gap:18px;padding:0 2px 12px;margin:0 0 13px;border:0;border-bottom:1px solid #d9dbd5;border-radius:0;background:transparent;color:#69736e;font-size:.76rem;line-height:1.5}
.demo:not(.demo-probe) .demo-notice strong{color:var(--demo-forest);font-weight:730}
.demo:not(.demo-probe) .demo-notice a{color:var(--demo-forest);font-weight:700;text-decoration:underline;text-decoration-color:#aab2a8;text-underline-offset:3px;white-space:nowrap}
.demo:not(.demo-probe) .demo-notice a:hover{text-decoration-color:var(--demo-forest)}
.demo:not(.demo-probe) .demo-app{display:block;min-height:0;overflow:hidden;border:1px solid #dcddd7;border-radius:3px;background:#fff;box-shadow:0 6px 22px rgba(32,45,38,.045)}
.demo:not(.demo-probe) .demo-shopbar{min-height:76px;display:flex;align-items:center;justify-content:space-between;gap:20px;padding:10px clamp(18px,3vw,34px);border-bottom:1px solid #e5e6e1;background:#fff}
.demo:not(.demo-probe) .demo-brand{display:flex;align-items:center;max-width:55%;color:var(--demo-forest);text-decoration:none}
.demo:not(.demo-probe) .demo-brand img{display:block;width:min(244px,100%);height:52px;object-fit:contain;object-position:left center}
.demo:not(.demo-probe) .demo-shopbar-actions{display:flex;align-items:center;gap:22px}
.demo:not(.demo-probe) .demo-shopbar-label{display:flex;align-items:center;gap:8px;color:#6c7771;font-size:.64rem;font-weight:720;letter-spacing:.105em}
.demo:not(.demo-probe) .demo-shopbar-label i{width:7px;height:7px;border-radius:50%;background:#758e7d}
.demo:not(.demo-probe) .demo-cart{display:inline-flex;align-items:center;gap:10px;margin:0;padding:9px 12px;border:1px solid #d9ddd7;border-radius:4px;background:#fff;color:var(--demo-forest);font:inherit;font-size:.76rem;font-weight:680;cursor:pointer;transition:background .16s,border-color .16s}
.demo:not(.demo-probe) .demo-cart:hover{border-color:#9aa89d;background:#f7f8f5}
.demo:not(.demo-probe) .demo-cart span{display:grid;place-items:center;min-width:22px;height:22px;padding:0 5px;border-radius:3px;background:#e9ece7;color:var(--demo-forest);font-size:.68rem;font-weight:760}
.demo:not(.demo-probe) .demo-main{min-width:0;background:#fff}
.demo:not(.demo-probe) [id]{scroll-margin-top:98px}
.demo:not(.demo-probe) .demo-hero{position:relative;display:grid;grid-template-columns:minmax(0,1.35fr) minmax(250px,.65fr);align-items:center;gap:clamp(32px,6vw,78px);min-height:315px;padding:clamp(35px,5vw,60px) clamp(25px,5vw,68px);overflow:hidden;background:#f1f1ed;color:var(--demo-ink)}
.demo:not(.demo-probe) .demo-hero-copy{position:relative;z-index:1;max-width:660px}
.demo:not(.demo-probe) .demo-eyebrow{display:block;margin:0 0 10px;color:#8a6846;font-size:.63rem;font-weight:740;letter-spacing:.13em;line-height:1.45}
.demo:not(.demo-probe) .demo-hero .demo-eyebrow{color:#886543}
.demo:not(.demo-probe) .demo-hero h1{max-width:610px;margin:0;color:var(--demo-forest-deep);font-size:clamp(2.25rem,4.5vw,3.55rem);font-weight:670;line-height:1.04;letter-spacing:-.045em}
.demo:not(.demo-probe) .demo-hero-copy>p{max-width:54ch;margin:15px 0 0;color:#56645c;font-size:.91rem;line-height:1.65}
.demo:not(.demo-probe) .demo-hero-link{display:inline-flex;align-items:center;gap:22px;margin-top:20px;padding:11px 15px;border:1px solid var(--demo-forest);border-radius:3px;background:var(--demo-forest);color:#fff;font-size:.76rem;font-weight:700;text-decoration:none;transition:background .16s,border-color .16s}
.demo:not(.demo-probe) .demo-hero-link:hover{border-color:#172d25;background:#172d25}
.demo:not(.demo-probe) .demo-hero-link span{font-size:1rem}
.demo:not(.demo-probe) .demo-assurances{display:flex;flex-wrap:wrap;gap:12px 21px;margin-top:20px;color:#59675e;font-size:.68rem;font-weight:570}
.demo:not(.demo-probe) .demo-assurances span{display:inline-flex;align-items:center;gap:7px}
.demo:not(.demo-probe) .demo-assurances i{display:grid;place-items:center;width:16px;height:16px;border:1px solid #a88a68;border-radius:50%;color:#806143;font-size:.6rem;font-style:normal}
.demo:not(.demo-probe) .demo-budget{position:relative;z-index:1;display:flex;flex-direction:column;gap:7px;min-width:0;padding:9px 0 9px 25px;border:0;border-left:1px solid #d1d4cd;border-radius:0;background:transparent;color:var(--demo-ink);box-shadow:none}
.demo:not(.demo-probe) .demo-budget-kicker{color:#806143;font-size:.59rem;font-weight:750;letter-spacing:.12em}
.demo:not(.demo-probe) .demo-budget-title{color:#5c6961;font-size:.74rem;font-weight:560}
.demo:not(.demo-probe) .demo-budget strong{display:block;color:var(--demo-forest-deep);font-size:clamp(1.85rem,3vw,2.35rem);font-weight:680;letter-spacing:-.035em;font-variant-numeric:tabular-nums;white-space:nowrap}
.demo:not(.demo-probe) .demo-progress{height:4px;margin-top:3px;overflow:hidden;border-radius:0;background:#dfe2dc}
.demo:not(.demo-probe) .demo-progress span{display:block;width:73%;height:100%;border-radius:0;background:#7d9686;transition:width .25s}
.demo:not(.demo-probe) .demo-budget small{display:block;margin:2px 0 0;color:#707a73;font-size:.66rem;line-height:1.4}
.demo:not(.demo-probe) .demo-budget-foot{display:flex;align-items:center;gap:7px;margin-top:9px;padding-top:10px;border-top:1px solid #dfe1dc;color:#67726a;font-size:.65rem;font-weight:560}
.demo:not(.demo-probe) .demo-budget-foot span{color:#7b917f;font-size:.55rem}
.demo:not(.demo-probe) .demo-hero-mark{display:none}
.demo:not(.demo-probe) .demo-content{max-width:1320px;margin:0 auto;padding:37px clamp(18px,4vw,48px) 38px}
.demo:not(.demo-probe) .demo-tools{display:grid;gap:15px;margin:0 0 18px;padding:0;border:0}
.demo:not(.demo-probe) .demo-tools-title{display:flex;justify-content:space-between;align-items:end;gap:16px}
.demo:not(.demo-probe) .demo-tools-title .demo-eyebrow{margin:0 0 5px;color:#806143}
.demo:not(.demo-probe) .demo-tools-title h2{margin:0;color:var(--demo-forest-deep);font-size:clamp(1.4rem,2.3vw,1.85rem);font-weight:660;letter-spacing:-.035em}
.demo:not(.demo-probe) .demo-item-count{color:#707a73;font-size:.7rem;font-weight:570}
.demo:not(.demo-probe) .demo-filters{display:flex;flex-wrap:wrap;gap:7px}
.demo:not(.demo-probe) .demo-filter{padding:8px 12px;border:1px solid #d8dcd6;border-radius:3px;background:#fff;color:#4f5e55;font:inherit;font-size:.72rem;font-weight:620;cursor:pointer;transition:background .16s,border-color .16s,color .16s}
.demo:not(.demo-probe) .demo-filter:hover{border-color:#8d9c91}
.demo:not(.demo-probe) .demo-filter.active{border-color:var(--demo-forest);background:var(--demo-forest);color:#fff}
.demo:not(.demo-probe) .demo-product[hidden]{display:none}
.demo:not(.demo-probe) .demo-selection{margin:0 0 20px;padding:17px 18px;border:1px solid #d9ded7;border-left:3px solid var(--demo-forest);border-radius:2px;background:#f6f7f4}
.demo:not(.demo-probe) .demo-selection[hidden]{display:none}
.demo:not(.demo-probe) .demo-selection .demo-eyebrow{margin-bottom:5px;color:#806143}
.demo:not(.demo-probe) .demo-selection-head{display:flex;justify-content:space-between;align-items:center;gap:14px}
.demo:not(.demo-probe) .demo-selection-head h2{margin:0;color:var(--demo-forest);font-size:1.08rem}
.demo:not(.demo-probe) .demo-clear{padding:7px 10px;border:1px solid #d4d9d2;border-radius:3px;background:#fff;color:#405148;font:inherit;font-size:.7rem;font-weight:650;cursor:pointer}
.demo:not(.demo-probe) .demo-selection ul{display:grid;gap:6px;margin:12px 0 0;padding:0;list-style:none}
.demo:not(.demo-probe) .demo-selection li{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid #e1e4de;color:#405047;font-size:.76rem}
.demo:not(.demo-probe) .demo-remove{flex:0 0 auto;border:0;background:none;color:#795d40;font:inherit;font-weight:650;cursor:pointer}
.demo:not(.demo-probe) .demo-selection>p{margin:11px 0;color:#68736c;font-size:.78rem}
.demo:not(.demo-probe) .demo-selection-total{display:flex;justify-content:space-between;gap:14px;margin-top:12px;color:#58665d;font-size:.75rem}
.demo:not(.demo-probe) .demo-selection-total strong{color:var(--demo-forest);font-size:.88rem}
.demo:not(.demo-probe) .demo-products{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));align-items:stretch;gap:22px}
.demo:not(.demo-probe) .demo-product{min-width:0;overflow:hidden;border:1px solid #e2e3dd;border-radius:2px;background:#fff;transition:border-color .16s}
.demo:not(.demo-probe) .demo-product:hover{transform:none;border-color:#b8c0b8;box-shadow:none}
.demo:not(.demo-probe) .demo-visual{position:relative;display:grid;place-items:center;aspect-ratio:1.17/1;height:auto;padding:13px;overflow:hidden;background:#f0f0ec}
.demo:not(.demo-probe) .demo-visual:before{display:none}
.demo:not(.demo-probe) .demo-visual .company-product-visual{position:absolute;inset:0;width:100%;height:100%}
.demo:not(.demo-probe) .demo-visual .mockup-view{position:relative;width:100%;height:100%}
.demo:not(.demo-probe) .demo-visual .mockup-base{position:relative;z-index:1;display:block;width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply;filter:none;transition:none}
.demo:not(.demo-probe) .demo-visual .mockup-layers{position:absolute;pointer-events:none}
.demo:not(.demo-probe) .demo-visual .mockup-logo{position:absolute;z-index:2;transform:translate(-50%,-50%);line-height:0}
.demo:not(.demo-probe) .demo-visual .mockup-logo img{display:block;width:100%;height:auto;filter:drop-shadow(0 .4px .6px rgba(0,0,0,.2))}
.demo:not(.demo-probe) .demo-product-code{position:absolute;z-index:3;top:11px;left:11px;padding:5px 7px;border:1px solid rgba(38,54,44,.08);border-radius:2px;background:rgba(255,255,255,.88);color:#59665e;font-size:.57rem;font-weight:700;letter-spacing:.08em}
.demo:not(.demo-probe) .demo-logo-caption{position:absolute;right:10px;bottom:10px;z-index:3;display:flex;align-items:center;gap:6px;padding:5px 7px;border:1px solid rgba(218,221,214,.9);border-radius:2px;background:rgba(255,255,255,.9);color:#526056;font-size:.57rem;font-weight:610}
.demo:not(.demo-probe) .demo-mark-dot{width:7px;height:7px;border-radius:1px;background:#7f9684}
.demo:not(.demo-probe) .demo-product-body{padding:15px 15px 16px}
.demo:not(.demo-probe) .demo-cat{color:#806143;font-size:.6rem;font-weight:700;letter-spacing:.11em;text-transform:uppercase}
.demo:not(.demo-probe) .demo-product h3{margin:5px 0;color:#27372f;font-size:.98rem;font-weight:660;line-height:1.28;letter-spacing:-.015em}
.demo:not(.demo-probe) .demo-product p{min-height:2.7em;margin:0;color:#66736b;font-size:.72rem;line-height:1.5}
.demo:not(.demo-probe) .demo-product-foot{display:flex;justify-content:space-between;gap:8px;margin-top:11px;padding-top:10px;border-top:1px solid #eceee9;color:#707b73;font-size:.63rem}
.demo:not(.demo-probe) .demo-product-foot span{display:flex;align-items:center;gap:5px}
.demo:not(.demo-probe) .demo-product-foot b{color:#46564b;font-size:.65rem;font-weight:650}
.demo:not(.demo-probe) .demo-color-dot{width:9px;height:9px;border:1px solid rgba(0,0,0,.13);border-radius:50%;background:#253c52}
.demo:not(.demo-probe) .demo-product[data-category="Hoodies"] .demo-color-dot{background:#454b49}
.demo:not(.demo-probe) .demo-product[data-category="Jacken"] .demo-color-dot{background:#252a28}
.demo:not(.demo-probe) .demo-buy{display:flex;align-items:baseline;gap:8px;margin-top:11px;padding:0}
.demo:not(.demo-probe) .demo-buy strong{color:var(--demo-forest);font-size:1.08rem;font-weight:700;font-variant-numeric:tabular-nums}
.demo:not(.demo-probe) .demo-buy span{color:#778078;font-size:.61rem}
.demo:not(.demo-probe) .demo-buy span b{color:#536158;font-weight:650}
.demo:not(.demo-probe) .demo-order-row{display:grid;grid-template-columns:minmax(0,1fr) 76px;align-items:end;gap:8px;margin-top:12px}
.demo:not(.demo-probe) .demo-size-label{display:grid;gap:5px;color:#526057;font-size:.65rem;font-weight:630}
.demo:not(.demo-probe) .demo-size,.demo:not(.demo-probe) .demo-quantity{width:100%;min-height:40px;padding:0 9px;border:1px solid #d6dbd4;border-radius:3px;background:#fff;color:#283a30;font:inherit;font-size:.73rem}
.demo:not(.demo-probe) .demo-add{grid-column:1/-1;display:flex;justify-content:space-between;align-items:center;min-height:41px;padding:0 12px;border:1px solid var(--demo-forest);border-radius:3px;background:var(--demo-forest);color:#fff;font:inherit;font-size:.73rem;font-weight:680;cursor:pointer;transition:background .15s,border-color .15s}
.demo:not(.demo-probe) .demo-add>span:last-child{color:#dec6a8;font-size:1rem}
.demo:not(.demo-probe) .demo-add:hover:not(:disabled){border-color:#172d25;background:#172d25}
.demo:not(.demo-probe) .demo-add:disabled{border-color:#e0e3de;background:#f0f2ee;color:#79837b;cursor:not-allowed}
.demo:not(.demo-probe) .demo-add:disabled>span:last-child{color:#879087}
.demo:not(.demo-probe) .demo-sample-note{margin:16px 0 0;color:#737d75;font-size:.67rem;line-height:1.55}
.demo:not(.demo-probe) .demo-toast{position:fixed;right:22px;bottom:22px;z-index:99;max-width:min(410px,calc(100vw - 32px));padding:12px 15px;border:1px solid rgba(255,255,255,.14);border-radius:3px;background:var(--demo-forest-deep);color:#fff;box-shadow:0 8px 25px rgba(0,0,0,.17);font-size:.78rem;opacity:0;transform:translateY(8px);pointer-events:none;transition:opacity .18s,transform .18s}
.demo:not(.demo-probe) .demo-toast.show{opacity:1;transform:none}
.demo:not(.demo-probe) :focus-visible{outline:3px solid #b68b5b;outline-offset:3px}
.demo-probe .demo-nav a{position:relative;display:flex;align-items:center;padding:0 12px;color:#5b6678;font-size:.86rem;font-weight:700;text-decoration:none;white-space:nowrap}
.demo-probe .demo-nav a.active{color:#101828}
.demo-probe .demo-nav a.active:after{position:absolute;right:12px;bottom:-1px;left:12px;height:2px;border-radius:2px;background:var(--demo-navy);content:""}
@media(max-width:980px){
  .demo:not(.demo-probe) .demo-products{grid-template-columns:repeat(2,minmax(0,1fr))}
  .demo:not(.demo-probe) .demo-hero{gap:28px;padding-inline:32px}
}
@media(max-width:700px){
  .demo:not(.demo-probe) .demo-shell{width:calc(100% - 24px);padding:12px 0 30px}
  .demo:not(.demo-probe) .demo-notice{display:block;padding:0 0 10px;margin-bottom:10px;font-size:.72rem}
  .demo:not(.demo-probe) .demo-notice a{display:inline-block;margin:5px 0 0 8px;white-space:normal}
  .demo:not(.demo-probe) .demo-app{border-radius:2px}
  .demo:not(.demo-probe) .demo-shopbar{min-height:65px;padding:8px 13px;gap:8px}
  .demo:not(.demo-probe) .demo-brand{max-width:53%}
  .demo:not(.demo-probe) .demo-brand img{width:100%;height:43px}
  .demo:not(.demo-probe) .demo-shopbar-actions{gap:0}
  .demo:not(.demo-probe) .demo-shopbar-label{display:none}
  .demo:not(.demo-probe) .demo-cart{gap:7px;padding:7px 9px;font-size:.69rem}
  .demo:not(.demo-probe) .demo-cart span{min-width:20px;height:20px}
  .demo:not(.demo-probe) .demo-hero{grid-template-columns:1fr;gap:22px;min-height:0;padding:30px 21px 23px}
  .demo:not(.demo-probe) .demo-hero h1{font-size:clamp(2rem,9vw,2.85rem)}
  .demo:not(.demo-probe) .demo-hero-copy>p{margin-top:11px;font-size:.84rem;line-height:1.56}
  .demo:not(.demo-probe) .demo-hero-link{margin-top:16px}
  .demo:not(.demo-probe) .demo-assurances{gap:9px 14px;margin-top:16px;font-size:.65rem}
  .demo:not(.demo-probe) .demo-budget{width:100%;padding:13px 0 4px;border-top:1px solid #d1d4cd;border-left:0}
  .demo:not(.demo-probe) .demo-budget strong{font-size:1.75rem}
  .demo:not(.demo-probe) .demo-budget-foot{margin-top:5px;padding-top:8px}
  .demo:not(.demo-probe) .demo-content{padding:25px 16px 25px}
  .demo:not(.demo-probe) .demo-tools{gap:12px;margin-bottom:14px}
  .demo:not(.demo-probe) .demo-tools-title{align-items:center}
  .demo:not(.demo-probe) .demo-item-count{font-size:.64rem;text-align:right}
  .demo:not(.demo-probe) .demo-filter{padding:7px 10px;font-size:.69rem}
  .demo:not(.demo-probe) .demo-products{grid-template-columns:1fr;gap:14px}
  .demo:not(.demo-probe) .demo-product{display:block}
  .demo:not(.demo-probe) .demo-visual{aspect-ratio:1.55/1;padding:10px}
  .demo:not(.demo-probe) .demo-product-code{top:9px;left:9px;padding:5px 7px;font-size:.56rem}
  .demo:not(.demo-probe) .demo-logo-caption{right:8px;bottom:8px;padding:5px 7px;font-size:.56rem}
  .demo:not(.demo-probe) .demo-product-body{padding:14px 14px 15px}
  .demo:not(.demo-probe) .demo-product h3{font-size:.98rem}
  .demo:not(.demo-probe) .demo-product p{min-height:0;font-size:.76rem}
  .demo:not(.demo-probe) .demo-product-foot{display:flex;gap:8px;margin-top:10px;padding-top:9px;font-size:.66rem}
  .demo:not(.demo-probe) .demo-product-foot b{font-size:.68rem}
  .demo:not(.demo-probe) .demo-buy{gap:7px;margin-top:10px}
  .demo:not(.demo-probe) .demo-buy strong{font-size:1.05rem}
  .demo:not(.demo-probe) .demo-buy span{font-size:.62rem}
  .demo:not(.demo-probe) .demo-order-row{grid-template-columns:minmax(0,1fr) 74px;gap:8px;margin-top:10px}
  .demo:not(.demo-probe) .demo-size-label{font-size:.65rem}
  .demo:not(.demo-probe) .demo-size,.demo:not(.demo-probe) .demo-quantity{min-height:42px;padding:0 9px;font-size:.75rem}
  .demo:not(.demo-probe) .demo-add{min-height:42px;padding:0 11px;font-size:.75rem}
  .demo:not(.demo-probe) .demo-sample-note{margin-top:13px;font-size:.68rem}
}
@media(max-width:420px){
  .demo:not(.demo-probe) .demo-shell{width:calc(100% - 18px)}
  .demo:not(.demo-probe) .demo-notice a{display:block;margin-left:0}
  .demo:not(.demo-probe) .demo-shopbar{padding:8px 9px}
  .demo:not(.demo-probe) .demo-brand{max-width:49%}
  .demo:not(.demo-probe) .demo-brand img{height:40px}
  .demo:not(.demo-probe) .demo-cart{padding:6px 7px;font-size:.64rem}
  .demo:not(.demo-probe) .demo-hero{padding:27px 16px 19px}
  .demo:not(.demo-probe) .demo-hero h1{font-size:2.15rem}
  .demo:not(.demo-probe) .demo-assurances{display:grid;gap:7px}
  .demo:not(.demo-probe) .demo-content{padding:21px 11px 22px}
  .demo:not(.demo-probe) .demo-selection{padding:14px}
  .demo:not(.demo-probe) .demo-order-row{grid-template-columns:minmax(0,1fr) 53px}
  .demo:not(.demo-probe) .demo-remove{font-size:.66rem}
}
@media(max-width:350px){
  .demo:not(.demo-probe) .demo-product-body{padding:12px}
  .demo:not(.demo-probe) .demo-brand{max-width:45%}
  .demo:not(.demo-probe) .demo-cart{font-size:.6rem}
}
CSS;
}
