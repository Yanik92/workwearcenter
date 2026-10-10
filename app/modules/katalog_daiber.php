<?php
declare(strict_types=1);
/**
 * Produktbilder automatisch aus dem öffentlichen Daiber-Shop laden (ab 10.15).
 *
 * Die Daiber-Liste nennt nur Dateinamen, die Media Cloud braucht ein Login. Der öffentliche Shop
 * www.daiber.shop zeigt aber je Artikel und Farbe das Masterbild (Vorderansicht) und in der
 * Farbvariante die Detailansichten (Seite, Rücken). Diese Bilder werden verkleinert im Bilderordner
 * abgelegt (uploads/katalog/bilder/daiber/artikel_farbe.jpg, …_back.jpg, …_side.jpg); die bestehende
 * Zuordnung übernimmt sie als Produktbild, Rück- und Ärmelansicht, die Druckzonen folgen automatisch.
 *
 * Wann: beim Anlegen von Katalogprodukten, per Knopf im Lieferantenkatalog und im Cronjob für alles,
 * was noch fehlt. Je Artikel wird die Shopseite höchstens einmal pro Woche abgerufen.
 *
 * Sicherheit: nur HTTPS zu drei festen Daiber-Adressen, Weiterleitungen nur dorthin, Größen- und
 * Zeitgrenzen; jede Datei muss ein echtes JPG/PNG/WebP sein und wird neu gespeichert.
 */

const YW_DAIBER_SHOP='https://www.daiber.shop';
const YW_DAIBER_MEDIA='https://daiber-prod-media-hp.s3.sbg.perf.cloud.ovh.net/media/';
const YW_DAIBER_CDN='https://daiber-images.synaigy.com/http/media/';
const YW_DAIBER_HOSTS=['www.daiber.shop','daiber-prod-media-hp.s3.sbg.perf.cloud.ovh.net','daiber-images.synaigy.com'];
const YW_DAIBER_CACHE_DAYS=7;
const YW_DAIBER_MAX_HTML=6_000_000;
const YW_DAIBER_MAX_IMAGE=20_000_000;

function yw_daiber_enabled(): bool { return current_site_setting('katalog_daiber_auto','1')!=='0'; }
function yw_daiber_dir(): string { return yw_katalog_images_dir().'/daiber'; }
function yw_daiber_paused(): bool { return (int)current_site_setting('katalog_daiber_pause_until','0')>time(); }
function yw_daiber_pause(): void { try{set_site_setting('katalog_daiber_pause_until',(string)(time()+900));}catch(Throwable $ignored){} }

/** Nur HTTPS zu den festen Daiber-Adressen, ohne Zugangsdaten oder eigenen Port. */
function yw_daiber_url_allowed(string $url): bool {
    $p=parse_url($url);
    return is_array($p)&&strtolower((string)($p['scheme']??''))==='https'&&in_array(strtolower((string)($p['host']??'')),YW_DAIBER_HOSTS,true)&&!isset($p['user'])&&!isset($p['pass'])&&!isset($p['port']);
}

/** cURL-Abruf vorbereiten; der Inhalt landet in $slot['body'], bei Überschreiten von $maxBytes wird abgebrochen ($slot['big']). */
function yw_daiber_handle(string $url,int $maxBytes,array &$slot): CurlHandle {
    $slot=['body'=>'','big'=>false];
    // Höchstens bis kurz nach dem Zeitbudget des laufenden Abgleichs warten (sonst 25 s)
    $deadline=(float)($GLOBALS['yw_daiber_deadline']??0);
    $timeout=$deadline>0?max(5,min(25,(int)ceil($deadline-microtime(true))+8)):25;
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>min(6,$timeout),CURLOPT_TIMEOUT=>$timeout,CURLOPT_ENCODING=>'',
        CURLOPT_USERAGENT=>'WorkwearCenter/'.app_version().' (Bildabgleich Daiber-Fachhandel; +https://'.workwearcenter_domain().')',
        CURLOPT_HTTPHEADER=>['Accept: text/html,application/json,image/jpeg,image/png,image/webp;q=0.9','Accept-Language: de-DE,de;q=0.9'],
        CURLOPT_WRITEFUNCTION=>static function($ch,string $chunk) use(&$slot,$maxBytes): int{if(strlen($slot['body'])+strlen($chunk)>$maxBytes){$slot['big']=true;return 0;}$slot['body'].=$chunk;return strlen($chunk);}]);
    if(defined('CURLOPT_PROTOCOLS'))curl_setopt($ch,CURLOPT_PROTOCOLS,CURLPROTO_HTTPS);
    return $ch;
}

/**
 * Lädt eine Daiber-Adresse (höchstens $maxBytes). Rückgabe Inhalt oder null.
 * Ist der Daiber-Shop nicht erreichbar, pausiert der Abgleich 15 Minuten, damit Seiten und Cronjob nicht hängen.
 */
function yw_daiber_get(string $url,int $maxBytes): ?string {
    if(yw_daiber_paused())return null;
    for($hop=0;$hop<4;$hop++){
        if(!yw_daiber_url_allowed($url))return null;
        $status=0;$location='';$failed=false;$body='';
        if(function_exists('curl_init')){
            $slot=[];$ch=yw_daiber_handle($url,$maxBytes,$slot);
            $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$location=(string)curl_getinfo($ch,CURLINFO_REDIRECT_URL);curl_close($ch);
            if($slot['big'])return null; // größer als erlaubt: verwerfen, aber kein Ausfall des Daiber-Shops
            $failed=$ok===false;$body=$slot['body'];
        }else{
            $context=stream_context_create(['http'=>['method'=>'GET','follow_location'=>0,'timeout'=>20,'ignore_errors'=>true,'user_agent'=>'WorkwearCenter/'.app_version(),'header'=>"Accept: text/html,application/json,image/*\r\n"]]);
            $fp=@fopen($url,'rb',false,$context);
            if(!$fp)$failed=true;
            else{
                $body=(string)stream_get_contents($fp,$maxBytes+1);$meta=stream_get_meta_data($fp);fclose($fp);
                if(strlen($body)>$maxBytes)return null;
                foreach((array)($meta['wrapper_data']??[]) as $h){if(preg_match('~^HTTP/\S+\s+(\d{3})~',(string)$h,$m))$status=(int)$m[1];elseif(preg_match('~^Location:\s*(\S+)~i',(string)$h,$m))$location=$m[1];}
            }
        }
        // Pause nur, wenn der Shop selbst gestört ist; ein einzelnes fehlerhaftes Bild hält den Abgleich nicht an
        if($failed||$status>=500||$status===429){if(strtolower((string)parse_url($url,PHP_URL_HOST))==='www.daiber.shop')yw_daiber_pause();return null;}
        if($status>=300&&$status<400&&$location!==''){$url=preg_match('~^https?://~i',$location)?$location:YW_DAIBER_SHOP.'/'.ltrim($location,'/');continue;}
        return $status===200?$body:null;
    }
    return null;
}

/** Mehrere Adressen gleichzeitig laden (höchstens $parallel zugleich). Rückgabe: Adresse → Inhalt oder null. */
function yw_daiber_get_many(array $urls,int $maxBytes,int $parallel=4): array {
    $urls=array_values(array_unique(array_map('strval',$urls)));$out=array_fill_keys($urls,null);
    if(!$urls||yw_daiber_paused())return $out;
    if(!function_exists('curl_multi_init')||count($urls)===1){foreach($urls as $u)$out[$u]=yw_daiber_get($u,$maxBytes);return $out;}
    $queue=array_values(array_filter($urls,'yw_daiber_url_allowed'));$mh=curl_multi_init();$slots=[];$open=[];$failed=0;$redirect=[];
    while($queue||$open){
        while($queue&&count($open)<$parallel){$u=array_shift($queue);$slots[$u]=[];$ch=yw_daiber_handle($u,$maxBytes,$slots[$u]);curl_multi_add_handle($mh,$ch);$open[spl_object_id($ch)]=$u;}
        curl_multi_exec($mh,$running);
        if($running&&curl_multi_select($mh,1.0)===-1)usleep(20000);
        while(($info=curl_multi_info_read($mh))!==false){
            $ch=$info['handle'];$u=$open[spl_object_id($ch)]??null;unset($open[spl_object_id($ch)]);
            $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$location=(string)curl_getinfo($ch,CURLINFO_REDIRECT_URL);
            curl_multi_remove_handle($mh,$ch);curl_close($ch);
            if($u===null||!empty($slots[$u]['big']))continue;
            if($info['result']!==CURLE_OK||$status>=500||$status===429){$failed++;continue;}
            if($status>=300&&$status<400&&$location!=='')$redirect[$u]=$location;
            elseif($status===200)$out[$u]=$slots[$u]['body'];
        }
    }
    curl_multi_close($mh);
    if($failed>=max(2,(int)ceil(count($urls)/2))){yw_daiber_pause();return $out;}
    foreach($redirect as $u=>$location)$out[$u]=yw_daiber_get($location,$maxBytes);
    return $out;
}

/** Artikelseite im Daiber-Shop über die Suche finden (Adresse endet auf „-artikelnummer/nummer“). '' = nicht vorhanden, null = Shop nicht erreichbar. */
function yw_daiber_find_article_url(string $sku): ?string {
    $html=yw_daiber_get(YW_DAIBER_SHOP.'/search?search='.rawurlencode($sku),YW_DAIBER_MAX_HTML);
    if($html===null)return null;
    $suffix='-'.strtolower((string)preg_replace('/[^a-z0-9]+/i','',$sku));
    preg_match_all('~href="(https://www\.daiber\.shop/([a-z0-9][a-z0-9\-]*)/(\d+))"~i',$html,$m,PREG_SET_ORDER);
    foreach($m as $hit)if(str_ends_with(strtolower($hit[2]),$suffix))return $hit[1];
    return '';
}

/**
 * Farben einer Artikelseite: Farbname (wie in der Daiber-Liste, klein) → Masterbild und Auswahlwerte für
 * die Farbvariante; dazu die Adresse, über die der Shop eine Farbvariante öffnet.
 */
function yw_daiber_parse_article(string $html): array {
    $switch=null;
    if(preg_match('/data-variant-switch-options="([^"]+)"/',$html,$m)){$j=json_decode(html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5),true);$u=(string)($j['url']??'');if(yw_daiber_url_allowed($u))$switch=$u;}
    $colors=[];
    preg_match_all('~<label\s+class="product-detail-configurator-option-label[^"]*\bis-display-media\b[^"]*"([^>]*)>(.*?)</label>~s',$html,$labels,PREG_SET_ORDER);
    foreach($labels as $label){
        if(!preg_match('/\btitle="([^"]*)"/',$label[1],$t)||!preg_match('/\bfor="([0-9a-f]{32})-([0-9a-f]{32})/',$label[1],$f)||!preg_match('/<img\s[^>]*?\bsrc="([^"]+)"/s',$label[2],$img))continue;
        $src=(string)strtok(html_entity_decode($img[1],ENT_QUOTES|ENT_HTML5),'?');
        if(!str_starts_with($src,YW_DAIBER_MEDIA)||!preg_match('/_master\.(jpe?g|png|webp)$/i',$src))continue;
        $colors[mb_strtolower(trim(html_entity_decode($t[1],ENT_QUOTES|ENT_HTML5)))]??=['img'=>$src,'g'=>$f[1],'o'=>$f[2]];
    }
    // Artikel mit nur einer Farbe haben keine Farbauswahl: dann gilt das erste Masterbild der Galerie
    $single=null;
    if(!$colors&&preg_match('~'.preg_quote(YW_DAIBER_MEDIA,'~').'[^"\'\s?]+_master\.(?:jpe?g|png|webp)~i',$html,$m))$single=$m[0];
    return ['switch'=>$switch,'colors'=>$colors,'single'=>$single];
}

/**
 * Detailbilder (Seite, Rücken …) einer Farbvariante: Dateien „…<bildnummer>_detail_N“ derselben Farbe.
 * Verglichen wird die Bildnummer des Masterbilds (z. B. „.41627“), weil der Dateiname davor in der
 * Variante teils anders lautet (8001-ladies-t-blau… / 8001-ladies-t---ocs-standard-blau…).
 * Zeichnungen und Modellfotos („art-…“) bleiben außen vor.
 */
function yw_daiber_detail_urls(string $html,string $master): array {
    $base=(string)preg_replace('/_master\.(jpe?g|png|webp)$/i','',basename((string)parse_url($master,PHP_URL_PATH)));
    $pattern=preg_match('/(\.\d+)$/',$base,$id)?'/^(?!art-).+'.preg_quote($id[1],'/').'_detail_\d+\.(jpe?g|png|webp)$/i':'/^'.preg_quote($base,'/').'_detail_\d+\.(jpe?g|png|webp)$/i';
    preg_match_all('~'.preg_quote(YW_DAIBER_MEDIA,'~').'[^"\'\s?]+~',$html,$m);
    $out=[];
    foreach($m[0] as $u)if(preg_match($pattern,basename($u)))$out[$u]=true;
    return array_slice(array_keys($out),0,6);
}

/** Zwischenspeicher je Artikel (privater Ordner): Adresse, Farben und was schon versucht wurde. */
function yw_daiber_cache_file(string $sku): string {
    $dir=yw_private_dir().'/daiber-shop';
    if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Zwischenspeicher für Daiber-Bilder konnte nicht angelegt werden.');
    return $dir.'/'.preg_replace('/[^A-Z0-9_-]/','_',strtoupper($sku)).'.json';
}
function yw_daiber_cache_save(string $sku,array $data): void {
    $file=yw_daiber_cache_file($sku);$tmp=$file.'.'.bin2hex(random_bytes(4));
    if(@file_put_contents($tmp,json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))!==false)@rename($tmp,$file);else @unlink($tmp);
}

/**
 * Daten eines Artikels aus dem Daiber-Shop (höchstens wöchentlich neu abgerufen; die Adresse wird gemerkt).
 * Rückgabe: ['url','switch','colors','single','done'=>[farbe=>['front'|'views'=>Zeit]],'t'],
 * 'url'=>'' wenn es den Artikel im Daiber-Shop nicht gibt, null wenn der Shop gerade nicht erreichbar ist.
 */
function yw_daiber_article(string $sku): ?array {
    $file=yw_daiber_cache_file($sku);
    $cached=is_file($file)?json_decode((string)file_get_contents($file),true):null;
    if(is_array($cached)&&(int)($cached['t']??0)>time()-YW_DAIBER_CACHE_DAYS*86400)return $cached;
    $url=is_array($cached)&&!empty($cached['url'])&&yw_daiber_url_allowed((string)$cached['url'])?(string)$cached['url']:null;
    $html=$url!==null?yw_daiber_get($url,YW_DAIBER_MAX_HTML):null;
    $t=time();
    if($html===null){
        if(yw_daiber_paused())return null;
        $url=yw_daiber_find_article_url($sku);
        if($url===null){
            if(yw_daiber_paused())return null;
            $url='';$t=time()-(YW_DAIBER_CACHE_DAYS-1)*86400; // Suche antwortet nicht sinnvoll: als „nicht gefunden“ merken, morgen neu versuchen
        }elseif($url!==''){
            $html=yw_daiber_get($url,YW_DAIBER_MAX_HTML);
            if($html===null){if(yw_daiber_paused())return null;$url='';$t=time()-(YW_DAIBER_CACHE_DAYS-1)*86400;} // Artikelseite fehlt (z. B. 404)
        }
    }
    $data=['url'=>$url,'switch'=>null,'colors'=>[],'single'=>null,'done'=>[],'t'=>$t];
    if($html!==null){
        $data=array_merge($data,yw_daiber_parse_article($html));
        if(!$data['colors']&&!$data['single'])$data['url']='';
    }
    yw_daiber_cache_save($sku,$data);
    return $data;
}

/** Bildinhalt prüfen und verkleinert (max. 1400 px, JPG) unter $target ablegen. */
function yw_daiber_save_image(string $data,string $target): bool {
    $tmp=tempnam(sys_get_temp_dir(),'wwcd');if($tmp===false)return false;
    $part=$target.'.'.bin2hex(random_bytes(6)).'.part';
    try{
        if(file_put_contents($tmp,$data)===false)return false;
        yw_validate_uploaded_raster($tmp,'Daiber-Bild');
        $dir=dirname($target);if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))return false;
        [$w,$h,$type]=getimagesize($tmp);
        $img=function_exists('imagecreatetruecolor')?match($type){IMAGETYPE_JPEG=>@imagecreatefromjpeg($tmp),IMAGETYPE_PNG=>@imagecreatefrompng($tmp),IMAGETYPE_WEBP=>function_exists('imagecreatefromwebp')?@imagecreatefromwebp($tmp):false,default=>false}:false;
        if($img){
            $scale=min(1,1400/max($w,$h));$nw=max(1,(int)round($w*$scale));$nh=max(1,(int)round($h*$scale));
            $out=imagecreatetruecolor($nw,$nh);imagefill($out,0,0,imagecolorallocate($out,255,255,255));
            imagecopyresampled($out,$img,0,0,0,0,$nw,$nh,$w,$h);
            $ok=imagejpeg($out,$part,88);imagedestroy($out);imagedestroy($img);
        }else $ok=$type===IMAGETYPE_JPEG&&strlen($data)<=8*1024*1024&&copy($tmp,$part);
        if(!$ok||!@rename($part,$target))return false;
        @chmod($target,0644);
        return true;
    }catch(Throwable $e){return false;}
    finally{@unlink($tmp);if(is_file($part))@unlink($part);}
}

/**
 * Bilder laden und ablegen: Adresse → Zieldatei. Zuerst über den Bilddienst von Daiber in passender Größe
 * (schnell, klein), sonst das Original. Rückgabe: Liste der gespeicherten Zieldateien.
 */
function yw_daiber_download(array $targets): array {
    // Der Bilddienst liefert immer WebP: nur nutzen, wenn GD WebP lesen kann, sonst gleich das Original
    $cdn=[];if(function_exists('imagecreatefromwebp'))foreach($targets as $url=>$target)if(str_starts_with((string)$url,YW_DAIBER_MEDIA))$cdn[(string)$url]=YW_DAIBER_CDN.substr((string)$url,strlen(YW_DAIBER_MEDIA)).'?w=1000&quality=90&format=jpg';
    $got=yw_daiber_get_many(array_values($cdn),YW_DAIBER_MAX_IMAGE,6);$saved=[];$retry=[];
    foreach($targets as $url=>$target){
        $data=isset($cdn[$url])?($got[$cdn[$url]]??null):null;
        if($data!==null&&yw_daiber_save_image($data,$target))$saved[]=$target;else $retry[]=(string)$url;
    }
    if($retry&&!yw_daiber_paused()){
        $got=yw_daiber_get_many($retry,YW_DAIBER_MAX_IMAGE,4);
        foreach($retry as $url)if(($got[$url]??null)!==null&&yw_daiber_save_image($got[$url],$targets[$url]))$saved[]=$targets[$url];
    }
    return $saved;
}

/**
 * Rück- und Seitenansicht unter den Detailbildern erkennen: Der Rücken hat ungefähr den Umriss der
 * Vorderansicht, die Seitenansicht ist deutlich schmaler. Ausschnitte und Modellfotos fallen heraus.
 */
function yw_daiber_pick_views(string $frontFile,array $detailFiles,bool $sleeve): array {
    $front=yw_zone_image_shape($frontFile);if(!$front)return [];
    $size=static fn(array $s): array=>[($s['r']-$s['l'])/$s['w'],($s['b']-$s['t'])/$s['h']];
    [$fw,$fh]=$size($front);$views=[];
    foreach($detailFiles as $file){
        $s=yw_zone_image_shape($file);if(!$s)continue;
        [$w,$h]=$size($s);
        if($h<$fh*0.75||$h>$fh*1.25)continue;
        if(!isset($views['back'])&&$w>=$fw*0.8&&$w<=$fw*1.2)$views['back']=$file;
        elseif($sleeve&&!isset($views['sleeve'])&&$w<=$fw*0.7)$views['sleeve']=$file;
    }
    return $views;
}

/**
 * Was je Artikel und Farbe noch fehlt: Vorderansicht (front) und/oder Rücken/Ärmel (views).
 * $skus: nur diese Artikel. $allColors: für diese Artikel auch Farben, die noch nicht im Shop sind (nur Vorderansicht).
 */
function yw_daiber_needed(array $catalog,?array $skus,bool $allColors): array {
    $need=[];
    $rows=db()->query("SELECT id,sku,color,name,category,image_path,image_back_path,image_sleeve_path FROM products WHERE supplier_name='Daiber' AND (COALESCE(image_path,'')='' OR COALESCE(image_back_path,'')='' OR COALESCE(image_sleeve_path,'')='') ORDER BY (COALESCE(image_path,'')='') DESC,id")->fetchAll();
    foreach($rows as $p){
        $color=mb_strtolower(trim((string)$p['color']));
        $sku=yw_katalog_base_sku((string)$p['sku'],$color);
        if($skus!==null&&!in_array($sku,$skus,true))continue;
        $a=$catalog['articles'][$sku]??null;if(!$a)continue;
        $name=null;foreach($a['col'] as $n=>$c)if(mb_strtolower((string)$n)===$color){$name=(string)$n;break;}
        if($name===null)continue;
        $need[$sku][$name]??=['front'=>false,'views'=>false];
        if(empty($p['image_path']))$need[$sku][$name]['front']=true;
        // Rücken bei Oberteilen und Hosen, Ärmel nur bei Oberteilen (Mützen, Taschen usw. haben dafür keine passenden Fotos)
        $type=yw_zone_garment_type($p);
        if(($type==='top'||$type==='legs')&&empty($p['image_back_path'])||$type==='top'&&empty($p['image_sleeve_path']))$need[$sku][$name]['views']=true;
    }
    if($allColors&&$skus)foreach($skus as $sku)foreach(($catalog['articles'][$sku]['col']??[]) as $name=>$c)$need[$sku][(string)$name]??=['front'=>true,'views'=>false];
    return $need;
}

/**
 * Lädt fehlende Bilder für Daiber-Produkte im Shop und ordnet sie zu (siehe yw_daiber_needed).
 * Arbeitet höchstens etwa $seconds Sekunden; was übrig bleibt, folgt beim nächsten Aufruf.
 * Läuft schon ein Abgleich (Cronjob oder Knopf in einem anderen Fenster), kommt busy=true zurück.
 * Rückgabe: loaded (Dateien), assigned (Produkte), remaining (Artikel noch offen), notfound (Artikelnummern), offline, busy.
 */
function yw_daiber_fetch_missing(float $seconds=20.0,?array $skus=null,bool $allColors=false): array {
    $result=['loaded'=>0,'assigned'=>0,'remaining'=>0,'notfound'=>[],'offline'=>false,'busy'=>false];
    $catalog=yw_katalog_load();
    if(!$catalog)return $result;
    $dir=yw_daiber_dir();
    if(!is_dir($dir)&&!@mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Der Bilderordner uploads/katalog/bilder/daiber/ kann nicht angelegt werden. Bitte beim Hoster Schreibrechte für uploads/ prüfen.');
    if(!is_writable($dir))throw new RuntimeException('Der Bilderordner uploads/katalog/bilder/daiber/ ist nicht beschreibbar. Bitte per FTP Schreibrechte setzen (z. B. 755 bzw. 775).');
    $cacheDir=dirname(yw_daiber_cache_file('x'));
    if(!is_writable($cacheDir))throw new RuntimeException('Der private Datenordner ('.basename($cacheDir).') ist nicht beschreibbar.');
    // Nur ein Abgleich gleichzeitig (Cronjob und Verwaltung schreiben sonst dieselben Dateien)
    $lock=@fopen($cacheDir.'/.lock','c');
    if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if($lock)fclose($lock);$result['busy']=true;return $result;}
    try{
        $deadline=microtime(true)+$seconds;$GLOBALS['yw_daiber_deadline']=$deadline;
        @set_time_limit((int)ceil($seconds)+90);
        $limit=trim((string)ini_get('memory_limit'));$bytes=(float)$limit*match(strtolower(substr($limit,-1))){'g'=>1073741824,'m'=>1048576,'k'=>1024,default=>1};
        if($limit!=='-1'&&$bytes<256*1048576)@ini_set('memory_limit','256M'); // Originalfotos haben bis zu 10 Megapixel
        $have=yw_katalog_image_map(true)['map']; // schon vorhandene Bilder (Upload, FTP oder früherer Abgleich)
        foreach(yw_daiber_needed($catalog,$skus,$allColors) as $sku=>$colors){
            $sku=(string)$sku;$article=null;$todo=[];
            if($result['offline']||microtime(true)>$deadline){$result['remaining']++;continue;}
            foreach($colors as $color=>$want){
                $color=(string)$color;$key=yw_katalog_color_key($sku,$color);
                $front=$want['front']&&!isset($have[$key]);
                if(!$front&&!$want['views'])continue;
                if($article===null){
                    $article=yw_daiber_article($sku);
                    if($article===null){$result['offline']=true;break;}
                    if($article['url']===''){$result['notfound'][]=$sku;break;}
                }
                $done=$article['done'][$color]??[];
                $front=$front&&!isset($done['front']);$views=$want['views']&&!isset($done['views']);
                if(!$front&&!$views)continue;
                $c=$article['colors'][mb_strtolower($color)]??null;
                if($c===null&&$article['single']&&count($catalog['articles'][$sku]['col'])===1)$c=['img'=>$article['single'],'g'=>'','o'=>''];
                if($c===null){$article['done'][$color]=['front'=>time(),'views'=>time()];continue;} // Farbe gibt es im Daiber-Shop nicht (mehr)
                // Eigenes Foto (Upload/FTP) hat Vorrang: Daiber-Vorderansicht dann nur als Vergleichsbild, nicht im Bilderordner
                $own=isset($have[$key])&&!str_starts_with(str_replace('\\','/',(string)$have[$key]),$dir.'/');
                $todo[$color]=['key'=>$key,'c'=>$c,'views'=>$views,'cmp'=>$own?sys_get_temp_dir().'/wwcf-'.bin2hex(random_bytes(6)).'.jpg':$dir.'/'.$key.'.jpg'];
            }
            if($result['offline']){$result['remaining']++;continue;}
            $tmpFiles=[];
            // In Paketen zu 4 Farben, damit auch Artikel mit vielen Farben in die Zeitgrenze passen
            foreach(array_chunk($todo,4,true) as $chunk){
                if(microtime(true)>$deadline){$result['remaining']++;break;}
                // 1) Vorderansichten (Produktbild bzw. Vergleich für die Erkennung von Rücken und Seite)
                $targets=[];foreach($chunk as $color=>$t){if(!is_file($t['cmp']))$targets[$t['c']['img']]=$t['cmp'];if(!str_starts_with($t['cmp'],$dir.'/'))$tmpFiles[]=$t['cmp'];}
                foreach(yw_daiber_download($targets) as $file)if(str_starts_with($file,$dir.'/'))$result['loaded']++;
                if(yw_daiber_paused()){$result['offline']=true;$result['remaining']++;break;}
                foreach($chunk as $color=>$t){
                    $article['done'][$color]['front']=time();
                    if(!is_file($t['cmp'])){$article['done'][$color]['views']=time();unset($chunk[$color]);continue;} // Bild nicht zu bekommen: nicht endlos wiederholen
                    if(str_starts_with($t['cmp'],$dir.'/'))$have[$t['key']]=$t['cmp'];
                }
                if(microtime(true)>$deadline&&array_filter($chunk,static fn(array $t): bool=>$t['views'])){$result['remaining']++;break;}
                // 2) Farbvarianten öffnen und deren Detailbilder sammeln
                $viewColors=array_filter($chunk,static fn(array $t): bool=>$t['views']);
                $pages=[];$switch=[];
                foreach($viewColors as $color=>$t){
                    if($article['switch']&&$t['c']['g']!=='')$switch[$color]=$article['switch'].'?'.http_build_query(['switched'=>$t['c']['g'],'options'=>json_encode([$t['c']['g']=>$t['c']['o']])]);
                    else $pages[$color]=$article['url']; // Artikel ohne Farbauswahl: Detailbilder stehen auf der Artikelseite
                }
                $answers=$switch?yw_daiber_get_many(array_values($switch),200_000,4):[];
                foreach($switch as $color=>$u){$target=(string)(json_decode((string)($answers[$u]??''),true)['url']??'');if($target!==''&&yw_daiber_url_allowed($target))$pages[$color]=$target;}
                $html=$pages?yw_daiber_get_many(array_values($pages),YW_DAIBER_MAX_HTML,4):[];
                $details=[];$targets=[];
                foreach($pages as $color=>$u){
                    foreach(yw_daiber_detail_urls((string)($html[$u]??''),$chunk[$color]['c']['img']) as $d){$tmp=sys_get_temp_dir().'/wwcv-'.bin2hex(random_bytes(6)).'.jpg';$targets[$d]=$tmp;$details[$color][]=$tmp;$tmpFiles[]=$tmp;}
                }
                yw_daiber_download($targets);
                // 3) Rücken und Seite erkennen und ablegen
                foreach($viewColors as $color=>$t){
                    $a=$catalog['articles'][$sku];
                    $found=yw_daiber_pick_views($t['cmp'],array_values(array_filter($details[$color]??[],'is_file')),yw_zone_garment_type(['category'=>$a['c'],'name'=>$a['n']])==='top');
                    foreach(['back'=>'_back','sleeve'=>'_side'] as $view=>$suffix){
                        $target=$dir.'/'.$t['key'].$suffix.'.jpg';
                        if(isset($found[$view])&&!is_file($target)&&@copy($found[$view],$target)){@chmod($target,0644);$result['loaded']++;}
                    }
                    // Erledigt, wenn die Variantenseite gelesen wurde; bei Störungen höchstens dreimal versuchen
                    $tries=(int)($article['tries'][$color]??0)+1;$article['tries'][$color]=$tries;
                    if(($html[$pages[$color]??'']??null)!==null||$tries>=3)$article['done'][$color]['views']=time();
                }
                if(yw_daiber_paused()){$result['offline']=true;$result['remaining']++;break;}
            }
            foreach($tmpFiles as $tmp)if(is_file($tmp))@unlink($tmp);
            if($article!==null)yw_daiber_cache_save($sku,$article);
        }
        $result['notfound']=array_values(array_unique($result['notfound']));
        if($result['loaded']){
            $assigned=yw_katalog_assign_images();$result['assigned']=$assigned['assigned'];
            if($result['assigned'])try{require_once __DIR__.'/shop.php';yw_zone_auto_backfill(40);}catch(Throwable $e){error_log('Druckzonen: '.$e->getMessage());}
        }
        try{set_site_setting('katalog_daiber_last',(string)json_encode(['t'=>date('Y-m-d H:i'),'loaded'=>$result['loaded'],'assigned'=>$result['assigned'],'notfound'=>$result['notfound'],'remaining'=>$result['remaining'],'offline'=>$result['offline']],JSON_UNESCAPED_UNICODE));}catch(Throwable $ignored){}
        return $result;
    }finally{
        unset($GLOBALS['yw_daiber_deadline']);
        flock($lock,LOCK_UN);fclose($lock);
    }
}

/** Rückmeldung für die Verwaltung. */
function yw_daiber_fetch_message(array $r): string {
    if(!empty($r['busy']))return 'Gerade läuft schon ein Abgleich (z. B. der Cronjob). Bitte in einer Minute noch einmal klicken.';
    if($r['offline']&&!$r['loaded'])return 'Der Daiber-Shop ist gerade nicht erreichbar. Der Abgleich wird in 15 Minuten automatisch wiederholt.';
    $msg=$r['loaded'].' Bild(er) von daiber.shop geladen, '.$r['assigned'].' Produkt(e) zugeordnet.';
    if($r['remaining'])$msg.=' '.$r['remaining'].' Artikel folgen automatisch (Cronjob) oder mit einem weiteren Klick.';
    if($r['notfound'])$msg.=' Im Daiber-Shop nicht gefunden: '.implode(', ',array_slice($r['notfound'],0,10)).(count($r['notfound'])>10?' …':'').' – diese Bilder bitte aus der Media Cloud hochladen.';
    return $msg;
}
