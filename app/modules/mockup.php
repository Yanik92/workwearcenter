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

/**
 * Druckzonen eines Produkts, bereinigt. Struktur: [view][position] => [x,y,w].
 * 10.12.4: Ohne von Hand gespeicherte Zonen werden sie automatisch aus den Produktfotos erkannt
 * und gespeichert (mit Merkmal "_auto"). Ändert sich ein Foto, wird neu erkannt. Von Hand im
 * Editor gespeicherte Zonen bleiben unangetastet.
 */
function yw_product_zones(array $product): array {
    $raw=trim((string)($product['print_zones_json']??''));
    $decoded=$raw!==''?json_decode($raw,true):null;
    if(is_array($decoded)&&!isset($decoded['_auto'])){
        $manual=yw_zones_sanitize($decoded);
        if($manual)return $manual;
    }
    $signature=yw_zone_auto_signature($product);
    if(is_array($decoded)&&is_array($decoded['_auto']??null)&&($decoded['_auto']['sig']??'')===$signature)return yw_zones_sanitize($decoded);
    if(!yw_product_views($product))return [];
    // Höchstens 25 Erkennungen je Seitenaufruf; den Rest erledigt der nächste Aufruf bzw. der Cronjob.
    $GLOBALS['yw_zone_budget']??=25;
    if($GLOBALS['yw_zone_budget']<=0)return is_array($decoded)&&isset($decoded['_auto'])?yw_zones_sanitize($decoded):[];
    $GLOBALS['yw_zone_budget']--;
    $zones=yw_zone_auto_detect($product);
    $id=(int)($product['id']??0);
    if($id>0){
        try{db()->prepare('UPDATE products SET print_zones_json=? WHERE id=?')->execute([json_encode($zones+['_auto'=>['sig'=>$signature,'at'=>date('c')]],JSON_UNESCAPED_UNICODE),$id]);}
        catch(Throwable $e){error_log('Druckzonen speichern: '.$e->getMessage());}
    }
    return $zones;
}

/** 10.24: Bekommt der Artikel ein Logo ins Bild? Schuhe nicht – außer jemand hat im Editor bewusst Zonen gesetzt. */
function yw_product_decoratable(array $product): bool {
    return yw_zone_garment_type($product)!=='none'||!yw_product_zones_are_auto($product);
}

/** Sind die Zonen des Produkts automatisch erkannt (true) oder von Hand gesetzt (false)? */
function yw_product_zones_are_auto(array $product): bool {
    $decoded=json_decode(trim((string)($product['print_zones_json']??'')),true);
    return !is_array($decoded)||isset($decoded['_auto'])||!yw_zones_sanitize($decoded);
}

const YW_ZONE_AUTO_VERSION='3'; // 2: Brust links am Rumpf, Schuhe ohne Zonen; 3: Logo unter die Brusttasche mit Reißverschluss

function yw_zone_auto_signature(array $product): string {
    return md5(YW_ZONE_AUTO_VERSION.'|'.($product['image_path']??'').'|'.($product['image_back_path']??'').'|'.($product['image_sleeve_path']??'').'|'.mb_strtolower((string)($product['category']??'')));
}

/** Art des Artikels aus Kategorie und Name: top (Shirts, Jacken …), head (Caps, Mützen), legs (Hosen), flat (Handtuch, Decke, Schürze …), none (Schuhe – werden nicht veredelt). */
function yw_zone_garment_type(array $product): string {
    $text=mb_strtolower((string)($product['category']??'').' '.(string)($product['name']??''));
    if(preg_match('/((?<!hand)schuh|stiefel|sandale|clog|pantolette|sneaker|boots?\b|footwear|einlegesohle|schnürsenkel)/u',$text))return 'none';
    if(preg_match('/\b(cap|caps|mütze|muetze|beanie|hut|hüte|huete|sunvisor|visor|stirnband)/u',$text))return 'head';
    if(preg_match('/\b(hose|hosen|shorts|jeans|bermuda|latzhose)/u',$text))return 'legs';
    if(preg_match('/\b(handtuch|frottier|decke|decken|schal|schals|tuch|tücher|schürze|schuerze|tasche|beutel|socken|handschuh|schweissband|point of sale)/u',$text))return 'flat';
    return 'top';
}

/**
 * Erkennt die Druckzonen aus den Produktfotos: Freisteller-Umriss suchen (transparenter oder
 * einfarbiger Hintergrund), daraus Rumpf, Schulterlinie und Ärmel ableiten und die üblichen
 * Positionen (Brust, Rücken, Nacken, Ärmel) relativ dazu setzen. Kein Treffer → leere Liste
 * (dann gelten die Standardzonen).
 */
function yw_zone_auto_detect(array $product): array {
    $type=yw_zone_garment_type($product);$zones=[];
    if($type==='none')return [];
    foreach(yw_product_views($product) as $view=>$path){
        $shape=yw_zone_image_shape(dirname(__DIR__,2).'/'.$path);
        if(!$shape){ // z. B. weißes Teil auf weißem Grund: Zonen einer anderen Farbe desselben Artikels übernehmen
            $sibling=yw_zone_sibling_zones($product,$view);
            if($sibling)$zones[$view]=$sibling;
            continue;
        }
        $z=yw_zone_from_shape($shape,$view,$type);
        if($z)$zones[$view]=$z;
    }
    return yw_zones_sanitize($zones);
}

/** Automatisch erkannte Zonen einer anderen Farbe desselben Artikels (gleiche Grund-Artikelnummer). */
function yw_zone_sibling_zones(array $product,string $view): array {
    $sku=strtoupper(trim((string)($product['sku']??'')));$id=(int)($product['id']??0);
    if($sku===''||!function_exists('yw_katalog_base_sku'))return [];
    $base=yw_katalog_base_sku($sku,mb_strtolower((string)($product['color']??'')));
    if($base===$sku)return [];
    try{
        $q=db()->prepare("SELECT print_zones_json FROM products WHERE id<>? AND (sku=? OR sku LIKE ?) AND print_zones_json LIKE '%_auto%' LIMIT 20");
        $q->execute([$id,$base,$base.'-%']);
        foreach($q->fetchAll(PDO::FETCH_COLUMN) as $json){$d=json_decode((string)$json,true);$z=is_array($d)?yw_zones_sanitize($d):[];if(!empty($z[$view]))return $z[$view];}
    }catch(Throwable $e){}
    return [];
}

/** Erkennt fehlende Druckzonen im Hintergrund (Cronjob, Schaltfläche „Alle automatisch erkennen“). */
function yw_zone_auto_backfill(int $limit=200,bool $resetAuto=false): int {
    if($resetAuto)db()->exec("UPDATE products SET print_zones_json=NULL WHERE print_zones_json LIKE '%\\_auto%'");
    $rows=db()->query("SELECT * FROM products WHERE (print_zones_json IS NULL OR print_zones_json='' OR print_zones_json LIKE '%\\_auto%') AND COALESCE(image_path,'')<>'' ORDER BY id")->fetchAll();
    $done=0;$GLOBALS['yw_zone_budget']=$limit;
    foreach($rows as $p){
        if($done>=$limit)break;
        $raw=json_decode((string)($p['print_zones_json']??''),true);
        if(is_array($raw)&&($raw['_auto']['sig']??'')===yw_zone_auto_signature($p))continue;
        yw_product_zones($p);$done++;
    }
    return $done;
}

/**
 * Umriss eines freigestellten Produkts. Rückgabe in Prozent der Bildbreite/-höhe:
 * bbox (l,r,t,b), Zeilenläufe je Zeile, oder null bei unklarem Bild.
 */
function yw_zone_image_shape(string $file): ?array {
    if(!is_file($file)||!function_exists('imagecreatetruecolor'))return null;
    $info=@getimagesize($file);if(!is_array($info))return null;
    [$w,$h,$type]=$info;if($w<20||$h<20||$w*$h>40_000_000)return null;
    $src=match($type){IMAGETYPE_JPEG=>@imagecreatefromjpeg($file),IMAGETYPE_PNG=>@imagecreatefrompng($file),IMAGETYPE_WEBP=>function_exists('imagecreatefromwebp')?@imagecreatefromwebp($file):false,default=>false};
    if(!$src)return null;
    $sw=160;$sh=max(20,(int)round($h*$sw/$w));
    $img=imagecreatetruecolor($sw,$sh);imagealphablending($img,false);imagesavealpha($img,true);
    imagefill($img,0,0,imagecolorallocatealpha($img,0,0,0,127));
    imagecopyresampled($img,$src,0,0,0,0,$sw,$sh,$w,$h);imagedestroy($src);
    $px=[];for($y=0;$y<$sh;$y++)for($x=0;$x<$sw;$x++)$px[$y][$x]=imagecolorat($img,$x,$y);
    imagedestroy($img);
    // Hintergrund aus dem Rand schätzen
    $border=[];
    for($x=0;$x<$sw;$x++){$border[]=$px[0][$x];$border[]=$px[$sh-1][$x];}
    for($y=0;$y<$sh;$y++){$border[]=$px[$y][0];$border[]=$px[$y][$sw-1];}
    $transparent=0;foreach($border as $c)if((($c>>24)&0x7F)>100)$transparent++;
    $alphaMode=$transparent>count($border)*0.6;
    $rs=[];$gs=[];$bs=[];foreach($border as $c){$rs[]=($c>>16)&255;$gs[]=($c>>8)&255;$bs[]=$c&255;}
    sort($rs);sort($gs);sort($bs);$m=intdiv(count($rs),2);$bg=[$rs[$m],$gs[$m],$bs[$m]];
    $isGarment=static function(int $c) use($alphaMode,$bg): bool {
        $a=($c>>24)&0x7F;
        if($alphaMode)return $a<90;
        if($a>100)return false;
        return abs((($c>>16)&255)-$bg[0])+abs((($c>>8)&255)-$bg[1])+abs(($c&255)-$bg[2])>36;
    };
    $runs=[];$count=0;$t=null;$b=null;$l=$sw;$r=-1;
    for($y=0;$y<$sh;$y++){
        $row=[];$start=null;
        for($x=0;$x<=$sw;$x++){
            $on=$x<$sw&&$isGarment($px[$y][$x]);
            if($on){$count++;if($start===null)$start=$x;}
            elseif($start!==null){if($x-$start>=2)$row[]=[$start,$x-1];$start=null;}
        }
        // kleine Lücken (Nähte, Schatten) schließen
        $merged=[];foreach($row as $run){if($merged&&$run[0]-$merged[count($merged)-1][1]<=2)$merged[count($merged)-1][1]=$run[1];else $merged[]=$run;}
        $runs[$y]=$merged;
        if($merged){$t??=$y;$b=$y;$l=min($l,$merged[0][0]);$r=max($r,$merged[count($merged)-1][1]);}
    }
    if($t===null||$b-$t<$sh*0.2||$r-$l<$sw*0.2)return null;
    $coverage=$count/($sw*$sh);
    if($coverage<0.06||$coverage>0.95)return null; // nichts erkannt bzw. Hintergrund nicht trennbar
    // 10.25.1: Helligkeit je Pixel, um Brusttaschen mit Reißverschluss zu erkennen
    $lum=[];for($y=0;$y<$sh;$y++)for($x=0;$x<$sw;$x++){$c=$px[$y][$x];$lum[$y][$x]=$isGarment($c)?(int)(0.3*(($c>>16)&255)+0.59*(($c>>8)&255)+0.11*($c&255)):-1;}
    return ['w'=>$sw,'h'=>$sh,'l'=>$l,'r'=>$r,'t'=>$t,'b'=>$b,'runs'=>$runs,'lum'=>$lum];
}

/** Zonen einer Ansicht aus dem Umriss ableiten (Ergebnis in Prozent). */
function yw_zone_from_shape(array $s,string $view,string $type): array {
    $W=$s['w'];$H=$s['h'];$px=static fn(float $v)=>round($v/$W*100,1);$py=static fn(float $v)=>round($v/$H*100,1);
    $bw=$s['r']-$s['l'];$bh=$s['b']-$s['t'];$cx=($s['l']+$s['r'])/2;
    if($type==='head'){
        $front=['x'=>$px($cx),'y'=>$py($s['t']+$bh*0.38),'w'=>$px($bw*0.3)];
        return $view==='front'?['chest_left'=>$front,'chest_right'=>$front,'other'=>$front]:['back_top'=>['x'=>$px($cx),'y'=>$py($s['t']+$bh*0.45),'w'=>$px($bw*0.3)],'neck'=>['x'=>$px($cx),'y'=>$py($s['t']+$bh*0.6),'w'=>$px($bw*0.2)]];
    }
    if($type==='flat'||$view==='sleeve'){
        if($view==='sleeve'){$z=['x'=>$px($cx),'y'=>$py($s['t']+$bh*0.3),'w'=>$px($bw*0.45)];return ['sleeve_left'=>$z,'sleeve_right'=>$z];}
        return ['other'=>['x'=>$px($cx),'y'=>$py($s['t']+$bh*0.5),'w'=>$px($bw*0.4)],'chest_left'=>['x'=>$px($cx),'y'=>$py($s['t']+$bh*0.3),'w'=>$px($bw*0.3)]];
    }
    // Maße relativ zum Umriss; die Faktoren sind an freigestellten Shirt-, Hoodie- und Jackenfotos
    // (gerade und leicht gedreht) gegen von Hand gesetzte Zonen abgeglichen.
    $at=static fn(float $f)=>$s['l']+$bw*$f;
    if($type==='legs'){
        // Bein oben = linkes Bein des Trägers, vom Betrachter aus das rechte: Mitte des rechten Hosenbeins, oberer Oberschenkel
        $legY=(int)round($s['t']+$bh*0.6);$runs=$s['runs'][$legY]??[];
        $leg=$runs?$runs[count($runs)-1]:null;
        $legX=$leg&&count($runs)>1?($leg[0]+$leg[1])/2:$at(0.75);
        $legW=$leg&&count($runs)>1?$leg[1]-$leg[0]:$bw*0.45;
        $z=['x'=>$px($legX),'y'=>$py($s['t']+$bh*0.3),'w'=>$px($legW*0.4)];
        return $view==='back'?['back_top'=>['x'=>$px($at(0.5)),'y'=>$py($s['t']+$bh*0.08),'w'=>$px($bw*0.3)],'other'=>$z]:['other'=>$z,'chest_left'=>$z];
    }
    // 10.24: Rumpf statt Gesamtumriss messen. Gesucht wird in der unteren Hälfte die schmalste Zeile um die Mitte –
    // dort liegen bei Jacken die Ärmel abgesetzt daneben (am Saum wachsen sie oft mit dem Rumpf zusammen), bei T-Shirts
    // stehen sie oben ab. Vorher landete das Logo bei Jacken und Westen fast auf dem Reißverschluss.
    $torsoL=$s['l']+$bw*0.19;$torsoR=$s['r']-$bw*0.19;$narrow=null;
    for($f=0.5;$f<=0.92;$f+=0.02){
        $best=null;
        foreach($s['runs'][(int)round($s['t']+$bh*$f)]??[] as $run)if($best===null||abs(($run[0]+$run[1])/2-$cx)<abs(($best[0]+$best[1])/2-$cx))$best=$run;
        if($best&&$best[1]-$best[0]>=$bw*0.4&&($narrow===null||$best[1]-$best[0]<$narrow[1]-$narrow[0]))$narrow=$best;
    }
    if($narrow)[$torsoL,$torsoR]=$narrow;
    $tw=$torsoR-$torsoL;$tc=($torsoL+$torsoR)/2;
    // Schulterlinie: erste Zeile, die 90 % so breit ist wie der Rumpf (Kragen und Kapuze sind schmaler)
    $shoulder=$s['t'];
    for($y=$s['t'];$y<=$s['b'];$y++){$runs=$s['runs'][$y]??[];if(!$runs)continue;if($runs[count($runs)-1][1]-$runs[0][0]>=$tw*0.9){$shoulder=$y;break;}}
    $hb=max(1,$s['b']-$shoulder);
    if($view==='back'){
        return [
            'neck'=>['x'=>$px($tc),'y'=>$py($shoulder+$hb*0.03),'w'=>$px($tw*0.15)],
            'back_top'=>['x'=>$px($tc),'y'=>$py($shoulder+$hb*0.13),'w'=>$px($tw*0.5)],
            'back_large'=>['x'=>$px($tc),'y'=>$py($shoulder+$hb*0.34),'w'=>$px($tw*0.7)],
            'other'=>['x'=>$px($tc),'y'=>$py($shoulder+$hb*0.4),'w'=>$px($tw*0.45)],
        ];
    }
    // Brust links: gut die halbe Strecke von der Rumpfmitte zur Seitennaht, knapp ein Fünftel unter der Schulter
    $logoW=min($tw*0.24,$bw*0.15);
    $chestY=$shoulder+$hb*0.18;
    return [
        'chest_left'=>['x'=>$px($tc+$tw*0.27),'y'=>$py(yw_zone_below_pocket($s,$tc+$tw*0.27,$chestY,$logoW,$hb)),'w'=>$px($logoW)],   // Brust links = vom Betrachter aus rechts
        'chest_right'=>['x'=>$px($tc-$tw*0.27),'y'=>$py(yw_zone_below_pocket($s,$tc-$tw*0.27,$chestY,$logoW,$hb)),'w'=>$px($logoW)],
        'sleeve_left'=>['x'=>$px($at(0.9)),'y'=>$py($shoulder+$hb*0.2),'w'=>$px($bw*0.08)],
        'sleeve_right'=>['x'=>$px($at(0.1)),'y'=>$py($shoulder+$hb*0.2),'w'=>$px($bw*0.08)],
        'other'=>['x'=>$px($tc),'y'=>$py($shoulder+$hb*0.4),'w'=>$px($tw*0.45)],
    ];
}

/**
 * 10.25.1: Brusttasche mit Reißverschluss erkennen: In der Spalte des Logos wird um die übliche Höhe nach einer
 * waagerechten Kante gesucht (Reißverschluss, Taschenpatte, Reflexstreifen). Gibt es eine, kommt das Logo darunter.
 * Rückgabe: y der Logomitte in Pixeln des verkleinerten Bilds.
 */
function yw_zone_below_pocket(array $s,float $x0,float $y0,float $logoW,float $hb): float {
    $lum=$s['lum']??null;if(!$lum)return $y0;
    $half=max(3,(int)round($logoW*0.4));// sichtbares Logo ist etwa 80 % der Zonenbreite
    $edge=static function(int $y) use($lum,$x0,$half): float {
        $e=0;$n=0;
        for($x=(int)round($x0-$half*1.2);$x<=(int)round($x0+$half*1.2);$x++){
            $a=$lum[$y][$x]??-1;$b=$lum[$y+1][$x]??-1;
            if($a<0||$b<0)continue;$e+=abs($a-$b);$n++;
        }
        return $n?$e/$n:0.0;
    };
    $bandEnd=null;
    for($y=(int)round($y0-$hb*0.12);$y<=(int)round($y0+$hb*0.22);$y++){
        if($edge($y)>=12)$bandEnd=$y;
        elseif($bandEnd!==null&&$y-$bandEnd>3)break; // erste zusammenhängende Kante genügt
    }
    return $bandEnd===null?$y0:max($y0,$bandEnd+$half+5);
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
    // Gibt es ein Foto der passenden Ansicht, dort zeigen: Rücken → Rückansicht; das Ärmelfoto (Daiber-Seitenansicht)
    // zeigt den linken Ärmel – der rechte bleibt auf der Vorderansicht, damit zwei Ärmel-Logos nicht übereinander liegen
    $preferredView=$position==='sleeve_left'?'sleeve':(yw_zone_default_view($position)==='back'?'back':null);
    if($preferredView!==null&&isset($zones[$preferredView][$position])&&in_array($preferredView,$availableViews,true))return ['view'=>$preferredView,'zone'=>$zones[$preferredView][$position]];
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
    $svgNs='http://www.w3.org/2000/svg';
    if(!$ok||!$doc->documentElement||strtolower($doc->documentElement->localName)!=='svg'||!in_array($doc->documentElement->namespaceURI,[null,'',$svgNs],true))throw new RuntimeException('Die Datei ist kein gültiges SVG.');
    $banned=['script','foreignobject','iframe','object','embed','audio','video','animate','set','animatemotion','animatetransform','handler','listener'];
    $xpath=new DOMXPath($doc);
    // Verarbeitungsanweisungen (z. B. xml-stylesheet mit type="text/xsl") entfernen: Ein XSLT im SVG selbst
    // erzeugt sonst beim direkten Aufruf der Datei neue <script>-Elemente, an allen Prüfungen unten vorbei.
    foreach(iterator_to_array($xpath->query('//processing-instruction()')) as $pi)$pi->parentNode?->removeChild($pi);
    $remove=[];
    foreach($xpath->query('//*') as $el){
        if(!$el instanceof DOMElement)continue;
        // Nur SVG-Elemente behalten (XSLT-, XHTML- und Editor-Elemente wie inkscape:* fliegen raus)
        if(!in_array($el->namespaceURI,[null,'',$svgNs],true)){$remove[]=$el;continue;}
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
            elseif($name==='style' && preg_match('/url\s*\(|image-set|expression|javascript:|@import|\\\\/i',$value))$dropAttrs[]=$attr;
            elseif(preg_match('/url\s*\(\s*[\'"]?\s*(?!#)/i',$value))$dropAttrs[]=$attr; // fill="url(#verlauf)" ja, externe Adressen nein
        }
        foreach($dropAttrs as $a)$el->removeAttributeNode($a);
    }
    foreach($remove as $el)if($el->parentNode)$el->parentNode->removeChild($el);
    foreach($xpath->query('//*[local-name()="style"]') as $style){
        $css=(string)$style->textContent;
        if(preg_match('/@import|url\s*\(|image-set|expression|javascript:|\\\\/i',$css)&&$style->parentNode)$style->parentNode->removeChild($style);
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
        if(!yw_move_upload($tmp,$dir.'/'.$name))throw new RuntimeException('Logo konnte nicht gespeichert werden.');
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
    if(!yw_product_decoratable($product))$decorations=[];
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
    $auto=yw_product_zones_are_auto($product);
    echo '<section class="card"><div class="title-row"><div><h2>Druckzonen: '.e($product['name']).'</h2><p class="muted">'.($auto?'<strong>Automatisch erkannt</strong> aus den Produktfotos – normalerweise ist hier nichts zu tun. Nur wenn eine Position nicht passt: anklicken, verschieben, speichern.':'<strong>Von Hand angepasst.</strong> Diese Zonen werden nicht mehr automatisch verändert.').' Gilt für alle Firmen, die den Artikel bekommen.</p></div><div class="actions">'.($auto?'':'<form method="post">'.csrf_field().'<input type="hidden" name="action" value="auto_print_zones"><input type="hidden" name="return_tab" value="products"><input type="hidden" name="product_id" value="'.$productId.'"><button class="btn" type="submit">Wieder automatisch erkennen</button></form>').'<a class="btn" href="/admin?tab=products">Zurück zu den Produkten</a></div></div>';
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

/** Kurzer Status für die Produktliste. */
function yw_zone_status_text(array $product): string {
    if(!yw_product_views($product))return 'Ohne Foto – es gelten Standardzonen';
    if(!yw_product_zones_are_auto($product))return 'Von Hand angepasst';
    $zones=yw_product_zones($product);
    return $zones?'Automatisch erkannt':'Noch nicht erkannt – es gelten Standardzonen';
}

/** Admin: Zonen eines Produkts (product_id) oder aller Produkte (all=1) wieder automatisch erkennen. */
function yw_auto_print_zones_action(): never {
    @set_time_limit(300);
    if(!empty($_POST['all'])){
        if(!empty($_POST['include_manual']))db()->exec('UPDATE products SET print_zones_json=NULL');
        $n=yw_zone_auto_backfill(2000,true);
        audit_log('product.zones_auto','product',null,['count'=>$n]);
        flash('success','Druckzonen für '.$n.' Produkt(e) automatisch erkannt.');
        redirect('/admin?tab=products');
    }
    $productId=(int)($_POST['product_id']??0);
    db()->prepare('UPDATE products SET print_zones_json=NULL WHERE id=?')->execute([$productId]);
    $q=db()->prepare('SELECT * FROM products WHERE id=?');$q->execute([$productId]);$p=$q->fetch();
    if(!$p)throw new RuntimeException('Produkt wurde nicht gefunden.');
    $GLOBALS['yw_zone_budget']=1;$zones=yw_product_zones($p);
    audit_log('product.zones_auto','product',$productId);
    flash($zones?'success':'error',$zones?'Druckzonen wurden automatisch erkannt.':'Im Foto war kein freigestelltes Produkt zu erkennen – es gelten die Standardzonen. Bitte von Hand festlegen.');
    redirect('/admin?tab=products&zones='.$productId);
}

/** Veredelungen, die im Bild nicht gezeigt werden können – mit Grund (für Admin-Hinweise). */
function yw_mockup_missing(array $product,array $motifsById): array {
    $views=yw_product_views($product);$zones=yw_product_zones($product);$missing=[];
    if(!yw_product_decoratable($product))return company_product_decorations($product)?['Schuhe werden im Bild ohne Logo gezeigt (Druckzone bei Bedarf von Hand setzen)']:[];
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
