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

function company_shop_style(array $company): string {
    $primary=color_ok((string)($company['primary_color']??'')) ? (string)$company['primary_color'] : '#132238';
    $accent=color_ok((string)($company['accent_color']??'')) ? (string)$company['accent_color'] : '#ff6b00';

    return '<style>
    :root{
      --shop-primary:'.e($primary).';
      --shop-accent:'.e($accent).';
      --shop-bg:#f5f7fa;
      --shop-card:#ffffff;
      --shop-text:#142033;
      --shop-muted:#667085;
      --shop-border:#e4e8ef;
      --shop-radius:20px;
      --shop-shadow:0 16px 42px rgba(15,23,42,.08);
    }

    body{background:var(--shop-bg);color:var(--shop-text)}
    .shop-wrap{max-width:1320px;margin:0 auto;padding-left:28px;padding-right:28px}

    .company-shop-header{
      background:
        radial-gradient(circle at 85% 20%,rgba(255,255,255,.12),transparent 24%),
        linear-gradient(135deg,var(--shop-primary),color-mix(in srgb,var(--shop-primary) 82%,#000));
      color:#fff;
      padding:26px 0 46px;
      position:relative;
      overflow:hidden;
    }
    .company-shop-top{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:28px;
      margin-bottom:40px;
    }
    .company-logo-panel{
      width:min(380px,48vw);
      min-height:118px;
      display:flex;
      align-items:center;
      justify-content:flex-start;
      background:transparent;
    }
    .company-logo-panel img{
      display:block;
      max-width:100%;
      max-height:118px;
      object-fit:contain;
      object-position:left center;
      filter:drop-shadow(0 8px 18px rgba(0,0,0,.16));
    }
    .company-logo-fallback{
      font-size:2rem;
      font-weight:900;
      color:#fff;
      text-align:left;
    }
    .shop-access-badge{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:10px 15px;
      border:1px solid rgba(255,255,255,.22);
      background:rgba(255,255,255,.08);
      border-radius:999px;
      font-size:.9rem;
      font-weight:700;
      white-space:nowrap;
    }
    .company-shop-hero{
      display:grid;
      grid-template-columns:minmax(0,1fr) auto;
      gap:34px;
      align-items:end;
    }
    .company-shop-kicker{
      color:var(--shop-accent);
      font-size:.83rem;
      letter-spacing:.13em;
      text-transform:uppercase;
      font-weight:900;
      margin-bottom:11px;
    }
    .company-shop-hero h1{
      color:#fff;
      font-size:clamp(2.3rem,5vw,4.5rem);
      line-height:1.02;
      margin:0 0 16px;
      max-width:850px;
    }
    .company-shop-hero p{
      color:rgba(255,255,255,.82);
      font-size:1.08rem;
      line-height:1.7;
      max-width:770px;
      margin:0;
    }
    .company-shop-person{
      min-width:190px;
      padding:17px 20px;
      border-radius:16px;
      background:rgba(255,255,255,.09);
      border:1px solid rgba(255,255,255,.14);
    }
    .company-shop-person small{display:block;color:rgba(255,255,255,.65);margin-bottom:5px}
    .company-shop-person strong{font-size:1rem}

    .shop-main{padding:38px 0 90px}
    .shop-process{
      display:grid;
      grid-template-columns:repeat(4,minmax(0,1fr));
      gap:16px;
      margin-bottom:46px;
    }
    .shop-step{
      background:#fff;
      border:1px solid var(--shop-border);
      border-radius:17px;
      padding:20px;
      box-shadow:0 8px 24px rgba(15,23,42,.04);
    }
    .shop-step-number{
      width:36px;height:36px;
      display:grid;place-items:center;
      border-radius:10px;
      background:var(--shop-primary);
      color:#fff;
      font-weight:900;
      margin-bottom:14px;
    }
    .shop-step h3{font-size:1rem;margin:0 0 7px}
    .shop-step p{font-size:.91rem;line-height:1.5;color:var(--shop-muted);margin:0}
    .shop-info-note{
      grid-column:1/-1;
      display:flex;
      align-items:flex-start;
      gap:12px;
      background:color-mix(in srgb,var(--shop-accent) 8%,#fff);
      border:1px solid color-mix(in srgb,var(--shop-accent) 30%,#fff);
      border-radius:15px;
      padding:15px 18px;
      color:#4a3520;
    }

    .shop-section-head{
      display:flex;
      justify-content:space-between;
      align-items:end;
      gap:22px;
      margin:0 0 20px;
    }
    .shop-section-head h2{font-size:2rem;margin:0 0 5px}
    .shop-section-head p{margin:0;color:var(--shop-muted)}
    .shop-category-filter{
      display:flex;
      gap:9px;
      flex-wrap:wrap;
      margin:0 0 26px;
    }
    .shop-category-filter button{
      border:1px solid var(--shop-border);
      background:#fff;
      color:var(--shop-text);
      padding:9px 15px;
      border-radius:999px;
      cursor:pointer;
      font-weight:750;
      transition:.18s ease;
    }
    .shop-category-filter button:hover{
      transform:translateY(-1px);
      border-color:var(--shop-primary);
    }
    .shop-category-filter button.active{
      background:var(--shop-primary);
      border-color:var(--shop-primary);
      color:#fff;
    }

    .company-product-grid{
      display:grid;
      grid-template-columns:repeat(3,minmax(0,1fr));
      gap:22px;
    }
    .company-product-card{
      background:var(--shop-card);
      border:1px solid var(--shop-border);
      border-radius:var(--shop-radius);
      overflow:hidden;
      box-shadow:var(--shop-shadow);
      display:flex;
      flex-direction:column;
      min-width:0;
    }
    .company-product-image{
      height:310px;
      padding:24px;
      box-sizing:border-box;
      display:flex;
      align-items:center;
      justify-content:center;
      background:#fff;
      border-bottom:1px solid var(--shop-border);
      position:relative;
    }
    .company-product-image img{
      width:100%;
      height:100%;
      object-fit:contain;
      object-position:center;
      display:block;
    }
    .company-product-placeholder{
      font-size:70px;
      opacity:.28;
    }
    .company-category-badge{
      position:absolute;
      left:18px;
      top:18px;
      background:var(--shop-primary);
      color:#fff;
      padding:7px 11px;
      border-radius:999px;
      font-size:.77rem;
      font-weight:850;
      box-shadow:0 5px 16px rgba(0,0,0,.12);
    }
    .company-product-body{
      padding:22px;
      display:flex;
      flex-direction:column;
      flex:1;
    }
    .company-product-title{
      display:flex;
      justify-content:space-between;
      gap:16px;
      align-items:start;
      margin-bottom:10px;
    }
    .company-product-title h3{
      font-size:1.3rem;
      line-height:1.18;
      margin:0;
    }
    .company-product-price{
      color:var(--shop-primary);
      font-size:1.16rem;
      font-weight:900;
      white-space:nowrap;
    }
    .company-price-breakdown{
      border:1px solid var(--shop-border);
      border-radius:13px;
      overflow:hidden;
      margin:8px 0 16px;
      background:#fafbfc;
    }
    .company-price-row{
      display:flex;
      justify-content:space-between;
      gap:18px;
      padding:8px 12px;
      font-size:.87rem;
      border-bottom:1px solid #edf0f4;
    }
    .company-price-row:last-child{border-bottom:0}
    .company-price-row span{color:var(--shop-muted)}
    .company-price-row strong{white-space:nowrap}
    .company-price-row.employee{
      background:color-mix(in srgb,var(--shop-accent) 8%,#fff);
      font-size:.96rem;
    }
    .company-price-row.employee span,
    .company-price-row.employee strong{color:var(--shop-text);font-weight:900}
    .shop-finance-note{
      margin:-14px 0 32px;
      padding:17px 19px;
      border-radius:15px;
      background:#fff;
      border:1px solid var(--shop-border);
      box-shadow:0 7px 22px rgba(15,23,42,.04);
      line-height:1.55;
    }

    .company-product-description{
      color:var(--shop-muted);
      line-height:1.6;
      margin:0 0 15px;
      min-height:51px;
    }
    .company-product-meta{
      display:flex;
      flex-wrap:wrap;
      gap:8px;
      margin-bottom:14px;
    }
    .company-meta-pill{
      display:inline-flex;
      padding:6px 9px;
      border-radius:999px;
      background:#f3f5f7;
      color:#475467;
      font-size:.8rem;
      font-weight:700;
    }
    .company-decoration{
      background:color-mix(in srgb,var(--shop-accent) 7%,#fff);
      border:1px solid color-mix(in srgb,var(--shop-accent) 24%,#fff);
      border-radius:12px;
      padding:12px 13px;
      margin-bottom:17px;
      line-height:1.5;
      font-size:.9rem;
    }
    .company-decoration strong{color:var(--shop-primary)}
    .company-product-controls{
      margin-top:auto;
      padding-top:16px;
      border-top:1px solid var(--shop-border);
      display:grid;
      grid-template-columns:minmax(0,1fr) 92px;
      gap:10px;
    }
    .company-product-controls select,
    .company-product-controls input{
      height:46px;
      border:1px solid #d8dee8;
      border-radius:11px;
      padding:0 12px;
      background:#fff;
      width:100%;
      box-sizing:border-box;
      font-size:.95rem;
    }
    .company-product-controls input{text-align:center;font-weight:750}

    .shop-order-panel{
      margin-top:30px;
      background:#fff;
      border:1px solid var(--shop-border);
      border-radius:20px;
      padding:25px;
      box-shadow:var(--shop-shadow);
      display:grid;
      grid-template-columns:minmax(0,1fr) auto;
      gap:28px;
      align-items:end;
    }
    .shop-order-panel h3{margin:0 0 8px;font-size:1.25rem}
    .shop-order-panel p{margin:0 0 14px;color:var(--shop-muted);line-height:1.55}
    .shop-order-panel textarea{
      width:100%;
      min-height:95px;
      border:1px solid #d8dee8;
      border-radius:12px;
      padding:13px;
      box-sizing:border-box;
      resize:vertical;
    }
    .shop-submit-box{
      min-width:270px;
      background:#f7f9fb;
      border:1px solid var(--shop-border);
      border-radius:15px;
      padding:18px;
    }
    .shop-selection-count{
      font-size:.88rem;
      color:var(--shop-muted);
      margin-bottom:10px;
    }
    .shop-selection-count strong{color:var(--shop-text);font-size:1.25rem}
    .shop-checkout-lines{border-top:1px solid var(--shop-border);margin:12px 0 15px;padding-top:10px}
    .shop-checkout-lines>div{display:flex;justify-content:space-between;gap:14px;padding:6px 0;font-size:.88rem}
    .shop-checkout-lines span{color:var(--shop-muted)}
    .shop-checkout-lines strong{white-space:nowrap}
    .shop-checkout-lines .employee{margin-top:5px;padding:10px 11px;border-radius:10px;background:color-mix(in srgb,var(--shop-accent) 10%,#fff)}
    .shop-checkout-lines .employee span,.shop-checkout-lines .employee strong{color:var(--shop-text);font-weight:900}

    .shop-submit{
      width:100%;
      min-height:50px;
      border:0;
      border-radius:12px;
      background:var(--shop-accent);
      color:#fff;
      font-weight:900;
      font-size:1rem;
      cursor:pointer;
      padding:0 17px;
    }
    .shop-submit:hover{filter:brightness(.96)}

    .company-shop-footer{
      background:var(--shop-primary);
      color:rgba(255,255,255,.72);
      padding:25px 0;
      font-size:.86rem;
    }
    .company-shop-footer-inner{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:18px;
      flex-wrap:wrap;
    }
    .company-shop-footer a{color:#fff;text-decoration:none}

    /* Firmenverwaltung / Verantwortlichenbereich */
    .manager-header{
      background:
        radial-gradient(circle at 85% 20%,rgba(255,255,255,.10),transparent 25%),
        linear-gradient(135deg,var(--shop-primary),color-mix(in srgb,var(--shop-primary) 80%,#000));
      color:#fff;
      padding:28px 0 54px;
    }
    .manager-header-top{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:24px;
      margin-bottom:44px;
    }
    .manager-logo{
      width:min(390px,50vw);
      min-height:112px;
      display:flex;
      align-items:center;
      justify-content:flex-start;
    }
    .manager-logo img{
      max-width:100%;
      max-height:112px;
      object-fit:contain;
      object-position:left center;
      display:block;
      filter:drop-shadow(0 8px 20px rgba(0,0,0,.16));
    }
    .manager-logo-fallback{
      font-size:2rem;
      font-weight:900;
      color:#fff;
    }
    .manager-access{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:10px 15px;
      border:1px solid rgba(255,255,255,.20);
      background:rgba(255,255,255,.08);
      border-radius:999px;
      font-size:.88rem;
      font-weight:800;
    }
    .manager-heading-kicker{
      color:var(--shop-accent);
      font-weight:900;
      font-size:.82rem;
      letter-spacing:.14em;
      text-transform:uppercase;
      margin-bottom:10px;
    }
    .manager-heading h1{
      color:#fff;
      font-size:clamp(2.3rem,5vw,4.2rem);
      line-height:1.02;
      margin:0 0 14px;
    }
    .manager-heading p{
      margin:0;
      color:rgba(255,255,255,.78);
      font-size:1.07rem;
      line-height:1.65;
      max-width:760px;
    }

    .manager-main{padding:34px 0 90px}
    .manager-kpis{
      display:grid;
      grid-template-columns:repeat(4,minmax(0,1fr));
      gap:16px;
      margin-bottom:34px;
    }
    .manager-kpi-card{
      background:#fff;
      border:1px solid var(--shop-border);
      border-radius:18px;
      padding:20px;
      box-shadow:0 10px 28px rgba(15,23,42,.055);
    }
    .manager-kpi-icon{
      width:42px;height:42px;
      border-radius:12px;
      display:grid;place-items:center;
      background:color-mix(in srgb,var(--shop-primary) 9%,#fff);
      color:var(--shop-primary);
      margin-bottom:16px;
      font-size:1.15rem;
      font-weight:900;
    }
    .manager-kpi-icon svg{width:20px;height:20px}
    .manager-kpi-card strong{
      display:block;
      font-size:1.75rem;
      line-height:1.1;
      color:var(--shop-text);
      margin-bottom:6px;
    }
    .manager-kpi-card span{
      color:var(--shop-muted);
      font-size:.9rem;
      line-height:1.35;
    }

    .manager-section-head{
      display:flex;
      align-items:flex-end;
      justify-content:space-between;
      gap:18px;
      margin:6px 0 20px;
    }
    .manager-section-head h2{font-size:2rem;margin:0 0 5px}
    .manager-section-head p{margin:0;color:var(--shop-muted)}
    .manager-order-filter{
      display:flex;
      gap:8px;
      flex-wrap:wrap;
    }
    .manager-order-filter button{
      border:1px solid var(--shop-border);
      background:#fff;
      padding:9px 13px;
      border-radius:999px;
      cursor:pointer;
      font-weight:750;
      color:var(--shop-text);
    }
    .manager-order-filter button.active{
      background:var(--shop-primary);
      color:#fff;
      border-color:var(--shop-primary);
    }

    .manager-orders{display:flex;flex-direction:column;gap:17px}
    .manager-order{
      background:#fff;
      border:1px solid var(--shop-border);
      border-radius:19px;
      overflow:hidden;
      box-shadow:0 10px 28px rgba(15,23,42,.05);
    }
    .manager-order-top{
      display:grid;
      grid-template-columns:minmax(0,1fr) auto;
      gap:20px;
      padding:20px 22px;
      border-bottom:1px solid var(--shop-border);
    }
    .manager-order-title{
      display:flex;
      align-items:center;
      gap:12px;
      flex-wrap:wrap;
    }
    .manager-order-title h3{margin:0;font-size:1.15rem}
    .manager-order-meta{
      margin-top:7px;
      color:var(--shop-muted);
      font-size:.9rem;
    }
    .manager-order-total{
      text-align:right;
      min-width:120px;
    }
    .manager-order-total strong{
      display:block;
      font-size:1.35rem;
      margin-top:7px;
      color:var(--shop-text);
    }
    .manager-order-status{
      display:inline-flex;
      padding:6px 9px;
      border-radius:999px;
      font-size:.78rem;
      font-weight:850;
    }
    .manager-order-status.pending{background:#fff7ed;color:#9a3412}
    .manager-order-status.approved{background:#ecfdf3;color:#067647}
    .manager-order-status.rejected{background:#fef3f2;color:#b42318}

    .manager-order-items{padding:14px 22px 6px}
    .manager-order-item{
      display:grid;
      grid-template-columns:minmax(0,1fr) auto;
      gap:18px;
      padding:12px 14px;
      background:#f8fafc;
      border:1px solid #eef1f5;
      border-radius:11px;
      margin-bottom:9px;
    }
    .manager-order-item strong{white-space:nowrap}
    .manager-order-note{
      margin:8px 22px 16px;
      padding:13px 14px;
      border-radius:11px;
      background:#fffaf3;
      border:1px solid #fde7c6;
      color:#6b4c24;
    }
    .manager-order-actions{
      display:flex;
      justify-content:flex-end;
      gap:10px;
      padding:15px 22px 20px;
    }
    .manager-order-actions form{margin:0}
    .manager-btn-reject,.manager-btn-approve{
      min-height:44px;
      padding:0 17px;
      border-radius:11px;
      font-weight:850;
      cursor:pointer;
    }
    .manager-btn-reject{
      background:#fff;
      color:#b42318;
      border:1px solid #f4c7c2;
    }
    .manager-btn-approve{
      background:var(--shop-accent);
      color:#fff;
      border:1px solid var(--shop-accent);
    }
    .manager-empty{
      background:#fff;
      border:1px solid var(--shop-border);
      border-radius:18px;
      padding:38px;
      text-align:center;
      color:var(--shop-muted);
    }

    @media(max-width:1000px){
      .manager-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media(max-width:700px){
      .manager-header-top{align-items:flex-start}
      .manager-logo{width:min(280px,70vw);min-height:90px}
      .manager-logo img{max-height:90px}
      .manager-kpis{grid-template-columns:1fr}
      .manager-section-head{align-items:flex-start;flex-direction:column}
      .manager-order-top{grid-template-columns:1fr}
      .manager-order-total{text-align:left}
      .manager-order-actions{justify-content:stretch;flex-direction:column}
      .manager-order-actions form,.manager-order-actions button{width:100%}
    }

    @media(max-width:1050px){
      .company-product-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
      .shop-process{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media(max-width:760px){
      .shop-wrap{padding-left:18px;padding-right:18px}
      .company-shop-top{align-items:flex-start}
      .company-logo-panel{width:min(280px,70vw);min-height:90px}.company-logo-panel img{max-height:90px}
      .company-shop-hero{grid-template-columns:1fr}
      .company-shop-person{min-width:0}
      .company-product-grid{grid-template-columns:1fr}
      .company-product-image{height:290px}
      .shop-process{grid-template-columns:1fr}
      .shop-order-panel{grid-template-columns:1fr}
      .shop-submit-box{min-width:0}
    }
    
    .company-product-visual{position:relative}
    .product-side-image{display:none!important;width:100%;height:100%;object-fit:contain}
    .product-side-image.active{display:block!important}
    .product-view-switch{position:absolute;left:50%;bottom:11px;transform:translateX(-50%);display:flex;gap:4px;padding:4px;background:rgba(255,255,255,.93);border:1px solid var(--shop-border);border-radius:999px;box-shadow:0 6px 18px rgba(15,23,42,.10)}
    .product-view-switch button{border:0;background:transparent;padding:6px 10px;border-radius:999px;font-weight:800;font-size:.75rem;cursor:pointer;color:var(--shop-muted)}
    .product-view-switch button.active{background:var(--shop-primary);color:#fff}
    .company-decoration-structured{display:flex;flex-direction:column;gap:7px}
    .company-decoration-structured>strong{margin-bottom:2px}
    .decoration-line{display:flex;justify-content:space-between;gap:12px;font-size:.82rem}
    .decoration-line span{color:var(--shop-muted)}
    .decoration-line b{text-align:right;font-weight:750}
    .shop-order-panel .shop-submit{
      width:100%!important;min-height:50px!important;border:0!important;border-radius:12px!important;
      background:var(--shop-accent,#ff6b00)!important;color:#fff!important;font-weight:850!important;font-size:1rem!important;
      cursor:pointer!important;display:flex!important;align-items:center!important;justify-content:center!important;
      padding:0 18px!important;box-shadow:0 10px 24px rgba(15,23,42,.11)!important
    }
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

function company_portal_style(array $company): string {
    $primary=color_ok((string)($company['primary_color']??'')) ? (string)$company['primary_color'] : '#152a40';
    $accent=color_ok((string)($company['accent_color']??'')) ? (string)$company['accent_color'] : '#ff6b00';

    return '<style>
    :root{
      --cp-primary:'.e($primary).';
      --cp-accent:'.e($accent).';
      --cp-bg:#f4f6f9;
      --cp-card:#ffffff;
      --cp-text:#172033;
      --cp-muted:#667085;
      --cp-border:#e3e8ef;
      --cp-radius:6px;
      --cp-shadow:none;
    }
    body{background:var(--cp-bg);color:var(--cp-text)}
    .cp-wrap{width:min(1380px,calc(100% - 44px));margin:0 auto}

    .cp-header{
      background:var(--cp-primary);
      color:#fff;
      position:sticky;
      top:0;
      z-index:60;
      box-shadow:none;
      border-bottom:3px solid var(--cp-accent);
    }
    .cp-header-row{
      min-height:90px;
      display:grid;
      grid-template-columns:220px minmax(0,1fr) auto;
      gap:26px;
      align-items:center;
    }
    .cp-logo{display:flex;align-items:center;min-width:0}
    .cp-logo img{max-width:210px;max-height:66px;object-fit:contain;object-position:left center;display:block}
    .cp-logo-text{font-size:1.28rem;font-weight:900;color:#fff}
    .cp-nav{display:flex;align-items:center;gap:5px;min-width:0}
    .cp-nav a{
      color:rgba(255,255,255,.80);
      text-decoration:none;
      padding:10px 12px;
      border-radius:4px;
      font-size:.91rem;
      font-weight:760;
      white-space:nowrap;
    }
    .cp-nav a:hover{background:rgba(255,255,255,.08);color:#fff}
    .cp-nav a.active{background:#fff;color:var(--cp-primary)}
    .cp-user{display:flex;align-items:center;gap:10px}
    .cp-user-meta{text-align:right;line-height:1.2}
    .cp-user-meta strong{display:block;font-size:.9rem;color:#fff}
    .cp-user-meta small{color:rgba(255,255,255,.65)}
    .cp-logout{border:1px solid rgba(255,255,255,.22);background:rgba(255,255,255,.07);color:#fff;border-radius:4px;padding:9px 11px;cursor:pointer;font-weight:750}

    .cp-main{padding:38px 0 90px}
    .cp-hero{
      background:var(--cp-primary);
      color:#fff;
      border-radius:3px;
      padding:36px 38px;
      margin-bottom:24px;
      overflow:hidden;
      position:relative;
    }
    .cp-eyebrow{font-size:.77rem;letter-spacing:.14em;font-weight:900;color:var(--cp-accent);margin-bottom:9px}
    .cp-hero h1{font-size:clamp(2rem,3.5vw,3.35rem);line-height:1.04;color:#fff;margin:0 0 14px;max-width:850px}
    .cp-hero p{font-size:1.04rem;line-height:1.65;color:rgba(255,255,255,.80);max-width:800px;margin:0}
    .cp-role-badge{display:inline-flex;max-width:100%;margin-top:20px;padding:8px 12px;border-radius:3px;background:rgba(255,255,255,.10);border:1px solid rgba(255,255,255,.14);font-size:.85rem;font-weight:800;white-space:normal;overflow-wrap:anywhere}

    .cp-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px;margin:0 0 28px}
    .cp-kpi{background:#fff;border:1px solid var(--cp-border);border-radius:6px;padding:19px;box-shadow:none}
    .cp-kpi .icon{width:40px;height:40px;border-radius:4px;display:grid;place-items:center;background:color-mix(in srgb,var(--cp-primary) 8%,#fff);margin-bottom:13px}.cp-kpi .icon svg{width:20px;height:20px}
    .cp-kpi strong{font-size:1.45rem;display:block;margin-bottom:4px}
    .cp-kpi span{font-size:.86rem;color:var(--cp-muted)}

    .cp-grid-2{display:grid;grid-template-columns:1.1fr .9fr;gap:20px}
    .cp-card{background:#fff;border:1px solid var(--cp-border);border-radius:6px;padding:24px;box-shadow:var(--cp-shadow)}
    .cp-card h2{margin:0 0 7px;font-size:1.45rem}
    .cp-card>p{margin:0 0 18px;color:var(--cp-muted);line-height:1.55}
    .cp-rule-list{display:flex;flex-direction:column;gap:10px}
    .cp-rule{display:flex;justify-content:space-between;gap:20px;padding:12px 0;border-bottom:1px solid #eef1f5}
    .cp-rule:last-child{border-bottom:0}
    .cp-rule span{color:var(--cp-muted)}
    .cp-rule strong{text-align:right}

    .cp-steps{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
    .cp-step{padding:17px;border:1px solid var(--cp-border);border-radius:5px;background:#fafbfc}
    .cp-step-num{width:32px;height:32px;border-radius:3px;background:var(--cp-primary);color:#fff;display:grid;place-items:center;font-weight:900;margin-bottom:11px}
    .cp-step h3{font-size:.96rem;margin:0 0 5px}
    .cp-step p{font-size:.84rem;color:var(--cp-muted);margin:0;line-height:1.45}

    .cp-section-head{display:flex;align-items:end;justify-content:space-between;gap:18px;margin:0 0 20px}
    .cp-section-head h1,.cp-section-head h2{margin:0 0 5px}
    .cp-section-head p{margin:0;color:var(--cp-muted)}

    .cp-order-list{display:flex;flex-direction:column;gap:14px}
    .cp-order{background:#fff;border:1px solid var(--cp-border);border-radius:6px;overflow:hidden;box-shadow:none}
    .cp-order-head{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:18px;padding:18px 20px;border-bottom:1px solid var(--cp-border)}
    .cp-order-title strong{font-size:1.05rem}
    .cp-order-meta{font-size:.86rem;color:var(--cp-muted);margin-top:5px}
    .cp-status{display:inline-flex;padding:6px 9px;border-radius:999px;font-size:.76rem;font-weight:850}
    .cp-status.pending{background:#fff7ed;color:#9a3412}
    .cp-status.approved{background:#ecfdf3;color:#067647}
    .cp-status.rejected{background:#fef3f2;color:#b42318}
    .cp-order-items{padding:12px 20px}
    .cp-order-item{display:flex;justify-content:space-between;gap:18px;padding:9px 11px;border-radius:4px;background:#f8fafc;margin-bottom:7px;font-size:.9rem}
    .cp-order-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;padding:0 20px 18px}
    .cp-order-sum{padding:11px 12px;background:#f7f8fa;border-radius:4px}
    .cp-order-sum span{display:block;font-size:.75rem;color:var(--cp-muted);margin-bottom:3px}
    .cp-order-sum strong{font-size:.92rem}

    .cp-action-row{display:flex;justify-content:flex-end;gap:9px;padding:0 20px 18px}
    .cp-decision-form{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:9px;align-items:end;width:100%}
    .cp-decision-form label{display:grid;gap:6px;color:var(--cp-muted);font-size:.8rem;font-weight:750}
    .cp-decision-form textarea{min-height:64px;max-height:160px;border-radius:4px}
    .cp-btn{min-height:42px;border-radius:4px;padding:0 15px;font-weight:850;cursor:pointer}
    .cp-btn.approve{border:1px solid var(--cp-accent);background:var(--cp-accent);color:#fff}
    .cp-btn.reject{border:1px solid #f4c7c2;background:#fff;color:#b42318}

    .cp-people{display:flex;flex-direction:column;gap:10px}
    .cp-person{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:14px;align-items:center;padding:14px 16px;border:1px solid var(--cp-border);border-radius:5px}
    .cp-person strong{display:block}.cp-person small{color:var(--cp-muted)}
    .cp-person-role{padding:6px 9px;border-radius:999px;background:#f2f4f7;font-size:.77rem;font-weight:850}

    .cp-profile-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .cp-profile-grid label{display:flex;flex-direction:column;gap:7px;font-weight:700}
    .cp-profile-grid input{height:44px;border:1px solid #d8dee8;border-radius:5px;padding:0 12px}
    .cp-save{margin-top:16px;border:0;background:var(--cp-accent);color:#fff;border-radius:4px;padding:12px 17px;font-weight:850;cursor:pointer}

    .cp-products .company-product-grid{margin-top:20px}
    .cp-manager-alert{padding:14px 16px;border-radius:5px;background:color-mix(in srgb,var(--cp-accent) 9%,#fff);border:1px solid color-mix(in srgb,var(--cp-accent) 25%,#fff);margin-bottom:20px}
    .cp-order>.cp-manager-alert{margin:0 20px 18px}

    @media(max-width:1150px){
      .cp-header-row{grid-template-columns:170px minmax(0,1fr) auto}
      .cp-logo img{max-width:160px}
      .cp-nav{overflow:auto}
      .cp-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
      .cp-steps{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media(max-width:820px){
      .cp-wrap{width:min(100% - 28px,1380px)}
      .cp-header{position:relative}
      .cp-header-row{grid-template-columns:1fr auto;min-height:auto;padding:16px 0}
      .cp-nav{grid-column:1/-1;order:3;padding-bottom:4px}
      .cp-user-meta{display:none}
      .cp-main{padding-top:20px}
      .cp-hero{padding:28px}
      .cp-grid-2{grid-template-columns:1fr}
      .cp-order-summary{grid-template-columns:1fr}
    }
    @media(max-width:560px){
      .cp-kpis,.cp-steps,.cp-profile-grid{grid-template-columns:1fr}
      .cp-order-head{grid-template-columns:1fr}
      .cp-action-row{flex-direction:column}
      .cp-action-row form,.cp-action-row button{width:100%}
      .cp-decision-form{grid-template-columns:1fr}
    }
    </style>';
}

function company_portal_header(array $company,array $account,string $section): void {
    $manager=account_is_manager($account);
    $nav=[
        'overview'=>['Übersicht','/'.$company['slug']],
        'produkte'=>['Produkte','/'.$company['slug'].'/produkte'],
        'bestellungen'=>['Meine Bestellungen','/'.$company['slug'].'/bestellungen'],
    ];
    if($manager){
        $nav['freigaben']=['Freigaben','/'.$company['slug'].'/freigaben'];
        $nav['mitarbeiter']=['Mitarbeiter','/'.$company['slug'].'/mitarbeiter'];
    }
    $nav['profil']=['Profil','/'.$company['slug'].'/profil'];

    echo '<header class="cp-header"><div class="cp-wrap cp-header-row">';
    echo '<a class="cp-logo" href="/'.e($company['slug']).'">';
    $companyLogo=yw_company_logo_path($company);
    if($companyLogo!=='') echo '<img src="/'.e($companyLogo).'" alt="'.e($company['legal_name']).'">';
    else echo '<span class="cp-logo-text">'.e($company['legal_name']).'</span>';
    echo '</a>';

    echo '<nav class="cp-nav">';
    foreach($nav as $key=>[$label,$href]){
        echo '<a class="'.($section===$key?'active':'').'" href="'.e($href).'">'.e($label).'</a>';
    }
    echo '</nav>';

    echo '<div class="cp-user"><div class="cp-user-meta"><strong>'.e($account['name']).'</strong><small>'.($manager?'Verantwortlicher':'Mitarbeiter').'</small></div>';
    echo '<form method="post" action="/'.e($company['slug']).'/logout">'.csrf_field().'<button class="cp-logout" type="submit">Abmelden</button></form></div>';
    echo '</div></header>';
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

function company_shop_header(array $company,string $personName): void {
    echo '<header class="company-shop-header"><div class="shop-wrap">';
    echo '<div class="company-shop-top"><div class="company-logo-panel">';
    $companyLogo=yw_company_logo_path($company);
    if($companyLogo!==''){
        echo '<img src="/'.e($companyLogo).'" alt="'.e($company['legal_name']).'">';
    }else{
        echo '<div class="company-logo-fallback">'.e($company['legal_name']).'</div>';
    }
    echo '</div><span class="shop-access-badge">'.wc_public_icon('lock').' Geschützter Mitarbeiter-Shop</span></div>';

    echo '<div class="company-shop-hero"><div>';
    echo '<div class="company-shop-kicker">DEINE FIRMENKOLLEKTION</div>';
    echo '<h1>'.e($company['shop_name']).'</h1>';
    echo '<p>Wähle die für dich passenden Artikel, lege Größe und Menge fest und sende deine Auswahl anschließend zur internen Freigabe. Du siehst ausschließlich Produkte, die für '.e($company['legal_name']).' freigegeben wurden.</p>';
    echo '</div><div class="company-shop-person"><small>Angemeldet als</small><strong>'.e($personName).'</strong></div></div>';
    echo '</div></header>';
}

function company_shop_process(array $company): void {
    $approval=(int)($company['approval_required']??1)===1;

    echo '<section class="shop-process">';
    $steps=[
        ['1','Artikel auswählen','Wähle aus der für dein Unternehmen freigegebenen Kollektion.'],
        ['2','Größe & Menge','Lege für jeden gewünschten Artikel deine Größe und Stückzahl fest.'],
        ['3',$approval?'Zur Freigabe senden':'Bestellung absenden',$approval?'Deine Auswahl geht an den zuständigen Firmenverantwortlichen.':'Deine Bestellung wird ohne zusätzliche interne Freigabe übernommen.'],
        ['4','Produktion & Ausgabe',$approval?'Nach der Freigabe wird die Bestellung für Produktion und Bereitstellung übernommen.':'Die Bestellung wird anschließend für Produktion und Bereitstellung übernommen.'],
    ];

    foreach($steps as [$number,$title,$text]){
        echo '<article class="shop-step"><div class="shop-step-number">'.$number.'</div><h3>'.e($title).'</h3><p>'.e($text).'</p></article>';
    }

    echo '</section>';
    echo '<div class="shop-finance-note"><strong>Deine Preisregel:</strong> '.e(financing_label($company)).'. ';
    if((string)($company['financing_mode']??'company')==='percentage'){
        echo 'Der Eigenanteil wird aus dem jeweiligen Bruttopreis berechnet. Netto-, Brutto-, Mitarbeiter- und Arbeitgeberanteil werden bei jedem Artikel transparent ausgewiesen.';
    }else{
        echo 'Die Firma übernimmt den vollständigen Bruttopreis. Netto- und Bruttopreis bleiben für dich trotzdem transparent sichtbar.';
    }
    echo '</div>';
}

function company_shop_categories(array $products): void {
    $categories=[];
    foreach($products as $p){
        $cid=(int)($p['category_id']??0);
        if($cid) $categories[$cid]=product_category_name($cid,(string)($p['category']??''));
    }

    if(!$categories) return;

    echo '<div class="shop-category-filter"><button type="button" class="active" data-filter="all">Alle Produkte</button>';
    foreach($categories as $id=>$name){
        echo '<button type="button" data-filter="'.(int)$id.'">'.e($name).'</button>';
    }
    echo '</div>';
}

function company_product_card(array $p,array $company,bool $withControls=true): string {
    $sizes=json_decode((string)$p['sizes'],true)?:[];
    $category=product_category_name((int)($p['category_id']??0),(string)($p['category']??''));
    $price=product_price_breakdown($p,$company);
    $displayName=company_product_display_name($p);
    $description=company_product_display_description($p);
    $front=company_product_front_image($p);
    $back=company_product_back_image($p);
    $decorations=company_product_decorations($p);

    $html='<article class="company-product-card product-card" data-category="'.(int)($p['category_id']??0).'">';

    $mockupViews=yw_mockup_views($p,yw_company_motifs_by_id((int)$company['id']));
    if($mockupViews){
        $visual=yw_mockup_html($mockupViews,$displayName,true,'company-product-image');
        if($category!=='')$visual=preg_replace('/^(<div[^>]*>)/','$1<span class="company-category-badge">'.e($category).'</span>',$visual,1);
        $html.=$visual;
    }else{
        $html.='<div class="company-product-image company-product-visual" data-product-visual>';
        if($category!=='') $html.='<span class="company-category-badge">'.e($category).'</span>';
        $html.='<div class="company-product-placeholder product-side-image active" data-side="front">'.wc_public_icon('shirt').'</div>';
        $html.='</div>';
    }

    $html.='<div class="company-product-body">';
    $html.='<div class="company-product-title"><h3>'.e($displayName).'</h3></div>';

    if($description!=='') $html.='<p class="company-product-description">'.e($description).'</p>';

    $html.='<div class="company-product-meta">';
    if(!empty($p['color'])) $html.='<span class="company-meta-pill">Farbe: '.e($p['color']).'</span>';
    if($decorations) $html.='<span class="company-meta-pill">'.count($decorations).' Veredelungsposition'.(count($decorations)===1?'':'en').'</span>';
    if(isset($p['profile_starter_qty']) && (int)$p['profile_starter_qty']>0) $html.='<span class="company-meta-pill">Starterempfehlung: '.(int)$p['profile_starter_qty'].' Stück</span>';
    if(array_key_exists('profile_remaining_qty',$p)) $html.='<span class="company-meta-pill">Noch '.(int)$p['profile_remaining_qty'].' im Budgetjahr</span>';
    $html.='</div>';

    if($decorations){
        $html.='<div class="company-decoration company-decoration-structured"><strong>Deine Veredelung</strong>';
        foreach($decorations as $d){
            $position=decoration_position_label((string)($d['position']??''));
            $method=decoration_method_label((string)($d['method']??''));
            $text=trim((string)($d['text']??''));
            $html.='<div class="decoration-line"><span>'.e($position).'</span><b>'.e($method).($text!==''?' · '.e($text):'').'</b></div>';
        }
        $html.='</div>';
    }elseif(!empty($p['decoration_details'])){
        $html.='<div class="company-decoration"><strong>Veredelung</strong><br>'.nl2br(e($p['decoration_details'])).'</div>';
    }

    $html.='<div class="company-price-breakdown">';
    $html.='<div class="company-price-row"><span>Nettopreis</span><strong>'.money($price['net']).'</strong></div>';
    $html.='<div class="company-price-row"><span>Bruttopreis inkl. '.e(rtrim(rtrim(number_format($price['vat'],2,',','.'),'0'),',')).' % MwSt.</span><strong>'.money($price['gross']).'</strong></div>';
    $html.='<div class="company-price-row employee"><span>Dein Eigenanteil</span><strong>'.money($price['employee']).'</strong></div>';
    $html.='<div class="company-price-row"><span>Arbeitgeber übernimmt</span><strong>'.money($price['employer']).'</strong></div>';
    $html.='</div>';

    if($withControls){
        $maxQty=array_key_exists('profile_remaining_qty',$p)?min(10,max(0,(int)$p['profile_remaining_qty'])):10;
        $html.='<div class="company-product-controls"><select name="size['.(int)$p['id'].']" aria-label="Größe für '.e($displayName).'">';
        $preSize=(string)($p['preselect_size']??'');
        foreach($sizes as $size) $html.='<option value="'.e((string)$size).'"'.((string)$size===$preSize?' selected':'').'>Größe '.e((string)$size).'</option>';
        $qtyId='qty-'.(int)$p['id'];
        $preQty=min($maxQty,max(0,(int)($p['preselect_qty']??0)));
        $html.='</select><div class="shop-stepper"><button type="button" data-qty-step="-1" data-qty-target="'.$qtyId.'" aria-label="Menge verringern" '.($maxQty===0?'disabled':'').'>−</button><input class="shop-qty" id="'.$qtyId.'" type="number" inputmode="numeric" min="0" max="'.$maxQty.'" value="'.$preQty.'" name="qty['.(int)$p['id'].']" data-gross="'.(int)$price['gross'].'" data-employee="'.(int)$price['employee'].'" data-company="'.(int)$price['employer'].'" aria-label="Menge für '.e($displayName).'" '.($maxQty===0?'disabled':'').'><button type="button" data-qty-step="1" data-qty-target="'.$qtyId.'" aria-label="Menge erhöhen" '.($maxQty===0?'disabled':'').'>+</button></div></div>';
        if($maxQty===0)$html.='<p class="shop-limit-reached">Stücklimit für dieses Budgetjahr erreicht.</p>';
    }

    $html.='</div></article>';
    return $html;
}

function company_shop_script(): string {
    // Logik liegt in assets/app.js (CSP ohne 'unsafe-inline').
    return '';
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

    render_head('Login – '.$company['shop_name']);
    echo company_shop_style($company);
    echo '<style>
      body{background:#f4f6f9}
      .portal-login-shell{min-height:100vh;display:grid;grid-template-columns:1.05fr .95fr}
      .portal-login-brand{position:relative;overflow:hidden;background:#102b42;padding:clamp(34px,5vw,68px);display:flex;flex-direction:column;justify-content:space-between;color:#fff}
      .portal-login-brand:after{content:"";position:absolute;left:0;bottom:0;width:8px;height:100%;background:var(--shop-accent)}
      .portal-login-brand>*{position:relative;z-index:1}
      .portal-login-logo{width:min(100%,300px);min-height:72px;display:flex;align-items:center;background:#fff;border-radius:4px;padding:12px 16px}
      .portal-login-logo img{display:block;max-width:100%;max-height:62px;object-fit:contain;object-position:left center}
      .portal-login-logo-text{font-size:1.7rem;line-height:1.08;font-weight:900;color:#112a40}
      .portal-login-brand h1{font-size:clamp(2.6rem,5vw,5rem);line-height:1;margin:0 0 18px;color:#fff}
      .portal-login-brand p{max-width:620px;font-size:1.08rem;line-height:1.65;color:rgba(255,255,255,.78)}
      .portal-login-points{display:grid;gap:10px;margin:26px 0 0;padding:0;list-style:none;max-width:610px}
      .portal-login-points li{display:flex;align-items:center;gap:10px;color:rgba(255,255,255,.88);font-size:.95rem}
      .portal-login-points li:before{content:"";width:8px;height:8px;border-radius:50%;background:var(--shop-accent);box-shadow:0 0 0 5px rgba(255,255,255,.08)}
      .portal-login-powered{font-size:.82rem;color:rgba(255,255,255,.55)}
      .portal-login-form-wrap{display:grid;place-items:center;padding:40px}
      .portal-login-card{width:min(100%,480px);background:#fff;border:1px solid #dce2e7;border-radius:6px;padding:34px;box-shadow:0 16px 40px rgba(15,23,42,.08)}
      .portal-login-card h2{font-size:2rem;margin:0 0 8px}
      .portal-login-card>p{color:#667085;margin:0 0 24px}
      .portal-login-help{display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px 18px;margin:22px 0 0;padding-top:20px;border-top:1px solid #e7ebf0;font-size:.92rem}
      @media(max-width:850px){.portal-login-shell{grid-template-columns:1fr}.portal-login-brand{padding:28px;min-height:auto;gap:46px}.portal-login-brand h1{font-size:clamp(2.35rem,12vw,4rem)}.portal-login-logo{width:min(100%,300px);min-height:78px}.portal-login-powered{display:none}.portal-login-form-wrap{padding:24px 18px 38px}.portal-login-card{padding:28px 24px}}
    </style>';

    echo '<main class="portal-login-shell">';
    echo '<section class="portal-login-brand"><div class="portal-login-logo">';
    $companyLogo=yw_company_logo_path($company);
    if($companyLogo!=='') echo '<img src="/'.e($companyLogo).'" alt="'.e($company['legal_name']).'">';
    else echo '<div class="portal-login-logo-text">'.e($company['legal_name']).'</div>';
    echo '</div><div><div class="company-shop-kicker">MITARBEITER-SHOP</div><h1>'.e($company['shop_name']).'</h1><p>Deine freigegebene Firmenkollektion an einem Ort – klar, einheitlich und nur für dein Team.</p><ul class="portal-login-points"><li>Persönlicher Zugang und eigene Bestellübersicht</li><li>Freigegebene Artikel in verbindlicher Corporate Identity</li><li>Budgets, Mengen und Freigaben automatisch berücksichtigt</li></ul></div>';
    echo '<div class="portal-login-powered">Bereitgestellt über das WorkwearCenter</div></section>';

    echo '<section class="portal-login-form-wrap"><div class="portal-login-card"><h2>Anmelden</h2><p>Mit deinem persönlichen Firmenkonto.</p>';
    if($error) echo '<div class="flash error">'.e($error).'</div>';
    echo '<form method="post" class="form-stack">'.csrf_field()
        .'<label>E-Mail<input type="email" name="email" autocomplete="email" required></label>'
        .'<label>Passwort<input type="password" name="password" autocomplete="current-password" required></label>'
        .'<button class="btn primary" type="submit">Im Shop anmelden</button></form>';
    echo '<div class="portal-login-help"><a href="/'.e($company['slug']).'/passwort-vergessen">Passwort vergessen?</a><span>Noch kein Zugang? <a href="/'.e($company['slug']).'/registrieren">Registrieren</a></span></div>';
    echo '</div></section></main>';

    company_shop_footer($company);
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
    echo company_shop_style($company);
    echo company_portal_style($company);
    company_portal_header($company,$account,$section);
    if(yw_is_preview($account))echo yw_preview_banner($company);

    echo '<main class="cp-main"><div class="cp-wrap">';
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

    echo '</div></main>';
    echo company_shop_script();
    company_shop_footer($company);
    render_foot();
}


function company_portal_overview(array $company,array $account): void {
    $stmt=db()->prepare('SELECT * FROM orders WHERE company_id=? AND (employee_account_id=? OR (employee_account_id IS NULL AND invite_id=?)) ORDER BY id DESC LIMIT 5');
    $stmt->execute([(int)$company['id'],(int)$account['id'],(int)($account['invite_id']??0)]);
    $myOrders=$stmt->fetchAll();

    $myCount=0;
    $myPending=0;
    $myGross=0;
    foreach($myOrders as $o){
        $myCount++;
        if((string)$o['status']==='pending') $myPending++;
        if((string)$o['status']==='rejected') continue; // abgelehnte Bestellungen zählen nicht zum Bestellwert
        $gross=(int)($o['gross_total_cents']??0);
        $myGross+=$gross>0?$gross:(int)$o['total_cents'];
    }

    $manager=account_is_manager($account);
    $budgetRemaining=employee_budget_remaining($company,$account);
    $openApprovals=0;
    if($manager){
        $stmt=db()->prepare("SELECT COUNT(*) FROM orders WHERE company_id=? AND status='pending'");
        $stmt->execute([(int)$company['id']]);
        $openApprovals=(int)$stmt->fetchColumn();
    }

    echo '<section class="cp-hero"><div class="cp-eyebrow">DEIN FIRMENSHOP</div><h1>Hallo '.e($account['name']).'</h1>';
    echo '<p>Willkommen im Mitarbeiter-Shop von '.e($company['legal_name']).'. Hier findest du deine Firmenkollektion, deine Bestellungen und alle für dich relevanten Shopregeln.</p>';
    echo '<span class="cp-role-badge">'.($manager?'Verantwortlicher · erweiterte Rechte':'Mitarbeiterkonto').'</span></section>';

    $equipmentProfile=yw_account_equipment_profile($account);

    // Erste Bestellung? Dann direkt zur Grundausstattung führen.
    $q=db()->prepare("SELECT COUNT(*) FROM orders WHERE company_id=? AND employee_account_id=?");$q->execute([(int)$company['id'],(int)$account['id']]);
    if((int)$q->fetchColumn()===0){
        $hasStarter=false;
        foreach((yw_account_profile_rules($account)??[]) as $rule)if((int)$rule['starter_qty']>0){$hasStarter=true;break;}
        echo '<section class="cp-card cp-first-order"><div><h2>Deine erste Bestellung</h2>';
        echo $hasStarter
            ? '<p>Für dich ist eine Grundausstattung hinterlegt'.($equipmentProfile?' ('.e($equipmentProfile['name']).')':'').'. Mit einem Klick ist alles vorausgewählt – du musst nur noch deine Größen prüfen.</p></div><a class="cp-btn" href="/'.e($company['slug']).'/produkte?starter=1">Grundausstattung bestellen</a>'
            : '<p>Wähle deine Artikel aus der Firmenkollektion. Beim nächsten Mal merkt sich der Shop deine Größen, und du kannst Bestellungen mit einem Klick wiederholen.</p></div><a class="cp-btn" href="/'.e($company['slug']).'/produkte">Zur Kollektion</a>';
        echo '</section>';
    }

    echo '<section class="cp-kpis">';
    echo '<article class="cp-kpi"><div class="icon">'.wc_public_icon('budget').'</div><strong>'.e(financing_label($company)).'</strong><span>Finanzierungsregel</span></article>';
    echo '<article class="cp-kpi"><div class="icon">'.wc_public_icon('box').'</div><strong>'.e(shipping_rule_label($company)).'</strong><span>Versandregel</span></article>';
    echo '<article class="cp-kpi"><div class="icon">'.wc_public_icon('layers').'</div><strong>'.($budgetRemaining===null?$myCount:money($budgetRemaining)).'</strong><span>'.($budgetRemaining===null?'Letzte eigene Bestellungen':'Verfügbares Jahresbudget').'</span></article>';
    echo '<article class="cp-kpi"><div class="icon">'.wc_public_icon($manager?'check':'budget').'</div><strong>'.($manager?$openApprovals:money($myGross)).'</strong><span>'.($manager?'Offene Freigaben':'Bestellwert zuletzt').'</span></article>';
    echo '</section>';

    echo '<div class="cp-grid-2">';
    echo '<section class="cp-card"><h2>So funktioniert deine Bestellung</h2><p>Vom Artikel bis zur Produktion in vier klaren Schritten.</p><div class="cp-steps">';
    $approval=(int)($company['approval_required']??1)===1;
    $steps=[
        ['1','Produkte wählen','Artikel, Größe und Menge auswählen.'],
        ['2','Kosten prüfen','Eigenanteil, Firmenanteil und Versand werden transparent angezeigt.'],
        ['3',$approval?'Freigabe':'Bestellung',$approval?'Die Bestellung geht an den Verantwortlichen.':'Die Bestellung wird direkt übernommen.'],
        ['4','Produktion','Nach Freigabe startet die weitere Bearbeitung.'],
    ];
    foreach($steps as [$n,$title,$text]){
        echo '<article class="cp-step"><div class="cp-step-num">'.$n.'</div><h3>'.e($title).'</h3><p>'.e($text).'</p></article>';
    }
    echo '</div></section>';

    echo '<section class="cp-card"><h2>Shopregeln</h2><p>Die wichtigsten Regeln für deinen Firmen-Shop.</p><div class="cp-rule-list">';
    echo '<div class="cp-rule"><span>Finanzierung</span><strong>'.e(financing_label($company)).'</strong></div>';
    echo '<div class="cp-rule"><span>Versand</span><strong>'.e(shipping_rule_label($company)).'</strong></div>';
    echo '<div class="cp-rule"><span>Freigabe</span><strong>'.($approval?'Erforderlich':'Nicht erforderlich').'</strong></div>';
    if($budgetRemaining!==null) echo '<div class="cp-rule"><span>Jahresbudget verfügbar</span><strong>'.money($budgetRemaining).'</strong></div>';
    if(!empty($account['department'])) echo '<div class="cp-rule"><span>Abteilung</span><strong>'.e($account['department']).'</strong></div>';
    if(!empty($account['cost_center'])) echo '<div class="cp-rule"><span>Kostenstelle</span><strong>'.e($account['cost_center']).'</strong></div>';
    if($equipmentProfile) echo '<div class="cp-rule"><span>Ausstattungsprofil</span><strong>'.e($equipmentProfile['name']).'</strong></div>';
    echo '</div></section></div>';

    if($equipmentProfile){
        echo '<section class="cp-card"><h2>Deine Sollausstattung · '.e($equipmentProfile['name']).'</h2><p>'.e($equipmentProfile['description']?:'Für deine Tätigkeit freigegebene Firmenkleidung im aktuellen Budgetjahr.').'</p>';
        $products=shop_company_products((int)$company['id'],$account);
        $targets=array_filter($products,fn($p)=>$p['profile_target_qty']!==null);
        if($targets){echo '<div class="cp-rule-list">';foreach($targets as $product){$used=yw_quantity_used($company,$account,(int)$product['id']);$target=(int)$product['profile_target_qty'];echo '<div class="cp-rule"><span>'.e(company_product_display_name($product)).' · bestellt/reserviert '.$used.'</span><strong>'.max(0,$target-$used).' von '.$target.' offen</strong></div>';}echo '</div>';}
        else echo '<p class="muted">Für dieses Profil sind Produkte freigegeben, aber keine jährlichen Sollmengen hinterlegt.</p>';
        echo '</section>';
    }
}

function company_portal_products(array $company,array $account): void {
    $products=shop_company_products((int)$company['id'],$account);
    yw_shop_rules_summary($company,$account);

    echo '<div class="cp-section-head"><div><h1>Produkte</h1><p>Deine freigegebene Firmenkollektion. Wähle Größe und Menge und sende die Bestellung anschließend ab.</p></div></div>';

    if(!$products){
        echo '<div class="empty">Für dein Ausstattungsprofil sind derzeit keine Produkte freigeschaltet. Bitte wende dich an deinen Firmenverantwortlichen.</div>';
        return;
    }

    // Zuletzt bestellte Größe je Artikel vorbelegen – spart Zeit und Fehlbestellungen.
    $lastSizes=[];
    $q=db()->prepare("SELECT i.product_id,i.size FROM order_items i JOIN orders o ON o.id=i.order_id WHERE o.company_id=? AND o.employee_account_id=? AND o.status IN ('pending','approved') ORDER BY o.id ASC,i.id ASC");
    $q->execute([(int)$company['id'],(int)$account['id']]);
    foreach($q as $row)$lastSizes[(int)$row['product_id']]=(string)$row['size'];

    // „Wie letztes Mal bestellen“ bzw. Starterpaket: Auswahl vorbelegen (Prüfung erfolgt wie immer serverseitig).
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
    if($presetNote!=='')echo '<div class="cp-manager-alert">'.e($presetNote).'</div>';

    company_shop_categories($products);

    echo '<form method="post" data-order-form>'.csrf_field().'<input type="hidden" name="action" value="order">';
    echo '<div class="company-product-grid cp-products">';
    foreach($products as $p){
        $profileRule=array_key_exists('profile_target_qty',$p)?['yearly_target_qty'=>$p['profile_target_qty']]:null;
        $limit=yw_effective_product_limit($p,$profileRule);
        if($limit!==null)$p['profile_remaining_qty']=max(0,$limit-yw_quantity_used($company,$account,(int)$p['id']));
        $pid=(int)$p['id'];
        $p['preselect_size']=($preset[$pid]['size']??'')!==''?$preset[$pid]['size']:($lastSizes[$pid]??'');
        $p['preselect_qty']=$preset[$pid]['qty']??0;
        echo company_product_card($p,$company,true);
    }
    echo '</div>';

    echo '<section class="shop-order-panel" data-shipping-enabled="'.(shipping_enabled($company)?'1':'0').'" data-shipping-net="'.(int)($company['shipping_net_cents']??0).'" data-shipping-vat="'.e((string)($company['shipping_vat_rate']??19)).'" data-free-shipping="'.(int)($company['free_shipping_gross_cents']??0).'" data-shipping-payer="'.e((string)($company['shipping_payer']??'company')).'">';
    echo '<div><h3>Bestellung abschließen</h3><p>Vor dem Absenden siehst du Warenwert, Versand, deinen Eigenanteil und den Anteil des Unternehmens.</p><label>Hinweis zur Bestellung <small>optional</small><textarea name="note" maxlength="500" placeholder="z. B. gemeinsam mit Abteilung Montage ausliefern"></textarea></label></div>';
    echo '<div class="shop-submit-box"><div class="shop-selection-count"><strong data-selection-count>0</strong> Artikel ausgewählt</div>'
        .'<div class="shop-checkout-lines">'
        .'<div><span>Warenwert brutto</span><strong data-summary-gross>0,00 €</strong></div>'
        .'<div><span>Versand brutto</span><strong data-summary-shipping>0,00 €</strong></div>'
        .'<div class="total"><span>Gesamt brutto</span><strong data-summary-total>0,00 €</strong></div>'
        .'<div class="employee"><span>Dein Anteil gesamt</span><strong data-summary-employee>0,00 €</strong></div>'
        .'<div><span>Firma übernimmt</span><strong data-summary-company>0,00 €</strong></div>'
        .'</div>'
        .'<p class="shop-budget-warning" data-budget-warning hidden>Der Firmenanteil übersteigt dein verfügbares Budget. Bitte die Auswahl verkleinern.</p>'
        .'<button class="shop-submit" type="submit" disabled>'.(((int)($company['approval_required']??1)===1)?'Zur Freigabe senden':'Bestellung absenden').'</button></div>';
    echo yw_checkout_fields($company);
    echo '</section>';
    echo '<div class="shop-mobile-cart" data-mobile-cart><div><strong><span data-selection-count>0</span> Artikel</strong><small>Dein Anteil: <span data-summary-employee>0,00 €</span></small></div><button type="button" data-scroll-checkout>Zur Bestellung</button></div>';
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
            echo '<div class="cp-order-item"><span>'.(int)$item['quantity'].'× '.e($item['product_name']).' · Größe '.e($item['size']).'</span><strong>'.money($unitGross*(int)$item['quantity']).'</strong></div>';
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
            echo '<div class="cp-order-item"><span>'.(int)$item['quantity'].'× '.e($item['product_name']).' · Größe '.e($item['size']).'</span><strong>'.money($unitGross*(int)$item['quantity']).'</strong></div>';
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
