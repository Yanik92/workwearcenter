<?php
declare(strict_types=1);

function yw_product_csv_utf8(mixed $value): string {
    $value=is_string($value)?trim($value):'';
    if($value!=='' && !mb_check_encoding($value,'UTF-8'))$value=mb_convert_encoding($value,'UTF-8','Windows-1252');
    return $value;
}

function yw_product_csv_value(mixed $value,int $max,string $label): string {
    $value=yw_product_csv_utf8($value);
    if(mb_strlen($value)>$max)throw new DomainException($label.' ist zu lang.');
    return $value;
}

function yw_product_csv_number(string $value,string $label,bool $positive=false): float {
    $value=str_replace(["\xc2\xa0",' ','€'],'',trim($value));
    if(str_contains($value,',')&&str_contains($value,'.')){
        $value=strrpos($value,',')>strrpos($value,'.')?str_replace(',','.',str_replace('.','',$value)):str_replace(',','',$value);
    }elseif(str_contains($value,','))$value=str_replace(',','.',$value);
    if($value===''||!preg_match('/^\d{1,7}(?:\.\d{1,4})?$/D',$value))throw new DomainException($label.' ist keine gültige Zahl.');
    $number=(float)$value;
    if(($positive&&$number<=0)||(!$positive&&$number<0))throw new DomainException($label.' ist ungültig.');
    return $number;
}

function yw_product_csv_rows(array $file): array {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new DomainException('Bitte eine CSV-Datei auswählen.');
    $size=(int)($file['size']??0);
    if($size<=0||$size>5_000_000)throw new DomainException('Die CSV-Datei ist leer oder größer als 5 MB.');
    $tmp=(string)($file['tmp_name']??'');
    $name=(string)($file['name']??'');
    if($tmp==='' || !is_uploaded_file($tmp))throw new DomainException('CSV-Datei wurde nicht als gültiger Upload erkannt.');
    if(strtolower((string)pathinfo($name,PATHINFO_EXTENSION))!=='csv')throw new DomainException('Bitte eine Datei mit der Endung .csv auswählen.');
    $mime=(string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if(!in_array($mime,['text/plain','text/csv','application/csv','application/vnd.ms-excel','application/octet-stream'],true))throw new DomainException('Das Dateiformat wurde nicht als CSV erkannt.');
    $handle=fopen($tmp,'rb');if(!$handle)throw new DomainException('CSV-Datei konnte nicht gelesen werden.');
    $first=fgets($handle);if($first===false){fclose($handle);throw new DomainException('CSV-Datei ist leer.');}
    $counts=[';'=>substr_count($first,';'),','=>substr_count($first,','),"\t"=>substr_count($first,"\t")];arsort($counts);$delimiter=(string)array_key_first($counts);rewind($handle);
    $header=fgetcsv($handle,0,$delimiter,'"','');if(!$header){fclose($handle);throw new DomainException('CSV-Kopfzeile fehlt.');}
    $aliases=[
        'produkt-id'=>'product_id','produkt id'=>'product_id','id'=>'product_id',
        'sku'=>'sku','artikelnummer'=>'sku','artikelnummer / sku'=>'sku',
        'name'=>'name','produktname'=>'name','kategorie'=>'category',
        'beschreibung'=>'description','produktbeschreibung'=>'description',
        'verkaufspreis netto'=>'sale_price','vk netto'=>'sale_price','preis netto'=>'sale_price',
        'einkaufspreis netto'=>'purchase_price','ek netto'=>'purchase_price',
        'mwst'=>'vat_rate','mwst.'=>'vat_rate','mwst. %'=>'vat_rate','mwst %'=>'vat_rate',
        'größen'=>'sizes','groessen'=>'sizes','grössen'=>'sizes','farbe'=>'color',
        'marke'=>'brand','lieferant'=>'supplier','veredelungsart'=>'decoration_type',
        'veredelungsdetails'=>'decoration_details','aktiv'=>'active',
    ];
    $map=[];foreach($header as $i=>$cell){$key=mb_strtolower(trim(str_replace("\xEF\xBB\xBF",'',yw_product_csv_utf8($cell))));if(isset($aliases[$key]))$map[$aliases[$key]]=$i;}
    foreach(['product_id','sku','name','category','description','sale_price','purchase_price','vat_rate','sizes','color','brand','supplier','decoration_type','decoration_details','active'] as $required)if(!isset($map[$required])){fclose($handle);throw new DomainException('Bitte die vollständige WorkwearCenter-CSV-Vorlage mit unveränderter Kopfzeile verwenden.');}
    $rows=[];$line=1;$seen=[];
    while(($cells=fgetcsv($handle,0,$delimiter,'"',''))!==false){
        $line++;if(count($rows)>=1000){fclose($handle);throw new DomainException('Pro Import sind höchstens 1.000 Produkte möglich.');}
        if(count(array_filter($cells,fn($v)=>trim((string)$v)!==''))===0)continue;
        $get=fn(string $key):string=>isset($map[$key])?yw_product_csv_utf8($cells[$map[$key]]??''):'';
        try{
            $productIdRaw=$get('product_id');if($productIdRaw!==''&&!ctype_digit($productIdRaw))throw new DomainException('Produkt-ID muss leer oder eine ganze Zahl sein.');$productId=(int)$productIdRaw;
            $sku=yw_product_csv_value($get('sku'),100,'SKU');$name=yw_product_csv_value($get('name'),190,'Produktname');$category=yw_product_csv_value($get('category'),120,'Kategorie');
            if(!$productId&&$sku==='')throw new DomainException('Neue Produkte benötigen eine SKU.');
            if($name===''||$category==='')throw new DomainException('Name und Kategorie dürfen nicht leer sein.');
            if($productId){$identity='id:'.$productId;if(isset($seen[$identity]))throw new DomainException('Produkt-ID '.$productId.' kommt mehrfach vor.');$seen[$identity]=true;}
            if($sku!==''){$identity='sku:'.mb_strtolower($sku);if(isset($seen[$identity]))throw new DomainException('SKU '.$sku.' kommt mehrfach vor.');$seen[$identity]=true;}
            $sizes=array_values(array_unique(array_filter(array_map('trim',preg_split('/[,|]+/u',$get('sizes'))?:[]))));
            if(!$sizes||count($sizes)>50||array_filter($sizes,fn($size)=>mb_strlen($size)>30))throw new DomainException('Größen fehlen oder sind ungültig.');
            $vatRaw=$get('vat_rate');$purchaseRaw=$get('purchase_price');$activeRaw=mb_strtolower($get('active'));
            $active=null;if(isset($map['active'])&&$activeRaw!==''){$truthy=['1','ja','j','true','aktiv'];$falsy=['0','nein','n','false','inaktiv'];if(in_array($activeRaw,$truthy,true))$active=1;elseif(in_array($activeRaw,$falsy,true))$active=0;else throw new DomainException('Aktiv muss Ja/Nein oder 1/0 sein.');}
            $decoration=mb_strtolower($get('decoration_type'));$decorationMap=[''=>'none','keine'=>'none','keine veredelung'=>'none','none'=>'none','druck'=>'print','bedruckt'=>'print','print'=>'print','stick'=>'embroidery','gestickt'=>'embroidery','embroidery'=>'embroidery','druck & stick'=>'mixed','druck und stick'=>'mixed','mixed'=>'mixed'];if(!isset($decorationMap[$decoration]))throw new DomainException('Veredelungsart ist unbekannt.');
            $rows[]=['line'=>$line,'product_id'=>$productId,'sku'=>$sku,'name'=>$name,'category'=>$category,'description'=>yw_product_csv_value($get('description'),10000,'Beschreibung'),'sale_cents'=>(int)round(yw_product_csv_number($get('sale_price'),'Verkaufspreis netto',true)*100),'purchase_cents'=>$purchaseRaw===''?0:(int)round(yw_product_csv_number($purchaseRaw,'Einkaufspreis netto')*100),'vat_rate'=>$vatRaw===''?19.0:yw_product_csv_number($vatRaw,'MwSt.'),'sizes'=>$sizes,'color'=>yw_product_csv_value($get('color'),120,'Farbe'),'brand'=>yw_product_csv_value($get('brand'),120,'Marke'),'supplier'=>yw_product_csv_value($get('supplier'),160,'Lieferant'),'decoration_type'=>$decorationMap[$decoration],'decoration_details'=>yw_product_csv_value($get('decoration_details'),10000,'Veredelungsdetails'),'active'=>$active];
            $last=end($rows);if($last['vat_rate']>100)throw new DomainException('MwSt. muss zwischen 0 und 100 liegen.');if($last['sale_cents']>1_000_000_000||$last['purchase_cents']>1_000_000_000)throw new DomainException('Ein Preis überschreitet das zulässige Maximum.');
        }catch(DomainException $e){fclose($handle);throw new DomainException('CSV-Zeile '.$line.': '.$e->getMessage());}
    }
    fclose($handle);if(!$rows)throw new DomainException('CSV enthält keine Produktdaten.');return $rows;
}

function yw_import_products_csv(array $file): array {
    $rows=yw_product_csv_rows($file);$created=0;$updated=0;$categoriesCreated=0;
    db()->beginTransaction();
    try{
        $categories=[];foreach(db()->query('SELECT id,name FROM product_categories FOR UPDATE') as $category)$categories[mb_strtolower(trim((string)$category['name']))]=(int)$category['id'];
        $productsById=[];$productsBySku=[];
        foreach(db()->query('SELECT id,sku,active FROM products FOR UPDATE') as $product){
            $productsById[(int)$product['id']]=$product;
            $sku=trim((string)($product['sku']??''));
            if($sku!=='')$productsBySku[mb_strtolower($sku)][]=$product;
        }
        $resolved=[];
        foreach($rows as $index=>$row){
            $target=null;
            if($row['product_id']){
                $target=$productsById[(int)$row['product_id']]??null;
                if(!$target)throw new DomainException('Produkt-ID '.$row['product_id'].' aus CSV-Zeile '.$row['line'].' wurde nicht gefunden.');
            }
            if($row['sku']!==''){
                $skuMatches=$productsBySku[mb_strtolower($row['sku'])]??[];
                if(count($skuMatches)>1)throw new DomainException('SKU '.$row['sku'].' ist bereits mehrfach im Produktstamm vorhanden. Bitte Dubletten zuerst bereinigen.');
                if($target&&$skuMatches&&(int)$skuMatches[0]['id']!==(int)$target['id'])throw new DomainException('SKU '.$row['sku'].' gehört bereits zu einem anderen Produkt.');
                if(!$target&&$skuMatches)$target=$skuMatches[0];
            }
            if($target){
                $targetId=(int)$target['id'];
                if(isset($resolved[$targetId]))throw new DomainException('CSV-Zeilen '.$resolved[$targetId].' und '.$row['line'].' beziehen sich auf dasselbe Produkt.');
                $resolved[$targetId]=(int)$row['line'];
                $rows[$index]['_target_id']=$targetId;
                $rows[$index]['_target_active']=(int)$target['active'];
            }
        }
        $insertCategory=db()->prepare('INSERT INTO product_categories(name,sort_order) VALUES(?,0)');
        $insert=db()->prepare('INSERT INTO products(name,category_id,category,description,price_cents,purchase_price_cents,vat_rate,sizes,color,image_path,decoration_type,decoration_details,sku,brand,supplier_name,active) VALUES(?,?,?,?,?,?,?,?,?,NULL,?,?,?,?,?,?)');
        $update=db()->prepare('UPDATE products SET name=?,category_id=?,category=?,description=?,price_cents=?,purchase_price_cents=?,vat_rate=?,sizes=?,color=?,decoration_type=?,decoration_details=?,sku=?,brand=?,supplier_name=?,active=? WHERE id=?');
        foreach($rows as $row){
            $categoryKey=mb_strtolower($row['category']);if(!isset($categories[$categoryKey])){$insertCategory->execute([$row['category']]);$categories[$categoryKey]=(int)db()->lastInsertId();$categoriesCreated++;}
            $categoryId=$categories[$categoryKey];$active=$row['active'];$values=[$row['name'],$categoryId,$row['category'],$row['description'],$row['sale_cents'],$row['purchase_cents'],$row['vat_rate'],json_encode($row['sizes'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$row['color'],$row['decoration_type'],$row['decoration_details'],$row['sku'],$row['brand'],$row['supplier']];
            if(isset($row['_target_id'])){$update->execute([...$values,$active??(int)$row['_target_active'],(int)$row['_target_id']]);$updated++;}
            else{$insert->execute([...$values,$active??1]);$created++;}
        }
        db()->commit();
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();throw $e;}
    return ['rows'=>count($rows),'created'=>$created,'updated'=>$updated,'categories_created'=>$categoriesCreated];
}

function yw_product_csv_template(): never {
    require_admin();header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="workwearcenter-produktimport-vorlage.csv"');
    $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Produkt-ID','SKU','Name','Kategorie','Beschreibung','Verkaufspreis netto','Einkaufspreis netto','MwSt. %','Größen','Farbe','Marke','Lieferant','Veredelungsart','Veredelungsdetails','Aktiv'],';','"','');
    fputcsv($out,['','JN001','Workwear T-Shirt','T-Shirts','Robustes Firmen-Shirt','24,90','9,50','19','S, M, L, XL, 2XL','Schwarz','Beispielmarke','Beispiellieferant','Druck','Grundprodukt; kundenspezifische Position später im Firmenshop','Ja'],';','"','');fclose($out);exit;
}

function yw_product_csv_export(): never {
    require_admin();
    $rows=db()->query('SELECT p.*,COALESCE(pc.name,p.category) category_name FROM products p LEFT JOIN product_categories pc ON pc.id=p.category_id ORDER BY p.active DESC,category_name,p.name')->fetchAll();
    header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="workwearcenter-produkte-'.date('Y-m-d').'.csv"');
    $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");$header=['Produkt-ID','SKU','Name','Kategorie','Beschreibung','Verkaufspreis netto','Einkaufspreis netto','MwSt. %','Größen','Farbe','Marke','Lieferant','Veredelungsart','Veredelungsdetails','Aktiv'];fputcsv($out,$header,';','"','');
    $decorations=['none'=>'Keine','print'=>'Druck','embroidery'=>'Stick','mixed'=>'Druck & Stick'];
    foreach($rows as $row){$values=[(string)$row['id'],(string)($row['sku']??''),(string)$row['name'],(string)$row['category_name'],(string)($row['description']??''),number_format((int)$row['price_cents']/100,2,',',''),number_format((int)($row['purchase_price_cents']??0)/100,2,',',''),number_format((float)($row['vat_rate']??19),2,',',''),implode(', ',json_decode((string)$row['sizes'],true)?:[]),(string)($row['color']??''),(string)($row['brand']??''),(string)($row['supplier_name']??''),$decorations[(string)($row['decoration_type']??'none')]??'Keine',(string)($row['decoration_details']??''),(int)$row['active']?'Ja':'Nein'];fputcsv($out,array_map('yw_csv_safe',$values),';','"','');}
    fclose($out);exit;
}
