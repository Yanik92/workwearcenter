<?php
declare(strict_types=1);
/**
 * Lieferantenkatalog (ab 10.9): Produkte aus der Daiber-SKU-Liste (Excel) anlegen,
 * Bilder aus der Daiber Media Cloud automatisch zuordnen.
 *
 * Ablauf: Excel-Liste → kompakter Katalog (JSON) → Artikel suchen → Farben/Größen wählen → Produkte.
 * Bilder: Dateien aus der Media Cloud (z. B. JN840_navy.jpg) in uploads/katalog/bilder/ legen
 * (Upload oder FTP) → „Bilder zuordnen“ findet sie anhand von Artikelnummer + Farbe.
 */

const YW_KATALOG_PRICE_FACTOR_DEFAULT='2.00';

function yw_katalog_dir(): string { return dirname(__DIR__,2).'/uploads/katalog'; }
function yw_katalog_images_dir(): string { return yw_katalog_dir().'/bilder'; }
/**
 * Die eingelesene Daiber-Liste enthält Händler-Einkaufspreise und darf nie öffentlich
 * abrufbar sein. Sie liegt deshalb im privaten Datenordner (siehe yw_private_dir()),
 * nicht mehr unter uploads/katalog/ (bis 10.9.0). Eine alte Datei wird beim ersten
 * Laden verschoben.
 */
function yw_katalog_file(): string { return yw_private_dir().'/katalog-daiber.json'; }
function yw_katalog_legacy_file(): string { return yw_katalog_dir().'/daiber.json'; }
function yw_katalog_migrate_legacy(): void {
    $old=yw_katalog_legacy_file();
    if(!is_file($old))return;
    try{$new=yw_katalog_file();}catch(Throwable $e){error_log('Katalog: '.$e->getMessage());return;}
    if(!is_file($new)&&@rename($old,$new))return;
    if(is_file($new))@unlink($old);
}
function yw_katalog_bundled_file(): string { return dirname(__DIR__).'/data/katalog-daiber.json'; }

/** Marke anhand der Daiber-Artikelnummer. */
function yw_katalog_brand(string $sku): string {
    $u=strtoupper($sku);
    if(str_starts_with($u,'JN'))return 'James & Nicholson';
    if(str_starts_with($u,'MB'))return 'Myrtle Beach';
    return 'Daiber';
}

/** Lädt den Katalog: hochgeladene Liste zuerst, sonst die mitgelieferte. */
function yw_katalog_load(): ?array {
    static $cache=null;
    if($cache!==null)return $cache?:null;
    yw_katalog_migrate_legacy();
    foreach([yw_katalog_file(),yw_katalog_bundled_file()] as $file){
        if(!is_file($file))continue;
        $data=json_decode((string)file_get_contents($file),true);
        if(is_array($data)&&isset($data['articles'])&&is_array($data['articles'])){$data['file']=$file;$cache=$data;return $data;}
    }
    $cache=false;return null;
}

/* ---------- Excel (xlsx) lesen – ohne Fremdbibliothek ---------- */

function yw_katalog_xlsx_shared_strings(ZipArchive $zip): array {
    $xml=$zip->getFromName('xl/sharedStrings.xml');
    if($xml===false)return [];
    $strings=[];$reader=new XMLReader();
    if(!$reader->XML($xml,'UTF-8',LIBXML_NONET))return [];
    $ok=$reader->read();
    while($ok){
        if($reader->nodeType===XMLReader::ELEMENT&&$reader->localName==='si'){
            $node=$reader->expand();$text='';
            if($node){foreach($node->getElementsByTagName('t') as $t)$text.=$t->textContent;}
            $strings[]=$text;$ok=$reader->next();continue; // next() steht schon auf dem Folgeknoten
        }
        $ok=$reader->read();
    }
    $reader->close();
    return $strings;
}

function yw_katalog_col_index(string $ref): int {
    $letters=preg_replace('/\d+/','',$ref);$n=0;
    foreach(str_split($letters) as $ch)$n=$n*26+(ord($ch)-64);
    return $n-1;
}

/** Liest das erste Tabellenblatt zeilenweise; ruft $onRow(array $cells) je Zeile auf. */
function yw_katalog_xlsx_rows(string $path,callable $onRow): void {
    if(!class_exists('ZipArchive')||!class_exists('XMLReader'))throw new RuntimeException('Der Server kann keine Excel-Dateien lesen (ZipArchive/XMLReader fehlt). Bitte die Liste als CSV speichern.');
    $zip=new ZipArchive();
    if($zip->open($path)!==true)throw new RuntimeException('Die Datei ist keine gültige Excel-Datei (.xlsx).');
    $strings=yw_katalog_xlsx_shared_strings($zip);
    $sheetName=null;
    foreach(['xl/worksheets/sheet1.xml'] as $candidate)if($zip->locateName($candidate)!==false){$sheetName=$candidate;break;}
    if($sheetName===null){for($i=0;$i<$zip->numFiles;$i++){$n=$zip->getNameIndex($i);if(preg_match('#^xl/worksheets/sheet\d+\.xml$#',$n)){$sheetName=$n;break;}}}
    if($sheetName===null){$zip->close();throw new RuntimeException('Kein Tabellenblatt gefunden.');}
    $stream=$zip->getStream($sheetName);
    if(!$stream){$zip->close();throw new RuntimeException('Tabellenblatt konnte nicht gelesen werden.');}
    $tmp=tempnam(sys_get_temp_dir(),'wwc-xlsx');file_put_contents($tmp,$stream);fclose($stream);$zip->close();
    $reader=new XMLReader();
    if(!$reader->open($tmp,'UTF-8',LIBXML_NONET)){@unlink($tmp);throw new RuntimeException('Tabellenblatt konnte nicht geöffnet werden.');}
    try{
        $ok=$reader->read();
        while($ok){
            if($reader->nodeType!==XMLReader::ELEMENT||$reader->localName!=='row'){$ok=$reader->read();continue;}
            $row=$reader->expand();if(!$row){$ok=$reader->next();continue;}
            $cells=[];
            foreach($row->childNodes as $c){
                if($c->nodeName!=='c'&&$c->localName!=='c')continue;
                $ref=(string)$c->getAttribute('r');$type=(string)$c->getAttribute('t');$value='';
                foreach($c->childNodes as $child){
                    if($child->localName==='v')$value=$child->textContent;
                    elseif($child->localName==='is')$value=$child->textContent;
                }
                if($type==='s')$value=$strings[(int)$value]??'';
                $cells[yw_katalog_col_index($ref)]=$value;
            }
            if($cells)$onRow($cells);
            $ok=$reader->next();
        }
    } finally {$reader->close();@unlink($tmp);}
}

/** Näherungsfarbe aus den CMYK-Angaben der Liste (z. B. "C:0/M:0/Y:0/K:100"). */
function yw_katalog_cmyk_hex(string $cmyk): ?string {
    if(!preg_match('/C:\s*(\d+)\s*\/\s*M:\s*(\d+)\s*\/\s*Y:\s*(\d+)\s*\/\s*K:\s*(\d+)/i',$cmyk,$m))return null;
    [$c,$mm,$y,$k]=[(int)$m[1]/100,(int)$m[2]/100,(int)$m[3]/100,(int)$m[4]/100];
    $r=(int)round(255*(1-min(1,$c))*(1-min(1,$k)));$g=(int)round(255*(1-min(1,$mm))*(1-min(1,$k)));$b=(int)round(255*(1-min(1,$y))*(1-min(1,$k)));
    return sprintf('#%02x%02x%02x',$r,$g,$b);
}

/** Bereinigt Daiber-Texte (Excel-Zeilenumbrüche _x000D_). */
function yw_katalog_text(?string $value): string {
    $value=str_replace(["_x000D_\n","_x000D_","\r"],["\n","\n",''],(string)$value);
    return trim(preg_replace("/\n{2,}/","\n",$value));
}

/** Baut aus der Daiber-SKU-Liste den kompakten Katalog. */
function yw_katalog_build_from_xlsx(string $path,string $sourceName=''): array {
    $header=null;$articles=[];$rows=0;
    $need=['Artikelnummer','Farbe','Größe','Artikelbezeichnung','Warengruppe','Preis 1'];
    yw_katalog_xlsx_rows($path,function(array $cells) use (&$header,&$articles,&$rows,$need){
        if($header===null){
            $header=[];foreach($cells as $i=>$v)$header[trim((string)$v)]=$i;
            foreach($need as $col)if(!isset($header[$col]))throw new RuntimeException('Spalte „'.$col.'“ fehlt – ist das die Daiber-SKU-Liste (DAIBER_SKU_DE_EUR_Liste…xlsx)?');
            return;
        }
        $g=static fn(string $col):string=>trim((string)($cells[$header[$col]??-1]??''));
        $sku=strtoupper($g('Artikelnummer'));$color=$g('Farbe');$size=$g('Größe');
        if($sku===''||$color===''||$size==='')return;
        $rows++;
        $price=(float)str_replace(',','.',$g('Preis 1'));
        if(!isset($articles[$sku])){
            $articles[$sku]=[
                'n'=>preg_replace('/\s{2,}/',' ',$g('Artikelbezeichnung')),
                's'=>yw_katalog_text($g('Kurzbeschreibung')),
                'd'=>yw_katalog_text($g('Beschreibung')),
                'c'=>$g('Warengruppe'),
                'm'=>yw_katalog_text($g('Material')),
                'f'=>yw_katalog_text($g('Features')),
                'nw'=>$g('Neu (1=ja/0=nein)')==='1'?1:0,
                'col'=>[],
            ];
        }
        $a=&$articles[$sku];
        if(!isset($a['col'][$color])){
            $img=basename(str_replace('\\','/',$g('Bildverweis-Masterbild')));
            $a['col'][$color]=['de'=>$g('Suchfarbe'),'hex'=>yw_katalog_cmyk_hex($g('Farbwerte CMYK (ca.)')),'mc'=>$g('Multicolor (1=ja/0=nein)')==='1'?1:0,'img'=>$img!==''?$img:null,'sz'=>[],'p'=>$price,'ps'=>[]];
        }
        $c=&$a['col'][$color];
        if(!in_array($size,$c['sz'],true))$c['sz'][]=$size;
        if($price<$c['p']){ // niedrigster Preis = Grundpreis
            foreach($c['sz'] as $s)if(!isset($c['ps'][$s])&&$s!==$size)$c['ps'][$s]=$c['p'];
            $c['p']=$price;
        }
        if(abs($price-$c['p'])>0.001)$c['ps'][$size]=$price; else unset($c['ps'][$size]);
        unset($a,$c);
    });
    if($header===null||!$articles)throw new RuntimeException('Die Liste enthält keine Artikel.');
    ksort($articles,SORT_NATURAL);
    return ['supplier'=>'Daiber','source'=>$sourceName,'generated'=>date('Y-m-d H:i'),'rows'=>$rows,'articles'=>$articles];
}

/* ---------- Suche ---------- */

function yw_katalog_search(array $catalog,string $q,int $limit=60): array {
    $q=trim(mb_strtolower($q));$hits=[];
    foreach($catalog['articles'] as $sku=>$a){
        $sku=(string)$sku;
        $hay=mb_strtolower($sku.' '.$a['n'].' '.$a['c'].' '.($a['s']??''));
        if($q===''||str_contains($hay,$q)||str_contains(mb_strtolower(str_replace(' ','',$sku)),str_replace(' ','',$q))){
            $score=str_starts_with(mb_strtolower($sku),$q)?0:(str_contains(mb_strtolower($a['n']),$q)?1:2);
            $hits[]=[$score,$sku,$a];
        }
    }
    usort($hits,static fn($x,$y)=>[$x[0],$x[1]]<=>[$y[0],$y[1]]);
    return array_slice(array_map(static fn($h)=>[$h[1],$h[2]],$hits),0,$limit);
}

/** Welche Daiber-Produkte (Artikel+Farbe) gibt es schon? */
function yw_katalog_existing(): array {
    $map=[];
    try{foreach(db()->query("SELECT id,sku,color,image_path,active FROM products WHERE supplier_name='Daiber'") as $p){
        $color=mb_strtolower((string)$p['color']);
        $map[yw_katalog_base_sku((string)$p['sku'],$color).'|'.$color]=$p;
    }}catch(Throwable $e){}
    return $map;
}

/**
 * Je Farbe entsteht ein eigenes Produkt. Damit SKU-Prüfung, Produkt-CSV und
 * Beschaffung eindeutig bleiben, bekommt es die Artikelnummer „JN840-NAVY“.
 */
function yw_katalog_color_code(string $color): string { return strtoupper((string)preg_replace('/[^a-z0-9]+/i','',$color)); }
function yw_katalog_product_sku(string $sku,string $color): string { return strtoupper($sku).'-'.yw_katalog_color_code($color); }
function yw_katalog_base_sku(string $productSku,string $color): string {
    $productSku=strtoupper($productSku);$suffix='-'.yw_katalog_color_code($color);
    return $suffix!=='-'&&str_ends_with($productSku,$suffix)?substr($productSku,0,-strlen($suffix)):$productSku;
}

/** Ältere Katalogprodukte (bis 10.9.0) teilen sich eine SKU je Artikel – eindeutig machen. */
function yw_katalog_fix_duplicate_skus(): int {
    $rows=db()->query("SELECT id,sku,color FROM products WHERE supplier_name='Daiber' AND TRIM(COALESCE(sku,''))<>''")->fetchAll();
    $count=[];foreach($rows as $r)$count[strtoupper(trim((string)$r['sku']))]=($count[strtoupper(trim((string)$r['sku']))]??0)+1;
    $update=db()->prepare('UPDATE products SET sku=? WHERE id=?');$fixed=0;
    foreach($rows as $r){
        $sku=strtoupper(trim((string)$r['sku']));
        if(($count[$sku]??0)<2||trim((string)$r['color'])==='')continue;
        $update->execute([yw_katalog_product_sku($sku,(string)$r['color']),(int)$r['id']]);$fixed++;
    }
    return $fixed;
}

function yw_katalog_color_label(string $color): string { return mb_strtoupper(mb_substr($color,0,1)).mb_substr($color,1); }

/* ---------- Bilder ---------- */

function yw_katalog_image_index(): array {
    $dir=yw_katalog_images_dir();$index=[];
    if(!is_dir($dir))return $index;
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));
    foreach($it as $file){
        if(!$file->isFile())continue;
        $name=strtolower($file->getFilename());
        if(!preg_match('/\.(jpe?g|png|webp)$/',$name))continue;
        $index[$name]=$file->getPathname();
    }
    return $index;
}

/** Verkleinert ein Katalogbild und legt es als Produktbild ab. Gibt den relativen Pfad zurück. */
function yw_katalog_store_image(string $source): string {
    $info=@getimagesize($source);
    if(!is_array($info))throw new RuntimeException('Bild nicht lesbar: '.basename($source));
    [$w,$h,$type]=$info;
    $dir=dirname(__DIR__,2).'/uploads/products';
    if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Produktbild-Ordner konnte nicht erstellt werden.');
    $max=1400;
    if(function_exists('imagecreatefromjpeg')&&$w*$h<=40_000_000){
        if($w*$h>8_000_000)@ini_set('memory_limit','512M');
        $img=match($type){IMAGETYPE_JPEG=>@imagecreatefromjpeg($source),IMAGETYPE_PNG=>@imagecreatefrompng($source),IMAGETYPE_WEBP=>function_exists('imagecreatefromwebp')?@imagecreatefromwebp($source):false,default=>false};
        if($img){
            $scale=min(1,$max/max($w,$h));
            $nw=max(1,(int)round($w*$scale));$nh=max(1,(int)round($h*$scale));
            $out=imagecreatetruecolor($nw,$nh);
            if($type===IMAGETYPE_PNG){imagealphablending($out,false);imagesavealpha($out,true);}
            else{imagefill($out,0,0,imagecolorallocate($out,255,255,255));}
            imagecopyresampled($out,$img,0,0,0,0,$nw,$nh,$w,$h);
            $ext=$type===IMAGETYPE_PNG?'png':'jpg';
            $name='product-'.bin2hex(random_bytes(8)).'.'.$ext;
            $ok=$ext==='png'?imagepng($out,$dir.'/'.$name,7):imagejpeg($out,$dir.'/'.$name,86);
            imagedestroy($out);imagedestroy($img);
            if($ok)return 'uploads/products/'.$name;
        }
    }
    // Rückfall: unverändert übernehmen, wenn die Datei klein genug ist
    if(filesize($source)>8*1024*1024)throw new RuntimeException('Bild zu groß und konnte nicht verkleinert werden: '.basename($source));
    $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][(string)($info['mime']??'')]??null;
    if(!$ext)throw new RuntimeException('Bildformat nicht erlaubt: '.basename($source));
    $name='product-'.bin2hex(random_bytes(8)).'.'.$ext;
    if(!copy($source,$dir.'/'.$name))throw new RuntimeException('Bild konnte nicht kopiert werden.');
    return 'uploads/products/'.$name;
}

/** Ordnet allen Daiber-Produkten ohne Bild die passende Datei zu. */
function yw_katalog_assign_images(): array {
    $index=yw_katalog_image_index();$assigned=0;$missing=[];
    if(!$index)return ['assigned'=>0,'missing'=>[],'files'=>0,'unknown'=>[]];
    $images=yw_katalog_image_map(true);
    $products=db()->query("SELECT id,sku,color,name FROM products WHERE supplier_name='Daiber' AND (image_path IS NULL OR image_path='')")->fetchAll();
    $update=db()->prepare('UPDATE products SET image_path=? WHERE id=?');
    foreach($products as $p){
        $base=yw_katalog_base_sku((string)$p['sku'],mb_strtolower((string)$p['color']));
        $file=yw_katalog_find_image($base,(string)$p['color']);
        if($file===null){$missing[]=yw_katalog_expected_filename($base,(string)$p['color']);continue;}
        try{$path=yw_katalog_store_image($file);$update->execute([$path,(int)$p['id']]);$assigned++;}
        catch(Throwable $e){$missing[]=basename($file).' ('.$e->getMessage().')';}
    }
    if($assigned)audit_log('product.images_assigned','product',null,['count'=>$assigned]);
    return ['assigned'=>$assigned,'missing'=>$missing,'files'=>count($index),'unknown'=>$images['unknown']];
}

/** Vergleichsschlüssel für Bilddateien: klein, ohne Endung, alle Trennzeichen als „_“ (JN010_Black-Red (1).JPG → jn010_black_red_1). */
function yw_katalog_image_key(string $name): string {
    $name=basename(str_replace('\\','/',$name));
    $name=(string)preg_replace('/\.(jpe?g|png|webp)$/i','',$name);
    $name=strtr(mb_strtolower($name),['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss']);
    return trim((string)preg_replace('/[^a-z0-9]+/','_',$name),'_');
}

/** Sicherer Dateiname für den Bilderordner (Umlaute, Leerzeichen, Klammern werden ersetzt). */
function yw_katalog_safe_filename(string $name): ?string {
    if(!preg_match('/\.(jpe?g|png|webp)$/i',$name,$m))return null;
    $key=yw_katalog_image_key($name);
    return $key===''?null:$key.'.'.strtolower($m[1]);
}

/**
 * Ordnet die Dateien im Bilderordner den Katalogfarben zu: Schlüssel „artikel_farbe“ → Datei.
 * Erkannt wird der längste passende Anfang des Dateinamens, damit z. B. JN010_black_red.jpg zur
 * Farbe black/red gehört und nicht zu black, und JN840_navy_front.jpg oder „JN840_navy (1).jpg“
 * trotzdem zu navy. Bei mehreren Dateien gewinnt der genaue Name, sonst der kürzeste.
 * Rückgabe: ['map'=>[schlüssel=>pfad], 'unknown'=>[dateinamen ohne Katalogtreffer]].
 */
function yw_katalog_image_map(bool $fresh=false): array {
    static $cache=null;
    if($cache!==null&&!$fresh)return $cache;
    $known=[];
    $catalog=yw_katalog_load();
    if($catalog)foreach($catalog['articles'] as $sku=>$a)foreach($a['col'] as $color=>$c){
        $known[yw_katalog_image_key($sku.'_'.$color)]=true;
        if(!empty($c['img']))$known[yw_katalog_image_key((string)$c['img'])]=true;
    }
    $map=[];$rank=[];$unknown=[];
    foreach(yw_katalog_image_index() as $name=>$path){
        $parts=explode('_',yw_katalog_image_key($name));$hit=null;
        for($n=count($parts);$n>=2;$n--){$try=implode('_',array_slice($parts,0,$n));if(isset($known[$try])){$hit=$try;break;}}
        if($hit===null){$unknown[]=$name;continue;}
        $r=[count($parts)-$n,strlen($name)];
        if(!isset($rank[$hit])||$r<$rank[$hit]){$rank[$hit]=$r;$map[$hit]=$path;}
    }
    sort($unknown);
    return $cache=['map'=>$map,'unknown'=>$unknown];
}

/** Bilddatei für Artikel + Farbe aus dem Bilderordner oder null. */
function yw_katalog_find_image(string $sku,string $color): ?string {
    $map=yw_katalog_image_map()['map'];
    $key=yw_katalog_image_key($sku.'_'.$color);
    if(isset($map[$key]))return $map[$key];
    $catalog=yw_katalog_load();$a=$catalog['articles'][strtoupper($sku)]??null;
    if($a)foreach($a['col'] as $name=>$c)if(mb_strtolower((string)$name)===mb_strtolower($color)&&!empty($c['img'])){$k=yw_katalog_image_key((string)$c['img']);if(isset($map[$k]))return $map[$k];}
    return null;
}

/** Dateiname, unter dem Daiber das Masterbild führt (für Hinweise „Bild fehlt“). */
function yw_katalog_expected_filename(string $sku,string $color): string {
    $catalog=yw_katalog_load();$a=$catalog['articles'][strtoupper($sku)]??null;
    if($a)foreach($a['col'] as $name=>$c)if(mb_strtolower((string)$name)===mb_strtolower($color)&&!empty($c['img']))return (string)$c['img'];
    return strtoupper($sku).'_'.str_replace(['/',' '],'_',mb_strtolower($color)).'.jpg';
}

/** Entpackt ein ZIP mit Katalogbildern in den Bilderordner (nur Bilddateien, flach). */
function yw_katalog_import_zip(string $tmp): int {
    if(!class_exists('ZipArchive'))throw new RuntimeException('ZIP-Dateien werden auf diesem Server nicht unterstützt – bitte Bilder einzeln hochladen oder per FTP ablegen.');
    $zip=new ZipArchive();if($zip->open($tmp)!==true)throw new RuntimeException('ZIP-Datei konnte nicht geöffnet werden.');
    $dir=yw_katalog_images_dir();if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Bilderordner konnte nicht erstellt werden.');
    $count=0;
    for($i=0;$i<$zip->numFiles&&$i<5000;$i++){
        $name=(string)$zip->getNameIndex($i);$base=basename(str_replace('\\','/',$name));
        if(str_starts_with($base,'._')||str_contains($name,'__MACOSX/'))continue; // macOS-Begleitdateien
        $safe=yw_katalog_safe_filename($base);if($safe===null)continue;
        $stat=$zip->statIndex($i);if(!$stat||($stat['size']??0)>40*1024*1024)continue;
        $stream=$zip->getStream($name);if(!$stream)continue;
        $target=$dir.'/'.$safe;
        file_put_contents($target,$stream);fclose($stream);
        try{yw_validate_uploaded_raster($target,'Bild '.$base);}catch(Throwable $e){@unlink($target);continue;}
        $count++;
    }
    $zip->close();
    return $count;
}

/* ---------- Produkte anlegen ---------- */

function yw_katalog_create_products(string $sku,array $colors,array $sizesPerColor,string $priceMode,float $factor,int $fixedCents,int $categoryId,string $categoryName,bool $assignImages=true): array {
    $catalog=yw_katalog_load();if(!$catalog)throw new RuntimeException('Kein Katalog geladen.');
    $sku=strtoupper(trim($sku));$a=$catalog['articles'][$sku]??null;
    if(!$a)throw new RuntimeException('Artikel '.$sku.' ist nicht im Katalog.');
    if(!$colors)throw new RuntimeException('Bitte mindestens eine Farbe auswählen.');
    if($priceMode==='factor'&&($factor<1||$factor>10))throw new RuntimeException('Der Preisfaktor muss zwischen 1,0 und 10 liegen.');
    if($priceMode==='fixed'&&$fixedCents<=0)throw new RuntimeException('Bitte einen festen Verkaufspreis angeben.');
    $existing=yw_katalog_existing();$index=$assignImages?yw_katalog_image_index():[];
    $description=trim(($a['s']!==''?$a['s']."\n\n":'').$a['d'].($a['m']!==''?"\n\nMaterial: ".$a['m']:''));
    $brand=yw_katalog_brand($sku);
    $insert=db()->prepare('INSERT INTO products(name,category_id,category,description,price_cents,purchase_price_cents,vat_rate,sizes,color,image_path,decoration_type,decoration_details,sku,brand,supplier_name,active) VALUES(?,?,?,?,?,?,19,?,?,?,?,?,?,?,?,1)');
    $created=[];$skipped=[];$withImage=0;
    db()->beginTransaction();
    try{
        foreach($colors as $color){
            $c=$a['col'][$color]??null;if(!$c)throw new RuntimeException('Farbe '.$color.' gibt es für '.$sku.' nicht.');
            $label=yw_katalog_color_label($color);
            if(isset($existing[$sku.'|'.mb_strtolower($label)])){$skipped[]=$label;continue;}
            $sizes=array_values(array_intersect($c['sz'],$sizesPerColor[$color]??$c['sz']));
            if(!$sizes)$sizes=$c['sz'];
            // Übergrößen kosten bei Daiber teils mehr. Ein Produkt hat einen Preis – deshalb
            // gilt der höchste Einkaufspreis der gewählten Größen, damit nichts unter Plan verkauft wird.
            $cost=(float)$c['p'];foreach($sizes as $sz)$cost=max($cost,(float)($c['ps'][$sz]??0));
            $purchase=(int)round($cost*100);
            $sale=$priceMode==='fixed'?$fixedCents:(int)(ceil($purchase*$factor/10)*10); // auf 10 Cent aufrunden
            $image=null;
            if($index&&($file=yw_katalog_find_image($sku,(string)$color))!==null){try{$image=yw_katalog_store_image($file);$withImage++;}catch(Throwable $e){$image=null;}}
            $insert->execute([$a['n'],$categoryId?:null,$categoryName,$description,$sale,$purchase,json_encode($sizes,JSON_UNESCAPED_UNICODE),$label,$image,'','',yw_katalog_product_sku($sku,$label),$brand,'Daiber']);
            $created[]=['id'=>(int)db()->lastInsertId(),'color'=>$label,'sale'=>$sale,'purchase'=>$purchase,'image'=>$image!==null];
        }
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    foreach($created as $p)audit_log('product.created','product',$p['id'],['source'=>'katalog','sku'=>$sku,'color'=>$p['color']]);
    return ['created'=>$created,'skipped'=>$skipped,'with_image'=>$withImage];
}

/* ---------- Admin ---------- */

function yw_admin_katalog_action(string $action): void {
    if($action==='katalog_upload_list'){
        $file=$_FILES['katalog_xlsx']??null;
        if(!$file||(int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Bitte die Daiber-SKU-Liste (.xlsx) auswählen. Hinweis: Die Datei darf höchstens '.ini_get('upload_max_filesize').' groß sein.');
        if(!is_uploaded_file((string)$file['tmp_name']))throw new RuntimeException('Ungültiger Upload.');
        @set_time_limit(300);@ini_set('memory_limit','512M');
        $catalog=yw_katalog_build_from_xlsx((string)$file['tmp_name'],mb_substr((string)$file['name'],0,120));
        if(file_put_contents(yw_katalog_file(),json_encode($catalog,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))===false)throw new RuntimeException('Katalog konnte nicht gespeichert werden.');
        audit_log('katalog.updated','system',null,['source'=>$catalog['source'],'articles'=>count($catalog['articles'])]);
        flash('success','Daiber-Liste eingelesen: '.count($catalog['articles']).' Artikel aus '.$catalog['rows'].' Varianten.');redirect('/admin?tab=katalog');
    }
    if($action==='katalog_create'){
        $sku=(string)($_POST['sku']??'');$colors=array_values(array_filter(array_map('strval',(array)($_POST['colors']??[]))));
        $sizes=[];$rawSizes=(array)($_POST['sizes']??[]);
        if($rawSizes&&array_is_list($rawSizes)){$flat=array_map('strval',$rawSizes);foreach($colors as $color)$sizes[$color]=$flat;}
        else foreach($rawSizes as $color=>$list)$sizes[(string)$color]=array_map('strval',(array)$list);
        $mode=($_POST['price_mode']??'factor')==='fixed'?'fixed':'factor';
        $factor=(float)str_replace(',','.',(string)($_POST['price_factor']??YW_KATALOG_PRICE_FACTOR_DEFAULT));
        $fixed=$mode==='fixed'?yw_money_input((string)($_POST['price_fixed']??'0')):0;
        $categoryId=(int)($_POST['category_id']??0);$categoryName=trim((string)($_POST['category_name']??''));
        if($categoryId>0){$q=db()->prepare('SELECT name FROM product_categories WHERE id=?');$q->execute([$categoryId]);$categoryName=(string)$q->fetchColumn();if($categoryName==='')throw new RuntimeException('Kategorie nicht gefunden.');}
        elseif($categoryName!==''){db()->prepare('INSERT INTO product_categories(name,sort_order) VALUES(?,0)')->execute([mb_substr($categoryName,0,120)]);$categoryId=(int)db()->lastInsertId();}
        else throw new RuntimeException('Bitte eine Kategorie wählen.');
        if($mode==='factor'){db()->prepare("INSERT INTO site_settings(setting_key,setting_value) VALUES('katalog_price_factor',?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)")->execute([number_format($factor,2,'.','')]);}
        $result=yw_katalog_create_products($sku,$colors,$sizes,$mode,$factor,$fixed,$categoryId,$categoryName);
        $msg=count($result['created']).' Produkt(e) für '.strtoupper($sku).' angelegt'.($result['with_image']?' ('.$result['with_image'].' mit Bild)':'').'.';
        if($result['skipped'])$msg.=' Übersprungen, weil schon vorhanden: '.implode(', ',$result['skipped']).'.';
        if($result['created']&&!$result['with_image'])$msg.=' Noch ohne Bild – Bilder unten hochladen und zuordnen.';
        flash('success',$msg);redirect('/admin?tab=katalog&artikel='.rawurlencode(strtoupper($sku)));
    }
    if($action==='katalog_images_upload'){
        // Mit ajax=1 schickt app.js große Auswahlen in Paketen; Antwort dann als JSON.
        $ajax=($_POST['ajax']??'')==='1';
        @set_time_limit(300);
        $files=$_FILES['katalog_images']??null;$count=0;$problems=[];
        try{
            if(!$files||!is_array($files['name']??null))throw new RuntimeException('Bitte Bilddateien oder eine ZIP-Datei auswählen.');
            $dir=yw_katalog_images_dir();if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Bilderordner konnte nicht erstellt werden.');
            foreach($files['name'] as $i=>$name){
                $name=basename(str_replace('\\','/',(string)$name));$error=(int)$files['error'][$i];
                if($error===UPLOAD_ERR_NO_FILE)continue;
                if($error!==UPLOAD_ERR_OK){$problems[]=$name.': '.upload_error_message($error,'Datei');continue;}
                $tmp=(string)$files['tmp_name'][$i];if(!is_uploaded_file($tmp))continue;
                if(preg_match('/\.zip$/i',$name)){$count+=yw_katalog_import_zip($tmp);continue;}
                $safe=yw_katalog_safe_filename($name);
                if($safe===null){$problems[]=$name.': kein JPG, PNG oder WebP';continue;}
                try{yw_validate_uploaded_raster($tmp,'Bild '.$name);}catch(RuntimeException $e){$problems[]=$e->getMessage();continue;}
                if(move_uploaded_file($tmp,$dir.'/'.$safe))$count++;
            }
            if($count===0)throw new RuntimeException('Keine Bilddatei übernommen'.($problems?': '.implode(' · ',array_slice($problems,0,5)):' (erlaubt: JPG, PNG, WebP oder ZIP).'));
        }catch(RuntimeException $e){
            if(!$ajax)throw $e;
            header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>false,'error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);exit;
        }
        $r=yw_katalog_assign_images();
        if($ajax){header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>true,'files'=>$count,'assigned'=>$r['assigned'],'problems'=>$problems],JSON_UNESCAPED_UNICODE);exit;}
        flash('success',yw_katalog_images_message($count,$r,$problems));redirect('/admin?tab=katalog#bilder');
    }
    if($action==='katalog_images_assign'){
        @set_time_limit(300);
        $r=yw_katalog_assign_images();
        $summary=mb_substr(trim((string)($_POST['upload_summary']??'')),0,1500);
        flash($r['assigned']||$summary!==''?'success':'error',$summary!==''?$summary.yw_katalog_images_message(null,$r,[],false):yw_katalog_images_message(null,$r,[]));
        redirect('/admin?tab=katalog#bilder');
    }
}

/** Höchstgröße eines Upload-Pakets in Bytes (etwas unter post_max_size und upload_max_filesize). */
function yw_katalog_upload_budget(): int {
    $bytes=static function(string $v): int{$v=trim($v);if($v==='')return 0;$n=(float)$v;return (int)match(strtolower(substr($v,-1))){'g'=>$n*1073741824,'m'=>$n*1048576,'k'=>$n*1024,default=>$n};};
    $post=$bytes((string)ini_get('post_max_size'));
    $limit=$post>0?$post:8*1048576;
    return max(512*1024,(int)($limit*0.9)-64*1024);
}

/** Rückmeldung nach Upload oder Zuordnung: was übernommen wurde, was fehlt und welche Dateien unbekannt sind. */
function yw_katalog_images_message(?int $uploaded,array $r,array $problems,bool $counts=true): string {
    $msg='';
    if($counts)$msg=($uploaded!==null?$uploaded.' Bilddatei(en) übernommen, ':$r['files'].' Dateien im Bilderordner, ').$r['assigned'].' Produkt(e) neu zugeordnet.';
    if($r['missing'])$msg.=' Noch ohne Bild: '.implode(', ',array_slice($r['missing'],0,10)).(count($r['missing'])>10?' … (+'.(count($r['missing'])-10).')':'').'.';
    if(!empty($r['unknown']))$msg.=' Keinem Katalogartikel zuzuordnen: '.implode(', ',array_slice($r['unknown'],0,8)).(count($r['unknown'])>8?' … (+'.(count($r['unknown'])-8).')':'').' – der Name muss mit Artikelnummer_Farbe beginnen.';
    if($problems)$msg.=' Nicht übernommen: '.implode(' · ',array_slice($problems,0,5)).(count($problems)>5?' …':'').'.';
    return $msg;
}

function yw_katalog_swatch(array $c): string {
    $hex=(string)($c['hex']??'');$style='';
    if(!empty($c['mc'])&&$hex!=='')$style='background:linear-gradient(135deg,'.e($hex).' 0 55%,#f4f6f9 55% 100%)'; // zweifarbig: Hauptfarbe + Kontrast
    elseif(!empty($c['mc']))$style='background:linear-gradient(135deg,#17324d 0 55%,#f4f6f9 55% 100%)';
    elseif($hex!=='')$style='background:'.e($hex);
    else $style='background:repeating-linear-gradient(45deg,#e5e7eb 0 6px,#f8fafc 0 12px)';
    return '<span class="kswatch" style="'.$style.'"></span>';
}

function yw_katalog_article_min_price(array $a): float { $min=null;foreach($a['col'] as $c)$min=$min===null?(float)$c['p']:min($min,(float)$c['p']);return (float)($min??0); }

function yw_admin_katalog_page(): void {
    $catalog=yw_katalog_load();
    $q=trim((string)($_GET['q']??''));$artikel=strtoupper(trim((string)($_GET['artikel']??'')));
    $factorSetting=current_site_setting('katalog_price_factor',YW_KATALOG_PRICE_FACTOR_DEFAULT);
    $categories=db()->query('SELECT id,name FROM product_categories ORDER BY sort_order,name')->fetchAll();
    $existing=yw_katalog_existing();$imageIndex=yw_katalog_image_index();
    $daiberCount=count($existing);$withoutImage=0;foreach($existing as $p)if(empty($p['image_path']))$withoutImage++;
    $backToSearch='/admin?tab=katalog'.($q!==''?'&q='.rawurlencode($q):'');

    /* ---- Kopf mit Suche ---- */
    echo '<section class="card katalog-head"><div class="katalog-head-main"><div class="eyebrow">Daiber · James &amp; Nicholson · Myrtle Beach</div><h2>Artikel aus dem Daiber-Katalog übernehmen</h2>';
    if(!$catalog){
        echo '<p class="muted">Noch keine Daiber-Liste vorhanden. Die Liste gibt es im Daiber-Händlerbereich unter <em>Preislisten</em> (Datei „DAIBER_SKU_DE_EUR_Liste_….xlsx“) – unten einlesen.</p></div></section>';
    } else {
        echo '<form method="get" class="katalog-search" action="/admin"><input type="hidden" name="tab" value="katalog"><input type="search" name="q" value="'.e($q).'" placeholder="Artikelnummer oder Bezeichnung – z. B. JN840, Softshell, Polo" aria-label="Katalog durchsuchen" autofocus><button class="btn primary" type="submit">Suchen</button></form></div>';
        echo '<dl class="katalog-stats"><div><dt>Artikel im Katalog</dt><dd>'.count($catalog['articles']).'</dd></div><div><dt>Daiber-Produkte im Shop</dt><dd>'.$daiberCount.'</dd></div><div><dt>Davon ohne Bild</dt><dd'.($withoutImage?' class="warn"':'').'>'.$withoutImage.'</dd></div><div><dt>Liste vom</dt><dd>'.e(preg_match('/(\d{8})/',(string)$catalog['source'],$m)?substr($m[1],6,2).'.'.substr($m[1],4,2).'.'.substr($m[1],0,4):(string)$catalog['generated']).'</dd></div></dl></section>';
    }

    /* ---- Artikelseite ---- */
    if($catalog&&$artikel!==''&&isset($catalog['articles'][$artikel])){
        $a=$catalog['articles'][$artikel];$minPrice=yw_katalog_article_min_price($a);
        $allSizes=[];foreach($a['col'] as $c)foreach($c['sz'] as $sz)if(!in_array($sz,$allSizes,true))$allSizes[]=$sz;
        $surcharge=[];foreach($a['col'] as $c)foreach($c['ps'] as $sz=>$pr)$surcharge[$sz]=max($surcharge[$sz]??0,(float)$pr-(float)$c['p']);
        $inShop=0;$withImg=0;foreach($a['col'] as $color=>$c){$label=yw_katalog_color_label((string)$color);if(isset($existing[$artikel.'|'.mb_strtolower($label)]))$inShop++;if(yw_katalog_find_image($artikel,(string)$color)!==null)$withImg++;}
        $features=array_values(array_filter(array_map('trim',explode("\n",(string)($a['f']??'')))));

        echo '<form method="post" class="katalog-article" data-katalog data-ek="'.e(number_format($minPrice,2,'.','')).'">'.csrf_field().'<input type="hidden" name="action" value="katalog_create"><input type="hidden" name="sku" value="'.e($artikel).'">';
        echo '<div class="katalog-article-main">';
        // Kopf
        echo '<section class="card katalog-article-head"><a class="katalog-back" href="'.e($backToSearch).'">← Zurück zur Suche</a><div class="katalog-article-title"><div><div class="eyebrow">'.e(yw_katalog_brand($artikel)).' · '.e($a['c']).'</div><h2><span class="katalog-sku">'.e($artikel).'</span> '.e($a['n']).'</h2>'.($a['s']!==''?'<p class="lead">'.e($a['s']).'</p>':'').'</div><div class="katalog-article-facts"><div><span>Händler-EK ab</span><strong>'.money((int)round($minPrice*100)).'</strong></div><div><span>Farben</span><strong>'.count($a['col']).'</strong></div><div><span>Größen</span><strong>'.e(reset($allSizes)).' – '.e(end($allSizes)).'</strong></div></div></div>';
        if($features){echo '<div class="katalog-chips">';foreach(array_slice($features,0,8) as $f)echo '<span class="chip">'.e($f).'</span>';echo '</div>';}
        echo '<details class="katalog-details"><summary>Beschreibung und Material</summary><p style="white-space:pre-line">'.e($a['d']).'</p>'.($a['m']!==''?'<p><strong>Material:</strong> '.e($a['m']).'</p>':'').'</details></section>';

        // Farben
        echo '<section class="card"><div class="katalog-section-head"><div><h3>Farben</h3><p class="muted">Jede gewählte Farbe wird ein eigenes Produkt im Shop. '.($inShop?$inShop.' Farbe(n) sind bereits angelegt.':'').'</p></div><div class="katalog-tools"><button type="button" class="btn small" data-katalog-select="all">Alle</button><button type="button" class="btn small" data-katalog-select="image">Nur mit Bild</button><button type="button" class="btn small" data-katalog-select="none">Keine</button></div></div><div class="katalog-colors">';
        foreach($a['col'] as $color=>$c){
            $label=yw_katalog_color_label((string)$color);$has=isset($existing[$artikel.'|'.mb_strtolower($label)]);
            $imgFile=yw_katalog_find_image($artikel,(string)$color);
            $extra=(float)$c['p']-$minPrice;
            echo '<label class="kcolor'.($has?' is-done':'').'" data-has-image="'.($imgFile?'1':'0').'"><input type="checkbox" name="colors[]" value="'.e((string)$color).'" data-ek="'.e(number_format((float)$c['p'],2,'.','')).'" '.($has?'disabled':'').'>'.yw_katalog_swatch($c).'<span class="kcolor-body"><span class="kcolor-name">'.e($label).'</span><span class="kcolor-meta">'.e((string)$c['de']).($extra>0.001?' · EK +'.money((int)round($extra*100)):'').'</span><span class="kcolor-state">'.($has?'<span class="kstate done">Im Shop</span>':'').($imgFile?'<span class="kstate ok">Bild vorhanden</span>':'<span class="kstate missing" title="Erwartet: '.e(yw_katalog_expected_filename($artikel,(string)$color)).'">Bild fehlt</span>').'</span></span></label>';
        }
        echo '</div></section>';

        // Größen
        echo '<section class="card"><div class="katalog-section-head"><div><h3>Größen</h3><p class="muted">Gilt für alle gewählten Farben; Größen, die es in einer Farbe nicht gibt, werden dort weggelassen.</p></div><div class="katalog-tools"><button type="button" class="btn small" data-katalog-sizes="all">Alle</button><button type="button" class="btn small" data-katalog-sizes="standard">Ohne Aufpreis</button></div></div><div class="katalog-sizes">';
        foreach($allSizes as $sz){$sur=$surcharge[$sz]??0;echo '<label class="ksize'.($sur>0.001?' has-extra':'').'"><input type="checkbox" name="sizes[]" value="'.e($sz).'" checked><span>'.e($sz).'</span>'.($sur>0.001?'<small>+'.money((int)round($sur*100)).'</small>':'').'</label>';}
        echo '</div>'.($surcharge?'<p class="muted katalog-note">Größen mit Aufpreis kosten bei Daiber mehr. Der Shop führt einen Preis je Produkt: Sind solche Größen angehakt, wird der höhere Einkaufspreis für alle Größen dieser Farbe verwendet. Für den günstigeren Normalpreis diese Größen abwählen.</p>':'').'</section>';
        echo '</div>'; // main

        // Seitenleiste: Preis & Anlegen
        echo '<aside class="katalog-side"><section class="card katalog-pricebox"><h3>Preis und Kategorie</h3>';
        echo '<div class="katalog-price-modes"><label class="kmode"><input type="radio" name="price_mode" value="factor" checked><span><strong>Faktor auf Einkauf</strong><small>Empfohlen – wird gemerkt</small></span></label><div class="kmode-field"><input name="price_factor" value="'.e(str_replace('.',',',$factorSetting)).'" inputmode="decimal" aria-label="Preisfaktor" data-katalog-factor><span>×</span></div>';
        echo '<label class="kmode"><input type="radio" name="price_mode" value="fixed"><span><strong>Fester Verkaufspreis</strong><small>netto je Stück</small></span></label><div class="kmode-field"><input name="price_fixed" placeholder="z. B. 24,90" inputmode="decimal" aria-label="Fester Preis" data-katalog-fixed disabled><span>€</span></div></div>';
        echo '<dl class="katalog-calc"><div><dt>Einkauf netto</dt><dd data-katalog-out="ek">'.money((int)round($minPrice*100)).'</dd></div><div><dt>Verkauf netto</dt><dd data-katalog-out="vk">–</dd></div><div><dt>Verkauf brutto (19 %)</dt><dd data-katalog-out="brutto">–</dd></div><div><dt>Rohertrag je Stück</dt><dd data-katalog-out="marge">–</dd></div></dl>';
        $pre=null;foreach($categories as $cat)if(mb_strtolower($cat['name'])===mb_strtolower($a['c']))$pre=(int)$cat['id'];
        echo '<label>Kategorie im Shop<select name="category_id"><option value="0">Neu anlegen: „'.e($a['c']).'“</option>';
        foreach($categories as $cat)echo '<option value="'.(int)$cat['id'].'" '.($pre===(int)$cat['id']?'selected':'').'>'.e($cat['name']).'</option>';
        echo '</select><input type="hidden" name="category_name" value="'.e($a['c']).'"></label>';
        echo '<button class="btn primary katalog-submit" type="submit" data-katalog-submit disabled><span data-katalog-out="count">0 Produkte</span> anlegen</button><p class="muted small">Marke, Beschreibung, Größen, EK und VK werden übernommen. Bilder folgen automatisch, sobald die Dateien im Bilderordner liegen.</p></section></aside>';
        echo '</form>';

    } elseif($catalog&&($q!==''||$artikel!=='')){
        /* ---- Suchergebnis ---- */
        $hits=yw_katalog_search($catalog,$q!==''?$q:$artikel);
        echo '<section class="katalog-results"><div class="katalog-section-head"><h3>'.count($hits).' Treffer für „'.e($q!==''?$q:$artikel).'“'.(count($hits)>=60?' – bitte genauer suchen':'').'</h3></div>';
        if(!$hits)echo '<div class="card"><p class="muted">Nichts gefunden. Artikelnummern sehen z. B. so aus: JN840, MB7112, 8001 – oder nach Bezeichnung suchen (Softshell, Polo, Hoodie).</p></div>';
        else{
            echo '<div class="katalog-grid">';
            foreach($hits as [$sku,$a]){
                $sku=(string)$sku;$inShop=0;foreach($a['col'] as $color=>$c)if(isset($existing[$sku.'|'.mb_strtolower(yw_katalog_color_label((string)$color))]))$inShop++;
                echo '<a class="card katalog-item" href="/admin?tab=katalog&q='.rawurlencode($q).'&artikel='.rawurlencode($sku).'"><div class="katalog-item-top"><span class="katalog-sku">'.e($sku).'</span>'.($a['nw']?'<span class="badge">neu</span>':'').($inShop?'<span class="kstate done">'.$inShop.'/'.count($a['col']).' im Shop</span>':'').'</div><strong class="katalog-item-name">'.e($a['n']).'</strong><span class="muted">'.e(yw_katalog_brand($sku)).' · '.e($a['c']).'</span><span class="katalog-dots">';
                $i=0;foreach($a['col'] as $c){if($i++>=14){echo '<span class="katalog-more">+'.(count($a['col'])-14).'</span>';break;}echo yw_katalog_swatch($c);}
                echo '</span><span class="katalog-item-foot"><span>EK ab <strong>'.money((int)round(yw_katalog_article_min_price($a)*100)).'</strong></span><span class="katalog-item-cta">Auswählen →</span></span></a>';
            }
            echo '</div>';
        }
        echo '</section>';
    } elseif($catalog){
        echo '<section class="katalog-results"><div class="katalog-grid katalog-grid-hint"><div class="card katalog-hint"><h3>1 · Suchen</h3><p class="muted">Artikelnummer aus dem Daiber-Katalog eingeben oder nach Bezeichnung suchen.</p></div><div class="card katalog-hint"><h3>2 · Farben und Größen wählen</h3><p class="muted">Jede Farbe wird ein Produkt. Preis per Faktor auf deinen Einkauf.</p></div><div class="card katalog-hint"><h3>3 · Bilder zuordnen</h3><p class="muted">Dateien aus der Daiber Media Cloud hochladen – Zuordnung läuft automatisch über den Dateinamen.</p></div></div></section>';
    }

    /* ---- Bilder ---- */
    echo '<section class="card katalog-images" id="bilder"><div class="katalog-section-head"><div><h3>Produktbilder aus der Daiber Media Cloud</h3><p class="muted">Der Dateiname muss mit <code>Artikelnummer_Farbe</code> beginnen, z. B. <code>JN840_navy.jpg</code> oder <code>JN010_black_red.jpg</code> für black/red – so heißen die Masterbilder in der Media Cloud. Zusätze wie <code>_front</code> oder <code>(1)</code> und Groß-/Kleinschreibung sind egal. Nur die Bilder der Artikel nötig, die im Shop sind; große Fotos werden automatisch verkleinert.</p></div><div class="katalog-stat-inline"><strong>'.count($imageIndex).'</strong> Dateien im Ordner'.($withoutImage?' · <strong class="warn">'.$withoutImage.'</strong> Produkte ohne Bild':'').'</div></div>';
    echo '<div class="katalog-image-ways"><form method="post" enctype="multipart/form-data" class="katalog-way" data-katalog-upload data-max-bytes="'.(int)yw_katalog_upload_budget().'" data-max-files="'.max(1,min(50,(int)ini_get('max_file_uploads')?:20)).'">'.csrf_field().'<input type="hidden" name="action" value="katalog_images_upload"><h4>Hochladen</h4><p class="muted">Beliebig viele Bilder auf einmal auswählen – sie werden automatisch in Paketen übertragen. ZIP-Dateien bis '.e(ini_get('upload_max_filesize')).'; größere ZIPs entpacken und die Bilder auswählen oder per FTP ablegen.</p><input type="file" name="katalog_images[]" multiple accept=".jpg,.jpeg,.png,.webp,.zip"><button class="btn primary" type="submit">Hochladen und zuordnen</button><p class="muted small" data-katalog-upload-status role="status" aria-live="polite"></p></form>';
    echo '<form method="post" class="katalog-way">'.csrf_field().'<input type="hidden" name="action" value="katalog_images_assign"><h4>Per FTP</h4><p class="muted">Dateien in <code>uploads/katalog/bilder/</code> legen (Unterordner erlaubt), dann hier zuordnen lassen.</p><button class="btn" type="submit">Bilder zuordnen</button></form></div>';
    $unknownImages=$imageIndex?yw_katalog_image_map()['unknown']:[];
    if($unknownImages){
        echo '<details class="katalog-details"><summary>Dateien ohne passenden Katalogartikel ('.count($unknownImages).')</summary><p class="muted">Diese Dateien liegen im Bilderordner, ihr Name beginnt aber nicht mit Artikelnummer_Farbe aus der Daiber-Liste. Umbenennen, dann „Bilder zuordnen“.</p><div class="katalog-chips">';
        foreach(array_slice($unknownImages,0,200) as $n)echo '<span class="chip mono">'.e($n).'</span>';
        echo '</div></details>';
    }
    if($withoutImage){
        echo '<details class="katalog-details"><summary>Fehlende Dateien anzeigen ('.$withoutImage.')</summary><div class="katalog-chips">';
        foreach($existing as $p)if(empty($p['image_path']))echo '<span class="chip mono">'.e(yw_katalog_expected_filename(yw_katalog_base_sku((string)$p['sku'],mb_strtolower((string)$p['color'])),(string)$p['color'])).'</span>';
        echo '</div></details>';
    }
    echo '</section>';

    echo '<details class="card katalog-details katalog-list-upload"><summary>Neue Daiber-Liste einlesen</summary><p class="muted">Wenn Daiber eine neue SKU-Liste bereitstellt (Händlerbereich → Preislisten → Excel), hier hochladen. Produkte im Shop bleiben unverändert; nur der Katalog zum Auswählen wird erneuert. Höchstgröße je Upload: '.e(ini_get('upload_max_filesize')).'.</p><form method="post" enctype="multipart/form-data" class="katalog-way">'.csrf_field().'<input type="hidden" name="action" value="katalog_upload_list"><input type="file" name="katalog_xlsx" accept=".xlsx" required><button class="btn" type="submit">Liste einlesen</button></form></details>';
}
