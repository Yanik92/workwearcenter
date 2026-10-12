<?php
declare(strict_types=1);

/** 10.15: $closeUrl gesetzt = geöffnet unter der Zeile der Artikelliste (Kundenakte), mit „Schließen“ statt Aufklappen. */
function render_company_product_customization_form(array $company,array $product,?string $closeUrl=null): void {
    $decorations=company_product_decorations($product);
    $rows=min(20,max(5,count($decorations)));
    $positionOptions=decoration_position_options();
    $methodOptions=decoration_method_options();
    // Inaktive, bereits verknüpfte Motive bleiben auswählbar, damit eine
    // andere Produktänderung die historische Zuordnung nicht still entfernt.
    static $motifCache=[]; // Kollektion mit 300 Produkten: Motive einmal je Firma statt je Produkt laden
    $motifs=$motifCache[(int)$company['id']]??=yw_company_decoration_motifs((int)$company['id'],true);

    $front=company_product_front_image($product);
    $customFront=yw_local_image_path((string)($product['preview_front_path']??''),['company-products']);
    $back=company_product_back_image($product);
    $baseImage=yw_local_image_path((string)($product['image_path']??''),['products'],true);
    $displayName=company_product_display_name($product);
    $override=$product['price_override_cents']===null ? '' : number_format((int)$product['price_override_cents']/100,2,',','');

    if($closeUrl!==null){
        echo '<div class="collection-product-editor collection-inline-editor" id="bearbeiten-'.(int)$product['id'].'"><div class="collection-editor-head"><strong>'.e($displayName).' bearbeiten</strong><a class="btn small" href="'.e($closeUrl).'#produkt-'.(int)$product['id'].'">Schließen</a></div>';
    }else{
    echo '<details class="collection-product-editor"><summary>';
    echo '<div class="collection-product-summary">';
    echo '<div class="collection-product-mini">';
    if($front!=='') echo '<img src="/'.e($front).'" alt="">';
    else echo '<span class="collection-product-fallback">'.wc_public_icon('shirt').'</span>';
    echo '</div><div><strong>'.e($displayName).'</strong><small>Stammprodukt: '.e($product['name']).'</small></div>';
    if($customFront!=='' || $back!=='' || $decorations || $override!==''){
        echo '<span class="collection-configured">Individualisiert</span>';
    }else{
        echo '<span class="collection-standard">Standard</span>';
    }
    echo '</div></summary>';
    }

    // 10.15: in der Artikelliste warnt der Browser vor dem Verlassen mit ungespeicherten Änderungen (assets/app.js)
    echo '<div class="collection-product-body"><form method="post" enctype="multipart/form-data" class="form-stack"'.($closeUrl!==null?' data-unsaved-warning':'').'>'.csrf_field();
    echo '<input type="hidden" name="action" value="update_company_product_customization">';
    echo '<input type="hidden" name="return_tab" value="companies">';
    echo '<input type="hidden" name="company_id" value="'.(int)$company['id'].'">';
    echo '<input type="hidden" name="product_id" value="'.(int)$product['id'].'">';

    echo '<div class="collection-base-info"><strong>Stammprodukt bleibt unverändert.</strong> Alles hier gilt nur für <b>'.e($company['legal_name']).'</b>.</div>';
    $motifsById=[];foreach($motifs as $m)$motifsById[(int)$m['id']]=$m;
    $mockupViews=yw_mockup_views($product,$motifsById);
    echo '<div class="collection-mockup"><div class="collection-mockup-stage">'.($mockupViews?yw_mockup_html($mockupViews,$displayName,true):'<div class="empty compact">Noch kein Produktbild.</div>').'</div>';
    echo '<div class="collection-mockup-info"><h4>So sehen Mitarbeitende den Artikel</h4><p class="muted">Das Bild entsteht automatisch aus Produktbild, Druckzone und Logo-Datei des Motivs. Position, Verfahren und Motiv unten auswählen und speichern – die Vorschau aktualisiert sich nach dem Speichern.</p>';
    $hasLayers=false;foreach($mockupViews as $v)if($v['layers'])$hasLayers=true;
    $missing=yw_mockup_missing($product,$motifsById);
    if($missing){echo '<ul class="collection-mockup-missing">';foreach($missing as $m)echo '<li>'.e($m).'</li>';echo '</ul>';}
    if($hasLayers)echo '<button class="btn" type="button" data-proof-download="'.e(preg_replace('/[^a-z0-9]+/i','-',$company['legal_name'].'-'.$displayName)).'">Korrekturabzug als PNG herunterladen</button>';
    else echo '<p class="muted">Für ein automatisches Bild braucht das gewählte Motiv eine Logo-Datei (Veredelungsmotive) und der Artikel ein Produktbild.</p>';
    echo '</div></div>';

    echo '<div class="grid2">';
    echo '<label>Name im Firmenshop <small>leer = „'.e($product['name']).'“</small><input name="custom_name" maxlength="190" value="'.e((string)($product['custom_name']??'')).'" placeholder="'.e($product['name']).'"></label>';
    echo '<label>Individueller Nettopreis <small>leer = '.money((int)$product['price_cents']).' netto aus Stammprodukt</small><input name="price_override" inputmode="decimal" value="'.e($override).'" placeholder="'.e(number_format((int)$product['price_cents']/100,2,',','')).'"></label>';
    echo '</div>';

    echo '<label>Beschreibung im Firmenshop <small>leer = Beschreibung des Stammprodukts</small><textarea name="custom_description" maxlength="3000" rows="3">'.e((string)($product['custom_description']??'')).'</textarea></label>';

    echo '<div class="collection-preview-grid">';
    foreach([
        ['front','Vorderansicht',$customFront],
        ['back','Rückansicht',$back],
    ] as [$side,$label,$path]){
        echo '<div class="collection-preview-card"><div class="collection-preview-image">';
        if($path!=='') echo '<img src="/'.e($path).'" alt="'.e($label).'">';
        elseif($side==='front' && $baseImage!=='') echo '<img src="/'.e($baseImage).'" alt="Stammprodukt">';
        else echo '<span>Kein individuelles Bild</span>';
        echo '</div><strong>'.e($label).'</strong>';
        if($side==='front' && $path==='') echo '<small>Aktuell wird das Bild des Stammprodukts verwendet.</small>';
        echo '<label>Eigenes Vorschaubild<input type="file" name="preview_'.$side.'" accept="image/png,image/jpeg,image/webp"></label>';
        if($path!=='') echo '<label class="check"><input type="checkbox" name="delete_preview_'.$side.'" value="1"><span>Individuelles Bild löschen</span></label>';
        echo '</div>';
    }
    echo '</div>';

    echo '<div class="collection-decoration-head"><div><h4>Veredelung dieses Shop-Produkts</h4><p>Hier beschreibst du exakt, was auf welcher Position umgesetzt wird.</p></div></div>';
    echo '<div class="collection-decoration-table">';
    echo '<div class="collection-decoration-labels"><span>Position</span><span>Verfahren</span><span>Motiv / Beschreibung</span><span>Motiv (Logo)</span><span>Größe</span></div>';

    for($i=0;$i<$rows;$i++){
        $d=$decorations[$i]??[];
        $position=(string)($d['position']??'');
        $method=(string)($d['method']??'');
        $text=(string)($d['text']??'');
        $motifId=(int)($d['motif_id']??0);
        $size=(string)($d['size']??'m');

        echo '<div class="collection-decoration-row"><select name="decoration_position[]" aria-label="Veredelungsposition '.($i+1).'"><option value="">Position wählen</option>';
        foreach($positionOptions as $value=>$label){
            echo '<option value="'.e($value).'" '.($position===$value?'selected':'').'>'.e($label).'</option>';
        }
        echo '</select><select name="decoration_method[]" aria-label="Verfahren '.($i+1).'"><option value="">Verfahren wählen</option>';
        foreach($methodOptions as $value=>$label){
            echo '<option value="'.e($value).'" '.($method===$value?'selected':'').'>'.e($label).'</option>';
        }
        echo '</select><input name="decoration_text[]" maxlength="500" aria-label="Motivbeschreibung '.($i+1).'" value="'.e($text).'" placeholder="z. B. Firmenlogo 9 cm breit"><select name="decoration_motif_id[]" aria-label="Motivbestand '.($i+1).'"><option value="0">Nicht verknüpft</option>';
        foreach($motifs as $motif){
            $stockLabel=(int)$motif['track_stock']===1?' · Bestand '.(int)$motif['stock_qty']:' · ohne Mengenbestand';
            if(!(int)$motif['active'])$stockLabel.=' · inaktiv';
            echo '<option value="'.(int)$motif['id'].'" '.($motifId===(int)$motif['id']?'selected':'').'>'.e($motif['name'].' · '.decoration_method_label((string)$motif['method']).$stockLabel.(yw_motif_file_path($motif)===''?' · ohne Datei':'')).'</option>';
        }
        echo '</select><select name="decoration_size[]" aria-label="Größe '.($i+1).'">';
        foreach(yw_decoration_size_options() as $value=>$label)echo '<option value="'.e($value).'" '.($size===$value?'selected':'').'>'.e($label).'</option>';
        echo '</select></div>';
    }

    echo '</div>';
    echo '<p class="muted">Eigene Vorschaubilder sind nur noch nötig, wenn das automatische Bild einmal nicht passt – sie haben dann Vorrang.</p>';
    echo '<button class="btn primary" type="submit">Shop-Produkt speichern</button>';
    echo '</form></div>'.($closeUrl!==null?'</div>':'</details>');
}

function delete_company_product_preview(string $path): void {
    if(!yw_upload_path_ok($path,'company-products')) return;
    $file=dirname(__DIR__, 2).'/'.$path;
    if(is_file($file)) @unlink($file);
}

function upload_company_product_preview(?array $file,string $old=''): string {
    if(!$file || (int)($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return $old;

    $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
    if($error!==UPLOAD_ERR_OK) throw new RuntimeException('Vorschaubild-Upload fehlgeschlagen.');

    $tmp=(string)($file['tmp_name']??'');
    $size=(int)($file['size']??0);
    if($tmp==='' || !is_uploaded_file($tmp)) throw new RuntimeException('Vorschaubild wurde nicht als gültiger Upload erkannt.');
    if($size<=0||$size>10*1024*1024) throw new RuntimeException('Vorschaubild darf maximal 10 MB groß sein.');

    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$mime]??null;
    if(!$ext) throw new RuntimeException('Für Vorschaubilder sind PNG, JPG und WEBP erlaubt.');
    yw_validate_uploaded_raster($tmp,'Produktvorschau');

    $dir=dirname(__DIR__, 2).'/uploads/company-products';
    if(!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)){
        throw new RuntimeException('Ordner für Firmenprodukt-Vorschauen konnte nicht erstellt werden.');
    }

    $name='shop-product-'.bin2hex(random_bytes(10)).'.'.$ext;
    if(!yw_move_upload($tmp,$dir.'/'.$name)){
        throw new RuntimeException('Vorschaubild konnte nicht gespeichert werden.');
    }

    return 'uploads/company-products/'.$name;
}

function shop_company_products(int $companyId,?array $account=null): array {
    $profileRules=$account===null?null:yw_account_profile_rules($account);
    $stmt=db()->prepare("
        SELECT
            p.*,
            cp.custom_name,
            cp.custom_description,
            cp.price_override_cents,
            cp.preview_front_path,
            cp.preview_back_path,
            cp.decorations_json,
            cp.yearly_limit_qty
        FROM company_products cp
        JOIN products p ON p.id=cp.product_id
        WHERE cp.company_id=? AND p.active=1
        ORDER BY p.category, p.name
    ");
    $stmt->execute([$companyId]);
    $products=$stmt->fetchAll();
    if($profileRules===null)return $products;
    $filtered=[];
    foreach($products as $product){
        $id=(int)$product['id'];if(!isset($profileRules[$id]))continue;
        $product['profile_starter_qty']=$profileRules[$id]['starter_qty'];
        $product['profile_target_qty']=$profileRules[$id]['yearly_target_qty'];
        $filtered[]=$product;
    }
    return $filtered;
}

function company_product_display_name(array $product): string {
    $custom=trim((string)($product['custom_name']??''));
    return $custom!=='' ? $custom : (string)$product['name'];
}

function company_product_display_description(array $product): string {
    $custom=trim((string)($product['custom_description']??''));
    return $custom!=='' ? $custom : (string)($product['description']??'');
}

function company_product_front_image(array $product): string {
    $custom=trim((string)($product['preview_front_path']??''));
    $custom=yw_local_image_path($custom,['company-products']);
    if($custom!=='')return $custom;
    return yw_local_image_path((string)($product['image_path']??''),['products'],true);
}

function company_product_back_image(array $product): string {
    return yw_local_image_path((string)($product['preview_back_path']??''),['company-products']);
}

function company_product_decorations(array $product): array {
    $raw=trim((string)($product['decorations_json']??''));
    if($raw==='') return [];
    $decoded=json_decode($raw,true);
    return is_array($decoded) ? array_slice(array_values(array_filter($decoded,'is_array')),0,20) : [];
}

function yw_company_decoration_motifs(int $companyId,bool $includeInactive=false): array {
    $sql='SELECT * FROM yw_decoration_motifs WHERE company_id=?';
    if(!$includeInactive)$sql.=' AND active=1';
    $sql.=' ORDER BY active DESC,name,method';
    $q=db()->prepare($sql);$q->execute([$companyId]);
    return $q->fetchAll();
}

function decoration_position_options(): array {
    $options=[];
    foreach(['chest_left','chest_right','back_top','back_large','sleeve_left','sleeve_right','neck','other'] as $key)$options[$key]=t('deco.pos.'.$key);
    return $options;
}

function decoration_method_options(): array {
    $options=[];
    foreach(['print','dtf','flex','embroidery','other'] as $key)$options[$key]=t('deco.method.'.$key);
    return $options;
}

function decoration_position_label(string $value): string {
    return decoration_position_options()[$value] ?? $value;
}

function decoration_method_label(string $value): string {
    return decoration_method_options()[$value] ?? $value;
}

function company_employee_share_percent(array $company): float {
    if((string)($company['financing_mode']??'company')!=='percentage') return 0.0;
    $pct=(float)($company['employee_share_percent']??0);
    return max(0.0,min(100.0,$pct));
}

function product_price_breakdown(array $product,array $company): array {
    $net=(isset($product['price_override_cents']) && $product['price_override_cents']!==null)
        ? max(0,(int)$product['price_override_cents'])
        : (int)$product['price_cents'];
    $vat=product_vat_rate($product);
    $gross=gross_from_net($net,$vat);
    $sharePct=company_employee_share_percent($company);
    $employee=(int)round($gross*($sharePct/100));
    $employer=max(0,$gross-$employee);

    return [
        'net'=>$net,
        'vat'=>$vat,
        'gross'=>$gross,
        'employee'=>$employee,
        'employer'=>$employer,
        'employee_percent'=>$sharePct,
    ];
}

function financing_label(array $company): string {
    if((string)($company['financing_mode']??'company')==='percentage'){
        return t('fin.employee_share',['pct'=>yw_percent(company_employee_share_percent($company))]);
    }
    return t('fin.company_full',['pct'=>yw_percent(100)]);
}


function shipping_enabled(array $company): bool {
    if(($company['delivery_mode']??'company')==='pickup') return false;
    return (int)($company['shipping_enabled']??0)===1;
}

function shipping_breakdown(array $company,int $itemsGrossCents,array $itemsNetByRate=[]): array {
    if(!shipping_enabled($company)){
        return ['net'=>0,'vat'=>0.0,'gross'=>0,'employee'=>0,'company'=>0,'free'=>true];
    }

    $freeFrom=max(0,(int)($company['free_shipping_gross_cents']??0));
    if($freeFrom>0 && $itemsGrossCents >= $freeFrom){
        return ['net'=>0,'vat'=>(float)($company['shipping_vat_rate']??19.0),'gross'=>0,'employee'=>0,'company'=>0,'free'=>true];
    }

    $net=max(0,(int)($company['shipping_net_cents']??0));
    $vat=(float)($company['shipping_vat_rate']??19.0);
    if($vat<0||$vat>100) $vat=19.0;
    $gross=$itemsNetByRate?$net+yw_shipping_tax_cents($itemsNetByRate,$net,$vat):gross_from_net($net,$vat); // 10.11: Steuer je Steuersatz

    $payer=(string)($company['shipping_payer']??'company');
    $employee=$payer==='employee' ? $gross : 0;
    $companyShare=$payer==='employee' ? 0 : $gross;

    return [
        'net'=>$net,
        'vat'=>$vat,
        'gross'=>$gross,
        'employee'=>$employee,
        'company'=>$companyShare,
        'free'=>$gross===0,
    ];
}

function shipping_rule_label(array $company): string {
    if(!shipping_enabled($company)) return t('ship.none');
    $gross=gross_from_net((int)($company['shipping_net_cents']??0),(float)($company['shipping_vat_rate']??19.0));
    // 10.11: Die Versandsteuer wird mit der Ware je Steuersatz gerundet – brutto daher „ca.“ (± 1 Cent).
    $text=t('ship.cost',['net'=>money((int)($company['shipping_net_cents']??0)),'gross'=>money($gross)]);
    $freeFrom=(int)($company['free_shipping_gross_cents']??0);
    if($freeFrom>0) $text.=' · '.t('ship.free_from',['amount'=>money($freeFrom)]);
    $text.=' · '.(((string)($company['shipping_payer']??'company'))==='employee'?t('ship.paid_by_employee'):t('ship.paid_by_company'));
    return $text;
}

/** Firmenfarben mit Standardwerten. */
function yw_shop_colors(array $company): array {
    $primary=color_ok((string)($company['primary_color']??'')) ? strtolower((string)$company['primary_color']) : '#152a40';
    $accent=color_ok((string)($company['accent_color']??'')) ? strtolower((string)$company['accent_color']) : '#ff6b00';
    return [$primary,$accent];
}

/** Relative Leuchtdichte nach WCAG für #rrggbb. */
function yw_shop_luminance(string $hex): float {
    $c=[];
    foreach([1,3,5] as $i){$v=hexdec(substr($hex,$i,2))/255;$c[]=$v<=0.03928?$v/12.92:(($v+0.055)/1.055)**2.4;}
    return 0.2126*$c[0]+0.7152*$c[1]+0.0722*$c[2];
}

function yw_shop_contrast(string $a,string $b): float {
    $la=yw_shop_luminance($a);$lb=yw_shop_luminance($b);
    return (max($la,$lb)+0.05)/(min($la,$lb)+0.05);
}

/** Lesbare Schriftfarbe auf einer Firmenfarbe (weiß oder fast schwarz). */
function yw_shop_on_color(string $hex): string {
    if(!color_ok($hex))return '#ffffff';
    return yw_shop_contrast($hex,'#ffffff')>=yw_shop_contrast($hex,'#111827')?'#ffffff':'#111827';
}

/** Firmenfarbe so weit abdunkeln, dass sie als Text auf Weiß mindestens 4,5:1 erreicht. */
function yw_shop_ink(string $hex): string {
    if(!color_ok($hex))return '#152a40';
    $rgb=[hexdec(substr($hex,1,2)),hexdec(substr($hex,3,2)),hexdec(substr($hex,5,2))];
    for($i=0;$i<30;$i++){
        $out=sprintf('#%02x%02x%02x',...$rgb);
        if(yw_shop_contrast($out,'#ffffff')>=4.5)return $out;
        $rgb=array_map(fn($v)=>(int)floor($v*0.88),$rgb);
    }
    return '#111827';
}

/** Initialen für Platzhalter-Logo und Avatar (max. zwei Buchstaben). */
function yw_shop_initials(string $name): string {
    $name=trim((string)preg_replace('/\b(GmbH|AG|KG|UG|OHG|GbR|e\.\s?K\.|Co\.?|mbH|&)\b/iu',' ',$name));
    $words=array_values(array_filter(preg_split('/[\s\-\/]+/u',$name)?:[],fn($w)=>preg_match('/^\p{L}/u',$w)));
    if(!$words)return 'W';
    $initials=mb_substr($words[0],0,1).(isset($words[1])?mb_substr($words[1],0,1):'');
    return mb_strtoupper($initials);
}

/**
 * Basisstil, den Registrierung und Passwortseiten weiterhin nutzen.
 * Der eigentliche Firmenshop lädt assets/shop.css (siehe company_portal_style()).
 */
function company_shop_style(array $company): string {
    [$primary,$accent]=yw_shop_colors($company);
    return '<style>
    :root{--shop-primary:'.e($primary).';--shop-accent:'.e($accent).';--shop-bg:#f5f7fa;--shop-card:#fff;--shop-text:#142033;--shop-muted:#667085;--shop-border:#e4e8ef;--shop-radius:20px;--shop-shadow:0 16px 42px rgba(15,23,42,.08)}
    body{background:var(--shop-bg);color:var(--shop-text)}
    .shop-wrap{max-width:1320px;margin:0 auto;padding-left:28px;padding-right:28px}
    .company-shop-kicker{color:var(--shop-accent);font-size:.83rem;letter-spacing:.13em;text-transform:uppercase;font-weight:900;margin-bottom:11px}
    .company-shop-footer{background:var(--shop-primary);color:rgba(255,255,255,.72);padding:25px 0;font-size:.86rem}
    .company-shop-footer-inner{display:flex;justify-content:space-between;align-items:center;gap:18px;flex-wrap:wrap}
    .company-shop-footer a{color:#fff;text-decoration:none}
    @media(max-width:760px){.shop-wrap{padding-left:18px;padding-right:18px}}
</style>';
}

function company_portal_account(array $company): ?array {
    if(($preview=yw_admin_preview_account($company))!==null)return $preview;
    $id=(int)($_SESSION['employee_account_id']??0);
    if(!$id) return null;

    $stmt=db()->prepare("SELECT * FROM employee_accounts WHERE id=? AND company_id=? AND active=1 AND registration_status='approved' AND (employment_start IS NULL OR employment_start<=CURDATE()) AND (employment_end IS NULL OR employment_end>=CURDATE()) LIMIT 1");
    $stmt->execute([$id,(int)$company['id']]);
    $account=$stmt->fetch();
    if($account){
        $version=(int)($account['auth_version']??1);
        if(!isset($_SESSION['employee_auth_version']) || (int)$_SESSION['employee_auth_version']!==$version){unset($_SESSION['employee_account_id'],$_SESSION['employee_auth_version']);return null;}
    }
    return $account ?: null;
}

function company_portal_logout(string $slug): never {
    if(!is_post()) { http_response_code(405); exit('Bitte das Abmeldeformular verwenden.'); }
    verify_csrf();
    unset($_SESSION['employee_account_id'],$_SESSION['employee_auth_version']);
    session_regenerate_id(true);
    redirect('/'.$slug.'/login');
}

/** Firmenfarben als CSS-Variablen + gemeinsames Stylesheet des Firmenshops (wie im Mustershop). */
function company_portal_style(array $company): string {
    [$primary,$accent]=yw_shop_colors($company);
    $vars='--cp-primary:'.$primary.';--cp-accent:'.$accent.';--cp-on-primary:'.yw_shop_on_color($primary).';--cp-on-accent:'.yw_shop_on_color($accent)
        .';--cp-primary-ink:'.yw_shop_ink($primary).';--cp-accent-ink:'.yw_shop_ink($accent).';--shop-primary:'.$primary.';--shop-accent:'.$accent;
    return '<link rel="stylesheet" href="'.e(asset_url('assets/shop.css')).'"><style>:root{'.e($vars).'}</style>';
}

/** Kleine Strich-Icons wie im Mustershop. */
function yw_shop_icon(string $name): string {
    $paths=[
        'home'=>'<path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10M9 20v-6h6v6"/>',
        'shirt'=>'<path d="m8 3-5 3 2 5 3-1v11h8V10l3 1 2-5-5-3a4 4 0 0 1-8 0Z"/>',
        'box'=>'<path d="m21 8-9 5-9-5 9-5 9 5Z"/><path d="m3 8 9 5 9-5v9l-9 5-9-5V8Z"/><path d="M12 13v9"/>',
        'check'=>'<path d="M9 11.5 11.5 14 16 9"/><rect x="3" y="3" width="18" height="18" rx="3"/>',
        'users'=>'<path d="M16 20a6 6 0 0 0-12 0"/><circle cx="10" cy="8" r="4"/><path d="M21 20a5 5 0 0 0-4-4.9M15.5 4.3a4 4 0 0 1 0 7.4"/>',
        'user'=>'<path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/>',
        'menu'=>'<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'=>'<path d="M6 6l12 12M18 6 6 18"/>',
        'bag'=>'<path d="M6 8h12l-1 13H7L6 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'logout'=>'<path d="M15 4h4v16h-4"/><path d="M10 8l-4 4 4 4M6 12h10"/>',
        'repeat'=>'<path d="M4 12a8 8 0 0 1 14-5.3L20 9"/><path d="M20 4v5h-5"/><path d="M20 12a8 8 0 0 1-14 5.3L4 15"/><path d="M4 20v-5h5"/>',
        'arrow'=>'<path d="M5 12h14M13 6l6 6-6 6"/>',
    ];
    return '<svg class="cp-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.($paths[$name]??'').'</svg>';
}

/** Firmenlogo oder Initialen-Platzhalter in Firmenfarbe. */
function yw_shop_brand_mark(array $company,string $class='cp-brand'): string {
    $logo=yw_company_logo_path($company);
    if($logo!=='')return '<span class="'.e($class).'-logo"><img src="/'.e($logo).'" alt="'.e($company['legal_name']).'"></span>';
    return '<span class="'.e($class).'-mark" aria-hidden="true">'.e(yw_shop_initials((string)$company['legal_name'])).'</span>';
}

function company_portal_section_title(string $section): string {
    return ['overview'=>t('nav.overview'),'produkte'=>t('nav.products'),'bestellungen'=>t('nav.orders'),'freigaben'=>t('nav.approvals'),'mitarbeiter'=>t('nav.people'),'team-order'=>t('nav.team_order'),'profil'=>t('nav.profile')][$section]??t('nav.shop');
}

function company_portal_footer(array $company): void {
    echo '<footer class="cp-foot"><span>'.e($company['legal_name']).' · '.e(t('shop.staff_shop')).'</span><span>WorkwearCenter · <a href="/impressum" hreflang="de">'.e(t('foot.imprint')).'</a> · <a href="/datenschutz" hreflang="de">'.e(t('foot.privacy')).'</a></span></footer>';
    echo '</div></div>';
}

/** Budgetdaten des Kontos für Anzeige (keine Berechnungslogik, nur vorhandene Funktionen). */
function yw_shop_budget_info(array $company,array $account): array {
    $remaining=employee_budget_remaining($company,$account);
    $limit=$remaining===null?null:(int)yw_budget_limit($company,$account);
    [$start,$end]=yw_period((int)$company['budget_reset_month']);
    $percent=($limit!==null&&$limit>0)?(int)round(max(0,min(100,$remaining/$limit*100))):0;
    return ['remaining'=>$remaining,'limit'=>$limit,'percent'=>$percent,'to'=>yw_date((new DateTimeImmutable($end))->modify('-1 day'))];
}

/** Budget-Karte wie im Mustershop. */
function yw_shop_budget_box(array $company,array $account): string {
    $b=yw_shop_budget_info($company,$account);
    if($b['remaining']===null){
        return '<div class="cp-budgetbox"><div class="cp-budgetbox-row"><span>'.e(t('budget.annual')).'</span><strong>'.e(t('budget.unlimited')).'</strong></div><small>'.e(financing_label($company)).'</small></div>';
    }
    return '<div class="cp-budgetbox" data-budget-remaining="'.(int)$b['remaining'].'"><div class="cp-budgetbox-row"><span>'.e(t('budget.available_annual')).'</span><strong>'.money((int)$b['remaining']).'</strong></div>'
        .'<div class="cp-budgetbox-bar" role="progressbar" aria-label="'.e(t('budget.remaining_aria')).'" aria-valuemin="0" aria-valuemax="100" aria-valuenow="'.$b['percent'].'"><span style="width:'.$b['percent'].'%"></span></div>'
        .'<small>'.e(t('budget.of_until',['limit'=>money((int)$b['limit']),'date'=>$b['to']])).'</small></div>';
}

function yw_shop_greeting(string $name,?int $hour=null): string {
    $hour=$hour??(int)date('G');
    $first=trim((string)(preg_split('/\s+/u',trim($name))[0]??''));
    $key=$hour<11?'greet.morning':($hour<18?'greet.day':'greet.evening');
    return $first!==''?t($key.'_name',['name'=>$first]):t($key);
}

/** Seitenleiste + Kopfzeile (Aufbau wie im Mustershop). */
function company_portal_header(array $company,array $account,string $section): void {
    $manager=account_is_manager($account);
    $slug='/'.$company['slug'];
    $nav=[
        'overview'=>[t('nav.overview'),$slug,'home'],
        'produkte'=>[t('nav.products'),$slug.'/produkte','shirt'],
        'bestellungen'=>[t('nav.orders'),$slug.'/bestellungen','box'],
    ];
    $pending=0;
    if($manager){
        $nav['team-order']=[t('nav.team_order'),$slug.'/sammelbestellung','users'];
        $nav['freigaben']=[t('nav.approvals'),$slug.'/freigaben','check'];
        $nav['mitarbeiter']=[t('nav.people'),$slug.'/mitarbeiter','users'];
        $pending=yw_pending_decision_count((int)$company['id'],(int)$account['id']); // 10.15: wie die Seite „Freigaben“, ohne eigene Bestellungen
    }
    $hasLogo=yw_company_logo_path($company)!=='';
    $links=function(string $class) use($nav,$section,$pending): string {
        $html='';
        foreach($nav as $key=>[$label,$href,$icon]){
            $badge=$key==='freigaben'&&$pending>0?'<span class="cp-nav-badge" aria-label="'.e(tn('nav.open_count',$pending)).'">'.$pending.'</span>':'';
            $html.='<a class="'.$class.($section===$key?' active':'').'" href="'.e($href).'"'.($section===$key?' aria-current="page"':'').'>'.yw_shop_icon($icon).'<span>'.e($label).'</span>'.$badge.'</a>';
        }
        return $html;
    };

    // 10.16: helle Kopfzeile wie in einem Onlineshop (Logo, Menü, Budget, Sprache, Konto); am Handy Menü als Leiste unten
    echo '<a class="cp-skip" href="#inhalt">'.e(t('nav.skip')).'</a><div class="cp-app'.($manager?' is-manager':'').'">';
    echo '<header class="cp-header"><div class="cp-header-inner">';
    echo '<a class="cp-brand'.($hasLogo?' has-logo':'').'" href="'.e($slug).'">'.yw_shop_brand_mark($company);
    echo '<span class="cp-brand-text">'.($hasLogo?'':'<strong>'.e($company['legal_name']).'</strong>').'<small>'.e(t('shop.staff_shop')).'</small></span></a>';
    echo '<nav class="cp-nav" aria-label="'.e(t('nav.aria')).'">'.$links('cp-nav-link').'</nav>';
    echo '<div class="cp-header-tools">';
    $budget=yw_shop_budget_info($company,$account);
    if($budget['remaining']!==null)echo '<a class="cp-budget-pill" href="'.e($slug).'" title="'.e(t('budget.available_annual')).'"><span>'.e(t('budget.annual')).'</span><strong>'.money((int)$budget['remaining']).'</strong></a>';
    echo yw_lang_switcher($company,'cp-top-lang');
    echo '<details class="cp-account"><summary aria-label="'.e(t('nav.profile')).'"><span class="cp-avatar" aria-hidden="true">'.e(yw_shop_initials((string)$account['name'])).'</span><span class="cp-account-name">'.e((string)(preg_split('/\s+/u',trim((string)$account['name']))[0]??'')).'</span></summary>';
    echo '<div class="cp-account-menu"><div class="cp-account-who"><strong>'.e($account['name']).'</strong><small>'.e($manager?t('nav.role_manager'):t('nav.role_employee')).(!empty($account['department'])?' · '.e($account['department']):'').'</small></div>';
    echo '<a href="'.e($slug).'/profil"'.($section==='profil'?' aria-current="page"':'').'>'.yw_shop_icon('user').'<span>'.e(t('nav.profile')).'</span></a>';
    echo '<form method="post" action="'.e($slug).'/logout">'.csrf_field().'<button class="cp-logout" type="submit">'.yw_shop_icon('logout').'<span>'.e(t('nav.logout')).'</span></button></form></div></details>';
    echo '</div></div></header>';
    echo '<nav class="cp-tabbar" aria-label="'.e(t('nav.aria')).'">'.$links('cp-tab').'</nav>';
    echo '<div class="cp-mainwrap">';
}

function company_portal_order_status(string $status): string {
    return in_array($status,['pending','approved','rejected','cancelled'],true)?t('status.'.$status):$status;
}

function company_portal_fulfillment_status(string $status): string {
    return in_array($status,['new','processing','accepted','goods_ordered','goods_received','production','quality_check','ready','shipped','picked_up','completed'],true)?t('fulfil.'.$status):$status;
}

/**
 * Gruppierungsschlüssel für Farbvarianten: Katalogprodukte über die Grund-Artikelnummer
 * („JN840-NAVY“ → JN840), sonst gleicher Anzeigename + Marke.
 */
function yw_shop_group_key(array $p): string {
    $sku=trim((string)($p['sku']??''));$color=trim((string)($p['color']??''));
    if($color==='')return 'id:'.(int)($p['id']??0);
    if($sku!==''){
        $base=yw_katalog_base_sku($sku,$color);
        if($base!==strtoupper($sku))return 'sku:'.$base;
    }
    return 'nb:'.mb_strtolower(trim(company_product_display_name($p))).'|'.mb_strtolower(trim((string)($p['brand']??'')));
}

/**
 * Fasst Produkte, die sich nur in der Farbe unterscheiden, zu einer Karte zusammen.
 * Jede Variante bleibt ein eigenes Produkt (product_id) für Warenkorb, Budget und Übergabe.
 * Rückgabe: Liste von Gruppen, jede Gruppe ist eine Liste von Produkten (Reihenfolge bleibt).
 */
function yw_shop_group_products(array $products): array {
    $groups=[];$colors=[];
    foreach($products as $p){
        $key=yw_shop_group_key($p);
        $color=mb_strtolower(trim((string)($p['color']??'')));
        if(isset($groups[$key]) && isset($colors[$key][$color]))$key.='#'.(int)($p['id']??0); // gleiche Farbe = anderes Produkt
        $groups[$key][]=$p;$colors[$key][$color]=true;
    }
    return array_values($groups);
}

/** Farbcode einer Variante aus dem Lieferantenkatalog (falls vorhanden), sonst ''. */
function yw_shop_variant_hex(array $p): string {
    $sku=trim((string)($p['sku']??''));$color=trim((string)($p['color']??''));
    if($sku===''||$color==='')return '';
    $base=yw_katalog_base_sku($sku,$color);
    if($base===strtoupper($sku))return '';
    $catalog=yw_katalog_load();
    $cols=$catalog['articles'][$base]['col']??null;
    if(!is_array($cols))return '';
    $code=yw_katalog_color_code($color);
    foreach($cols as $name=>$c){
        if(yw_katalog_color_code((string)$name)!==$code)continue;
        $hex=(string)($c['hex']??'');
        return color_ok($hex)&&empty($c['mc'])?strtolower($hex):'';
    }
    return '';
}

function yw_shop_swatch(array $p,string $class='sp-dot'): string {
    $hex=yw_shop_variant_hex($p);
    return '<span class="'.e($class).($hex===''?' is-neutral':'').'"'.($hex!==''?' style="background:'.e($hex).'"':'').' aria-hidden="true"></span>';
}

/**
 * Lange Beschreibungen (z. B. Daiber-Texte mit Zeilenumbrüchen) in kurze Teile zerlegen:
 * Einleitung, Stichpunkte, Material und Pflege. Einzeilige Texte bleiben ein Absatz.
 */
function yw_shop_description_parts(string $text): array {
    $text=str_replace(["_x000D_\n","_x000D_","\r\n","\r"],["\n","\n","\n","\n"],$text);
    $lines=array_values(array_filter(array_map(fn($l)=>trim((string)preg_replace('/^[\-•*·]\s*/u','',trim($l))),explode("\n",$text)),fn($l)=>$l!==''));
    $parts=['intro'=>'','bullets'=>[],'material'=>[],'care'=>[]];
    if(!$lines)return $parts;
    if(count($lines)===1){$parts['intro']=$lines[0];return $parts;}
    foreach($lines as $i=>$line){
        if(preg_match('/^(Material|Oberstoff|Futter|Obermaterial|Gewebe)\b\s*:?\s*/iu',$line) || preg_match('/\d\s*g\/m²/u',$line)){
            $parts['material'][]=(string)preg_replace('/^Material\s*:\s*/iu','',$line);continue;
        }
        if(preg_match('/waschbar|°\s?C|trockner|bügeln|chemisch reinigen|bleichen/iu',$line)){$parts['care'][]=$line;continue;}
        if($i===0 && $parts['intro']===''){$parts['intro']=$line;continue;}
        $parts['bullets'][]=$line;
    }
    return $parts;
}

/** Kurze Zeile unter dem Namen: erste Veredelung, sonst Veredelungsart. */
function yw_shop_short_line(array $p): string {
    $decorations=company_product_decorations($p);
    if($decorations){
        $d=$decorations[0];
        $line=decoration_position_label((string)($d['position']??'')).' · '.decoration_method_label((string)($d['method']??''));
        return count($decorations)>1?$line.' · +'.(count($decorations)-1):$line;
    }
    // Gespeichert ist der Schlüssel (none/print/embroidery/mixed) – nie roh anzeigen.
    $type=trim((string)($p['decoration_type']??''));
    if($type===''||$type==='none')return '';
    if($type==='mixed')return decoration_method_label('print').' & '.decoration_method_label('embroidery');
    return decoration_method_label($type);
}

/** Ein Preis für Karte/Detail: Eigenanteil oder Hinweis, dass die Firma zahlt. */
function yw_shop_price_html(array $breakdowns): string {
    if(!$breakdowns)return '';
    $share=(float)$breakdowns[0]['employee_percent']>0;
    $values=array_map(fn($b)=>(int)($share?$b['employee']:$b['gross']),$breakdowns);
    $min=min($values);$amount=count(array_unique($values))>1?t('price.from',['amount'=>money($min)]):money($min);
    if($share)return '<span class="sp-price"><small>'.e(t('price.your_share')).'</small><strong>'.e($amount).'</strong></span>';
    return '<span class="sp-price is-company"><strong>'.e($amount).'</strong><small>'.e(t('price.company_pays')).'</small></span>';
}

/** Kategorie-Filter wie im Mustershop. */
function company_shop_categories(array $groups): void {
    $categories=[];
    foreach($groups as $g){$p=$g[0];$cid=(int)($p['category_id']??0);if($cid)$categories[$cid]=product_category_name($cid,(string)($p['category']??''));}
    if(count($categories)<2)return;
    echo '<div class="sp-filters shop-category-filter" role="group" aria-label="'.e(t('product.filter_aria')).'"><button type="button" class="active" aria-pressed="true" data-filter="all">'.e(t('product.filter_all')).'</button>';
    foreach($categories as $id=>$name)echo '<button type="button" aria-pressed="false" data-filter="'.(int)$id.'">'.e($name).'</button>';
    echo '</div>';
}

/** Schlanke Produktkarte für eine Gruppe von Farbvarianten. */
function company_product_card(array $variants,array $company,array $motifs,string $slug): string {
    $p=$variants[0];$id=(int)$p['id'];
    foreach($variants as $v)if((int)($v['preselect_qty']??0)>0){$p=$v;break;}
    $name=company_product_display_name($p);
    $category=product_category_name((int)($p['category_id']??0),(string)($p['category']??''));
    $views=yw_mockup_views($p,$motifs);
    $line=yw_shop_short_line($p);
    if($line==='' && count($variants)===1 && trim((string)$p['color'])!=='')$line=(string)$p['color'];
    $breakdowns=array_map(fn($v)=>product_price_breakdown($v,$company),$variants);
    $dialogId='artikel-'.(int)$variants[0]['id'];
    $href='/'.$slug.'/produkte?artikel='.$id;

    $html='<article class="sp-card product-card" data-category="'.(int)($p['category_id']??0).'" data-group="'.e($dialogId).'">';
    $html.='<a class="sp-card-visual" href="'.e($href).'" data-open-product="'.e($dialogId).'" tabindex="-1" aria-hidden="true">';
    $html.=$views?yw_mockup_html([$views[0]],$name,false,'sp-card-mockup'):'<span class="sp-card-placeholder">'.yw_shop_icon('shirt').'</span>';
    $html.='<span class="sp-card-count" data-group-count hidden></span></a>';
    $html.='<div class="sp-card-body">'.($category!==''?'<span class="sp-cat">'.e($category).'</span>':'');
    $html.='<h2><a href="'.e($href).'" data-open-product="'.e($dialogId).'">'.e($name).'</a></h2>';
    if($line!=='')$html.='<p class="sp-line">'.e($line).'</p>';
    if(count($variants)>1){
        $html.='<div class="sp-dots" aria-label="'.e(tn('product.colors',count($variants)).': '.implode(', ',array_map(fn($v)=>(string)$v['color'],$variants))).'">';
        foreach(array_slice($variants,0,6) as $v)$html.=yw_shop_swatch($v);
        if(count($variants)>6)$html.='<span class="sp-dots-more">+'.(count($variants)-6).'</span>';
        $html.='<span class="sp-dots-label">'.e(tn('product.colors',count($variants))).'</span></div>';
    }
    $html.='<div class="sp-buy">'.yw_shop_price_html($breakdowns).'<button class="sp-add" type="button" data-open-product="'.e($dialogId).'" aria-haspopup="dialog">'.e(t('product.choose')).'</button></div>';
    $html.='</div></article>';
    return $html;
}

/** Detailansicht (Dialog) einer Produktgruppe mit allen Bestellfeldern der Varianten. */
function company_product_detail(array $variants,array $company,array $motifs,int $activeId,bool $open): string {
    $first=$variants[0];
    $dialogId='artikel-'.(int)$first['id'];
    $active=$first;
    foreach($variants as $v)if((int)$v['id']===$activeId){$active=$v;break;}
    if($activeId===0)foreach($variants as $v)if((int)($v['preselect_qty']??0)>0){$active=$v;break;}
    $name=company_product_display_name($active);
    $category=product_category_name((int)($active['category_id']??0),(string)($active['category']??''));

    $html='<dialog class="sp-detail" id="'.e($dialogId).'" aria-labelledby="'.e($dialogId).'-title"'.($open?' open':'').' data-detail>';
    $html.='<div class="sp-detail-inner"><button class="sp-detail-close" type="button" data-close-detail aria-label="'.e(t('common.close')).'">'.yw_shop_icon('close').'</button>';
    // Bild mit Logo, Umschalter vorne/hinten/Ärmel – je Farbe ein eigenes Bild
    $html.='<div class="sp-detail-media">';$hasLogo=false;
    foreach($variants as $v){
        $views=yw_mockup_views($v,$motifs);
        $label=trim((string)$v['color'])!==''?t('product.name_in_color',['name'=>company_product_display_name($v),'color'=>$v['color']]):company_product_display_name($v);
        if($views)foreach($views as $view)if($view['layers'])$hasLogo=true;
        $html.='<div class="sp-variant-visual'.((int)$v['id']===(int)$active['id']?' is-active':'').'" data-variant-visual="'.(int)$v['id'].'">';
        $html.=$views?yw_mockup_html($views,$label,true,'sp-detail-mockup'):'<span class="sp-card-placeholder">'.yw_shop_icon('shirt').'</span>';
        $html.='</div>';
    }
    // 10.12: Hinweis auf das Firmenlogo nur, wenn tatsächlich ein Logo-Motiv im Bild liegt.
    $html.=($hasLogo?'<p class="sp-media-note">'.e(t('product.logo_preview_note')).'</p>':'').'</div>';

    $html.='<div class="sp-detail-info">'.($category!==''?'<span class="sp-cat">'.e($category).'</span>':'');
    $html.='<h2 id="'.e($dialogId).'-title">'.e($name).'</h2>';
    if(!empty($active['brand']))$html.='<p class="sp-brand">'.e((string)$active['brand']).'</p>';

    // Farbauswahl
    if(count($variants)>1){
        $html.='<div class="sp-colors-wrap"><span class="sp-label" id="'.e($dialogId).'-colors">'.e(t('product.color')).': <b data-color-name>'.e((string)$active['color']).'</b></span><div class="sp-colors" role="radiogroup" aria-labelledby="'.e($dialogId).'-colors">';
        foreach($variants as $v){
            $on=(int)$v['id']===(int)$active['id'];
            $html.='<button type="button" role="radio" aria-checked="'.($on?'true':'false').'" tabindex="'.($on?'0':'-1').'" data-variant="'.(int)$v['id'].'" data-color="'.e((string)$v['color']).'">'.yw_shop_swatch($v,'sp-swatch').'<span>'.e((string)$v['color']).'</span></button>';
        }
        $html.='</div></div>';
    }

    // Bestellfelder je Variante (product_id bleibt Grundlage für Bestellung und Budget)
    foreach($variants as $v){
        $pid=(int)$v['id'];
        $price=product_price_breakdown($v,$company);
        $sizes=json_decode((string)$v['sizes'],true)?:[];
        $maxQty=array_key_exists('profile_remaining_qty',$v)?min(10,max(0,(int)$v['profile_remaining_qty'])):10;
        $preQty=min($maxQty,max(0,(int)($v['preselect_qty']??0)));
        $preSize=(string)($v['preselect_size']??'');
        $vName=company_product_display_name($v);
        $color=trim((string)$v['color']);
        $html.='<fieldset class="sp-variant'.($pid===(int)$active['id']?' is-active':'').'" data-variant-panel="'.$pid.'"><legend class="sp-variant-legend">'.e($vName).($color!==''?' · '.e($color):'').'</legend>';
        $html.='<div class="sp-variant-price">'.yw_shop_price_html([$price]).'</div>';
        $html.='<div class="sp-fields"><label class="sp-field"><span>'.e(t('common.size')).'</span><select id="size-'.$pid.'" name="size['.$pid.']"'.($maxQty===0?' disabled':'').'>';
        foreach($sizes as $size)$html.='<option value="'.e((string)$size).'"'.((string)$size===$preSize?' selected':'').'>'.e((string)$size).'</option>';
        $html.='</select></label>';
        $qtyId='qty-'.$pid;
        $html.='<div class="sp-field"><label for="'.$qtyId.'">'.e(t('common.quantity')).'</label><div class="shop-stepper"><button type="button" data-qty-step="-1" data-qty-target="'.$qtyId.'" aria-label="'.e(t('product.qty_less')).'"'.($maxQty===0?' disabled':'').'>−</button>';
        $html.='<input class="shop-qty" id="'.$qtyId.'" type="number" inputmode="numeric" min="0" max="'.$maxQty.'" value="'.$preQty.'" name="qty['.$pid.']" data-gross="'.(int)$price['gross'].'" data-net="'.(int)$price['net'].'" data-vat="'.e((string)$price['vat']).'" data-share="'.e((string)$price['employee_percent']).'" data-employee="'.(int)$price['employee'].'" data-company="'.(int)$price['employer'].'" data-group="'.e($dialogId).'" data-line-name="'.e($vName.($color!==''?' · '.$color:'')).'" data-size-target="size-'.$pid.'"'.($maxQty===0?' disabled':'').'>';
        $html.='<button type="button" data-qty-step="1" data-qty-target="'.$qtyId.'" aria-label="'.e(t('product.qty_more')).'"'.($maxQty===0?' disabled':'').'>+</button></div></div></div>';
        if($maxQty===0)$html.='<p class="shop-limit-reached">'.e(t('product.limit_reached')).'</p>';
        elseif(array_key_exists('profile_remaining_qty',$v))$html.='<p class="sp-hint">'.e(tn('product.remaining_qty',(int)$v['profile_remaining_qty'])).'</p>';
        if(isset($v['profile_starter_qty']) && (int)$v['profile_starter_qty']>0)$html.='<p class="sp-hint">'.e(tn('product.starter_qty',(int)$v['profile_starter_qty'])).'</p>';
        $html.='</fieldset>';
    }
    $html.='<div class="sp-detail-actions"><button class="sp-primary" type="button" data-add-detail>'.e(t('product.add')).'</button><button class="sp-secondary" type="button" data-close-detail>'.e(t('product.keep_browsing')).'</button></div>';
    $html.='<p class="sp-detail-status" data-detail-status role="status" aria-live="polite"></p>';

    // Veredelung
    $decorations=company_product_decorations($active);
    if($decorations){
        $html.='<section class="sp-section"><h3>'.e(t('product.decoration')).'</h3><ul class="sp-specs">';
        foreach($decorations as $d){$t=trim((string)($d['text']??''));$html.='<li><span>'.e(decoration_position_label((string)($d['position']??''))).'</span><b>'.e(decoration_method_label((string)($d['method']??''))).($t!==''?' · '.e($t):'').'</b></li>';}
        $html.='</ul></section>';
    }elseif(trim((string)($active['decoration_details']??''))!==''){
        $html.='<section class="sp-section"><h3>'.e(t('product.decoration')).'</h3><p>'.nl2br(e((string)$active['decoration_details'])).'</p></section>';
    }

    // Beschreibung kurz, Rest aufklappbar
    $parts=yw_shop_description_parts(company_product_display_description($active));
    if($parts['intro']!==''||$parts['bullets']){
        $html.='<section class="sp-section"><h3>'.e(t('product.about')).'</h3>';
        if($parts['intro']!==''){
            $intro=$parts['intro'];
            if(mb_strlen($intro)>220){$cut=mb_substr($intro,0,200);$cut=mb_substr($cut,0,max(120,(int)mb_strrpos($cut,' ')));$html.='<p>'.e($cut).' …</p><details class="sp-more"><summary>'.e(t('product.show_more')).'</summary><p>'.e($intro).'</p></details>';}
            else $html.='<p>'.e($intro).'</p>';
        }
        if($parts['bullets']){
            $html.='<ul class="sp-bullets">';foreach(array_slice($parts['bullets'],0,4) as $b)$html.='<li>'.e($b).'</li>';$html.='</ul>';
            if(count($parts['bullets'])>4){$html.='<details class="sp-more"><summary>'.e(t('product.show_more')).'</summary><ul class="sp-bullets">';foreach(array_slice($parts['bullets'],4) as $b)$html.='<li>'.e($b).'</li>';$html.='</ul></details>';}
        }
        $html.='</section>';
    }
    if($parts['material']||$parts['care']){
        $html.='<section class="sp-section"><h3>'.e(t('product.material_care')).'</h3><ul class="sp-specs">';
        foreach($parts['material'] as $m)$html.='<li><span>'.e(t('product.material')).'</span><b>'.e($m).'</b></li>';
        foreach($parts['care'] as $c)$html.='<li><span>'.e(t('product.care')).'</span><b>'.e($c).'</b></li>';
        $html.='</ul></section>';
    }
    $sizesAll=[];foreach($variants as $v)foreach(json_decode((string)$v['sizes'],true)?:[] as $s)$sizesAll[(string)$s]=true;
    if($sizesAll)$html.='<section class="sp-section"><h3>'.e(t('product.sizes')).'</h3><p class="sp-sizes">'.e(implode(' · ',array_keys($sizesAll))).'</p></section>';
    $html.='</div></div></dialog>';
    return $html;
}

function company_shop_footer(array $company): void {
    echo '<footer class="company-shop-footer"><div class="shop-wrap company-shop-footer-inner">';
    echo '<span>'.e($company['legal_name']).' · '.e(t('shop.staff_shop')).'</span>';
    echo '<span>WorkwearCenter · <a href="/impressum" hreflang="de">'.e(t('foot.imprint')).'</a> · <a href="/datenschutz" hreflang="de">'.e(t('foot.privacy')).'</a></span>';
    echo '</div></footer>';
}

function employee_account_login_page(string $slug): void {
    $stmt=db()->prepare('SELECT * FROM companies WHERE slug=? AND active=1 LIMIT 1');
    $stmt->execute([$slug]);
    $company=$stmt->fetch();

    if(!$company){
        http_response_code(404);
        simple_message('Shop nicht gefunden','Dieser Unternehmensshop ist nicht verfügbar.');
        return;
    }

    if(company_portal_account($company)) redirect('/'.$slug);
    yw_shop_lang_init($company); // 10.12: Sprache vor der Anmeldung (Cookie bzw. Browser)

    $error='';
    if(is_post()){
        verify_csrf();
        $email=strtolower(trim((string)($_POST['email']??'')));
        $password=(string)($_POST['password']??'');
        $loginKey='employee-'.(int)$company['id'].'-'.hash('sha256',$email);

        // Pro IP (8 Versuche) und zusätzlich IP-unabhängig je Konto (25 Versuche) wie bei der Verwaltung,
        // damit verteiltes Passwort-Raten über viele Adressen ebenfalls gebremst wird.
        if(login_throttled($loginKey) || login_throttled($loginKey,25,false)){
            $error=t('auth.too_many');
        }else{
            $stmt=db()->prepare("SELECT * FROM employee_accounts WHERE company_id=? AND email=? AND active=1 AND registration_status='approved' AND (employment_start IS NULL OR employment_start<=CURDATE()) AND (employment_end IS NULL OR employment_end>=CURDATE()) LIMIT 1");
            $stmt->execute([(int)$company['id'],$email]);
            $account=$stmt->fetch();

            $passwordValid=$account
                ? password_verify($password,(string)$account['password_hash'])
                : password_verify($password,yw_dummy_password_hash());

            if($account && $passwordValid){
                if(password_needs_rehash((string)$account['password_hash'],PASSWORD_DEFAULT)){
                    db()->prepare('UPDATE employee_accounts SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),(int)$account['id']]);
                }
                clear_login_failures($loginKey);clear_login_failures($loginKey,false);
                session_regenerate_id(true);
                $_SESSION['employee_account_id']=(int)$account['id'];
                $_SESSION['employee_auth_version']=(int)($account['auth_version']??1);
                // 10.12: Vor der Anmeldung gewählte Sprache übernehmen, solange am Konto noch keine steht.
                if(!yw_lang_valid($account['language']??null) && yw_lang_valid($_COOKIE[YW_LANG_COOKIE]??null)){
                    try{db()->prepare('UPDATE employee_accounts SET language=? WHERE id=? AND language IS NULL')->execute([yw_lang(),(int)$account['id']]);}catch(Throwable $e){}
                }
                try{
                    db()->prepare('UPDATE employee_accounts SET last_login_at=NOW() WHERE id=?')->execute([(int)$account['id']]);
                }catch(Throwable $e){}
                redirect('/'.$company['slug']);
            }

            record_login_failure($loginKey);record_login_failure($loginKey,false);
            $error=t('auth.login_failed');
        }
    }

    $card='<h2>'.e(t('auth.login_title')).'</h2><p>'.e(t('auth.login_sub')).'</p>';
    if($error) $card.='<div class="flash error" role="alert">'.e($error).'</div>';
    $card.='<form method="post" class="form-stack">'.csrf_field()
        .'<label>'.e(t('common.email')).'<input type="email" name="email" autocomplete="email" required></label>'
        .'<label>'.e(t('common.password')).'<input type="password" name="password" autocomplete="current-password" required></label>'
        .'<button class="cp-btn-primary" type="submit">'.e(t('auth.login_button')).'</button></form>';
    $card.=yw_magic_login_form($company); // 10.11: Anmeldelink per E-Mail (app/modules/magic_login.php)
    $card.='<div class="cp-login-help"><a href="/'.e($company['slug']).'/passwort-vergessen">'.e(t('auth.forgot_link')).'</a><span>'.e(t('auth.no_access')).' <a href="/'.e($company['slug']).'/registrieren">'.e(t('auth.register_link')).'</a></span></div>';
    yw_shop_auth_page($company,t('auth.login_page_title',['shop'=>$company['shop_name']]),[t('shop.staff_shop'),t('auth.login_headline'),t('auth.login_intro')],$card);
}

/**
 * 10.11: Gemeinsamer Rahmen für Anmelden, Registrieren und Passwort-Seiten des Firmenshops
 * (links Firmenfarbe mit Logo bzw. Initialen, rechts die Karte). $intro = [Kicker, Überschrift, Text].
 */
function yw_shop_auth_page(array $company,string $title,array $intro,string $cardHtml): void {
    if(!yw_lang_shop_active())yw_shop_lang_init($company);
    render_head($title,$company);
    echo company_portal_style($company);
    $hasLogo=yw_company_logo_path($company)!=='';
    // 10.16: links Logo und Formular auf Weiß, rechts Fläche in Firmenfarbe mit Begrüßung und Bildern der Kollektion
    echo '<main class="cp-login"><section class="cp-login-form">';
    echo '<div class="cp-login-identity'.($hasLogo?' has-logo':'').'">'.yw_shop_brand_mark($company,'cp-login').($hasLogo?'':'<strong>'.e($company['legal_name']).'</strong>').'</div>';
    // 10.12: Sprachwahl über der Karte; im DOM nach dem Formular (Tastatur), per CSS-order oben
    echo '<div class="cp-login-card">'.$cardHtml.'</div>'.yw_lang_switcher($company,'cp-login-lang').'<p class="cp-login-legal"><a href="/impressum" hreflang="de">'.e(t('foot.imprint')).'</a> · <a href="/datenschutz" hreflang="de">'.e(t('foot.privacy')).'</a></p></section>';
    echo '<section class="cp-login-brand"><div class="cp-login-copy"><span class="cp-login-kicker">'.e($intro[0]??t('shop.staff_shop')).'</span><h1>'.e($intro[1]??'').'</h1><p>'.e($intro[2]??'').'</p></div>';
    $images=yw_shop_login_images($company);
    if($images){echo '<div class="cp-login-gallery" aria-hidden="true">';foreach($images as $img)echo '<span><img src="/'.e($img).'" alt="" decoding="async"></span>';echo '</div>';}
    echo '<p class="cp-login-powered">'.e(t('auth.powered')).'</p></section></main>';
    render_foot();
}

/** 10.16: bis zu drei Produktfotos der Kollektion (ohne Preise) für die Anmeldeseite. */
function yw_shop_login_images(array $company): array {
    try{
        $q=db()->prepare('SELECT p.name,p.image_path,cp.preview_front_path FROM company_products cp JOIN products p ON p.id=cp.product_id WHERE cp.company_id=? AND p.active=1 ORDER BY p.category,p.name LIMIT 40');
        $q->execute([(int)$company['id']]);
    }catch(Throwable $e){return [];}
    $images=[];$seen=[];
    foreach($q as $row){
        $img=company_product_front_image($row);$key=mb_strtolower((string)$row['name']); // je Artikel nur eine Farbe
        if($img===''||isset($seen[$key]))continue;
        $seen[$key]=true;$images[]=$img;
        if(count($images)===3)break;
    }
    return count($images)>=2?$images:[];
}

/** Rückmeldung nach Registrierung bzw. Passwort-Aktion im selben Rahmen. */
function yw_shop_auth_message(array $company,string $slug,string $title,string $text,array $intro): void {
    yw_shop_auth_page($company,$title.' – '.(string)($company['shop_name']??''),$intro,'<h2>'.e($title).'</h2><p>'.e($text).'</p><div class="cp-login-help"><a href="/'.e($slug).'/login">'.e(t('auth.to_login')).'</a></div>');
}

function company_portal_page(string $slug,string $section='overview'): void {
    $stmt=db()->prepare('SELECT * FROM companies WHERE slug=? AND active=1 LIMIT 1');
    $stmt->execute([$slug]);
    $company=$stmt->fetch();

    if(!$company){
        http_response_code(404);
        simple_message('Shop nicht gefunden','Dieser Unternehmensshop ist nicht verfügbar.');
        return;
    }

    $account=company_portal_account($company);
    if(!$account) redirect('/'.$slug.'/login');
    yw_shop_lang_init($company,$account); // 10.12: Sprache des Kontos (sonst Cookie/Browser)

    $manager=account_is_manager($account);
    if(in_array($section,['freigaben','mitarbeiter','team-order'],true) && !$manager){
        http_response_code(403);
        simple_message(t('err.no_access_title'),t('err.no_access_text'));
        return;
    }

    if(is_post() && yw_is_preview($account)){flash('info',t('flash.preview_mode'));redirect('/'.$slug.($section==='overview'?'':'/'.$section));}
    if(is_post()){
        verify_csrf();
        $action=(string)($_POST['action']??'');

        if($manager && $action==='registration_decision' && in_array($section,['mitarbeiter','overview'],true)) yw_registration_decision($company,$account,$section==='overview'?'':'/mitarbeiter');
        if($manager && in_array($action,['customer_employee_add','customer_employee_save','customer_employee_import','customer_employee_link','customer_employee_anonymize'],true) && $section==='mitarbeiter') yw_customer_employee_action($company,$account,$action);
        if($manager && $action==='team_order' && $section==='team-order') place_team_order($company,$account);
        if($action==='order' && $section==='produkte'){
            place_account_order($company,$account);
        }

        // 10.11: Umtausch & Rückgabe (app/modules/exchange.php)
        if(str_starts_with($action,'exchange_')) yw_exchange_portal_action($company,$account,$action,$section);

        // Freigabe – gemeinsame Regeln für Shop und E-Mail-Link (app/modules/order_mail_actions.php)
        if($manager && $action==='decision' && $section==='freigaben'){
            [$type,$message]=yw_order_decide($company,$account,(int)($_POST['order_id']??0),(string)($_POST['decision']??''),is_string($_POST['decision_note']??null)?$_POST['decision_note']:'');
            flash($type,$message);
            redirect('/'.$slug.'/freigaben');
        }

        if($action==='cancel_order' && $section==='bestellungen'){
            $orderId=(int)($_POST['order_id']??0);
            $stmt=db()->prepare("UPDATE orders SET status='cancelled',cancelled_at=NOW() WHERE id=? AND company_id=? AND employee_account_id=? AND status='pending'");
            $stmt->execute([$orderId,(int)$company['id'],(int)$account['id']]);
            if($stmt->rowCount()){
                audit_log('order.cancelled','order',$orderId,['company_id'=>(int)$company['id']]);
                $who=(string)$account['name'];
                notify_company_managers((int)$company['id'],fn(array $m)=>[t('mail.cancelled.subject',['id'=>$orderId]),t('mail.cancelled.body',['name'=>$who,'id'=>$orderId])]);
                flash('success',t('flash.order_cancelled',['id'=>$orderId]));
            }else flash('error',t('flash.order_cancel_failed'));
            redirect('/'.$slug.'/bestellungen');
        }

        if($action==='profile' && $section==='profil'){
            $name=trim((string)($_POST['name']??''));
            if($name===''||mb_strlen($name)>160){
                flash('error',t('flash.name_invalid'));
                redirect('/'.$slug.'/profil');
            }
            // 10.12: Sprache im Profil (nur angebotene Sprachen; sonst unverändert)
            $language=(string)($_POST['language']??'');
            if(!in_array($language,yw_company_langs($company),true))$language=yw_lang_valid($account['language']??null)?(string)$account['language']:null;
            $stmt=db()->prepare('UPDATE employee_accounts SET name=?,language=? WHERE id=? AND company_id=?');
            $stmt->execute([$name,$language,(int)$account['id'],(int)$company['id']]);
            if($language!==null){yw_lang_cookie_set($language);yw_set_lang($language);}
            audit_log('employee.profile_updated','employee',(int)$account['id'],['company_id'=>(int)$company['id']]);
            flash('success',t('flash.profile_saved'));
            redirect('/'.$slug.'/profil');
        }
    }

    render_head($company['shop_name'].' – WorkwearCenter',$company);
    echo company_portal_style($company);
    if(yw_is_preview($account))echo yw_preview_banner($company);
    company_portal_header($company,$account,$section);

    echo '<main class="cp-content cp-section-'.e($section).'" id="inhalt" tabindex="-1">';
    render_flash();

    if($section==='overview'){
        company_portal_overview($company,$account);
    }elseif($section==='produkte'){
        company_portal_products($company,$account);
    }elseif($section==='bestellungen'){
        company_portal_my_orders($company,$account);
    }elseif($section==='freigaben'){
        company_portal_approvals($company,$account);
    }elseif($section==='mitarbeiter'){
        company_portal_people($company,$account);
    }elseif($section==='team-order'){
        company_portal_team_order($company,$account);
    }elseif($section==='profil'){
        company_portal_profile($company,$account);
    }

    echo '</main>';
    company_portal_footer($company);
    echo '<script src="'.e(asset_url('assets/shop.js')).'" defer></script>';
    render_foot();
}

/** Sammelbestellung: Verantwortliche erfassen Mitarbeiteraufträge in einem Arbeitsgang. */
function company_portal_team_order(array $company,array $account): void {
    $companyId=(int)$company['id'];
    $q=db()->prepare("SELECT id,company_id,name,department,employee_number,equipment_profile_id FROM employee_accounts WHERE company_id=? AND active=1 AND registration_status='approved' AND (employment_start IS NULL OR employment_start<=CURDATE()) AND (employment_end IS NULL OR employment_end>=CURDATE()) ORDER BY name,id");
    $q->execute([$companyId]);$people=$q->fetchAll();
    $baseProducts=shop_company_products($companyId);$profileCatalogs=[];$accountCatalog=[];
    foreach($people as $person){
        $id=(int)$person['id'];$profileId=(int)($person['equipment_profile_id']??0);
        $catalogKey=$profileId>0?'profile-'.$profileId:'all';
        if(!array_key_exists($catalogKey,$profileCatalogs)){
            $rules=yw_account_profile_rules($person);
            $allowed=$rules===null?null:array_fill_keys(array_map('intval',array_keys($rules)),true);
            $products=[];
            foreach($baseProducts as $product){
                $productId=(int)$product['id'];
                if($allowed!==null&&!isset($allowed[$productId]))continue;
                $price=product_price_breakdown($product,$company);
                $products[]=['id'=>$productId,'name'=>company_product_display_name($product),'price'=>money((int)$price['gross']),'sizes'=>array_values(array_filter(json_decode((string)$product['sizes'],true)?:[],'is_string'))];
            }
            $profileCatalogs[$catalogKey]=$products;
        }
        $accountCatalog[(string)$id]=$catalogKey;
    }
    $catalog=['accounts'=>$accountCatalog,'profiles'=>$profileCatalogs];
    $catalogJson=json_encode($catalog,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
    echo '<section class="cp-team-page"><div class="cp-section-head"><div><span class="cp-team-eyebrow">'.e(t('nav.team_order')).'</span><h1>'.e(t('team.title')).'</h1><p>'.e(t('team.intro')).'</p><p class="cp-team-count">'.e(t('team.people_count',['n'=>count($people)])).'</p></div></div>';
    if(!$people){
        echo '<div class="cp-card cp-team-empty"><p>'.e(t('team.no_people')).'</p><a class="cp-btn" href="/'.e($company['slug']).'/mitarbeiter">'.e(t('team.go_people')).'</a></div></section>';
        return;
    }
    $employeeOptions='<option value="">'.e(t('team.choose_person')).'</option>';
    foreach($people as $person){
        $label=(string)$person['name'];
        $meta=trim((string)($person['department']??''));
        if($meta==='')$meta=trim((string)($person['employee_number']??''));
        if($meta!=='')$label.=' · '.$meta;
        $employeeOptions.='<option value="'.(int)$person['id'].'">'.e($label).'</option>';
    }
    $tokenField=yw_team_checkout_fields($company,$account);
    echo '<div class="cp-team-layout"><form method="post" class="cp-team-form" data-team-order data-catalog="'.e(base64_encode($catalogJson)).'" data-price-unit="'.e(t('team.price_each')).'" data-max-people="20" data-limit-message="'.e(t('team.limit_notice')).'" data-empty-message="'.e(t('err.no_items')).'" data-duplicate-message="'.e(t('team.choose_person')).'" data-size-message="'.e(t('team.choose_size')).'">'.csrf_field().'<input type="hidden" name="action" value="team_order">'.$tokenField;
    echo '<div class="cp-team-toolbar"><div><strong>'.e(t('team.people_count',['n'=>count($people)])).'</strong><span>'.e(t('team.limit_notice')).'</span></div><div class="cp-team-toolbar-actions"><button class="cp-btn cp-btn-light" type="button" data-team-add-all>'.e(t('team.add_all')).'</button><button class="cp-btn" type="button" data-team-add-person>'.e(t('team.add_person')).'</button></div></div>';
    echo '<div class="cp-team-people" data-team-people></div><p class="cp-team-feedback" data-team-feedback role="status" aria-live="polite"></p>';
    echo '<label class="cp-team-note">'.e(t('team.note')).'<small>'.e(t('team.note_hint')).'</small><textarea name="team_note" maxlength="350"></textarea></label>';
    echo '<div class="cp-team-submit"><div><strong>'.e(t('team.rules_title')).'</strong><p>'.e(t('team.rules_text')).'</p></div><button class="cp-btn" type="submit">'.e(t('team.submit')).'</button></div></form>';
    echo '<aside class="cp-team-aside"><div class="cp-team-aside-icon" aria-hidden="true">'.yw_shop_icon('users').'</div><h2>'.e(t('team.preview_title')).'</h2><ol><li><span>1</span>'.e(t('team.person')).'</li><li><span>2</span>'.e(t('team.item')).'</li><li><span>3</span>'.e(t('team.step_orders')).'</li></ol><div class="cp-team-policy"><strong>'.e(t('team.costs')).'</strong><ul><li>'.e(financing_label($company)).'</li><li>'.e(shipping_rule_label($company)).'</li><li>'.e(((int)($company['approval_required']??1)===1)?t('rules.internal_approval'):t('rules.no_approval')).'</li></ul></div><p>'.e(t('team.rules_text')).'</p></aside></div>';
    echo '<template data-team-person-template><section class="cp-card cp-team-person" data-team-person><div class="cp-team-person-head"><span class="cp-team-person-index" data-team-number>1</span><button type="button" class="cp-team-remove" data-team-remove>'.e(t('team.remove_person')).'</button></div><label class="cp-team-select">'.e(t('team.person')).'<select data-team-account>'.$employeeOptions.'</select></label><div class="cp-team-items" data-team-items></div><button type="button" class="cp-team-add-item" data-team-add-item>+ '.e(t('team.add_item')).'</button><template data-team-item-template><div class="cp-team-item" data-team-item><label>'.e(t('team.product')).'<select data-team-product><option value="">'.e(t('team.choose_product')).'</option></select></label><label>'.e(t('team.size')).'<select data-team-size disabled><option value="">'.e(t('team.choose_size')).'</option></select></label><label>'.e(t('team.quantity')).'<input type="number" min="1" max="10" value="1" inputmode="numeric" data-team-quantity></label><button type="button" class="cp-team-item-remove" data-team-remove-item aria-label="'.e(t('common.close')).'">×</button></div></template></section></template>';
    echo '</section>';
}


/** Positionsbezeichnung einer Bestellzeile inkl. Farbe aus dem Bestell-Schnappschuss. */
function yw_shop_item_label(array $item): string {
    $snapshot=json_decode((string)($item['decoration_snapshot_json']??''),true);
    $color=is_array($snapshot)?trim((string)($snapshot['color']??'')):'';
    return (string)$item['product_name'].($color!==''?' · '.$color:'').' · '.t('common.size_value',['size'=>(string)$item['size']]);
}

function company_portal_overview(array $company,array $account): void {
    $slug='/'.$company['slug'];
    $manager=account_is_manager($account);
    $approval=(int)($company['approval_required']??1)===1;

    echo '<div class="cp-welcome"><div><h1>'.e(yw_shop_greeting((string)$account['name'])).'</h1><p>'.e(t('overview.intro',['company'=>$company['legal_name']])).'</p>';
    echo '<p class="cp-rules">'.e(financing_label($company)).' · '.e(shipping_rule_label($company)).' · '.e($approval?t('rules.internal_approval'):t('rules.no_approval')).'</p></div>';
    echo yw_shop_budget_box($company,$account).'</div>';

    $equipmentProfile=yw_account_equipment_profile($account);
    echo yw_batch_shop_notice($company); // 10.11: Sammelbestellung
    $stmt=db()->prepare('SELECT * FROM orders WHERE company_id=? AND (employee_account_id=? OR (employee_account_id IS NULL AND invite_id=?)) ORDER BY id DESC LIMIT 1');
    $stmt->execute([(int)$company['id'],(int)$account['id'],(int)($account['invite_id']??0)]);
    $last=$stmt->fetch();

    echo '<div class="cp-dash">';
    if($manager){
        echo '<section class="cp-card cp-team-shortcut"><div><span>'.e(t('nav.team_order')).'</span><h2>'.e(t('team.shortcut')).'</h2><p>'.e(t('team.shortcut_text')).'</p></div><a class="cp-btn" href="'.e($slug).'/sammelbestellung">'.e(t('team.add_person')).'</a></section>';
    }
    if(!$last){
        $hasStarter=false;
        foreach((yw_account_profile_rules($account)??[]) as $rule)if((int)$rule['starter_qty']>0){$hasStarter=true;break;}
        echo '<section class="cp-card cp-first-order"><div><h2>'.e(t('overview.first_title')).'</h2>';
        echo $hasStarter
            ? '<p>'.e($equipmentProfile?t('overview.starter_profile',['profile'=>$equipmentProfile['name']]):t('overview.starter')).'</p></div><a class="cp-btn" href="'.e($slug).'/produkte?starter=1">'.e(t('overview.starter_button')).'</a>'
            : '<p>'.e(t('overview.first_text')).'</p></div><a class="cp-btn" href="'.e($slug).'/produkte">'.e(t('overview.to_collection')).'</a>';
        echo '</section>';
    }else{
        $items=order_items_grouped([(int)$last['id']])[(int)$last['id']]??[];
        $status=(string)$last['status'];
        $gross=(int)($last['gross_total_cents']??0); if($gross<=0)$gross=(int)$last['total_cents'];
        echo '<section class="cp-card cp-last-order"><div class="cp-card-head"><div><h2>'.e(t('overview.last_title')).'</h2><p>#'.(int)$last['id'].' · '.e(yw_date((string)$last['created_at'])).' · '.money($gross).'</p></div><span class="cp-status '.e($status).'">'.e(company_portal_order_status($status)).'</span></div>';
        echo '<ul class="cp-mini-items">';
        foreach(array_slice($items,0,3) as $item)echo '<li><span>'.(int)$item['quantity'].'×</span> '.e(yw_shop_item_label($item)).'</li>';
        if(count($items)>3)echo '<li class="muted">'.e(tn('overview.more_items',count($items)-3)).'</li>';
        echo '</ul>';
        $progress=company_portal_progress($last);
        if($progress!=='')echo $progress;
        elseif($status==='pending')echo '<p class="cp-state-note">'.e(t('overview.pending_note')).'</p>';
        echo '<div class="cp-card-actions"><a class="cp-btn" href="'.e($slug).'/produkte?nachbestellen='.(int)$last['id'].'">'.yw_shop_icon('repeat').e(t('orders.reorder')).'</a><a class="cp-btn cp-btn-light" href="'.e($slug).'/bestellungen">'.e(t('overview.all_orders')).'</a></div></section>';
    }

    if($manager){
        // 10.15: wie Menü-Zähler und „Freigaben“ – eigene Bestellungen nicht, solange eine andere Person freigeben kann; nur die ersten 4 laden
        $others=yw_other_manager_count((int)$company['id'],(int)$account['id'])>0;
        $q=db()->prepare("SELECT o.id,o.created_at,o.gross_total_cents,o.total_cents,COALESCE(ea.name,i.employee_name,'') employee_name FROM orders o LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id LEFT JOIN invites i ON i.id=o.invite_id WHERE o.company_id=? AND o.status='pending'".($others?' AND NOT (o.employee_account_id<=>?)':'')." ORDER BY o.id ASC LIMIT 4");
        $q->execute($others?[(int)$company['id'],(int)$account['id']]:[(int)$company['id']]);$pending=$q->fetchAll();$pendingCount=yw_pending_decision_count((int)$company['id'],(int)$account['id']);
        echo '<section class="cp-card cp-approvals-card"><div class="cp-card-head"><div><h2>'.e(t('overview.approvals_title')).'</h2><p>'.e($pendingCount?tn('overview.approvals_waiting',$pendingCount):t('overview.approvals_none')).'</p></div><span class="cp-count">'.$pendingCount.'</span></div>';
        if($pending){
            echo '<ul class="cp-mini-items">';
            foreach(array_slice($pending,0,4) as $o){$g=(int)($o['gross_total_cents']??0);if($g<=0)$g=(int)$o['total_cents'];echo '<li><span>#'.(int)$o['id'].'</span> '.e($o['employee_name']!==''?$o['employee_name']:t('common.unknown')).' · '.e(yw_date((string)$o['created_at'],'short')).'<b>'.money($g).'</b></li>';}
            echo '</ul><div class="cp-card-actions"><a class="cp-btn" href="'.e($slug).'/freigaben">'.e(t('overview.review_now')).'</a></div>';
        }else echo '<div class="cp-card-actions"><a class="cp-btn cp-btn-light" href="'.e($slug).'/freigaben">'.e(t('overview.view_orders')).'</a></div>';
        echo '</section>';
        // Offene Registrierungen direkt auf der Startseite entscheiden
        $q=db()->prepare("SELECT id,name,email,created_at FROM employee_accounts WHERE company_id=? AND registration_status='pending' AND role='employee' ORDER BY id ASC");
        $q->execute([(int)$company['id']]);$registrations=$q->fetchAll();
        if($registrations){
            echo '<section class="cp-card cp-registrations-card"><div class="cp-card-head"><div><h2>'.e(t('overview.registrations_title')).'</h2><p>'.e(tn('overview.registrations_waiting',count($registrations))).'</p></div><span class="cp-count">'.count($registrations).'</span></div><ul class="cp-registration-list">';
            foreach(array_slice($registrations,0,6) as $r){
                $form=fn(string $decision,string $class,string $label)=>'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="registration_decision"><input type="hidden" name="account_id" value="'.(int)$r['id'].'"><input type="hidden" name="decision" value="'.$decision.'"><button class="cp-btn '.$class.'" type="submit">'.e($label).'</button></form>';
                echo '<li><div><strong>'.e((string)$r['name']).'</strong><span>'.e((string)$r['email']).' · '.e(yw_date((string)$r['created_at'],'short')).'</span></div><div class="cp-action-row">'.$form('rejected','reject',t('approvals.reject')).$form('approved','approve',t('people.approve_access')).'</div></li>';
            }
            echo '</ul>'.(count($registrations)>6?'<div class="cp-card-actions"><a class="cp-btn cp-btn-light" href="'.e($slug).'/mitarbeiter">'.e(t('overview.registrations_all')).'</a></div>':'').'</section>';
        }
    }elseif($last){
        echo '<section class="cp-card cp-shop-card"><h2>'.e(t('overview.collection_title')).'</h2><p>'.e(t('overview.collection_text')).'</p><div class="cp-card-actions"><a class="cp-btn" href="'.e($slug).'/produkte">'.e(t('overview.to_collection')).' '.yw_shop_icon('arrow').'</a></div></section>';
    }
    echo '</div>';
    echo company_portal_collection_preview($company,$account);

    if($equipmentProfile){
        echo '<section class="cp-card"><h2>'.e(t('overview.target_title',['profile'=>$equipmentProfile['name']])).'</h2><p>'.e($equipmentProfile['description']?:t('overview.target_text')).'</p>';
        $products=shop_company_products((int)$company['id'],$account);
        $targets=array_filter($products,fn($p)=>$p['profile_target_qty']!==null);
        if($targets){echo '<div class="cp-rule-list">';foreach($targets as $product){$used=yw_quantity_used($company,$account,(int)$product['id']);$target=(int)$product['profile_target_qty'];echo '<div class="cp-rule"><span>'.e(company_product_display_name($product)).(trim((string)$product['color'])!==''?' · '.e($product['color']):'').' · '.e(t('overview.target_used',['n'=>$used])).'</span><strong>'.e(t('overview.target_open',['open'=>max(0,$target-$used),'target'=>$target])).'</strong></div>';}echo '</div>';}
        else echo '<p class="muted">'.e(t('overview.target_none')).'</p>';
        echo '</section>';
    }
}

/** 10.16: Startseite zeigt die ersten Artikel der Kollektion mit Bild (Klick öffnet den Artikel). */
function company_portal_collection_preview(array $company,array $account,int $max=4): string {
    $groups=yw_shop_group_products(shop_company_products((int)$company['id'],$account));
    if(!$groups)return '';
    $motifs=yw_company_motifs_by_id((int)$company['id']);
    $slug='/'.$company['slug'];
    $html='<section class="cp-collection"><div class="cp-collection-head"><h2>'.e(t('overview.collection_title')).'</h2><a href="'.e($slug).'/produkte">'.e(t('overview.to_collection')).' '.yw_shop_icon('arrow').'</a></div><div class="cp-collection-grid">';
    foreach(array_slice($groups,0,$max) as $g){
        $p=$g[0];$name=company_product_display_name($p);
        $views=yw_mockup_views($p,$motifs);
        $html.='<a class="cp-collection-item" href="'.e($slug.'/produkte?artikel='.(int)$p['id']).'"><span class="cp-collection-visual">'.($views?yw_mockup_html([$views[0]],$name,false,'sp-card-mockup'):'<span class="sp-card-placeholder">'.yw_shop_icon('shirt').'</span>').'</span>';
        $html.='<strong>'.e($name).'</strong>'.yw_shop_price_html(array_map(fn($v)=>product_price_breakdown($v,$company),$g)).'</a>';
    }
    return $html.'</div></section>';
}

function company_portal_products(array $company,array $account): void {
    $products=shop_company_products((int)$company['id'],$account);
    $slug=(string)$company['slug'];

    echo '<div class="cp-section-head cp-products-head"><div><h1>'.e(t('products.title')).'</h1><p>'.e(t('products.intro')).'</p></div></div>';

    echo yw_batch_shop_notice($company); // 10.11: Sammelbestellung

    if(!$products){
        echo '<div class="empty">'.e(t('products.none')).'</div>';
        return;
    }

    // Zuletzt bestellte Größe je Artikel vorbelegen – spart Zeit und Fehlbestellungen.
    $lastSizes=[];
    $q=db()->prepare("SELECT i.product_id,i.size FROM order_items i JOIN orders o ON o.id=i.order_id WHERE o.company_id=? AND o.employee_account_id=? AND o.status IN ('pending','approved') ORDER BY o.id ASC,i.id ASC");
    $q->execute([(int)$company['id'],(int)$account['id']]);
    foreach($q as $row)$lastSizes[(int)$row['product_id']]=(string)$row['size'];

    // „Nochmal bestellen“ bzw. Starterpaket: Auswahl vorbelegen (Prüfung erfolgt wie immer serverseitig).
    $preset=[];$presetNote='';
    $reorderId=(int)($_GET['nachbestellen']??0);
    if($reorderId>0){
        $q=db()->prepare("SELECT i.product_id,i.size,i.quantity FROM order_items i JOIN orders o ON o.id=i.order_id WHERE o.id=? AND o.company_id=? AND o.employee_account_id=?");
        $q->execute([$reorderId,(int)$company['id'],(int)$account['id']]);
        foreach($q as $row)$preset[(int)$row['product_id']]=['qty'=>(int)$row['quantity'],'size'=>(string)$row['size']];
        if($preset)$presetNote=t('products.reorder_note',['id'=>$reorderId]);
    }elseif(isset($_GET['starter'])){
        foreach($products as $p)if((int)($p['profile_starter_qty']??0)>0)$preset[(int)$p['id']]=['qty'=>(int)$p['profile_starter_qty'],'size'=>$lastSizes[(int)$p['id']]??''];
        if($preset)$presetNote=t('products.starter_note');
    }
    $available=array_flip(array_map(fn($p)=>(int)$p['id'],$products));
    if($preset && !array_intersect_key($preset,$available)){$preset=[];$presetNote=t('products.reorder_unavailable');}
    if($presetNote!=='')echo '<div class="cp-manager-alert" role="status">'.e($presetNote).' <a href="#auswahl">'.e(t('cart.to_selection')).'</a></div>';

    foreach($products as &$p){
        $profileRule=array_key_exists('profile_target_qty',$p)?['yearly_target_qty'=>$p['profile_target_qty']]:null;
        $limit=yw_effective_product_limit($p,$profileRule);
        if($limit!==null)$p['profile_remaining_qty']=max(0,$limit-yw_quantity_used($company,$account,(int)$p['id']));
        $pid=(int)$p['id'];
        $p['preselect_size']=($preset[$pid]['size']??'')!==''?$preset[$pid]['size']:($lastSizes[$pid]??'');
        $p['preselect_qty']=$preset[$pid]['qty']??0;
    }
    unset($p);
    $groups=yw_shop_group_products($products);
    $motifs=yw_company_motifs_by_id((int)$company['id']);
    $openId=(int)($_GET['artikel']??0);

    // 10.16: Kollektion links, Auswahl mit Budget und Summen rechts mitlaufend (schmale Bildschirme: darunter)
    echo '<form method="post" data-order-form>'.csrf_field().'<input type="hidden" name="action" value="order">';
    echo '<div class="sp-layout"><div class="sp-main"><div class="sp-tools">';
    company_shop_categories($groups);
    echo '<a class="sp-cart-btn" href="#auswahl">'.yw_shop_icon('bag').'<span>'.e(t('cart.selection')).'</span><span class="sp-cart-btn-count" data-selection-count>0</span></a></div>';
    echo '<div class="sp-grid">';
    foreach($groups as $g)echo company_product_card($g,$company,$motifs,$slug);
    echo '</div></div>';

    $approval=(int)($company['approval_required']??1)===1;
    echo '<section class="sp-cart shop-order-panel" id="auswahl" tabindex="-1" aria-labelledby="auswahl-title" data-shipping-enabled="'.(shipping_enabled($company)?'1':'0').'" data-shipping-net="'.(int)($company['shipping_net_cents']??0).'" data-shipping-vat="'.e((string)($company['shipping_vat_rate']??19)).'" data-free-shipping="'.(int)($company['free_shipping_gross_cents']??0).'" data-shipping-payer="'.e((string)($company['shipping_payer']??'company')).'">';
    echo '<div class="sp-cart-main"><div class="sp-cart-head"><h2 id="auswahl-title">'.e(t('cart.title')).'</h2><span class="shop-selection-count">'.t('cart.count_selected',['n'=>'<strong data-selection-count>0</strong>']).'</span></div>';
    echo yw_shop_budget_box($company,$account);
    echo '<p class="sp-cart-empty" data-cart-empty>'.e(t('cart.empty')).'</p><ul class="sp-cart-lines" data-cart-lines></ul>';
    echo '<label class="sp-note">'.e(t('cart.note')).' <small>'.e(t('common.optional')).'</small><textarea name="note" maxlength="500" placeholder="'.e(t('cart.note_placeholder')).'"></textarea></label>';
    echo yw_checkout_fields($company).'</div>';
    echo '<div class="shop-submit-box">'
        .'<div class="shop-checkout-lines">'
        .'<div><span>'.e(t('cart.net')).'</span><strong data-summary-net>'.money(0).'</strong></div>'
        .'<div><span>'.e(t('cart.gross')).'</span><strong data-summary-gross>'.money(0).'</strong></div>'
        .'<div><span>'.e(t('cart.shipping')).'</span><strong data-summary-shipping>'.money(0).'</strong></div>'
        .'<div class="total"><span>'.e(t('cart.total')).'</span><strong data-summary-total>'.money(0).'</strong></div>'
        .'<div class="employee"><span>'.e(t('price.your_share')).'</span><strong data-summary-employee>'.money(0).'</strong></div>'
        .'<div><span>'.e(t('price.company_pays_label')).'</span><strong data-summary-company>'.money(0).'</strong></div>'
        .'</div>'
        .'<p class="sp-rules">'.e(financing_label($company)).' · '.e(shipping_rule_label($company)).'</p>'
        .'<p class="shop-budget-warning" data-budget-warning role="alert" hidden>'.e(t('cart.over_budget')).'</p>'
        .'<button class="shop-submit" type="submit" disabled>'.e($approval?t('cart.submit_approval'):t('cart.submit')).'</button>'
        .'<p class="sp-rules">'.e($approval?t('cart.approval_note'):t('cart.direct_note')).'</p></div>';
    echo '</section></div>';
    foreach($groups as $g){
        $ids=array_map(fn($v)=>(int)$v['id'],$g);
        $open=$openId>0 && in_array($openId,$ids,true);
        echo company_product_detail($g,$company,$motifs,$open?$openId:0,$open);
    }
    echo '<div class="shop-mobile-cart" data-mobile-cart aria-hidden="true"><div><strong>'.t('cart.mobile_count',['n'=>'<span data-selection-count>0</span>']).'</strong><small>'.(company_employee_share_percent($company)>0||(string)($company['shipping_payer']??'company')==='employee'?e(t('price.your_share')).': <span data-summary-employee>'.money(0).'</span>':e(t('cart.mobile_company')).' · <span data-summary-total>'.money(0).'</span>').'</small></div><button type="button" data-scroll-checkout tabindex="-1">'.e(t('cart.to_selection')).'</button></div>';
    echo '<div class="sp-toast" data-shop-toast role="status" aria-live="polite"></div>';
    echo '</form>';
}

/** Fortschrittsanzeige einer freigegebenen Bestellung (für Mitarbeitende verständlich). */
function company_portal_progress(array $o): string {
    if((string)$o['status']!=='approved')return '';
    $f=(string)($o['fulfillment_status']??'new');
    $stage=match(true){
        in_array($f,['completed','picked_up','shipped'],true)=>3,
        $f==='ready'=>2,
        in_array($f,['production','quality_check','goods_received','goods_ordered','processing','accepted'],true)=>1,
        default=>0,
    };
    $pickup=str_contains((string)($o['delivery_snapshot_json']??''),'"mode":"pickup"');
    $labels=[t('progress.approved'),t('progress.production'),t('progress.ready'),$pickup?t('progress.picked_up'):t('progress.shipped')];
    $html='<div class="cp-progress" aria-label="'.e(t('progress.aria')).'">';
    foreach($labels as $i=>$label)$html.='<div class="cp-progress-step'.($i<=$stage?' done':'').($i===$stage?' current':'').'"><span></span><small>'.e($label).'</small></div>';
    $html.='</div>';
    $link=yw_tracking_link($o['tracking_carrier']??null,$o['tracking_number']??null,$o['tracking_url']??null);
    if(!empty($o['tracking_number']))$html.='<div class="cp-tracking"><div><small>'.e(t('progress.tracking_number')).(!empty($o['tracking_carrier'])?' · '.e($o['tracking_carrier']):'').'</small><strong>'.e($o['tracking_number']).'</strong></div>'.($link?'<a class="cp-btn approve" href="'.e($link).'" target="_blank" rel="noopener noreferrer">'.e(t('mail.track')).'</a>':'').'</div>';
    return $html;
}

function company_portal_my_orders(array $company,array $account): void {
    $page=max(1,(int)($_GET['seite']??1));$perPage=25;
    $countStmt=db()->prepare('SELECT COUNT(*) FROM orders WHERE company_id=? AND (employee_account_id=? OR (employee_account_id IS NULL AND invite_id=?))');$countStmt->execute([(int)$company['id'],(int)$account['id'],(int)($account['invite_id']??0)]);$totalOrders=(int)$countStmt->fetchColumn();
    $maxPage=max(1,(int)ceil($totalOrders/$perPage));$page=min($page,$maxPage);$offset=($page-1)*$perPage;
    $stmt=db()->prepare('SELECT * FROM orders WHERE company_id=? AND (employee_account_id=? OR (employee_account_id IS NULL AND invite_id=?)) ORDER BY id DESC LIMIT '.$perPage.' OFFSET '.$offset);
    $stmt->execute([(int)$company['id'],(int)$account['id'],(int)($account['invite_id']??0)]);
    $orders=$stmt->fetchAll();
    $itemsByOrder=order_items_grouped(array_column($orders,'id'));

    echo '<div class="cp-section-head"><div><h1>'.e(t('nav.orders')).'</h1><p>'.e(t('orders.intro')).'</p></div></div>';

    if(!$orders){
        echo '<div class="empty">'.e(t('orders.none')).'</div>';
        return;
    }

    echo '<div class="cp-order-list">';
    foreach($orders as $o){
        $items=$itemsByOrder[(int)$o['id']]??[];
        $status=(string)$o['status'];
        $gross=(int)($o['gross_total_cents']??0); if($gross<=0)$gross=(int)$o['total_cents'];
        $employee=(int)($o['employee_total_cents']??0);
        $companyShare=(int)($o['company_total_cents']??0);

        echo '<article class="cp-order"><div class="cp-order-head"><div class="cp-order-title"><strong>'.e(t('orders.order_no',['id'=>(int)$o['id']])).'</strong><div class="cp-order-meta">'.e(yw_date($o['created_at'],'datetime')).' · '.e(company_portal_fulfillment_status((string)($o['fulfillment_status']??'new'))).'</div></div><div><span class="cp-status '.e($status).'">'.e(company_portal_order_status($status)).'</span></div></div>';
        echo '<div class="cp-order-items">';
        foreach($items as $item){
            echo '<div class="cp-order-item"><span>'.(int)$item['quantity'].'× '.e(yw_shop_item_label($item)).'</span><strong>'.money(yw_order_item_amounts($item)['gross']).'</strong></div>';
        }
        if((int)($o['shipping_gross_cents']??0)>0) echo '<div class="cp-order-item"><span>'.e(t('mail.sum.shipping')).'</span><strong>'.money((int)$o['shipping_gross_cents']).'</strong></div>';
        echo '</div>';
        echo company_portal_progress($o);
        echo yw_exchange_order_panel($company,$account,$o,$items); // 10.11: Umtausch & Rückgabe
        echo '<div class="cp-order-summary"><div class="cp-order-sum"><span>'.e(t('cart.total')).'</span><strong>'.money($gross).'</strong></div><div class="cp-order-sum"><span>'.e(t('price.your_share')).'</span><strong>'.money($employee).'</strong></div><div class="cp-order-sum"><span>'.e(t('price.company_pays_label')).'</span><strong>'.money($companyShare).'</strong></div></div>';
        if(!empty($o['decision_note']))echo '<div class="cp-manager-alert"><strong>'.e(t('orders.decision_note')).'</strong><br>'.nl2br(e((string)$o['decision_note'])).'</div>';
        $own=(int)($o['employee_account_id']??0)===(int)$account['id'];
        if($own){
            echo '<div class="cp-action-row">';
            if($status==='pending')echo '<form method="post" data-confirm="'.e(t('orders.cancel_confirm',['id'=>(int)$o['id']])).'">'.csrf_field().'<input type="hidden" name="action" value="cancel_order"><input type="hidden" name="order_id" value="'.(int)$o['id'].'"><button class="cp-btn reject" type="submit">'.e(t('orders.cancel')).'</button></form>';
            echo '<a class="cp-btn cp-btn-light" href="/'.e($company['slug']).'/produkte?nachbestellen='.(int)$o['id'].'">'.e(t('orders.reorder')).'</a>';
            echo '</div>';
        }
        echo '</article>';
    }
    echo '</div>';
    if($maxPage>1){echo '<nav class="cp-action-row" aria-label="'.e(t('pager.orders_aria')).'">';if($page>1)echo '<a class="btn" href="/'.e($company['slug']).'/bestellungen?seite='.($page-1).'">← '.e(t('pager.newer')).'</a>';echo '<span>'.e(t('pager.page',['page'=>$page,'pages'=>$maxPage])).'</span>';if($page<$maxPage)echo '<a class="btn" href="/'.e($company['slug']).'/bestellungen?seite='.($page+1).'">'.e(t('pager.older')).' →</a>';echo '</nav>';}
}

function company_portal_approvals(array $company,array $account): void {
    $page=max(1,(int)($_GET['seite']??1));$perPage=50;
    $countStmt=db()->prepare('SELECT COUNT(*) FROM orders WHERE company_id=?');$countStmt->execute([(int)$company['id']]);$totalOrders=(int)$countStmt->fetchColumn();
    $maxPage=max(1,(int)ceil($totalOrders/$perPage));$page=min($page,$maxPage);$offset=($page-1)*$perPage;
    $stmt=db()->prepare("SELECT o.*,COALESCE(ea.name,i.employee_name,'') employee_name
        FROM orders o
        LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id
        LEFT JOIN invites i ON i.id=o.invite_id
        WHERE o.company_id=?
        ORDER BY FIELD(o.status,'pending','approved','rejected','cancelled'),o.id DESC LIMIT ".$perPage.' OFFSET '.$offset);
    $stmt->execute([(int)$company['id']]);
    $orders=$stmt->fetchAll();
    $itemsByOrder=order_items_grouped(array_column($orders,'id'));

    $others=yw_other_manager_count((int)$company['id'],(int)$account['id']); // 10.15: Vier-Augen-Prinzip wie in yw_order_decide()
    // 10.15: eigene Bestellungen warten nicht auf „deine Entscheidung“, solange eine andere Person freigeben kann
    $pending=yw_pending_decision_count((int)$company['id'],(int)$account['id']);

    echo '<div class="cp-section-head"><div><h1>'.e(t('nav.approvals')).'</h1><p>'.e(t('approvals.intro')).'</p></div></div>';
    if($pending>0) echo '<div class="cp-manager-alert">'.tn('approvals.pending_alert',$pending,['count'=>'<strong>'.$pending.'</strong>']).'</div>';
    echo yw_exchange_manager_panel($company,$account); // 10.11: Anträge auf Umtausch & Rückgabe

    if(!$orders){
        echo '<div class="empty">'.e(t('approvals.none')).'</div>';
        return;
    }

    echo '<div class="cp-order-list">';
    foreach($orders as $o){
        $items=$itemsByOrder[(int)$o['id']]??[];
        $status=(string)$o['status'];
        $gross=(int)($o['gross_total_cents']??0); if($gross<=0)$gross=(int)$o['total_cents'];
        $employee=(int)($o['employee_total_cents']??0);
        $companyShare=(int)($o['company_total_cents']??0);

        echo '<article class="cp-order"><div class="cp-order-head"><div class="cp-order-title"><strong>'.e(t('orders.order_no',['id'=>(int)$o['id']])).' · '.e($o['employee_name']!==''?$o['employee_name']:t('common.unknown')).'</strong><div class="cp-order-meta">'.e(yw_date($o['created_at'],'datetime')).' · '.e(company_portal_fulfillment_status((string)($o['fulfillment_status']??'new'))).'</div></div><div><span class="cp-status '.e($status).'">'.e(company_portal_order_status($status)).'</span></div></div>';
        echo '<div class="cp-order-items">';
        foreach($items as $item){
            echo '<div class="cp-order-item"><span>'.(int)$item['quantity'].'× '.e(yw_shop_item_label($item)).'</span><strong>'.money(yw_order_item_amounts($item)['gross']).'</strong></div>';
        }
        if((int)($o['shipping_gross_cents']??0)>0) echo '<div class="cp-order-item"><span>'.e(t('mail.sum.shipping')).'</span><strong>'.money((int)$o['shipping_gross_cents']).'</strong></div>';
        echo '</div>';
        echo company_portal_progress($o);
        echo '<div class="cp-order-summary"><div class="cp-order-sum"><span>'.e(t('cart.total')).'</span><strong>'.money($gross).'</strong></div><div class="cp-order-sum"><span>'.e(t('approvals.employee')).'</span><strong>'.money($employee).'</strong></div><div class="cp-order-sum"><span>'.e(t('approvals.company')).'</span><strong>'.money($companyShare).'</strong></div></div>';
        if($status==='pending'){
            $own=(int)($o['employee_account_id']??0)===(int)$account['id'];
            if($own)echo '<div class="cp-manager-alert">'.e($others>0?t('decide.own_order'):t('approvals.own_order_single')).'</div>';
            echo '<div class="cp-action-row"><form method="post" class="cp-decision-form">'.csrf_field().'<input type="hidden" name="action" value="decision"><input type="hidden" name="order_id" value="'.(int)$o['id'].'"><label>'.e(t('approvals.note')).' <small>'.e(t('approvals.note_required')).'</small><textarea name="decision_note" maxlength="1000" placeholder="'.e(t('approvals.note_placeholder')).'"></textarea></label><button class="cp-btn reject" type="submit" name="decision" value="rejected">'.e(t('approvals.reject')).'</button>'.($own&&$others>0?'':'<button class="cp-btn approve" type="submit" name="decision" value="approved">'.e(t('approvals.approve')).'</button>').'</form></div>';
        }
        elseif(!empty($o['decision_note']))echo '<div class="cp-manager-alert"><strong>'.e(t('orders.decision_note')).'</strong><br>'.nl2br(e((string)$o['decision_note'])).'</div>';
        echo '</article>';
    }
    echo '</div>';
    if($maxPage>1){echo '<nav class="cp-action-row" aria-label="'.e(t('pager.company_aria')).'">';if($page>1)echo '<a class="btn" href="/'.e($company['slug']).'/freigaben?seite='.($page-1).'">← '.e(t('pager.newer')).'</a>';echo '<span>'.e(t('pager.page',['page'=>$page,'pages'=>$maxPage])).'</span>';if($page<$maxPage)echo '<a class="btn" href="/'.e($company['slug']).'/freigaben?seite='.($page+1).'">'.e(t('pager.older')).' →</a>';echo '</nav>';}
}

function legacy_company_portal_people(array $company,array $account): void {
    $stmt=db()->prepare('SELECT * FROM employee_accounts WHERE company_id=? ORDER BY role DESC,active DESC,name ASC');
    $stmt->execute([(int)$company['id']]);
    $people=$stmt->fetchAll();

    echo '<div class="cp-section-head"><div><h1>Mitarbeiter</h1><p>Registrierte Benutzer dieses Firmenshops.</p></div><strong>'.count($people).' registriert</strong></div>';
    echo '<section class="cp-card"><div class="cp-people">';
    foreach($people as $person){
        echo '<div class="cp-person"><div><strong>'.e($person['name']).'</strong><small>'.e($person['email']);
        if(!empty($person['department'])) echo ' · '.e($person['department']);
        if(!empty($person['cost_center'])) echo ' · Kostenstelle '.e($person['cost_center']);
        echo '</small></div><span class="cp-person-role">'.(((string)($person['role']??'employee'))==='manager'?'Verantwortlicher':'Mitarbeiter').(((int)$person['active']===1)?'':' · inaktiv').'</span></div>';
    }
    echo '</div></section>';
}

function company_portal_profile(array $company,array $account): void {
    echo '<div class="cp-section-head"><div><h1>'.e(t('profile.title')).'</h1><p>'.e(t('profile.intro')).'</p></div></div>';
    echo '<section class="cp-card"><form method="post">'.csrf_field().'<input type="hidden" name="action" value="profile"><div class="cp-profile-grid">';
    echo '<label>'.e(t('common.name')).'<input name="name" maxlength="160" value="'.e($account['name']).'" required></label>';
    echo '<label>'.e(t('common.email')).'<input value="'.e($account['email']).'" disabled></label>';
    echo '<label>'.e(t('profile.department')).'<input disabled value="'.e((string)($account['department']??'')).'"></label>';
    echo '<label>'.e(t('profile.cost_center')).'<input disabled value="'.e((string)($account['cost_center']??'')).'"></label>';
    // 10.12: Sprache für Shop und E-Mails
    $langs=yw_company_langs($company);
    if(count($langs)>1){
        echo '<label>'.e(t('profile.language')).' <small>'.e(t('profile.language_hint')).'</small><select name="language">';
        foreach($langs as $code)echo '<option value="'.e($code).'" lang="'.e($code).'"'.($code===yw_lang()?' selected':'').'>'.e(YW_LANGS[$code]).'</option>';
        echo '</select></label>';
    }
    echo '</div><button class="cp-save" type="submit">'.e(t('profile.save')).'</button></form></section>';
}
