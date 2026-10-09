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
    if(!move_uploaded_file($tmp,$dir.'/'.$name)) throw new RuntimeException('Produktbild konnte nicht gespeichert werden.');
    return 'uploads/products/'.$name;
}


function category_filter_script(): string {
    // Logik liegt in assets/app.js (CSP ohne 'unsafe-inline').
    return '';
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

function manager_order_script(): string {
    // Logik liegt in assets/app.js (CSP ohne 'unsafe-inline').
    return '';
}

function simple_message(string $title,string $text): void {
    render_head($title);
    echo site_brand_style();
    echo '<div class="login-brandbar"><div class="wrap">'.site_brand_html().'</div></div>';
    echo '<main class="login-shell" style="--primary:#101b2d;--accent:#ff6b00"><section class="login-card"><div class="login-accent"></div><div class="login-body"><h1>'.e($title).'</h1><p>'.e($text).'</p></div></section></main>';
    global_footer();
    render_foot();
}
