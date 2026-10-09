<?php
declare(strict_types=1);

function render_company_product_customization_form(array $company,array $product): void {
    $decorations=company_product_decorations($product);
    $rows=min(20,max(5,count($decorations)));
    $positionOptions=decoration_position_options();
    $methodOptions=decoration_method_options();
    // Inaktive, bereits verknüpfte Motive bleiben auswählbar, damit eine
    // andere Produktänderung die historische Zuordnung nicht still entfernt.
    $motifs=yw_company_decoration_motifs((int)$company['id'],true);

    $front=company_product_front_image($product);
    $customFront=yw_local_image_path((string)($product['preview_front_path']??''),['company-products']);
    $back=company_product_back_image($product);
    $baseImage=yw_local_image_path((string)($product['image_path']??''),['products'],true);
    $displayName=company_product_display_name($product);
    $override=$product['price_override_cents']===null ? '' : number_format((int)$product['price_override_cents']/100,2,',','');

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

    echo '<div class="collection-product-body"><form method="post" enctype="multipart/form-data" class="form-stack">'.csrf_field();
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
    echo '</form></div></details>';
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
    if(!move_uploaded_file($tmp,$dir.'/'.$name)){
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
    return [
        'chest_left'=>'Brust links',
        'chest_right'=>'Brust rechts',
        'back_top'=>'Rücken oben',
        'back_large'=>'Rücken groß',
        'sleeve_left'=>'Ärmel links',
        'sleeve_right'=>'Ärmel rechts',
        'neck'=>'Nacken',
        'other'=>'Sonstiges',
    ];
}

function decoration_method_options(): array {
    return [
        'print'=>'Druck',
        'dtf'=>'DTF',
        'flex'=>'Flex',
        'embroidery'=>'Stick',
        'other'=>'Sonstiges',
    ];
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
        return rtrim(rtrim(number_format(company_employee_share_percent($company),2,',','.'),'0'),',').' % Mitarbeiter-Eigenanteil';
    }
    return 'Firma übernimmt 100 %';
}


function shipping_enabled(array $company): bool {
    if(($company['delivery_mode']??'company')==='pickup') return false;
    return (int)($company['shipping_enabled']??0)===1;
}

function shipping_breakdown(array $company,int $itemsGrossCents): array {
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
    $gross=gross_from_net($net,$vat);

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
    if(!shipping_enabled($company)) return 'Keine Versandkosten';
    $gross=gross_from_net((int)($company['shipping_net_cents']??0),(float)($company['shipping_vat_rate']??19.0));
    $text=money($gross).' brutto Versand';
    $freeFrom=(int)($company['free_shipping_gross_cents']??0);
    if($freeFrom>0) $text.=' · kostenfrei ab '.money($freeFrom).' Warenwert brutto';
    $text.=' · trägt '.(((string)($company['shipping_payer']??'company'))==='employee'?'Mitarbeiter':'Firma');
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
    return '<link rel="stylesheet" href="/assets/shop.css?v='.e(app_version()).'"><style>:root{'.e($vars).'}</style>';
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
    return ['overview'=>'Übersicht','produkte'=>'Produkte','bestellungen'=>'Meine Bestellungen','freigaben'=>'Freigaben','mitarbeiter'=>'Mitarbeiter','profil'=>'Mein Profil'][$section]??'Firmenshop';
}

function company_portal_footer(array $company): void {
    echo '<footer class="cp-foot"><span>'.e($company['legal_name']).' · Mitarbeiter-Shop</span><span>WorkwearCenter · <a href="/impressum">Impressum</a> · <a href="/datenschutz">Datenschutz</a></span></footer>';
    echo '</div></div>';
}

/** Budgetdaten des Kontos für Anzeige (keine Berechnungslogik, nur vorhandene Funktionen). */
function yw_shop_budget_info(array $company,array $account): array {
    $remaining=employee_budget_remaining($company,$account);
    $limit=$remaining===null?null:(int)yw_budget_limit($company,$account);
    [$start,$end]=yw_period((int)$company['budget_reset_month']);
    $percent=($limit!==null&&$limit>0)?(int)round(max(0,min(100,$remaining/$limit*100))):0;
    return ['remaining'=>$remaining,'limit'=>$limit,'percent'=>$percent,'to'=>(new DateTimeImmutable($end))->modify('-1 day')->format('d.m.Y')];
}

/** Budget-Karte wie im Mustershop. */
function yw_shop_budget_box(array $company,array $account): string {
    $b=yw_shop_budget_info($company,$account);
    if($b['remaining']===null){
        return '<div class="cp-budgetbox"><div class="cp-budgetbox-row"><span>Jahresbudget</span><strong>Ohne Limit</strong></div><small>'.e(financing_label($company)).'</small></div>';
    }
    return '<div class="cp-budgetbox" data-budget-remaining="'.(int)$b['remaining'].'"><div class="cp-budgetbox-row"><span>Verfügbares Jahresbudget</span><strong>'.money((int)$b['remaining']).'</strong></div>'
        .'<div class="cp-budgetbox-bar" role="progressbar" aria-label="Verbleibendes Budget" aria-valuemin="0" aria-valuemax="100" aria-valuenow="'.$b['percent'].'"><span style="width:'.$b['percent'].'%"></span></div>'
        .'<small>von '.money((int)$b['limit']).' · gilt bis '.e($b['to']).'</small></div>';
}

function yw_shop_greeting(string $name): string {
    $hour=(int)date('G');
    $first=trim((string)(preg_split('/\s+/u',trim($name))[0]??''));
    $hello=$hour<11?'Guten Morgen':($hour<18?'Hallo':'Guten Abend');
    return $hello.($first!==''?', '.$first:'').'.';
}

/** Seitenleiste + Kopfzeile (Aufbau wie im Mustershop). */
function company_portal_header(array $company,array $account,string $section): void {
    $manager=account_is_manager($account);
    $slug='/'.$company['slug'];
    $nav=[
        'overview'=>['Übersicht',$slug,'home'],
        'produkte'=>['Produkte',$slug.'/produkte','shirt'],
        'bestellungen'=>['Meine Bestellungen',$slug.'/bestellungen','box'],
    ];
    $pending=0;
    if($manager){
        $nav['freigaben']=['Freigaben',$slug.'/freigaben','check'];
        $nav['mitarbeiter']=['Mitarbeiter',$slug.'/mitarbeiter','users'];
        $q=db()->prepare("SELECT COUNT(*) FROM orders WHERE company_id=? AND status='pending'");$q->execute([(int)$company['id']]);$pending=(int)$q->fetchColumn();
    }
    $nav['profil']=['Mein Profil',$slug.'/profil','user'];
    $hasLogo=yw_company_logo_path($company)!=='';

    echo '<a class="cp-skip" href="#inhalt">Zum Inhalt springen</a><div class="cp-app">';
    echo '<aside class="cp-side" data-cp-side><div class="cp-side-head"><a class="cp-brand'.($hasLogo?' has-logo':'').'" href="'.e($slug).'">'.yw_shop_brand_mark($company);
    echo '<span class="cp-brand-text">'.($hasLogo?'':'<strong>'.e($company['legal_name']).'</strong>').'<small>Mitarbeiter-Shop</small></span></a>';
    echo '<button class="cp-menu-toggle" type="button" aria-expanded="false" aria-controls="cp-menu" hidden>'.yw_shop_icon('menu').'<span>Menü</span></button></div>';
    echo '<div class="cp-menu" id="cp-menu"><nav class="cp-nav" aria-label="Shop-Navigation">';
    foreach($nav as $key=>[$label,$href,$icon]){
        $badge=$key==='freigaben'&&$pending>0?'<span class="cp-nav-badge" aria-label="'.$pending.' offen">'.$pending.'</span>':'';
        echo '<a href="'.e($href).'"'.($section===$key?' class="active" aria-current="page"':'').'>'.yw_shop_icon($icon).'<span>'.e($label).'</span>'.$badge.'</a>';
    }
    echo '</nav><div class="cp-person"><div><strong>'.e($account['name']).'</strong><small>'.($manager?'Verantwortliche Person':'Mitarbeiterkonto').(!empty($account['department'])?' · '.e($account['department']):'').'</small></div>';
    echo '<form method="post" action="'.e($slug).'/logout">'.csrf_field().'<button class="cp-logout" type="submit">'.yw_shop_icon('logout').'<span>Abmelden</span></button></form></div></div></aside>';

    echo '<div class="cp-mainwrap"><header class="cp-top"><div class="cp-top-title"><strong>'.e(company_portal_section_title($section)).'</strong><small>'.e($company['shop_name']).'</small></div>';
    echo '<a class="cp-avatar" href="'.e($slug).'/profil" title="Mein Profil"><span aria-hidden="true">'.e(yw_shop_initials((string)$account['name'])).'</span><span class="cp-sr">Mein Profil</span></a></header>';
}

function company_portal_order_status(string $status): string {
    return [
        'pending'=>'Freigabe offen',
        'approved'=>'Freigegeben',
        'rejected'=>'Abgelehnt',
        'cancelled'=>'Zurückgezogen',
    ][$status] ?? $status;
}

function company_portal_fulfillment_status(string $status): string {
    return [
        'new'=>'Neu',
        'processing'=>'In Bearbeitung (Bestand)',
        'accepted'=>'Angenommen',
        'goods_ordered'=>'Ware bestellt',
        'goods_received'=>'Ware eingetroffen',
        'production'=>'In Produktion',
        'quality_check'=>'Qualitätskontrolle',
        'ready'=>'Versand-/abholbereit',
        'shipped'=>'Versendet',
        'picked_up'=>'Abgeholt',
        'completed'=>'Abgeschlossen',
    ][$status] ?? $status;
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
    $type=trim((string)($p['decoration_type']??''));
    return $type!==''?$type:'';
}

/** Ein Preis für Karte/Detail: Eigenanteil oder Hinweis, dass die Firma zahlt. */
function yw_shop_price_html(array $breakdowns): string {
    if(!$breakdowns)return '';
    $share=(float)$breakdowns[0]['employee_percent']>0;
    $values=array_map(fn($b)=>(int)($share?$b['employee']:$b['gross']),$breakdowns);
    $min=min($values);$from=count(array_unique($values))>1?'ab ':'';
    if($share)return '<span class="sp-price"><small>Dein Anteil</small><strong>'.$from.money($min).'</strong></span>';
    return '<span class="sp-price is-company"><strong>'.$from.money($min).'</strong><small>übernimmt deine Firma</small></span>';
}

/** Kategorie-Filter wie im Mustershop. */
function company_shop_categories(array $groups): void {
    $categories=[];
    foreach($groups as $g){$p=$g[0];$cid=(int)($p['category_id']??0);if($cid)$categories[$cid]=product_category_name($cid,(string)($p['category']??''));}
    if(count($categories)<2)return;
    echo '<div class="sp-filters shop-category-filter" role="group" aria-label="Nach Kategorie filtern"><button type="button" class="active" aria-pressed="true" data-filter="all">Alle Artikel</button>';
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
        $html.='<div class="sp-dots" aria-label="'.count($variants).' Farben: '.e(implode(', ',array_map(fn($v)=>(string)$v['color'],$variants))).'">';
        foreach(array_slice($variants,0,6) as $v)$html.=yw_shop_swatch($v);
        if(count($variants)>6)$html.='<span class="sp-dots-more">+'.(count($variants)-6).'</span>';
        $html.='<span class="sp-dots-label">'.count($variants).' Farben</span></div>';
    }
    $html.='<div class="sp-buy">'.yw_shop_price_html($breakdowns).'<button class="sp-add" type="button" data-open-product="'.e($dialogId).'" aria-haspopup="dialog">Auswählen</button></div>';
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
    $html.='<div class="sp-detail-inner"><button class="sp-detail-close" type="button" data-close-detail aria-label="Schließen">'.yw_shop_icon('close').'</button>';
    // Bild mit Logo, Umschalter vorne/hinten/Ärmel – je Farbe ein eigenes Bild
    $html.='<div class="sp-detail-media">';
    foreach($variants as $v){
        $views=yw_mockup_views($v,$motifs);
        $label=company_product_display_name($v).(trim((string)$v['color'])!==''?' in '.$v['color']:'');
        $html.='<div class="sp-variant-visual'.((int)$v['id']===(int)$active['id']?' is-active':'').'" data-variant-visual="'.(int)$v['id'].'">';
        $html.=$views?yw_mockup_html($views,$label,true,'sp-detail-mockup'):'<span class="sp-card-placeholder">'.yw_shop_icon('shirt').'</span>';
        $html.='</div>';
    }
    $html.='<p class="sp-media-note">Vorschau mit deinem Firmenlogo · Darstellung ähnlich</p></div>';

    $html.='<div class="sp-detail-info">'.($category!==''?'<span class="sp-cat">'.e($category).'</span>':'');
    $html.='<h2 id="'.e($dialogId).'-title">'.e($name).'</h2>';
    if(!empty($active['brand']))$html.='<p class="sp-brand">'.e((string)$active['brand']).'</p>';

    // Farbauswahl
    if(count($variants)>1){
        $html.='<div class="sp-colors-wrap"><span class="sp-label" id="'.e($dialogId).'-colors">Farbe: <b data-color-name>'.e((string)$active['color']).'</b></span><div class="sp-colors" role="radiogroup" aria-labelledby="'.e($dialogId).'-colors">';
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
        $html.='<div class="sp-fields"><label class="sp-field"><span>Größe</span><select id="size-'.$pid.'" name="size['.$pid.']"'.($maxQty===0?' disabled':'').'>';
        foreach($sizes as $size)$html.='<option value="'.e((string)$size).'"'.((string)$size===$preSize?' selected':'').'>'.e((string)$size).'</option>';
        $html.='</select></label>';
        $qtyId='qty-'.$pid;
        $html.='<div class="sp-field"><label for="'.$qtyId.'">Menge</label><div class="shop-stepper"><button type="button" data-qty-step="-1" data-qty-target="'.$qtyId.'" aria-label="Menge verringern"'.($maxQty===0?' disabled':'').'>−</button>';
        $html.='<input class="shop-qty" id="'.$qtyId.'" type="number" inputmode="numeric" min="0" max="'.$maxQty.'" value="'.$preQty.'" name="qty['.$pid.']" data-gross="'.(int)$price['gross'].'" data-net="'.(int)$price['net'].'" data-employee="'.(int)$price['employee'].'" data-company="'.(int)$price['employer'].'" data-group="'.e($dialogId).'" data-line-name="'.e($vName.($color!==''?' · '.$color:'')).'" data-size-target="size-'.$pid.'"'.($maxQty===0?' disabled':'').'>';
        $html.='<button type="button" data-qty-step="1" data-qty-target="'.$qtyId.'" aria-label="Menge erhöhen"'.($maxQty===0?' disabled':'').'>+</button></div></div></div>';
        if($maxQty===0)$html.='<p class="shop-limit-reached">Stücklimit für dieses Budgetjahr erreicht.</p>';
        elseif(array_key_exists('profile_remaining_qty',$v))$html.='<p class="sp-hint">Noch '.(int)$v['profile_remaining_qty'].' Stück in diesem Budgetjahr möglich.</p>';
        if(isset($v['profile_starter_qty']) && (int)$v['profile_starter_qty']>0)$html.='<p class="sp-hint">Empfohlen für deine Grundausstattung: '.(int)$v['profile_starter_qty'].' Stück.</p>';
        $html.='</fieldset>';
    }
    $html.='<div class="sp-detail-actions"><button class="sp-primary" type="button" data-add-detail>In die Auswahl</button><button class="sp-secondary" type="button" data-close-detail>Weiter ansehen</button></div>';
    $html.='<p class="sp-detail-status" data-detail-status role="status" aria-live="polite"></p>';

    // Veredelung
    $decorations=company_product_decorations($active);
    if($decorations){
        $html.='<section class="sp-section"><h3>Veredelung</h3><ul class="sp-specs">';
        foreach($decorations as $d){$t=trim((string)($d['text']??''));$html.='<li><span>'.e(decoration_position_label((string)($d['position']??''))).'</span><b>'.e(decoration_method_label((string)($d['method']??''))).($t!==''?' · '.e($t):'').'</b></li>';}
        $html.='</ul></section>';
    }elseif(trim((string)($active['decoration_details']??''))!==''){
        $html.='<section class="sp-section"><h3>Veredelung</h3><p>'.nl2br(e((string)$active['decoration_details'])).'</p></section>';
    }

    // Beschreibung kurz, Rest aufklappbar
    $parts=yw_shop_description_parts(company_product_display_description($active));
    if($parts['intro']!==''||$parts['bullets']){
        $html.='<section class="sp-section"><h3>Über den Artikel</h3>';
        if($parts['intro']!==''){
            $intro=$parts['intro'];
            if(mb_strlen($intro)>220){$cut=mb_substr($intro,0,200);$cut=mb_substr($cut,0,max(120,(int)mb_strrpos($cut,' ')));$html.='<p>'.e($cut).' …</p><details class="sp-more"><summary>mehr anzeigen</summary><p>'.e($intro).'</p></details>';}
            else $html.='<p>'.e($intro).'</p>';
        }
        if($parts['bullets']){
            $html.='<ul class="sp-bullets">';foreach(array_slice($parts['bullets'],0,4) as $b)$html.='<li>'.e($b).'</li>';$html.='</ul>';
            if(count($parts['bullets'])>4){$html.='<details class="sp-more"><summary>mehr anzeigen</summary><ul class="sp-bullets">';foreach(array_slice($parts['bullets'],4) as $b)$html.='<li>'.e($b).'</li>';$html.='</ul></details>';}
        }
        $html.='</section>';
    }
    if($parts['material']||$parts['care']){
        $html.='<section class="sp-section"><h3>Material &amp; Pflege</h3><ul class="sp-specs">';
        foreach($parts['material'] as $m)$html.='<li><span>Material</span><b>'.e($m).'</b></li>';
        foreach($parts['care'] as $c)$html.='<li><span>Pflege</span><b>'.e($c).'</b></li>';
        $html.='</ul></section>';
    }
    $sizesAll=[];foreach($variants as $v)foreach(json_decode((string)$v['sizes'],true)?:[] as $s)$sizesAll[(string)$s]=true;
    if($sizesAll)$html.='<section class="sp-section"><h3>Größen</h3><p class="sp-sizes">'.e(implode(' · ',array_keys($sizesAll))).'</p></section>';
    $html.='</div></div></dialog>';
    return $html;
}

function company_shop_footer(array $company): void {
    echo '<footer class="company-shop-footer"><div class="shop-wrap company-shop-footer-inner">';
    echo '<span>'.e($company['legal_name']).' · Mitarbeiter-Shop</span>';
    echo '<span>WorkwearCenter · <a href="/impressum">Impressum</a> · <a href="/datenschutz">Datenschutz</a></span>';
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

    $error='';
    if(is_post()){
        verify_csrf();
        $email=strtolower(trim((string)($_POST['email']??'')));
        $password=(string)($_POST['password']??'');
        $loginKey='employee-'.(int)$company['id'].'-'.hash('sha256',$email);

        if(login_throttled($loginKey)){
            $error='Zu viele Anmeldeversuche. Bitte 15 Minuten warten.';
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
                clear_login_failures($loginKey);
                session_regenerate_id(true);
                $_SESSION['employee_account_id']=(int)$account['id'];
                $_SESSION['employee_auth_version']=(int)($account['auth_version']??1);
                try{
                    db()->prepare('UPDATE employee_accounts SET last_login_at=NOW() WHERE id=?')->execute([(int)$account['id']]);
                }catch(Throwable $e){}
                redirect('/'.$company['slug']);
            }

            record_login_failure($loginKey);
            $error='Anmeldung nicht möglich. Zugangsdaten oder Freigabestatus prüfen.';
        }
    }

    render_head('Login – '.$company['shop_name'],$company);
    echo company_portal_style($company);
    $hasLogo=yw_company_logo_path($company)!=='';
    echo '<main class="cp-login"><section class="cp-login-brand">';
    // Mit Logo steht der Firmenname nur im Logo; ohne Logo Initialen in Firmenfarbe + Name.
    echo '<div class="cp-login-identity'.($hasLogo?' has-logo':'').'">'.yw_shop_brand_mark($company,'cp-login').($hasLogo?'':'<strong>'.e($company['legal_name']).'</strong>').'</div>';
    echo '<div class="cp-login-copy"><span class="cp-login-kicker">Mitarbeiter-Shop</span><h1>Deine Firmenkleidung an einem Ort.</h1><p>Freigegebene Artikel mit deinem Firmenlogo, persönliche Bestellübersicht und dein Budget immer im Blick.</p></div>';
    echo '<p class="cp-login-powered">Bereitgestellt über das WorkwearCenter von Yanik Textildruck</p></section>';
    echo '<section class="cp-login-form"><div class="cp-login-card"><h2>Anmelden</h2><p>Mit deinem persönlichen Firmenkonto.</p>';
    if($error) echo '<div class="flash error" role="alert">'.e($error).'</div>';
    echo '<form method="post" class="form-stack">'.csrf_field()
        .'<label>E-Mail<input type="email" name="email" autocomplete="email" required></label>'
        .'<label>Passwort<input type="password" name="password" autocomplete="current-password" required></label>'
        .'<button class="cp-btn-primary" type="submit">Im Shop anmelden</button></form>';
    echo '<div class="cp-login-help"><a href="/'.e($company['slug']).'/passwort-vergessen">Passwort vergessen?</a><span>Noch kein Zugang? <a href="/'.e($company['slug']).'/registrieren">Registrieren</a></span></div>';
    echo '</div><p class="cp-login-legal"><a href="/impressum">Impressum</a> · <a href="/datenschutz">Datenschutz</a></p></section></main>';
    render_foot();
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

    $manager=account_is_manager($account);
    if(in_array($section,['freigaben','mitarbeiter'],true) && !$manager){
        http_response_code(403);
        simple_message('Kein Zugriff','Dieser Bereich ist nur für Verantwortliche freigeschaltet.');
        return;
    }

    if(is_post() && yw_is_preview($account)){flash('info','Vorschau-Modus: Änderungen und Bestellungen sind hier abgeschaltet.');redirect('/'.$slug.($section==='overview'?'':'/'.$section));}
    if(is_post()){
        verify_csrf();
        $action=(string)($_POST['action']??'');

        if($manager && $action==='registration_decision' && $section==='mitarbeiter') yw_registration_decision($company,$account);
        if($manager && in_array($action,['customer_employee_add','customer_employee_save','customer_employee_import','customer_employee_link','customer_employee_anonymize'],true) && $section==='mitarbeiter') yw_customer_employee_action($company,$account,$action);
        if($action==='order' && $section==='produkte'){
            place_account_order($company,$account);
        }

        if($manager && $action==='decision' && $section==='freigaben'){
            $orderId=(int)($_POST['order_id']??0);
            $decision=(string)($_POST['decision']??'');
            $decisionNote=trim(is_string($_POST['decision_note']??null)?$_POST['decision_note']:'');
            if(!in_array($decision,['approved','rejected'],true)){
                flash('error','Ungültige Entscheidung.');
                redirect('/'.$slug.'/freigaben');
            }
            if(mb_strlen($decisionNote)>1000 || ($decision==='rejected' && $decisionNote==='')){
                flash('error',$decision==='rejected'?'Bitte einen kurzen Ablehnungsgrund angeben.':'Der Hinweis darf höchstens 1.000 Zeichen enthalten.');
                redirect('/'.$slug.'/freigaben');
            }

            // Vier-Augen-Prinzip: Eigene Bestellungen gibt eine andere verantwortliche Person frei.
            // Nur wenn es keine weitere aktive verantwortliche Person gibt, ist das erlaubt (wird protokolliert).
            $ownerQuery=db()->prepare('SELECT employee_account_id FROM orders WHERE id=? AND company_id=? LIMIT 1');
            $ownerQuery->execute([$orderId,(int)$company['id']]);
            $selfDecision=(int)$ownerQuery->fetchColumn()===(int)$account['id'];
            if($selfDecision && $decision==='approved'){
                $otherManagers=db()->prepare("SELECT COUNT(*) FROM employee_accounts WHERE company_id=? AND role='manager' AND active=1 AND anonymized_at IS NULL AND id<>?");
                $otherManagers->execute([(int)$company['id'],(int)$account['id']]);
                if((int)$otherManagers->fetchColumn()>0){
                    flash('error','Eigene Bestellungen gibt eine andere verantwortliche Person frei (Vier-Augen-Prinzip).');
                    redirect('/'.$slug.'/freigaben');
                }
            }
            $stmt=db()->prepare("UPDATE orders SET status=?,decision_note=?,decided_by_account_id=?,decided_at=NOW() WHERE id=? AND company_id=? AND status='pending'");
            $stmt->execute([$decision,$decisionNote!==''?$decisionNote:null,(int)$account['id'],$orderId,(int)$company['id']]);
            $changed=$stmt->rowCount()>0;
            if($changed){
                audit_log('order.decision','order',$orderId,['company_id'=>(int)$company['id'],'decision'=>$decision]+($selfDecision?['self_decision'=>true]:[]));
                if($decision==='approved')yw_handover_enqueue($orderId);
                $decisionText=$decision==='approved'?'Deine Bestellung wurde freigegeben und wird nun bearbeitet.':'Deine Bestellung wurde abgelehnt.';
                if($decisionNote!=='')$decisionText.="\nHinweis: ".$decisionNote;
                notify_order_employee($orderId,'Entscheidung zu Bestellung #'.$orderId,$decisionText."\n\n".yw_order_mail_summary($orderId)."\n\nBestellstatus ansehen: ".workwearcenter_url($slug.'/bestellungen'));
            }

            flash($changed?'success':'error',$changed?($decision==='approved'?'Bestellung wurde freigegeben.':'Bestellung wurde abgelehnt.'):'Die Bestellung wurde bereits bearbeitet oder vom Mitarbeiter zurückgezogen.');
            redirect('/'.$slug.'/freigaben');
        }

        if($action==='cancel_order' && $section==='bestellungen'){
            $orderId=(int)($_POST['order_id']??0);
            $stmt=db()->prepare("UPDATE orders SET status='cancelled',cancelled_at=NOW() WHERE id=? AND company_id=? AND employee_account_id=? AND status='pending'");
            $stmt->execute([$orderId,(int)$company['id'],(int)$account['id']]);
            if($stmt->rowCount()){
                audit_log('order.cancelled','order',$orderId,['company_id'=>(int)$company['id']]);
                notify_company_managers((int)$company['id'],'Bestellung #'.$orderId.' zurückgezogen',$account['name'].' hat die Bestellung #'.$orderId.' zurückgezogen. Eine Freigabe ist nicht mehr nötig.');
                flash('success','Bestellung #'.$orderId.' wurde zurückgezogen. Das reservierte Budget ist wieder frei.');
            }else flash('error','Die Bestellung kann nicht mehr zurückgezogen werden – sie wurde bereits bearbeitet.');
            redirect('/'.$slug.'/bestellungen');
        }

        if($action==='profile' && $section==='profil'){
            $name=trim((string)($_POST['name']??''));
            if($name===''||mb_strlen($name)>160){
                flash('error','Bitte einen Namen mit höchstens 160 Zeichen eingeben.');
                redirect('/'.$slug.'/profil');
            }
            $stmt=db()->prepare('UPDATE employee_accounts SET name=? WHERE id=? AND company_id=?');
            $stmt->execute([$name,(int)$account['id'],(int)$company['id']]);
            audit_log('employee.profile_updated','employee',(int)$account['id'],['company_id'=>(int)$company['id']]);
            flash('success','Profil wurde gespeichert.');
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
    }elseif($section==='profil'){
        company_portal_profile($company,$account);
    }

    echo '</main>';
    company_portal_footer($company);
    echo '<script src="/assets/shop.js?v='.e(app_version()).'" defer></script>';
    render_foot();
}


/** Positionsbezeichnung einer Bestellzeile inkl. Farbe aus dem Bestell-Schnappschuss. */
function yw_shop_item_label(array $item): string {
    $snapshot=json_decode((string)($item['decoration_snapshot_json']??''),true);
    $color=is_array($snapshot)?trim((string)($snapshot['color']??'')):'';
    return (string)$item['product_name'].($color!==''?' · '.$color:'').' · Größe '.(string)$item['size'];
}

function company_portal_overview(array $company,array $account): void {
    $slug='/'.$company['slug'];
    $manager=account_is_manager($account);
    $approval=(int)($company['approval_required']??1)===1;

    echo '<div class="cp-welcome"><div><h1>'.e(yw_shop_greeting((string)$account['name'])).'</h1><p>Hier ist deine Firmenkollektion von '.e($company['legal_name']).'. Wähle aus den für dich freigegebenen Artikeln.</p>';
    echo '<p class="cp-rules">'.e(financing_label($company)).' · '.e(shipping_rule_label($company)).' · '.($approval?'Bestellungen werden intern freigegeben':'Keine interne Freigabe nötig').'</p></div>';
    echo yw_shop_budget_box($company,$account).'</div>';

    $equipmentProfile=yw_account_equipment_profile($account);
    $stmt=db()->prepare('SELECT * FROM orders WHERE company_id=? AND (employee_account_id=? OR (employee_account_id IS NULL AND invite_id=?)) ORDER BY id DESC LIMIT 1');
    $stmt->execute([(int)$company['id'],(int)$account['id'],(int)($account['invite_id']??0)]);
    $last=$stmt->fetch();

    echo '<div class="cp-dash">';
    if(!$last){
        $hasStarter=false;
        foreach((yw_account_profile_rules($account)??[]) as $rule)if((int)$rule['starter_qty']>0){$hasStarter=true;break;}
        echo '<section class="cp-card cp-first-order"><div><h2>Deine erste Bestellung</h2>';
        echo $hasStarter
            ? '<p>Für dich ist eine Grundausstattung hinterlegt'.($equipmentProfile?' ('.e($equipmentProfile['name']).')':'').'. Mit einem Klick ist alles vorausgewählt – du musst nur noch deine Größen prüfen.</p></div><a class="cp-btn" href="'.e($slug).'/produkte?starter=1">Grundausstattung bestellen</a>'
            : '<p>Wähle deine Artikel aus der Firmenkollektion. Beim nächsten Mal merkt sich der Shop deine Größen, und du kannst Bestellungen mit einem Klick wiederholen.</p></div><a class="cp-btn" href="'.e($slug).'/produkte">Zur Kollektion</a>';
        echo '</section>';
    }else{
        $items=order_items_grouped([(int)$last['id']])[(int)$last['id']]??[];
        $status=(string)$last['status'];
        $gross=(int)($last['gross_total_cents']??0); if($gross<=0)$gross=(int)$last['total_cents'];
        echo '<section class="cp-card cp-last-order"><div class="cp-card-head"><div><h2>Letzte Bestellung</h2><p>#'.(int)$last['id'].' · '.e(date('d.m.Y',strtotime((string)$last['created_at']))).' · '.money($gross).'</p></div><span class="cp-status '.e($status).'">'.e(company_portal_order_status($status)).'</span></div>';
        echo '<ul class="cp-mini-items">';
        foreach(array_slice($items,0,3) as $item)echo '<li><span>'.(int)$item['quantity'].'×</span> '.e(yw_shop_item_label($item)).'</li>';
        if(count($items)>3)echo '<li class="muted">+ '.(count($items)-3).' weitere Position'.(count($items)-3===1?'':'en').'</li>';
        echo '</ul>';
        $progress=company_portal_progress($last);
        if($progress!=='')echo $progress;
        elseif($status==='pending')echo '<p class="cp-state-note">Wartet auf die Freigabe durch die verantwortliche Person. Dein Budget ist so lange reserviert.</p>';
        echo '<div class="cp-card-actions"><a class="cp-btn" href="'.e($slug).'/produkte?nachbestellen='.(int)$last['id'].'">'.yw_shop_icon('repeat').'Nochmal bestellen</a><a class="cp-btn cp-btn-light" href="'.e($slug).'/bestellungen">Alle Bestellungen</a></div></section>';
    }

    if($manager){
        $q=db()->prepare("SELECT o.id,o.created_at,o.gross_total_cents,o.total_cents,COALESCE(ea.name,i.employee_name,'Unbekannt') employee_name FROM orders o LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id LEFT JOIN invites i ON i.id=o.invite_id WHERE o.company_id=? AND o.status='pending' ORDER BY o.id ASC");
        $q->execute([(int)$company['id']]);$pending=$q->fetchAll();
        echo '<section class="cp-card cp-approvals-card"><div class="cp-card-head"><div><h2>Offene Freigaben</h2><p>'.(count($pending)?count($pending).' Bestellung'.(count($pending)===1?' wartet':'en warten').' auf eine Entscheidung.':'Aktuell ist nichts offen.').'</p></div><span class="cp-count">'.count($pending).'</span></div>';
        if($pending){
            echo '<ul class="cp-mini-items">';
            foreach(array_slice($pending,0,4) as $o){$g=(int)($o['gross_total_cents']??0);if($g<=0)$g=(int)$o['total_cents'];echo '<li><span>#'.(int)$o['id'].'</span> '.e($o['employee_name']).' · '.e(date('d.m.',strtotime((string)$o['created_at']))).'<b>'.money($g).'</b></li>';}
            echo '</ul><div class="cp-card-actions"><a class="cp-btn" href="'.e($slug).'/freigaben">Jetzt prüfen</a></div>';
        }else echo '<div class="cp-card-actions"><a class="cp-btn cp-btn-light" href="'.e($slug).'/freigaben">Bestellungen ansehen</a></div>';
        echo '</section>';
    }elseif($last){
        echo '<section class="cp-card cp-shop-card"><h2>Kollektion</h2><p>Alle für dich freigegebenen Artikel mit Logo-Vorschau, Farben und Größen.</p><div class="cp-card-actions"><a class="cp-btn" href="'.e($slug).'/produkte">Zur Kollektion '.yw_shop_icon('arrow').'</a></div></section>';
    }
    echo '</div>';

    if($equipmentProfile){
        echo '<section class="cp-card"><h2>Deine Sollausstattung · '.e($equipmentProfile['name']).'</h2><p>'.e($equipmentProfile['description']?:'Für deine Tätigkeit freigegebene Firmenkleidung im aktuellen Budgetjahr.').'</p>';
        $products=shop_company_products((int)$company['id'],$account);
        $targets=array_filter($products,fn($p)=>$p['profile_target_qty']!==null);
        if($targets){echo '<div class="cp-rule-list">';foreach($targets as $product){$used=yw_quantity_used($company,$account,(int)$product['id']);$target=(int)$product['profile_target_qty'];echo '<div class="cp-rule"><span>'.e(company_product_display_name($product)).(trim((string)$product['color'])!==''?' · '.e($product['color']):'').' · bestellt/reserviert '.$used.'</span><strong>'.max(0,$target-$used).' von '.$target.' offen</strong></div>';}echo '</div>';}
        else echo '<p class="muted">Für dieses Profil sind Produkte freigegeben, aber keine jährlichen Sollmengen hinterlegt.</p>';
        echo '</section>';
    }
}

function company_portal_products(array $company,array $account): void {
    $products=shop_company_products((int)$company['id'],$account);
    $slug=(string)$company['slug'];

    echo '<div class="cp-welcome cp-welcome-products"><div><h1>Deine Firmenkollektion</h1><p>Wähle Artikel, Farbe, Größe und Menge. Die Kosten siehst du vor dem Absenden in deiner Auswahl.</p></div>'.yw_shop_budget_box($company,$account).'</div>';

    if(!$products){
        echo '<div class="empty">Für dein Ausstattungsprofil sind derzeit keine Produkte freigeschaltet. Bitte wende dich an deinen Firmenverantwortlichen.</div>';
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
        if($preset)$presetNote='Die Auswahl aus Bestellung #'.$reorderId.' wurde übernommen. Bitte kurz prüfen und dann absenden.';
    }elseif(isset($_GET['starter'])){
        foreach($products as $p)if((int)($p['profile_starter_qty']??0)>0)$preset[(int)$p['id']]=['qty'=>(int)$p['profile_starter_qty'],'size'=>$lastSizes[(int)$p['id']]??''];
        if($preset)$presetNote='Deine empfohlene Grundausstattung ist vorausgewählt. Bitte Größen prüfen und dann absenden.';
    }
    $available=array_flip(array_map(fn($p)=>(int)$p['id'],$products));
    if($preset && !array_intersect_key($preset,$available)){$preset=[];$presetNote='Die Artikel dieser Bestellung sind derzeit nicht mehr bestellbar.';}
    if($presetNote!=='')echo '<div class="cp-manager-alert" role="status">'.e($presetNote).' <a href="#auswahl">Zur Auswahl</a></div>';

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

    echo '<div class="sp-tools">';
    company_shop_categories($groups);
    echo '<a class="sp-cart-btn" href="#auswahl">'.yw_shop_icon('bag').'<span>Auswahl · <span data-selection-count>0</span></span></a></div>';

    echo '<form method="post" data-order-form>'.csrf_field().'<input type="hidden" name="action" value="order">';
    echo '<div class="sp-grid">';
    foreach($groups as $g)echo company_product_card($g,$company,$motifs,$slug);
    echo '</div>';
    foreach($groups as $g){
        $ids=array_map(fn($v)=>(int)$v['id'],$g);
        $open=$openId>0 && in_array($openId,$ids,true);
        echo company_product_detail($g,$company,$motifs,$open?$openId:0,$open);
    }

    $approval=(int)($company['approval_required']??1)===1;
    echo '<section class="sp-cart shop-order-panel" id="auswahl" tabindex="-1" aria-labelledby="auswahl-title" data-shipping-enabled="'.(shipping_enabled($company)?'1':'0').'" data-shipping-net="'.(int)($company['shipping_net_cents']??0).'" data-shipping-vat="'.e((string)($company['shipping_vat_rate']??19)).'" data-free-shipping="'.(int)($company['free_shipping_gross_cents']??0).'" data-shipping-payer="'.e((string)($company['shipping_payer']??'company')).'">';
    echo '<div class="sp-cart-main"><h2 id="auswahl-title">Deine Auswahl</h2>';
    echo '<p class="sp-cart-empty" data-cart-empty>Noch nichts ausgewählt. Öffne einen Artikel und lege Farbe, Größe und Menge fest.</p><ul class="sp-cart-lines" data-cart-lines></ul>';
    echo '<label class="sp-note">Hinweis zur Bestellung <small>optional</small><textarea name="note" maxlength="500" placeholder="z. B. gemeinsam mit Abteilung Montage ausliefern"></textarea></label>';
    echo yw_checkout_fields($company).'</div>';
    echo '<div class="shop-submit-box"><div class="shop-selection-count"><strong data-selection-count>0</strong> Artikel ausgewählt</div>'
        .'<div class="shop-checkout-lines">'
        .'<div><span>Warenwert netto</span><strong data-summary-net>0,00 €</strong></div>'
        .'<div><span>Warenwert brutto</span><strong data-summary-gross>0,00 €</strong></div>'
        .'<div><span>Versand brutto</span><strong data-summary-shipping>0,00 €</strong></div>'
        .'<div class="total"><span>Gesamt brutto</span><strong data-summary-total>0,00 €</strong></div>'
        .'<div class="employee"><span>Dein Anteil</span><strong data-summary-employee>0,00 €</strong></div>'
        .'<div><span>Firma übernimmt</span><strong data-summary-company>0,00 €</strong></div>'
        .'</div>'
        .'<p class="sp-rules">'.e(financing_label($company)).' · '.e(shipping_rule_label($company)).'</p>'
        .'<p class="shop-budget-warning" data-budget-warning role="alert" hidden>Der Firmenanteil übersteigt dein verfügbares Budget. Bitte die Auswahl verkleinern.</p>'
        .'<button class="shop-submit" type="submit" disabled>'.($approval?'Zur Freigabe senden':'Bestellung absenden').'</button>'
        .'<p class="sp-rules">'.($approval?'Deine verantwortliche Person gibt die Bestellung frei. Bis dahin ist dein Budget reserviert.':'Die Bestellung wird direkt übernommen.').'</p></div>';
    echo '</section>';
    echo '<div class="shop-mobile-cart" data-mobile-cart aria-hidden="true"><div><strong><span data-selection-count>0</span> Artikel</strong><small>'.(company_employee_share_percent($company)>0||(string)($company['shipping_payer']??'company')==='employee'?'Dein Anteil: <span data-summary-employee>0,00 €</span>':'Übernimmt deine Firma · <span data-summary-total>0,00 €</span>').'</small></div><button type="button" data-scroll-checkout tabindex="-1">Zur Auswahl</button></div>';
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
    $labels=['Freigegeben','In Produktion','Fertig',$pickup?'Abgeholt':'Versendet'];
    $html='<div class="cp-progress" aria-label="Bearbeitungsstand">';
    foreach($labels as $i=>$label)$html.='<div class="cp-progress-step'.($i<=$stage?' done':'').($i===$stage?' current':'').'"><span></span><small>'.e($label).'</small></div>';
    $html.='</div>';
    $link=yw_tracking_link($o['tracking_carrier']??null,$o['tracking_number']??null,$o['tracking_url']??null);
    if(!empty($o['tracking_number']))$html.='<div class="cp-tracking"><div><small>Sendungsnummer'.(!empty($o['tracking_carrier'])?' · '.e($o['tracking_carrier']):'').'</small><strong>'.e($o['tracking_number']).'</strong></div>'.($link?'<a class="cp-btn approve" href="'.e($link).'" target="_blank" rel="noopener noreferrer">Sendung verfolgen</a>':'').'</div>';
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

    echo '<div class="cp-section-head"><div><h1>Meine Bestellungen</h1><p>Hier findest du deine komplette Bestellhistorie und den aktuellen Status.</p></div></div>';

    if(!$orders){
        echo '<div class="empty">Du hast noch keine Bestellungen aufgegeben.</div>';
        return;
    }

    echo '<div class="cp-order-list">';
    foreach($orders as $o){
        $items=$itemsByOrder[(int)$o['id']]??[];
        $status=(string)$o['status'];
        $gross=(int)($o['gross_total_cents']??0); if($gross<=0)$gross=(int)$o['total_cents'];
        $employee=(int)($o['employee_total_cents']??0);
        $companyShare=(int)($o['company_total_cents']??0);

        echo '<article class="cp-order"><div class="cp-order-head"><div class="cp-order-title"><strong>Bestellung #'.(int)$o['id'].'</strong><div class="cp-order-meta">'.e(date('d.m.Y · H:i',strtotime($o['created_at']))).' Uhr · '.e(company_portal_fulfillment_status((string)($o['fulfillment_status']??'new'))).'</div></div><div><span class="cp-status '.e($status).'">'.e(company_portal_order_status($status)).'</span></div></div>';
        echo '<div class="cp-order-items">';
        foreach($items as $item){
            $unitGross=(int)($item['unit_gross_cents']??0); if($unitGross<=0)$unitGross=(int)$item['unit_price_cents'];
            echo '<div class="cp-order-item"><span>'.(int)$item['quantity'].'× '.e(yw_shop_item_label($item)).'</span><strong>'.money($unitGross*(int)$item['quantity']).'</strong></div>';
        }
        if((int)($o['shipping_gross_cents']??0)>0) echo '<div class="cp-order-item"><span>Versand</span><strong>'.money((int)$o['shipping_gross_cents']).'</strong></div>';
        echo '</div>';
        echo company_portal_progress($o);
        echo '<div class="cp-order-summary"><div class="cp-order-sum"><span>Gesamt brutto</span><strong>'.money($gross).'</strong></div><div class="cp-order-sum"><span>Dein Anteil</span><strong>'.money($employee).'</strong></div><div class="cp-order-sum"><span>Firma übernimmt</span><strong>'.money($companyShare).'</strong></div></div>';
        if(!empty($o['decision_note']))echo '<div class="cp-manager-alert"><strong>Hinweis zur Entscheidung</strong><br>'.nl2br(e((string)$o['decision_note'])).'</div>';
        $own=(int)($o['employee_account_id']??0)===(int)$account['id'];
        if($own){
            echo '<div class="cp-action-row">';
            if($status==='pending')echo '<form method="post" data-confirm="Bestellung #'.(int)$o['id'].' wirklich zurückziehen?">'.csrf_field().'<input type="hidden" name="action" value="cancel_order"><input type="hidden" name="order_id" value="'.(int)$o['id'].'"><button class="cp-btn reject" type="submit">Bestellung zurückziehen</button></form>';
            echo '<a class="cp-btn cp-btn-light" href="/'.e($company['slug']).'/produkte?nachbestellen='.(int)$o['id'].'">Nochmal bestellen</a>';
            echo '</div>';
        }
        echo '</article>';
    }
    echo '</div>';
    if($maxPage>1){echo '<nav class="cp-action-row" aria-label="Seiten der Bestellhistorie">';if($page>1)echo '<a class="btn" href="/'.e($company['slug']).'/bestellungen?seite='.($page-1).'">← Neuere</a>';echo '<span>Seite '.$page.' von '.$maxPage.'</span>';if($page<$maxPage)echo '<a class="btn" href="/'.e($company['slug']).'/bestellungen?seite='.($page+1).'">Ältere →</a>';echo '</nav>';}
}

function company_portal_approvals(array $company,array $account): void {
    $page=max(1,(int)($_GET['seite']??1));$perPage=50;
    $countStmt=db()->prepare('SELECT COUNT(*) FROM orders WHERE company_id=?');$countStmt->execute([(int)$company['id']]);$totalOrders=(int)$countStmt->fetchColumn();
    $maxPage=max(1,(int)ceil($totalOrders/$perPage));$page=min($page,$maxPage);$offset=($page-1)*$perPage;
    $stmt=db()->prepare("SELECT o.*,COALESCE(ea.name,i.employee_name,'Unbekannt') employee_name
        FROM orders o
        LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id
        LEFT JOIN invites i ON i.id=o.invite_id
        WHERE o.company_id=?
        ORDER BY FIELD(o.status,'pending','approved','rejected','cancelled'),o.id DESC LIMIT ".$perPage.' OFFSET '.$offset);
    $stmt->execute([(int)$company['id']]);
    $orders=$stmt->fetchAll();
    $itemsByOrder=order_items_grouped(array_column($orders,'id'));

    $pendingStmt=db()->prepare("SELECT COUNT(*) FROM orders WHERE company_id=? AND status='pending'");$pendingStmt->execute([(int)$company['id']]);$pending=(int)$pendingStmt->fetchColumn();

    echo '<div class="cp-section-head"><div><h1>Freigaben</h1><p>Bestellungen deines Unternehmens prüfen, freigeben oder ablehnen.</p></div></div>';
    if($pending>0) echo '<div class="cp-manager-alert"><strong>'.$pending.' offene Freigabe'.($pending===1?'':'n').'</strong> wartet auf deine Entscheidung.</div>';

    if(!$orders){
        echo '<div class="empty">Noch keine Bestellungen vorhanden.</div>';
        return;
    }

    echo '<div class="cp-order-list">';
    foreach($orders as $o){
        $items=$itemsByOrder[(int)$o['id']]??[];
        $status=(string)$o['status'];
        $gross=(int)($o['gross_total_cents']??0); if($gross<=0)$gross=(int)$o['total_cents'];
        $employee=(int)($o['employee_total_cents']??0);
        $companyShare=(int)($o['company_total_cents']??0);

        echo '<article class="cp-order"><div class="cp-order-head"><div class="cp-order-title"><strong>Bestellung #'.(int)$o['id'].' · '.e($o['employee_name']).'</strong><div class="cp-order-meta">'.e(date('d.m.Y · H:i',strtotime($o['created_at']))).' Uhr · '.e(company_portal_fulfillment_status((string)($o['fulfillment_status']??'new'))).'</div></div><div><span class="cp-status '.e($status).'">'.e(company_portal_order_status($status)).'</span></div></div>';
        echo '<div class="cp-order-items">';
        foreach($items as $item){
            $unitGross=(int)($item['unit_gross_cents']??0); if($unitGross<=0)$unitGross=(int)$item['unit_price_cents'];
            echo '<div class="cp-order-item"><span>'.(int)$item['quantity'].'× '.e(yw_shop_item_label($item)).'</span><strong>'.money($unitGross*(int)$item['quantity']).'</strong></div>';
        }
        if((int)($o['shipping_gross_cents']??0)>0) echo '<div class="cp-order-item"><span>Versand</span><strong>'.money((int)$o['shipping_gross_cents']).'</strong></div>';
        echo '</div>';
        echo company_portal_progress($o);
        echo '<div class="cp-order-summary"><div class="cp-order-sum"><span>Gesamt brutto</span><strong>'.money($gross).'</strong></div><div class="cp-order-sum"><span>Mitarbeiter</span><strong>'.money($employee).'</strong></div><div class="cp-order-sum"><span>Firma</span><strong>'.money($companyShare).'</strong></div></div>';
        if($status==='pending'){
            echo '<div class="cp-action-row"><form method="post" class="cp-decision-form">'.csrf_field().'<input type="hidden" name="action" value="decision"><input type="hidden" name="order_id" value="'.(int)$o['id'].'"><label>Hinweis <small>bei Ablehnung erforderlich</small><textarea name="decision_note" maxlength="1000" placeholder="Kurzer Hinweis für den Mitarbeiter"></textarea></label><button class="cp-btn reject" type="submit" name="decision" value="rejected">Ablehnen</button><button class="cp-btn approve" type="submit" name="decision" value="approved">Freigeben</button></form></div>';
        }
        elseif(!empty($o['decision_note']))echo '<div class="cp-manager-alert"><strong>Hinweis zur Entscheidung</strong><br>'.nl2br(e((string)$o['decision_note'])).'</div>';
        echo '</article>';
    }
    echo '</div>';
    if($maxPage>1){echo '<nav class="cp-action-row" aria-label="Seiten der Firmenbestellungen">';if($page>1)echo '<a class="btn" href="/'.e($company['slug']).'/freigaben?seite='.($page-1).'">← Neuere</a>';echo '<span>Seite '.$page.' von '.$maxPage.'</span>';if($page<$maxPage)echo '<a class="btn" href="/'.e($company['slug']).'/freigaben?seite='.($page+1).'">Ältere →</a>';echo '</nav>';}
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
    echo '<div class="cp-section-head"><div><h1>Profil</h1><p>Deine persönlichen Daten im Firmen-Shop.</p></div></div>';
    echo '<section class="cp-card"><form method="post">'.csrf_field().'<input type="hidden" name="action" value="profile"><div class="cp-profile-grid">';
    echo '<label>Name<input name="name" maxlength="160" value="'.e($account['name']).'" required></label>';
    echo '<label>E-Mail<input value="'.e($account['email']).'" disabled></label>';
    echo '<label>Abteilung (Verwaltung)<input disabled value="'.e((string)($account['department']??'')).'"></label>';
    echo '<label>Kostenstelle (Verwaltung)<input disabled value="'.e((string)($account['cost_center']??'')).'"></label>';
    echo '</div><button class="cp-save" type="submit">Profil speichern</button></form></section>';
}
