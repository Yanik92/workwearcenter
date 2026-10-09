<?php
declare(strict_types=1);

/*
 * 10.6: Produktbilder mit Logo – automatisch.
 *
 * Bausteine:
 *  - Motivdatei (SVG/PNG) je Veredelungsmotiv einer Firma  → uploads/motifs/
 *  - Ansichten je Stammprodukt: vorne (image_path), hinten, Ärmel
 *  - Druckzonen je Stammprodukt (print_zones_json): wo liegt „Brust links“ usw. auf welchem Bild
 *  - Zusammensetzen im Browser: Foto + Logo an der richtigen Stelle (CSS, kein Server-Rendering,
 *    damit SVG ohne zusätzliche Bibliotheken funktioniert). Korrekturabzug als PNG per Canvas.
 */

const YW_MOCKUP_VIEWS=['front'=>'Vorderansicht','back'=>'Rückansicht','sleeve'=>'Ärmelansicht'];

/** Standardzuordnung Position → Ansicht. */
function yw_zone_default_view(string $position): string {
    return match($position){
        'back_top','back_large','neck'=>'back',
        'sleeve_left','sleeve_right'=>'sleeve',
        default=>'front',
    };
}

/** Standardzonen (Prozent: x/y = Mitte, w = Breite), gelten solange ein Produkt keine eigenen hat. */
function yw_zone_defaults(): array {
    return [
        'front'=>[
            'chest_left'=>['x'=>62,'y'=>30,'w'=>16],
            'chest_right'=>['x'=>38,'y'=>30,'w'=>16],
            'sleeve_left'=>['x'=>86,'y'=>34,'w'=>10],
            'sleeve_right'=>['x'=>14,'y'=>34,'w'=>10],
            'other'=>['x'=>50,'y'=>50,'w'=>24],
        ],
        'back'=>[
            'back_top'=>['x'=>50,'y'=>24,'w'=>30],
            'back_large'=>['x'=>50,'y'=>46,'w'=>48],
            'neck'=>['x'=>50,'y'=>12,'w'=>12],
        ],
        'sleeve'=>[
            'sleeve_left'=>['x'=>50,'y'=>40,'w'=>34],
            'sleeve_right'=>['x'=>50,'y'=>40,'w'=>34],
        ],
    ];
}

/** Druckzonen eines Produkts, bereinigt. Struktur: [view][position] => [x,y,w]. */
function yw_product_zones(array $product): array {
    $raw=trim((string)($product['print_zones_json']??''));
    $zones=[];
    if($raw!==''){
        $decoded=json_decode($raw,true);
        if(is_array($decoded))$zones=yw_zones_sanitize($decoded);
    }
    return $zones;
}

function yw_zones_sanitize(array $input): array {
    $out=[];$positions=decoration_position_options();
    foreach(YW_MOCKUP_VIEWS as $view=>$label){
        if(!is_array($input[$view]??null))continue;
        foreach($input[$view] as $position=>$zone){
            if(!isset($positions[$position])||!is_array($zone))continue;
            $x=(float)($zone['x']??0);$y=(float)($zone['y']??0);$w=(float)($zone['w']??0);
            if($x<1||$x>99||$y<1||$y>99||$w<2||$w>90)continue;
            $out[$view][$position]=['x'=>round($x,1),'y'=>round($y,1),'w'=>round($w,1)];
        }
    }
    return $out;
}

/** Findet für eine Position die Ansicht und Zone: eigene Zonen des Produkts vor Standard. */
function yw_zone_for(array $zones,string $position,array $availableViews): ?array {
    foreach($zones as $view=>$list){
        if(isset($list[$position]) && in_array($view,$availableViews,true))return ['view'=>$view,'zone'=>$list[$position]];
    }
    $defaults=yw_zone_defaults();
    $preferred=yw_zone_default_view($position);
    $order=array_unique([$preferred,'front','back','sleeve']);
    foreach($order as $view){
        if(!in_array($view,$availableViews,true))continue;
        if(isset($defaults[$view][$position]))return ['view'=>$view,'zone'=>$defaults[$view][$position]];
    }
    return null;
}

/** Alle Ansichten eines Produkts mit Bildpfad (nur vorhandene). */
function yw_product_views(array $product): array {
    $views=[];
    $front=yw_local_image_path((string)($product['image_path']??''),['products'],true);
    if($front!=='')$views['front']=$front;
    $back=yw_local_image_path((string)($product['image_back_path']??''),['products']);
    if($back!=='')$views['back']=$back;
    $sleeve=yw_local_image_path((string)($product['image_sleeve_path']??''),['products']);
    if($sleeve!=='')$views['sleeve']=$sleeve;
    return $views;
}

function yw_motif_file_path(array $motif): string {
    $path=trim((string)($motif['file_path']??''));
    return preg_match('#^uploads/motifs/[a-zA-Z0-9][a-zA-Z0-9._-]*\.(?:svg|png)$#D',$path)?$path:'';
}

/**
 * SVG entschärfen: Skripte, Ereignis-Attribute, externe Verweise und eingebettete Fremdinhalte entfernen.
 * Rückgabe: bereinigter SVG-Text oder Ausnahme.
 */
function yw_sanitize_svg(string $raw): string {
    if(strlen($raw)>2*1024*1024)throw new RuntimeException('Die SVG-Datei ist zu groß (maximal 2 MB).');
    if(stripos($raw,'<!DOCTYPE')!==false||stripos($raw,'<!ENTITY')!==false)throw new RuntimeException('Die SVG-Datei enthält nicht erlaubte Definitionen (DOCTYPE/ENTITY).');
    $prev=libxml_use_internal_errors(true);
    $doc=new DOMDocument();
    $ok=$doc->loadXML($raw,LIBXML_NONET);
    libxml_clear_errors();libxml_use_internal_errors($prev);
    if(!$ok||!$doc->documentElement||strtolower($doc->documentElement->localName)!=='svg')throw new RuntimeException('Die Datei ist kein gültiges SVG.');
    $banned=['script','foreignobject','iframe','object','embed','audio','video','animate','set','animatemotion','animatetransform','handler','listener'];
    $xpath=new DOMXPath($doc);
    $remove=[];
    foreach($xpath->query('//*') as $el){
        if(!$el instanceof DOMElement)continue;
        if(in_array(strtolower($el->localName),$banned,true)){$remove[]=$el;continue;}
        $dropAttrs=[];
        foreach(iterator_to_array($el->attributes) as $attr){
            // localName statt nodeName: sonst rutscht z. B. <a x:href="javascript:…"> mit eigenem Präfix durch.
            $name=strtolower((string)($attr->localName??$attr->nodeName));$value=trim((string)$attr->nodeValue);
            $compact=strtolower((string)preg_replace('/[\s\x00-\x1f]+/','',$value));
            if(str_starts_with($name,'on'))$dropAttrs[]=$attr;
            elseif(str_contains($compact,'javascript:')||str_contains($compact,'vbscript:'))$dropAttrs[]=$attr;
            elseif(in_array($name,['href','src'],true)){
                // nur Verweise innerhalb der Datei (#id) oder eingebettete Rasterbilder
                if(!(str_starts_with($value,'#')||preg_match('#^data:image/(png|jpeg|webp);base64,#i',$value)))$dropAttrs[]=$attr;
            }
            elseif($name==='style' && preg_match('/url\s*\(|expression|javascript:|@import/i',$value))$dropAttrs[]=$attr;
        }
        foreach($dropAttrs as $a)$el->removeAttributeNode($a);
    }
    foreach($remove as $el)if($el->parentNode)$el->parentNode->removeChild($el);
    foreach($xpath->query('//*[local-name()="style"]') as $style){
        $css=(string)$style->textContent;
        if(preg_match('/@import|url\s*\(|expression|javascript:/i',$css)&&$style->parentNode)$style->parentNode->removeChild($style);
    }
    $root=$doc->documentElement;
    // Breite/Höhe sicherstellen, sonst kann der Browser das Bild nicht vermessen
    $viewBox=trim((string)$root->getAttribute('viewBox'));
    if(!$root->hasAttribute('width')||!$root->hasAttribute('height')){
        if(preg_match('/^\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)\s*$/',$viewBox,$m)){$root->setAttribute('width',$m[1]);$root->setAttribute('height',$m[2]);}
        else throw new RuntimeException('Das SVG hat weder viewBox noch Breite/Höhe. Bitte aus dem Grafikprogramm mit Zeichenfläche exportieren.');
    }
    if($viewBox===''){
        $w=(float)preg_replace('/[^\d.]/','',(string)$root->getAttribute('width'));$h=(float)preg_replace('/[^\d.]/','',(string)$root->getAttribute('height'));
        if($w>0&&$h>0)$root->setAttribute('viewBox','0 0 '.$w.' '.$h);
    }
    $root->setAttribute('xmlns','http://www.w3.org/2000/svg');
    $xml=$doc->saveXML();
    if($xml===false)throw new RuntimeException('Das SVG konnte nicht gespeichert werden.');
    return $xml;
}

/** Motivdatei (SVG oder PNG) speichern; gibt den relativen Pfad zurück. */
function yw_store_motif_file(?array $file,string $old=''): string {
    if(!$file || (int)($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return $old;
    if((int)$file['error']!==UPLOAD_ERR_OK)throw new RuntimeException('Logo-Upload fehlgeschlagen.');
    $tmp=(string)($file['tmp_name']??'');$size=(int)($file['size']??0);
    if($tmp===''||!is_uploaded_file($tmp))throw new RuntimeException('Logo wurde nicht als gültiger Upload erkannt.');
    if($size<=0||$size>4*1024*1024)throw new RuntimeException('Das Logo darf maximal 4 MB groß sein.');
    $dir=dirname(__DIR__,2).'/uploads/motifs';
    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Logo-Ordner konnte nicht erstellt werden.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $raw=(string)file_get_contents($tmp,false,null,0,2*1024*1024+1);
    $isSvg=$mime==='image/svg+xml'||$mime==='image/svg'||($mime==='text/plain'&&stripos($raw,'<svg')!==false)||($mime==='text/xml'&&stripos($raw,'<svg')!==false)||($mime==='application/xml'&&stripos($raw,'<svg')!==false);
    if($isSvg){
        $clean=yw_sanitize_svg($raw);
        $name='motif-'.bin2hex(random_bytes(8)).'.svg';
        if(file_put_contents($dir.'/'.$name,$clean)===false)throw new RuntimeException('Logo konnte nicht gespeichert werden.');
    }elseif($mime==='image/png'){
        yw_validate_uploaded_raster($tmp,'Logo');
        $name='motif-'.bin2hex(random_bytes(8)).'.png';
        if(!move_uploaded_file($tmp,$dir.'/'.$name))throw new RuntimeException('Logo konnte nicht gespeichert werden.');
    }else throw new RuntimeException('Erlaubt sind SVG oder PNG mit transparentem Hintergrund.');
    if($old!=='')yw_delete_motif_file($old);
    return 'uploads/motifs/'.$name;
}

function yw_delete_motif_file(string $path): void {
    if(!preg_match('#^uploads/motifs/[a-zA-Z0-9][a-zA-Z0-9._-]*\.(?:svg|png)$#D',$path))return;
    $file=dirname(__DIR__,2).'/'.$path;
    if(is_file($file))@unlink($file);
}

/** Produktansicht (hinten/Ärmel) speichern – wie Produktbild, gleicher Ordner. */
function yw_store_product_view(?array $file,string $old,bool $delete): string {
    if($delete){if($old!=='')delete_product_image($old);$old='';}
    $new=upload_product_image($file,$old);
    if($new!==$old&&$old!=='')delete_product_image($old);
    return $new;
}

/** Größenstufen je Veredelung relativ zur Zone. */
function yw_decoration_size_options(): array { return ['s'=>'klein','m'=>'normal','l'=>'groß']; }
function yw_decoration_size_factor(string $size): float { return ['s'=>0.7,'m'=>1.0,'l'=>1.3][$size]??1.0; }

/**
 * Ansichten für den Shop zusammenstellen: je Ansicht Bild + Logo-Ebenen.
 * Eigene Vorschaubilder der Firma (preview_front/back) haben Vorrang und bekommen keine Ebenen.
 * Rückgabe: Liste [ 'view'=>, 'label'=>, 'image'=>, 'layers'=>[ ['src','x','y','w','method','label'] ] ]
 */
function yw_mockup_views(array $product,array $motifsById): array {
    $customFront=yw_local_image_path((string)($product['preview_front_path']??''),['company-products']);
    $customBack=yw_local_image_path((string)($product['preview_back_path']??''),['company-products']);
    $views=yw_product_views($product);
    $zones=yw_product_zones($product);
    $decorations=company_product_decorations($product);
    $result=[];
    $labels=['front'=>t('mockup.front'),'back'=>t('mockup.back'),'sleeve'=>t('mockup.sleeve')]; // 10.12: je Sprache
    if($customFront!=='')$result['front']=['view'=>'front','label'=>$labels['front'],'image'=>$customFront,'layers'=>[],'custom'=>true];
    if($customBack!=='')$result['back']=['view'=>'back','label'=>$labels['back'],'image'=>$customBack,'layers'=>[],'custom'=>true];
    foreach($views as $view=>$image){
        if(isset($result[$view]))continue;
        $result[$view]=['view'=>$view,'label'=>$labels[$view],'image'=>$image,'layers'=>[],'custom'=>false];
    }
    $available=array_keys(array_filter($result,fn($v)=>!$v['custom']));
    foreach($decorations as $d){
        $motif=$motifsById[(int)($d['motif_id']??0)]??null;
        if(!$motif)continue;
        $file=yw_motif_file_path($motif);
        if($file==='')continue;
        $found=yw_zone_for($zones,(string)($d['position']??''),$available);
        if(!$found)continue;
        $z=$found['zone'];$w=$z['w']*yw_decoration_size_factor((string)($d['size']??'m'));
        $result[$found['view']]['layers'][]=['src'=>$file,'x'=>$z['x'],'y'=>$z['y'],'w'=>round($w,1),'method'=>(string)($d['method']??''),'label'=>decoration_position_label((string)($d['position']??'')).' · '.decoration_method_label((string)($d['method']??''))];
    }
    // Ansichten ohne Bild und ohne Logo weglassen, Reihenfolge vorne/hinten/Ärmel
    $ordered=[];
    foreach(['front','back','sleeve'] as $v)if(isset($result[$v]))$ordered[]=$result[$v];
    return $ordered;
}

/** HTML des zusammengesetzten Produktbilds (Shop und Admin-Vorschau). */
function yw_mockup_html(array $views,string $alt,bool $withSwitch=true,string $extraClass=''): string {
    if(!$views)return '';
    $html='<div class="company-product-visual mockup '.e($extraClass).'" data-product-visual>';
    foreach($views as $i=>$v){
        $html.='<div class="mockup-view product-side-image'.($i===0?' active':'').'" data-side="'.e($v['view']).'" data-image="/'.e($v['image']).'">';
        $html.='<img class="mockup-base" src="/'.e($v['image']).'" alt="'.e($alt.' – '.$v['label']).'" loading="lazy" data-mockup-base>';
        // Die Ebenen liegen in einem Rahmen, den app.js exakt auf die sichtbare Bildfläche legt
        // (Prozentwerte beziehen sich so immer auf das Foto, nicht auf den Kasten drumherum).
        $html.='<span class="mockup-layers" data-mockup-layers>';
        foreach($v['layers'] as $layer){
            $html.='<span class="mockup-logo mockup-'.e($layer['method']).'" style="left:'.$layer['x'].'%;top:'.$layer['y'].'%;width:'.$layer['w'].'%" title="'.e($layer['label']).'"><img src="/'.e($layer['src']).'" alt="" loading="lazy"></span>';
        }
        $html.='</span></div>';
    }
    if($withSwitch&&count($views)>1){
        $html.='<div class="product-view-switch">';
        foreach($views as $i=>$v)$html.='<button type="button"'.($i===0?' class="active"':'').' data-show-side="'.e($v['view']).'">'.e($v['label']).'</button>';
        $html.='</div>';
    }
    return $html.'</div>';
}

/** Motive einer Firma nach ID (für Vorschauen). */
function yw_company_motifs_by_id(int $companyId): array {
    $map=[];
    foreach(yw_company_decoration_motifs($companyId,true) as $m)$map[(int)$m['id']]=$m;
    return $map;
}

/* ---------- Admin: Druckzonen-Editor ---------- */

function yw_zone_editor_page(int $productId): void {
    $q=db()->prepare('SELECT * FROM products WHERE id=?');$q->execute([$productId]);$product=$q->fetch();
    if(!$product){echo '<div class="empty">Produkt nicht gefunden.</div>';return;}
    $views=yw_product_views($product);
    $zones=yw_product_zones($product);
    $defaults=yw_zone_defaults();
    $positions=decoration_position_options();
    echo '<section class="card"><div class="title-row"><div><h2>Druckzonen: '.e($product['name']).'</h2><p class="muted">Einmal festlegen, wo auf diesem Artikel „Brust links“, „Rücken groß“ usw. liegen. Das gilt für alle Firmen, die den Artikel bekommen.</p></div><a class="btn" href="/admin?tab=products">Zurück zu den Produkten</a></div>';
    if(!$views){echo '<div class="empty">Bitte zuerst ein Produktbild hochladen.</div></section>';return;}
    echo '<form method="post" class="zone-editor" data-zone-editor>'.csrf_field().'<input type="hidden" name="action" value="save_print_zones"><input type="hidden" name="return_tab" value="products"><input type="hidden" name="product_id" value="'.$productId.'">';
    echo '<input type="hidden" name="zones_json" value="'.e(json_encode($zones?:new stdClass(),JSON_UNESCAPED_UNICODE)).'" data-zone-input>';
    echo '<div class="zone-editor-grid">';
    echo '<div class="zone-editor-side"><label>Ansicht<select data-zone-view>';
    foreach($views as $view=>$img)echo '<option value="'.e($view).'">'.e(YW_MOCKUP_VIEWS[$view]).'</option>';
    echo '</select></label><label>Position<select data-zone-position>';
    foreach($positions as $value=>$label)echo '<option value="'.e($value).'">'.e($label).'</option>';
    echo '</select></label><label>Breite des Logos <small><span data-zone-width-label>16</span> % der Bildbreite</small><input type="range" min="2" max="90" step="0.5" value="16" data-zone-width></label>';
    echo '<p class="muted">So geht’s: Ansicht und Position wählen, dann auf das Bild klicken, wo die Mitte des Logos liegen soll. Mit dem Regler die Breite einstellen. Ein Beispiellogo zeigt die Wirkung.</p>';
    echo '<div class="zone-list" data-zone-list></div>';
    echo '<div class="actions"><button class="btn" type="button" data-zone-remove>Diese Zone entfernen</button><button class="btn primary" type="submit">Druckzonen speichern</button></div></div>';
    echo '<div class="zone-editor-stage">';
    foreach($views as $view=>$img)echo '<div class="zone-stage" data-zone-stage="'.e($view).'"><img src="/'.e($img).'" alt="'.e(YW_MOCKUP_VIEWS[$view]).'" draggable="false"></div>';
    echo '</div></div>';
    echo '<template data-zone-defaults>'.e(json_encode($defaults)).'</template><template data-zone-labels>'.e(json_encode($positions,JSON_UNESCAPED_UNICODE)).'</template>';
    echo '</form></section>';
}

/** Druckzonen speichern (Admin-Aktion). */
function yw_save_print_zones_action(): void {
    $productId=(int)($_POST['product_id']??0);
    $decoded=json_decode((string)($_POST['zones_json']??''),true);
    if(!is_array($decoded))throw new RuntimeException('Die Druckzonen sind ungültig.');
    $zones=yw_zones_sanitize($decoded);
    $stmt=db()->prepare('UPDATE products SET print_zones_json=? WHERE id=?');
    $stmt->execute([$zones?json_encode($zones,JSON_UNESCAPED_UNICODE):null,$productId]);
    audit_log('product.zones_updated','product',$productId,['zones'=>count($zones,COUNT_RECURSIVE)]);
    flash('success','Druckzonen wurden gespeichert.');
    redirect('/admin?tab=products&zones='.$productId);
}

/** Veredelungen, die im Bild nicht gezeigt werden können – mit Grund (für Admin-Hinweise). */
function yw_mockup_missing(array $product,array $motifsById): array {
    $views=yw_product_views($product);$zones=yw_product_zones($product);$missing=[];
    foreach(company_product_decorations($product) as $d){
        $label=decoration_position_label((string)($d['position']??''));
        $motif=$motifsById[(int)($d['motif_id']??0)]??null;
        if(!$motif){$missing[]=$label.': kein Motiv ausgewählt';continue;}
        if(yw_motif_file_path($motif)===''){$missing[]=$label.': Motiv „'.$motif['name'].'“ hat keine Logo-Datei';continue;}
        if(!yw_zone_for($zones,(string)($d['position']??''),array_keys($views))){
            $need=yw_zone_default_view((string)($d['position']??''));
            $missing[]=$label.': dem Stammprodukt fehlt die '.YW_MOCKUP_VIEWS[$need].' (Admin → Produkte)';
        }
    }
    return $missing;
}
