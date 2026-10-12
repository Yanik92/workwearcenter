<?php
declare(strict_types=1);

/*
 * 10.11: Probeshop für Interessenten.
 *
 * Aus Firmenname und Logo entsteht ein persönlicher, rein lesender Vorschau-Shop im Stil des
 * Mustershops – mit dem Logo des Interessenten an den Druckzonen der Musterartikel.
 *  - Adresse: /probeshop/<64 Hex-Zeichen>, nicht erratbar, noindex
 *  - läuft nach 30 Tagen ab; Zeile und Logo-Datei löscht danach der Cronjob (DSGVO)
 *  - Logos liegen mit zufälligem Dateinamen in uploads/probeshop (Ordner nicht auflistbar)
 */

const YW_PROBESHOP_DAYS=30;

function yw_probeshop_dir(): string {
    $dir=dirname(__DIR__,2).'/uploads/probeshop';
    if(!is_dir($dir)&&!@mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Der Ordner für Probeshop-Logos konnte nicht angelegt werden.');
    // Ordner nie auflisten, nie Skripte ausführen – auch wenn die Haupt-.htaccess fehlt.
    if(!is_file($dir.'/.htaccess'))@file_put_contents($dir.'/.htaccess',"Options -Indexes\n<FilesMatch \"\\.(php[0-9]?|phtml|phar|cgi|pl|py|sh|html?)$\">\n    Require all denied\n</FilesMatch>\n");
    if(!is_file($dir.'/index.html'))@file_put_contents($dir.'/index.html','');
    return $dir;
}

function yw_probeshop_logo_path_ok(string $path): bool {
    return (bool)preg_match('#^uploads/probeshop/[a-f0-9]{32}\.(?:svg|png|jpg|webp)$#D',$path);
}

function yw_probeshop_token_ok(string $token): bool { return (bool)preg_match('/^[a-f0-9]{64}$/D',$token); }

function yw_probeshop_url(string $token): string { return workwearcenter_url('probeshop/'.$token); }

/**
 * Logo eines Interessenten speichern (PNG/JPG/WebP geprüft, SVG entschärft).
 * Rückgabe: relativer Pfad oder '' ohne Datei.
 */
function yw_probeshop_store_logo(?array $file): string {
    if(!$file || (int)($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return '';
    if((int)$file['error']!==UPLOAD_ERR_OK)throw new RuntimeException(function_exists('upload_error_message')?upload_error_message((int)$file['error'],'Logo'):'Logo-Upload fehlgeschlagen.');
    $tmp=(string)($file['tmp_name']??'');$size=(int)($file['size']??0);
    if($tmp===''||!is_uploaded_file($tmp))throw new RuntimeException('Das Logo wurde nicht als gültiger Upload erkannt.');
    if($size<=0||$size>4*1024*1024)throw new RuntimeException('Das Logo darf höchstens 4 MB groß sein.');
    $mime=(string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $raw=(string)file_get_contents($tmp,false,null,0,2*1024*1024+1);
    $looksSvg=in_array($mime,['image/svg+xml','image/svg','text/plain','text/xml','application/xml','text/html'],true)&&stripos($raw,'<svg')!==false;
    $dir=yw_probeshop_dir();
    $name=bin2hex(random_bytes(16));
    if($looksSvg){
        $clean=yw_sanitize_svg($raw);
        if(file_put_contents($dir.'/'.$name.'.svg',$clean)===false)throw new RuntimeException('Das Logo konnte nicht gespeichert werden.');
        return 'uploads/probeshop/'.$name.'.svg';
    }
    $ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$mime]??null;
    if(!$ext)throw new RuntimeException('Bitte das Logo als PNG, JPG, WebP oder SVG hochladen.');
    yw_validate_uploaded_raster($tmp,'Das Logo');
    if(!yw_move_upload($tmp,$dir.'/'.$name.'.'.$ext))throw new RuntimeException('Das Logo konnte nicht gespeichert werden.');
    @chmod($dir.'/'.$name.'.'.$ext,0644);
    return 'uploads/probeshop/'.$name.'.'.$ext;
}

function yw_probeshop_delete_logo(string $path): void {
    if(!yw_probeshop_logo_path_ok($path))return;
    $file=dirname(__DIR__,2).'/'.$path;
    if(is_file($file))@unlink($file);
}

/** Relative Helligkeit (0 = schwarz, 1 = weiß) einer Farbe #rgb/#rrggbb, sonst null. */
function yw_color_luminance(string $hex): ?float {
    $hex=ltrim(trim($hex),'#');
    if(preg_match('/^[0-9a-f]{3}$/i',$hex))$hex=$hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    if(!preg_match('/^[0-9a-f]{6}$/i',$hex))return null;
    [$r,$g,$b]=[hexdec(substr($hex,0,2)),hexdec(substr($hex,2,2)),hexdec(substr($hex,4,2))];
    return round((0.2126*$r+0.7152*$g+0.0722*$b)/255,3);
}

/** Durchschnittliche Helligkeit der Füll-/Linienfarben eines SVG. Ohne Farbangaben zeichnet SVG schwarz. */
function yw_svg_average_luminance(string $svg): float {
    preg_match_all('/(?:fill|stroke|stop-color|color)\s*[:=]\s*["\']?\s*(#[0-9a-f]{3,6})\b/i',$svg,$m);
    $values=[];
    foreach($m[1] as $hex){$l=yw_color_luminance($hex);if($l!==null)$values[]=$l;}
    preg_match_all('/(?:fill|stroke)\s*[:=]\s*["\']?\s*(white|black)\b/i',$svg,$n);
    foreach($n[1] as $word)$values[]=strtolower($word)==='white'?1.0:0.0;
    if(!$values)return 0.0;
    return round(array_sum($values)/count($values),3);
}

/** Durchschnittliche Helligkeit der deckenden Pixel eines Rasterbilds (null ohne GD). */
function yw_raster_average_luminance(string $file): ?float {
    if(!function_exists('imagecreatefromstring')||!is_file($file))return null;
    $img=@imagecreatefromstring((string)file_get_contents($file));
    if(!$img)return null;
    $w=imagesx($img);$h=imagesy($img);$stepX=max(1,intdiv($w,40));$stepY=max(1,intdiv($h,40));
    $sum=0.0;$count=0;
    for($y=0;$y<$h;$y+=$stepY)for($x=0;$x<$w;$x+=$stepX){
        $c=imagecolorsforindex($img,imagecolorat($img,$x,$y));
        if(($c['alpha']??0)>100)continue; // transparent
        $sum+=(0.2126*$c['red']+0.7152*$c['green']+0.0722*$c['blue'])/255;$count++;
    }
    imagedestroy($img);
    return $count?round($sum/$count,3):null;
}

/** Textilfarbe, auf der das Logo am besten wirkt: dunkle Logos auf hellem Stoff und umgekehrt. */
function yw_probeshop_suggest_tone(string $logoPath): string {
    if(!yw_probeshop_logo_path_ok($logoPath))return 'dark';
    $file=dirname(__DIR__,2).'/'.$logoPath;
    $lum=str_ends_with($logoPath,'.svg')?yw_svg_average_luminance((string)@file_get_contents($file)):yw_raster_average_luminance($file);
    return yw_probeshop_tone_for_luminance($lum);
}

function yw_probeshop_tone_for_luminance(?float $lum): string { return ($lum!==null && $lum<0.42)?'light':'dark'; }

function yw_probeshop_create(string $companyName,string $logoPath,?int $inquiryId,string $source='inquiry',string $tone='auto',?string $color=null): array {
    $companyName=trim(preg_replace('/\s+/u',' ',$companyName)??'');
    if($companyName===''||mb_strlen($companyName)>190)throw new RuntimeException('Bitte einen gültigen Firmennamen angeben.');
    if(!yw_probeshop_logo_path_ok($logoPath))throw new RuntimeException('Für den Probeshop wird ein Logo benötigt.');
    if(!in_array($tone,['dark','light'],true))$tone=yw_probeshop_suggest_tone($logoPath);
    if($color!==null&&!color_ok($color))$color=null;
    $token=random_token();
    $q=db()->prepare('INSERT INTO yw_probeshops(token,inquiry_id,company_name,logo_path,garment_tone,primary_color,source,created_by,expires_at) VALUES(?,?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL '.YW_PROBESHOP_DAYS.' DAY))');
    $q->execute([$token,$inquiryId,$companyName,$logoPath,$tone,$color,$source==='admin'?'admin':'inquiry',!empty($_SESSION['admin_id'])?(int)$_SESSION['admin_id']:null]);
    $id=(int)db()->lastInsertId();
    audit_log('probeshop.created','probeshop',$id,['inquiry_id'=>$inquiryId,'source'=>$source]);
    return ['id'=>$id,'token'=>$token];
}

function yw_probeshop_find(int $id): ?array {
    $q=db()->prepare('SELECT * FROM yw_probeshops WHERE id=? LIMIT 1');$q->execute([$id]);
    return $q->fetch()?:null;
}

/** Probeshops je Anfrage (neuester zuerst). */
function yw_probeshops_by_inquiry(): array {
    $map=[];
    try{foreach(db()->query('SELECT * FROM yw_probeshops WHERE inquiry_id IS NOT NULL ORDER BY id DESC') as $row)$map[(int)$row['inquiry_id']]??=$row;}
    catch(Throwable $e){}
    return $map;
}

/** Neue Adresse (alter Link wird ungültig) und wieder volle Laufzeit. */
function yw_probeshop_regenerate(int $id): string {
    $token=random_token();
    $q=db()->prepare('UPDATE yw_probeshops SET token=?,expires_at=DATE_ADD(NOW(),INTERVAL '.YW_PROBESHOP_DAYS.' DAY),view_count=0,last_viewed_at=NULL WHERE id=?');
    $q->execute([$token,$id]);
    if(!$q->rowCount())throw new RuntimeException('Probeshop wurde nicht gefunden.');
    audit_log('probeshop.regenerated','probeshop',$id);
    return $token;
}

function yw_probeshop_delete(int $id,bool $audit=true): void {
    $shop=yw_probeshop_find($id);
    if(!$shop)return;
    db()->prepare('DELETE FROM yw_probeshops WHERE id=?')->execute([$id]);
    // Dieselbe Datei kann nach „neu erzeugen“ nur an dieser Zeile hängen – trotzdem prüfen.
    $q=db()->prepare('SELECT COUNT(*) FROM yw_probeshops WHERE logo_path=?');$q->execute([(string)$shop['logo_path']]);
    if((int)$q->fetchColumn()===0)yw_probeshop_delete_logo((string)$shop['logo_path']);
    if($audit)audit_log('probeshop.deleted','probeshop',$id);
}

/** Abgelaufene Probeshops samt Logo löschen, verwaiste Dateien entfernen. Rückgabe: gelöschte Probeshops. */
function yw_probeshop_cleanup(): int {
    $deleted=0;
    try{
        $ids=db()->query('SELECT id FROM yw_probeshops WHERE expires_at<NOW()')->fetchAll(PDO::FETCH_COLUMN);
        foreach($ids as $id){yw_probeshop_delete((int)$id,false);$deleted++;}
        if($deleted)audit_log('probeshop.expired_deleted','probeshop',null,['count'=>$deleted]);
        $dir=dirname(__DIR__,2).'/uploads/probeshop';
        if(is_dir($dir)){
            $known=array_flip(db()->query('SELECT logo_path FROM yw_probeshops WHERE logo_path IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN));
            foreach(glob($dir.'/*')?:[] as $file){
                $rel='uploads/probeshop/'.basename($file);
                // Nur eigene Dateien (Zufallsname) anfassen; Puffer von einem Tag für gerade laufende Uploads.
                if(!yw_probeshop_logo_path_ok($rel)||isset($known[$rel])||filemtime($file)>time()-86400)continue;
                @unlink($file);
            }
        }
    }catch(Throwable $e){error_log('WorkwearCenter probeshop cleanup: '.$e->getMessage());}
    return $deleted;
}

/* ---------- Öffentliche Ansicht ---------- */

/** Musterartikel mit Druckzone „Brust links“ (Prozentwerte wie im Druckzonen-Editor). */
function yw_probeshop_products(): array {
    return [
        ['name'=>'Premium Workwear T-Shirt','category'=>'T-Shirts','method'=>'dtf','price'=>2490,'sku'=>'YW-TS01','color'=>'Navy','color_light'=>'Hellgrau','sizes'=>'XS–5XL','image'=>'assets/media/products/workwear-tshirt.webp','w'=>1226,'h'=>1283,'zone'=>['x'=>62,'y'=>30,'w'=>17]],
        ['name'=>'Heavy Workwear Hoodie','category'=>'Hoodies','method'=>'embroidery','price'=>5490,'sku'=>'YW-HD02','color'=>'Graphit','color_light'=>'Hellgrau meliert','sizes'=>'XS–4XL','image'=>'assets/media/products/workwear-hoodie.webp','w'=>1312,'h'=>1199,'zone'=>['x'=>62,'y'=>36,'w'=>15]],
        ['name'=>'Softshelljacke Performance','category'=>'Jacken','method'=>'embroidery','price'=>8490,'sku'=>'YW-SJ03','color'=>'Schwarz','color_light'=>'Steingrau','sizes'=>'S–5XL','image'=>'assets/media/products/workwear-softshell.webp','w'=>1222,'h'=>1287,'zone'=>['x'=>63,'y'=>39,'w'=>15]],
    ];
}

/** Ansichten im Format von yw_mockup_views() – damit greifen dieselbe Darstellung und dasselbe Ausrichten per app.js. */
function yw_probeshop_views(array $product,string $logoPath): array {
    $zones=yw_zones_sanitize(['front'=>['chest_left'=>$product['zone']]]);
    $found=yw_zone_for($zones,'chest_left',['front']);
    $layers=[];
    if($found&&yw_probeshop_logo_path_ok($logoPath)){
        $z=$found['zone'];
        $layers[]=['src'=>$logoPath,'x'=>$z['x'],'y'=>$z['y'],'w'=>$z['w'],'method'=>$product['method'],'label'=>decoration_position_label('chest_left').' · '.decoration_method_label($product['method'])];
    }
    return [['view'=>'front','label'=>'Vorne','image'=>$product['image'],'layers'=>$layers,'custom'=>false]];
}

function yw_probeshop_route(string $token): void {
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Referrer-Policy: no-referrer');
    $shop=null;
    if(login_throttled('probeshop',40)){http_response_code(429);simple_message('Zu viele Aufrufe','Bitte versuch es in einigen Minuten erneut.');return;}
    if(yw_probeshop_token_ok($token)){
        try{$q=db()->prepare('SELECT * FROM yw_probeshops WHERE token=? LIMIT 1');$q->execute([$token]);$shop=$q->fetch()?:null;}
        catch(Throwable $e){$shop=null;}
    }
    if(!$shop){
        record_login_failure('probeshop');
        http_response_code(404);
        simple_message('Probeshop nicht gefunden','Dieser Link ist nicht (mehr) gültig. Gern erstellen wir dir einen neuen Probeshop – schreib uns einfach.');
        return;
    }
    if(strtotime((string)$shop['expires_at'])<time()){
        http_response_code(410);
        simple_message('Probeshop abgelaufen','Dein persönlicher Probeshop war 30 Tage lang verfügbar und ist jetzt abgelaufen. Deine Daten werden automatisch gelöscht. Gern richten wir dir einen neuen ein.');
        return;
    }
    try{db()->prepare('UPDATE yw_probeshops SET view_count=view_count+1,last_viewed_at=NOW() WHERE id=?')->execute([(int)$shop['id']]);}catch(Throwable $e){}
    yw_probeshop_page($shop);
}

function yw_probeshop_page(array $shop): void {
    $name=(string)$shop['company_name'];
    $logo=yw_probeshop_logo_path_ok((string)$shop['logo_path'])?(string)$shop['logo_path']:'';
    $tone=(string)$shop['garment_tone']==='light'?'light':'dark';
    $until=date('d.m.Y',strtotime((string)$shop['expires_at']));
    $products=[];
    foreach(yw_probeshop_products() as $p){
        $products[]=[
            'name'=>$p['name'],'category'=>$p['category'],
            'detail'=>decoration_position_label('chest_left').' · '.decoration_method_label($p['method']).' · dein Logo',
            'cents'=>$p['price'],'sku'=>$p['sku'],'color'=>$p['color'],'color_light'=>$p['color_light'],'sizes'=>$p['sizes'],
            'visual'=>yw_mockup_html(yw_probeshop_views($p,$logo),$p['name'].' mit Logo von '.$name,false,'demo-mockup'),
        ];
    }
    $mark=$logo!==''?'<div class="demo-company-mark demo-company-logo"><img src="/'.e($logo).'" alt=""></div>':'<div class="demo-company-mark">'.e(mb_strtoupper(mb_substr($name,0,1))).'</div>';
    render_head('Probeshop für '.$name.' – WorkwearCenter');
    w_header(); // 10.21: gleicher Kopf wie die Website
    yw_demo_shop_render([
        'company'=>$name,
        'mark'=>$mark,
        'notice'=>'<span><strong>Dein persönlicher Probeshop</strong> · So könnte der Firmenshop für '.e($name).' aussehen. Nur zur Ansicht – gültig bis '.e($until).'.</span><a href="mailto:'.e(s_mail()).'?subject='.rawurlencode('Probeshop '.$name).'">Fragen? Schreib uns →</a>',
        'products'=>$products,
        'tone'=>$tone,
        'primary'=>color_ok((string)($shop['primary_color']??''))?(string)$shop['primary_color']:'',
        'probe'=>true,
        'logo_dark'=>$logo!==''&&yw_probeshop_suggest_tone($logo)==='light',
    ]);
    echo '<section class="demo-probe-next"><div class="demo-shell demo-probe-next-inner"><div><h2>Gefällt dir, was du siehst?</h2><p>Im echten Firmenshop legen wir gemeinsam deine Kollektion, Logo-Positionen, Budgets und Freigaben fest. Dein Team bestellt danach selbst – am Computer oder Smartphone.</p></div><div class="demo-probe-actions"><a class="w-btn w-btn-o" href="mailto:'.e(s_mail()).'?subject='.rawurlencode('Firmenshop für '.$name).'">Gespräch vereinbaren</a><a class="w-btn w-btn-dark" href="/so-funktionierts">So funktioniert’s</a></div></div></section>';
    w_footer();
    render_foot();
}

/* ---------- E-Mail an den Interessenten ---------- */

/**
 * Eingangsbestätigung an Interessenten. 10.15: fester Text ohne Eingaben aus dem Formular (kein Name, keine Firma,
 * keine Nachricht), damit sich das Formular nicht zum Versand fremder Inhalte über unsere Adresse missbrauchen lässt.
 * Ob überhaupt gesendet wird, entscheidet vorher yw_inquiry_confirmation_reserve().
 */
function yw_inquiry_confirmation_mail(string $email,?string $probeshopToken): bool {
    [$subject,$text]=yw_inquiry_confirmation_text($probeshopToken);
    return send_system_mail($email,$subject,$text);
}

/** Betreff und Text der Eingangsbestätigung: nimmt bewusst keine Formulareingaben entgegen. @return array{0:string,1:string} */
function yw_inquiry_confirmation_text(?string $probeshopToken): array {
    $text="Hallo,\n\nvielen Dank für deine Anfrage zum WorkwearCenter. Wir haben deine Angaben erhalten und melden uns so schnell wie möglich persönlich bei dir.\n\n";
    if($probeshopToken!==null){
        $text.="Vorab haben wir aus deinem Logo einen persönlichen Probeshop erstellt. So könnte dein eigener Firmenshop aussehen:\n\n".yw_probeshop_url($probeshopToken)."\n\nDer Link ist nur für dich bestimmt, 30 Tage gültig und wird danach samt Logo automatisch gelöscht.\n\n";
    }
    $text.="Du hast keine Anfrage gestellt? Dann hat jemand deine Adresse eingetragen – du kannst diese E-Mail einfach ignorieren.\n\nViele Grüße\n".current_site_setting('imprint_company','Yanik Textildruck')."\n".s_mail();
    return ['Deine Anfrage beim WorkwearCenter'.($probeshopToken!==null?' – dein Probeshop ist fertig':''),$text];
}

/** 10.15: Höchstens eine Eingangsbestätigung je Adresse in 24 Stunden und YW_INQUIRY_CONFIRM_PER_HOUR insgesamt je Stunde. */
const YW_INQUIRY_CONFIRM_PER_HOUR=30;

/** Vergleichsschlüssel einer Empfängeradresse: Kleinschreibung, ohne „+Zusatz“, bei Gmail ohne Punkte (dieselbe Mailbox). */
function yw_inquiry_recipient_key(string $email): string {
    $email=strtolower(trim($email));$at=strrpos($email,'@');
    if($at===false)return hash('sha256',$email);
    $local=substr($email,0,$at);$domain=substr($email,$at+1);
    $plus=strpos($local,'+');if($plus!==false)$local=substr($local,0,$plus);
    if(in_array($domain,['gmail.com','googlemail.com'],true)){$local=str_replace('.','',$local);$domain='gmail.com';}
    return hash('sha256',$local.'@'.$domain);
}

/**
 * Prüft die Grenzen und vermerkt die Bestätigung an der Anfrage. Rückgabe: 'ok' (senden),
 * 'recipient' (Adresse hatte in 24 h schon eine), 'global' (Stundenlimit erreicht) oder 'unavailable'
 * (Sperre/Datenbankstand fehlt – dann sicherheitshalber nicht senden).
 */
function yw_inquiry_confirmation_reserve(int $inquiryId,string $email): string {
    $key=yw_inquiry_recipient_key($email);$locked=false;
    try{
        $locked=(int)db()->query("SELECT GET_LOCK('yw_inquiry_confirm',5)")->fetchColumn()===1;
        if(!$locked)return 'unavailable';
        $q=db()->prepare('SELECT COUNT(*) FROM inquiries WHERE confirmation_key=? AND confirmation_sent_at>DATE_SUB(NOW(),INTERVAL 24 HOUR)');$q->execute([$key]);
        if((int)$q->fetchColumn()>0)return 'recipient';
        if((int)db()->query('SELECT COUNT(*) FROM inquiries WHERE confirmation_sent_at>DATE_SUB(NOW(),INTERVAL 1 HOUR)')->fetchColumn()>=YW_INQUIRY_CONFIRM_PER_HOUR)return 'global';
        db()->prepare('UPDATE inquiries SET confirmation_key=?,confirmation_sent_at=NOW() WHERE id=?')->execute([$key,$inquiryId]);
        return 'ok';
    }catch(Throwable $e){error_log('Inquiry confirmation limit: '.$e->getMessage());return 'unavailable';}
    finally{if($locked)try{db()->query("SELECT RELEASE_LOCK('yw_inquiry_confirm')");}catch(Throwable $ignored){}}
}

/* ---------- Verwaltung ---------- */

/** Kleine Box an einer Anfrage bzw. einem selbst erzeugten Probeshop. */
function yw_probeshop_admin_box(?array $shop,?array $inquiry=null): string {
    $html='<div class="probeshop-box">';
    if($shop){
        $expired=strtotime((string)$shop['expires_at'])<time();
        $html.='<div class="probeshop-box-head"><strong>Probeshop</strong> <span class="badge '.($expired?'rejected':'approved').'">'.($expired?'abgelaufen':'aktiv bis '.e(date('d.m.Y',strtotime((string)$shop['expires_at'])))).'</span></div>';
        if(yw_probeshop_logo_path_ok((string)$shop['logo_path']))$html.='<img class="probeshop-box-logo'.(yw_probeshop_suggest_tone((string)$shop['logo_path'])==='dark'?' on-dark':'').'" src="/'.e((string)$shop['logo_path']).'" alt="Logo">';
        $html.='<p class="muted">'.(int)$shop['view_count'].'× angesehen'.(!empty($shop['last_viewed_at'])?', zuletzt '.e(date('d.m.Y H:i',strtotime((string)$shop['last_viewed_at']))):'').'</p>';
        $html.='<div class="actions"><a class="btn small primary" href="/probeshop/'.e((string)$shop['token']).'" target="_blank" rel="noopener noreferrer">Probeshop ansehen</a><button type="button" class="btn small" data-copy="'.e(yw_probeshop_url((string)$shop['token'])).'">Link kopieren</button></div>';
        $html.='<div class="actions"><form method="post" data-confirm="Neuen Link erzeugen? Der bisherige Link funktioniert dann nicht mehr; die Laufzeit beginnt neu (30 Tage).">'.csrf_field().'<input type="hidden" name="action" value="probeshop_regenerate"><input type="hidden" name="return_tab" value="inquiries"><input type="hidden" name="probeshop_id" value="'.(int)$shop['id'].'"><button class="btn small" type="submit">Neu erzeugen</button></form>';
        $html.='<form method="post" data-confirm="Probeshop und Logo endgültig löschen?">'.csrf_field().'<input type="hidden" name="action" value="probeshop_delete"><input type="hidden" name="return_tab" value="inquiries"><input type="hidden" name="probeshop_id" value="'.(int)$shop['id'].'"><button class="btn small danger" type="submit">Löschen</button></form></div>';
    }elseif($inquiry){
        $html.='<details><summary>Probeshop erzeugen</summary><form method="post" enctype="multipart/form-data" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="probeshop_create"><input type="hidden" name="return_tab" value="inquiries"><input type="hidden" name="inquiry_id" value="'.(int)$inquiry['id'].'"><input type="hidden" name="company_name" value="'.e((string)$inquiry['company']).'"><label>Logo <small>PNG, JPG, WebP oder SVG</small><input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg,image/png,image/jpeg,image/webp,image/svg+xml" required></label>'.yw_probeshop_tone_select().'<button class="btn small primary" type="submit">Probeshop erzeugen</button></form></details>';
    }
    return $html.'</div>';
}

function yw_probeshop_tone_select(): string {
    return '<label>Textilfarbe in der Vorschau<select name="garment_tone"><option value="auto">automatisch passend zum Logo</option><option value="dark">dunkle Kleidung</option><option value="light">helle Kleidung</option></select></label>';
}

/** Bereich „Probeshop für einen Termin“ oberhalb der Anfragen. */
function yw_probeshop_admin_panel(): void {
    $shops=[];
    try{$shops=db()->query("SELECT * FROM yw_probeshops WHERE inquiry_id IS NULL ORDER BY id DESC LIMIT 50")->fetchAll();}catch(Throwable $e){}
    echo '<section class="card probeshop-panel"><div class="title-row"><div><h2>Probeshop erzeugen</h2><p class="muted">Für den Termin vor Ort: Firmenname und Logo eingeben – in Sekunden entsteht ein persönlicher Vorschau-Shop mit dem Logo auf der Kleidung. Der Link ist 30 Tage gültig; danach werden Shop und Logo automatisch gelöscht.</p></div></div>';
    echo '<form method="post" enctype="multipart/form-data" class="probeshop-create">'.csrf_field().'<input type="hidden" name="action" value="probeshop_create"><input type="hidden" name="return_tab" value="inquiries">';
    echo '<label>Firmenname<input name="company_name" maxlength="190" required placeholder="z. B. Musterbau GmbH"></label><label>Logo <small>PNG, JPG, WebP oder SVG, max. 4 MB</small><input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg,image/png,image/jpeg,image/webp,image/svg+xml" required></label>'.yw_probeshop_tone_select().'<label>Shopfarbe <small>optional</small><input type="color" name="primary_color" value="#10283f"></label><button class="btn primary" type="submit">Probeshop erzeugen</button></form>';
    if($shops){
        echo '<div class="probeshop-list">';
        foreach($shops as $shop)echo '<article class="probeshop-item" id="probeshop-'.(int)$shop['id'].'"><h3>'.e((string)$shop['company_name']).'</h3><p class="muted">erstellt am '.e(date('d.m.Y',strtotime((string)$shop['created_at']))).'</p>'.yw_probeshop_admin_box($shop).'</article>';
        echo '</div>';
    }
    echo '</section>';
}

function yw_probeshop_admin_action(string $action): never {
    require_admin();
    try{
        if($action==='probeshop_create'){
            $inquiryId=(int)($_POST['inquiry_id']??0)?:null;
            $company=trim((string)($_POST['company_name']??''));
            if($inquiryId){
                $q=db()->prepare('SELECT company FROM inquiries WHERE id=?');$q->execute([$inquiryId]);$company=(string)($q->fetchColumn()?:'');
                if($company==='')throw new RuntimeException('Anfrage wurde nicht gefunden.');
            }
            if($company===''||mb_strlen($company)>190)throw new RuntimeException('Bitte einen Firmennamen angeben.');
            $logo=yw_probeshop_store_logo($_FILES['logo']??null);
            if($logo==='')throw new RuntimeException('Bitte ein Logo auswählen.');
            $color=trim((string)($_POST['primary_color']??''));
            try{
                $created=yw_probeshop_create($company,$logo,$inquiryId,'admin',(string)($_POST['garment_tone']??'auto'),($color!==''&&strtolower($color)!=='#10283f')?$color:null);
            }catch(Throwable $e){yw_probeshop_delete_logo($logo);throw $e;}
            flash('success','Probeshop für '.$company.' ist fertig: '.yw_probeshop_url($created['token']));
            redirect('/admin?tab=inquiries#probeshop-'.$created['id']);
        }
        $id=(int)($_POST['probeshop_id']??0);
        if($action==='probeshop_regenerate'){
            yw_probeshop_regenerate($id);
            flash('success','Neuer Probeshop-Link wurde erzeugt. Der alte Link ist ab sofort ungültig.');
            redirect('/admin?tab=inquiries');
        }
        if($action==='probeshop_delete'){
            if(!yw_probeshop_find($id))throw new RuntimeException('Probeshop wurde nicht gefunden.');
            yw_probeshop_delete($id);
            flash('success','Probeshop und Logo wurden gelöscht.');
            redirect('/admin?tab=inquiries');
        }
        throw new RuntimeException('Aktion unbekannt.');
    }catch(Throwable $e){
        if($e instanceof PDOException){error_log('WorkwearCenter probeshop: '.$e->getMessage());$msg='Speichern fehlgeschlagen. Bitte erneut versuchen.';}
        else $msg=$e->getMessage();
        flash('error',$msg);
        redirect('/admin?tab=inquiries');
    }
}
