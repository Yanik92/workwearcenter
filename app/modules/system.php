<?php
declare(strict_types=1);

function product_categories(): array {
    try {
        return db()->query('SELECT * FROM product_categories ORDER BY sort_order ASC, name ASC')->fetchAll();
    } catch(Throwable $e) {
        return [];
    }
}

function product_category_name(?int $id,string $fallback=''): string {
    if(!$id) return $fallback;
    $stmt=db()->prepare('SELECT name FROM product_categories WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $name=$stmt->fetchColumn();
    return $name===false ? $fallback : (string)$name;
}

function delete_product_image(string $path): void {
    if(!yw_upload_path_ok($path,'products'))return;
    $file=dirname(__DIR__,2).'/'.$path;
    if(is_file($file))@unlink($file);
}

function upload_product_image(?array $file,string $old=''): string {
    if(!$file || (int)($file['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return $old;
    $error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
    if($error!==UPLOAD_ERR_OK) throw new RuntimeException('Produktbild-Upload fehlgeschlagen.');
    $tmp=(string)($file['tmp_name']??'');
    $size=(int)($file['size']??0);
    if($tmp==='' || !is_uploaded_file($tmp)) throw new RuntimeException('Produktbild wurde nicht als gültiger Upload erkannt.');
    if($size<=0||$size>8*1024*1024) throw new RuntimeException('Produktbild darf maximal 8 MB groß sein.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $ext=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$mime]??null;
    if(!$ext) throw new RuntimeException('Erlaubt sind PNG, JPG oder WEBP.');
    yw_validate_uploaded_raster($tmp,'Produktbild');
    $dir=dirname(__DIR__, 2).'/uploads/products';
    if(!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new RuntimeException('Produktbild-Ordner konnte nicht erstellt werden.');
    $name='product-'.bin2hex(random_bytes(8)).'.'.$ext;
    if(!yw_move_upload($tmp,$dir.'/'.$name)) throw new RuntimeException('Produktbild konnte nicht gespeichert werden.');
    return 'uploads/products/'.$name;
}


function current_site_setting(string $key,string $default=''): string {
    try {
        $stmt=db()->prepare('SELECT setting_value FROM site_settings WHERE setting_key=? LIMIT 1');
        $stmt->execute([$key]);
        $v=$stmt->fetchColumn();
        return $v===false ? $default : (string)$v;
    } catch(Throwable $e) {
        return $default;
    }
}

function set_site_setting(string $key,string $value): void {
    $stmt=db()->prepare('INSERT INTO site_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $stmt->execute([$key,$value]);
}

function yw_ini_size_bytes(string $value): int {
    $value=trim($value);
    if($value==='-1')return PHP_INT_MAX;
    if(!preg_match('/^(\d+(?:\.\d+)?)\s*([kmg])?$/i',$value,$m))return 0;
    $factor=match(strtolower($m[2]??'')){'k'=>1024,'m'=>1048576,'g'=>1073741824,default=>1};
    return (int)min(PHP_INT_MAX,(float)$m[1]*$factor);
}

/** Laufzeitvoraussetzungen, die vor einem Go-live direkt aus der PHP-Installation geprüft werden. */
function yw_runtime_health(): array {
    $requiredExtensions=['pdo_mysql','gd','zip','mbstring','curl','openssl','fileinfo','dom','xmlreader'];
    $missingExtensions=array_values(array_filter($requiredExtensions,static fn(string $extension): bool=>!extension_loaded($extension)));
    $memory=yw_ini_size_bytes((string)ini_get('memory_limit'));
    $upload=yw_ini_size_bytes((string)ini_get('upload_max_filesize'));
    $post=yw_ini_size_bytes((string)ini_get('post_max_size'));
    $execution=(int)ini_get('max_execution_time');
    $uploadDir=dirname(__DIR__,2).'/uploads';
    $privateDir='';
    try{$privateDir=yw_private_dir();}catch(Throwable $ignored){}
    $root=realpath(dirname(__DIR__,2));
    $privateReal=$privateDir!==''?realpath($privateDir):false;
    $outside=false;
    if($root!==false&&$privateReal!==false){
        $rootPrefix=strtolower(rtrim($root,'/\\').DIRECTORY_SEPARATOR);
        $privateValue=strtolower(rtrim($privateReal,'/\\'));
        $outside=$privateValue!==strtolower(rtrim($root,'/\\'))&&!str_starts_with($privateValue,$rootPrefix);
    }
    return [
        'missing_extensions'=>$missingExtensions,
        'php_settings_ok'=>$memory>=256*1048576&&$upload>=20*1048576&&$post>=25*1048576&&($execution===0||$execution>=300),
        'php_settings_hint'=>'memory_limit '.ini_get('memory_limit').' · upload_max_filesize '.ini_get('upload_max_filesize').' · post_max_size '.ini_get('post_max_size').' · max_execution_time '.ini_get('max_execution_time'),
        'uploads_writable'=>is_dir($uploadDir)&&is_writable($uploadDir),
        'private_writable'=>$privateDir!==''&&is_dir($privateDir)&&is_writable($privateDir),
        'private_outside_webroot'=>$outside,
        'app_secret_ok'=>strlen(trim((string)cfg('app_secret')))>=32,
    ];
}

/** Letzte unhandled PHP-Fehler für die Betriebsprüfung; Details bleiben im privaten Datenordner. */
function yw_runtime_recent_errors(int $hours=24,int $limit=20): ?array {
    try{
        $path=yw_private_dir().'/runtime-errors.jsonl';
        if(!is_file($path))return [];
        $size=(int)filesize($path);
        $fh=@fopen($path,'rb');
        if(!$fh)return null;
        $offset=max(0,$size-1048576);
        if($offset>0)fseek($fh,$offset);
        $raw=stream_get_contents($fh);
        fclose($fh);
        if(!is_string($raw))return null;
        $lines=explode("\n",$raw);
        if($offset>0)array_shift($lines); // Ein möglicherweise angeschnittener erster Datensatz wird verworfen.
        $cutoff=time()-min(720,max(1,$hours))*3600;
        $limit=min(1000,max(1,$limit));
        $recent=[];
        foreach(array_reverse($lines) as $line){
            $record=json_decode(trim($line),true);
            if(!is_array($record))continue;
            $timestamp=strtotime((string)($record['at']??''));
            if($timestamp===false||$timestamp<$cutoff)continue;
            $recent[]=[
                'at'=>(string)$record['at'],
                'id'=>(string)($record['id']??''),
                'route'=>(string)($record['route']??'/'),
                'type'=>(string)($record['type']??'Fehler'),
                'file'=>basename((string)($record['file']??'')),
                'line'=>max(0,(int)($record['line']??0)),
            ];
            if(count($recent)>=$limit)break;
        }
        return $recent;
    }catch(Throwable $e){return null;}
}



function upload_error_message(int $error,string $label='Datei'): string {
    return match($error) {
        UPLOAD_ERR_INI_SIZE => $label.' ist größer als das serverseitige Upload-Limit (upload_max_filesize).',
        UPLOAD_ERR_FORM_SIZE => $label.' ist größer als das im Formular erlaubte Limit.',
        UPLOAD_ERR_PARTIAL => $label.' wurde nur teilweise hochgeladen. Bitte erneut versuchen.',
        UPLOAD_ERR_NO_FILE => 'Keine Datei ausgewählt.',
        UPLOAD_ERR_NO_TMP_DIR => 'Auf dem Server fehlt das temporäre Upload-Verzeichnis.',
        UPLOAD_ERR_CANT_WRITE => 'Der Server konnte die Datei nicht auf die Festplatte schreiben.',
        UPLOAD_ERR_EXTENSION => 'Eine PHP-Erweiterung hat den Upload gestoppt.',
        default => $label.'-Upload fehlgeschlagen (Fehlercode '.$error.').',
    };
}

function product_vat_rate(array $product): float {
    $rate=(float)($product['vat_rate']??19.0);
    if($rate<0 || $rate>100) $rate=19.0;
    return $rate;
}

function gross_from_net(int $netCents,float $vatRate): int {
    return (int)round($netCents*(1+($vatRate/100)));
}

function account_is_manager(array $account): bool {
    return (string)($account['role']??'employee')==='manager';
}

function ensure_account_invite(array $company,array $account): int {
    $inviteId=(int)($account['invite_id']??0);
    if($inviteId>0){
        $stmt=db()->prepare('SELECT id FROM invites WHERE id=? AND company_id=? LIMIT 1');
        $stmt->execute([$inviteId,(int)$company['id']]);
        if($stmt->fetchColumn()) return $inviteId;
    }

    $token=random_token();
    $stmt=db()->prepare('INSERT INTO invites(company_id,employee_name,token,active) VALUES(?,?,?,1)');
    $stmt->execute([(int)$company['id'],(string)$account['name'],$token]);
    $inviteId=(int)db()->lastInsertId();

    $stmt=db()->prepare('UPDATE employee_accounts SET invite_id=? WHERE id=?');
    $stmt->execute([$inviteId,(int)$account['id']]);

    return $inviteId;
}

function simple_message(string $title,string $text): void {
    render_head($title);
    echo site_brand_style();
    echo '<div class="login-brandbar"><div class="wrap">'.site_brand_html().'</div></div>';
    echo '<main class="login-shell" style="--primary:#fc6904;--accent:#fc6904"><section class="login-card"><div class="login-accent"></div><div class="login-body"><h1>'.e($title).'</h1><p>'.e($text).'</p></div></section></main>';
    global_footer();
    render_foot();
}
