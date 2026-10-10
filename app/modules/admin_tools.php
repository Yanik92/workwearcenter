<?php
declare(strict_types=1);

/*
 * 10.7: Auftragsseite, Suche, Shop-Vorschau, Datensicherung, Erinnerungen.
 */

/* ---------- Auftragsseite ---------- */

function yw_order_url(int $orderId): string { return '/admin?tab=orders&auftrag='.$orderId; }

function yw_admin_order_page(int $orderId): void {
    $q=db()->prepare("SELECT o.*,c.legal_name,c.shop_name,c.slug,c.id company_id,COALESCE(ea.name,i.employee_name,'Unbekannt') employee_name,COALESCE(ea.email,'') employee_email,ea.anonymized_at employee_anonymized_at,ea.department,ea.cost_center,ea.location,d.name decided_by_name
        FROM orders o JOIN companies c ON c.id=o.company_id
        LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id
        LEFT JOIN invites i ON i.id=o.invite_id
        LEFT JOIN employee_accounts d ON d.id=o.decided_by_account_id
        WHERE o.id=? LIMIT 1");
    $q->execute([$orderId]);$o=$q->fetch();
    if(!$o){echo '<div class="flash error">Auftrag wurde nicht gefunden.</div><a class="btn" href="/admin?tab=orders">Zur Auftragsliste</a>';return;}
    $items=order_items($orderId);
    $statusLabels=['pending'=>'Freigabe offen','approved'=>'Freigegeben','rejected'=>'Abgelehnt','cancelled'=>'Zurückgezogen'];
    $handover=yw_handover_status_map([$orderId])[$orderId]??null;
    $total=(int)($o['gross_total_cents']??0)>0?(int)$o['gross_total_cents']:(int)$o['total_cents'];
    $motifs=yw_company_motifs_by_id((int)$o['company_id']);
    $delivery=json_decode((string)($o['delivery_snapshot_json']??''),true)?:[];

    echo '<div class="customer-head"><a class="btn small" href="/admin?tab=orders">← Alle Aufträge</a>';
    echo '<div class="customer-ident"><div><h2>'.e(yw_order_number($orderId)).' <span class="badge '.e((string)$o['status']).'">'.e($statusLabels[$o['status']]??$o['status']).'</span></h2><p>'.e($o['legal_name']).' · '.e(yw_account_anonymized((string)$o['employee_email'],$o['employee_anonymized_at']??null)?'Konto gelöscht (DSGVO)':$o['employee_name']).' · '.e(date('d.m.Y H:i',strtotime((string)$o['created_at']))).' Uhr</p></div></div>';
    echo '<div class="customer-head-actions"><a class="btn small" href="'.e(yw_customer_url((int)$o['company_id'],'auftraege')).'">Kundenakte</a></div></div>';

    echo '<div class="order-grid">';

    // Positionen
    echo '<section class="card order-main"><h2>Positionen</h2><div class="table-wrap"><table class="table"><thead><tr><th>Artikel</th><th>Größe</th><th>Menge</th><th>Veredelung</th><th>Brutto</th></tr></thead><tbody>';
    foreach($items as $it){
        $snap=json_decode((string)($it['decoration_snapshot_json']??''),true)?:[];
        $decs=[];
        foreach((is_array($snap['decorations']??null)?$snap['decorations']:[]) as $d){
            if(!is_array($d))continue;
            $m=$motifs[(int)($d['motif_id']??0)]??null;
            $decs[]='<div>'.e(decoration_position_label((string)($d['position']??''))).' · '.e(decoration_method_label((string)($d['method']??''))).($m?' · <strong>'.e($m['name']).'</strong>':'').(trim((string)($d['text']??''))!==''?'<br><span class="muted">'.e((string)$d['text']).'</span>':'').'</div>';
        }
        $qty=(int)$it['quantity'];$lineGross=yw_order_item_amounts($it)['gross'];
        $itemProduct=(int)($it['product_id']??0); // 10.15: Artikelname öffnet den Artikel in der Kollektion (Korrekturabzug), auch bei großen Kollektionen
        echo '<tr><td><strong>'.($itemProduct>0?'<a href="'.e(yw_customer_state_url((int)$o['company_id'],'kollektion',['produkt'=>(string)$itemProduct])).'#produkt-'.$itemProduct.'">'.e($it['product_name']).'</a>':e($it['product_name'])).'</strong></td><td>'.e($it['size']).'</td><td>'.$qty.'</td><td>'.($decs?implode('',$decs):'<span class="muted">keine</span>').'</td><td>'.money($lineGross).'</td></tr>';
    }
    if((int)($o['shipping_gross_cents']??0)>0)echo '<tr><td colspan="4">Versand</td><td>'.money((int)$o['shipping_gross_cents']).'</td></tr>';
    echo '<tr><td colspan="4"><strong>Gesamt brutto</strong></td><td><strong>'.money($total).'</strong></td></tr>';
    echo '</tbody></table></div>';
    echo '<div class="order-shares"><span>Firmenanteil <strong>'.money((int)($o['company_total_cents']??0)).'</strong></span><span>Mitarbeiteranteil <strong>'.money((int)($o['employee_total_cents']??0)).'</strong></span></div>';
    if(trim((string)($o['note']??''))!=='')echo '<p><strong>Hinweis des Bestellers:</strong> '.e((string)$o['note']).'</p>';
    if(trim((string)($o['decision_note']??''))!=='')echo '<p><strong>Anmerkung zur Freigabe:</strong> '.e((string)$o['decision_note']).'</p>';
    echo '<p class="muted">Korrekturabzüge findest du beim jeweiligen Artikel in der <a href="'.e(yw_customer_url((int)$o['company_id'],'kollektion')).'">Kollektion des Kunden</a> – ein Klick auf den Artikelnamen öffnet ihn dort.</p>';
    echo '</section>';

    // Seitenleiste
    echo '<div class="order-side">';
    // Produktion
    echo '<section class="card"><h2>Produktion</h2>';
    if($o['status']==='approved'){
        echo '<form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="fulfillment"><input type="hidden" name="order_id" value="'.$orderId.'"><input type="hidden" name="return_order" value="'.$orderId.'"><label>Produktionsstand<select name="status">';
        foreach(yw_production_statuses() as $k=>$l)echo '<option value="'.e($k).'" '.($o['fulfillment_status']===$k?'selected':'').'>'.e($l).'</option>';
        echo '</select></label><button class="btn primary small">Stand speichern</button></form>';
        echo '<p class="muted">Der Besteller wird bei jeder Änderung per E-Mail informiert.</p>';
    }else echo '<p class="muted">Nur freigegebene Aufträge gehen in die Produktion.</p>';
    if(in_array($o['status'],['pending','approved'],true)){
        echo '<details class="details space-top"><summary>Auftrag stornieren</summary><div class="details-inner"><form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="order_cancel"><input type="hidden" name="order_id" value="'.$orderId.'"><input type="hidden" name="return_tab" value="orders"><label>Grund <small>geht per E-Mail an den Besteller</small><textarea name="cancel_reason" rows="3" maxlength="1000" required placeholder="z. B. Größe getauscht, Artikel nicht lieferbar"></textarea></label><button class="btn small danger" type="submit">Jetzt stornieren</button></form><p class="muted">Budget und Stückzahlen werden sofort wieder frei. Wurde der Auftrag schon übergeben, bitte auch in der Auftragszentrale stornieren.</p></div></details>';
    }
    if(!empty($o['tracking_number']))echo '<p><strong>Sendung:</strong> '.e(trim(($o['tracking_carrier']??'').' '.$o['tracking_number'])).(yw_tracking_link((string)$o['tracking_carrier'],(string)$o['tracking_number'],(string)($o['tracking_url']??''))?' · <a href="'.e(yw_tracking_link((string)$o['tracking_carrier'],(string)$o['tracking_number'],(string)($o['tracking_url']??''))).'" target="_blank" rel="noopener">verfolgen</a>':'').'</p>';
    if(!empty($o['external_reference']))echo '<p><strong>Auftragszentrale:</strong> '.e((string)$o['external_reference']).'</p>';
    echo '</section>';

    // Übergabe
    echo '<section class="card"><h2>Übergabe</h2>';
    if($handover){[$l,$cls]=yw_handover_status_label((string)$handover['status']);echo '<p><span class="badge '.e($cls).'">'.e($l).'</span>'.(!empty($handover['delivered_at'])?' <span class="muted">'.e(date('d.m.Y H:i',strtotime((string)$handover['delivered_at']))).'</span>':'').'</p>';if(!empty($handover['last_error']))echo '<p class="muted">'.e((string)$handover['last_error']).'</p>';echo '<a class="btn small" href="/admin?tab=handover">Zur Übergabe</a>';}
    else echo '<p class="muted">'.($o['status']==='approved'?'Noch nicht an die Auftragszentrale übergeben.':'Übergabe erfolgt nach Freigabe.').'</p>';
    echo '</section>';

    echo yw_lexware_order_card($o);

    // Besteller & Lieferung
    if(yw_account_anonymized((string)$o['employee_email'],$o['employee_anonymized_at']??null))echo '<section class="card"><h2>Besteller</h2><p><strong>Konto gelöscht (DSGVO)</strong><br><span class="muted">Persönliche Daten wurden entfernt; der Auftrag bleibt für die Buchhaltung erhalten.</span></p>';
    else echo '<section class="card"><h2>Besteller</h2><p><strong>'.e($o['employee_name']).'</strong>'.($o['employee_email']!==''?'<br><a href="mailto:'.e($o['employee_email']).'">'.e($o['employee_email']).'</a>':'').'</p>';
    $org=array_filter([$o['department']??'',$o['cost_center']??'',$o['location']??'']);
    if($org)echo '<p class="muted">'.e(implode(' · ',$org)).'</p>';
    if(!empty($o['decided_by_name']))echo '<p class="muted">Freigegeben von '.e((string)$o['decided_by_name']).(!empty($o['decided_at'])?' am '.e(date('d.m.Y',strtotime((string)$o['decided_at']))):'').'</p>';
    if($delivery){$addr=(string)($delivery['address']??'');echo '<p><strong>Lieferung:</strong> '.e((string)($delivery['mode']??'')==='pickup'?'Abholung':'Versand').($addr!==''?'<br>'.nl2br(e($addr)):'').'</p>';}
    echo '</section>';

    // Interne Notiz
    echo '<section class="card"><h2>Interne Notiz</h2><form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="save_order_note"><input type="hidden" name="order_id" value="'.$orderId.'"><textarea name="internal_note" rows="4" maxlength="3000" placeholder="z. B. Lieferung erst nach dem 15., Logo-Datei nachgereicht">'.e((string)($o['internal_note']??'')).'</textarea><button class="btn small">Notiz speichern</button></form></section>';
    echo '</div></div>';
    yw_exchange_admin_order_card($orderId); // 10.11: Umtausch & Retouren zu diesem Auftrag

    // Verlauf
    $labels=yw_audit_labels();
    $a=db()->prepare("SELECT * FROM audit_log WHERE entity_type='order' AND entity_id=? ORDER BY id DESC LIMIT 50");$a->execute([$orderId]);$logs=$a->fetchAll();
    echo '<section class="card"><h2>Verlauf</h2>';
    if(!$logs)echo '<p class="muted">Noch keine Einträge.</p>';
    else{
        $people=yw_actor_people($logs);
        echo '<ol class="order-history">';
        foreach($logs as $log){
            $meta=json_decode((string)($log['metadata_json']??''),true)?:[];$extra=yw_history_details($meta);
            echo '<li><span class="muted">'.e(date('d.m.Y H:i',strtotime((string)$log['created_at']))).'</span> <strong>'.e($labels[$log['action']]??$log['action']).'</strong>'.($extra!==''?' '.e($extra):'').' <span class="muted">· '.e(yw_actor_label((string)$log['actor_type'],$log['actor_id']!==null?(int)$log['actor_id']:null,$meta,$people)).'</span></li>';
        }
        echo '</ol>';
    }
    echo '</section>';
}

/* ---------- Suche ---------- */

function yw_admin_search_page(string $q): void {
    $q=trim($q);
    echo '<section class="card"><form method="get" class="filter-bar"><input type="hidden" name="tab" value="suche"><label>Suche<input name="q" value="'.e($q).'" placeholder="Firma, Mitarbeiter, Auftragsnummer, Artikel" autofocus></label><button class="btn primary">Suchen</button></form>';
    if(mb_strlen($q)<2){echo '<p class="muted">Mindestens zwei Zeichen eingeben.</p></section>';return;}
    $like='%'.$q.'%';$found=0;
    $num=preg_match('/^(?:WWC-?)?0*(\d{1,10})$/i',$q,$m)?(int)$m[1]:0;

    $c=db()->prepare('SELECT id,legal_name,shop_name,slug FROM companies WHERE legal_name LIKE ? OR shop_name LIKE ? OR slug LIKE ? ORDER BY legal_name LIMIT 20');$c->execute([$like,$like,$like]);
    if($rows=$c->fetchAll()){$found+=count($rows);echo '<h3>Kunden</h3><ul class="search-list">';foreach($rows as $r)echo '<li><a href="'.e(yw_customer_url((int)$r['id'])).'"><strong>'.e($r['shop_name']).'</strong> <span class="muted">'.e($r['legal_name']).' · /'.e($r['slug']).'</span></a></li>';echo '</ul>';}

    $e=db()->prepare('SELECT ea.id,ea.name,ea.email,ea.company_id,c.shop_name FROM employee_accounts ea JOIN companies c ON c.id=ea.company_id WHERE ea.anonymized_at IS NULL AND (ea.name LIKE ? OR ea.email LIKE ? OR ea.employee_number LIKE ?) ORDER BY ea.name LIMIT 20');$e->execute([$like,$like,$like]);
    if($rows=$e->fetchAll()){$found+=count($rows);echo '<h3>Mitarbeitende</h3><ul class="search-list">';foreach($rows as $r)echo '<li><a href="'.e(yw_customer_url((int)$r['company_id'],'mitarbeiter')).'"><strong>'.e($r['name']).'</strong> <span class="muted">'.e($r['email']).' · '.e($r['shop_name']).'</span></a></li>';echo '</ul>';}

    $sql="SELECT o.id,o.status,o.created_at,c.shop_name,COALESCE(ea.name,i.employee_name,'') employee_name FROM orders o JOIN companies c ON c.id=o.company_id LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id LEFT JOIN invites i ON i.id=o.invite_id WHERE ".($num?'o.id=? OR ':'')."o.external_reference LIKE ? OR ea.name LIKE ? ORDER BY o.id DESC LIMIT 20";
    $p=$num?[$num,$like,$like]:[$like,$like];$oq=db()->prepare($sql);$oq->execute($p);
    if($rows=$oq->fetchAll()){$found+=count($rows);echo '<h3>Aufträge</h3><ul class="search-list">';foreach($rows as $r)echo '<li><a href="'.e(yw_order_url((int)$r['id'])).'"><strong>'.e(yw_order_number((int)$r['id'])).'</strong> <span class="muted">'.e($r['shop_name']).' · '.e($r['employee_name']).' · '.e(date('d.m.Y',strtotime((string)$r['created_at']))).'</span></a></li>';echo '</ul>';}

    $pq=db()->prepare('SELECT id,name,sku,brand FROM products WHERE name LIKE ? OR sku LIKE ? OR brand LIKE ? ORDER BY name LIMIT 20');$pq->execute([$like,$like,$like]);
    if($rows=$pq->fetchAll()){$found+=count($rows);echo '<h3>Produkte</h3><ul class="search-list">';foreach($rows as $r)echo '<li><a href="/admin?tab=products#produkt-'.(int)$r['id'].'"><strong>'.e($r['name']).'</strong> <span class="muted">'.e(trim(($r['brand']??'').' '.($r['sku']??''))).'</span></a></li>';echo '</ul>';}

    if(!$found)echo '<div class="empty compact">Nichts gefunden für „'.e($q).'“.</div>';
    echo '</section>';
}

/* ---------- Shop-Vorschau (Admin sieht den Firmenshop wie ein Mitarbeiter, ohne bestellen zu können) ---------- */

function yw_admin_preview_start(int $companyId): never {
    require_admin();
    // Nur per Formular (POST + CSRF): eine fremde Seite darf keine Vorschau starten und damit Mitarbeiter-Sitzungen beenden.
    if(!is_post()){http_response_code(405);simple_message('Nicht erlaubt','Die Vorschau bitte über „Shop ansehen“ in der Kundenakte öffnen.');exit;}
    verify_csrf();
    $q=db()->prepare('SELECT slug FROM companies WHERE id=? LIMIT 1');$q->execute([$companyId]);$slug=$q->fetchColumn();
    if(!$slug)redirect('/admin?tab=companies');
    $_SESSION['admin_preview_company']=$companyId;
    unset($_SESSION['employee_account_id'],$_SESSION['employee_auth_version']);
    audit_log('company.preview_started','company',$companyId);
    redirect('/'.$slug);
}

function yw_admin_preview_stop(): never {
    yw_block_cross_site_get();
    // Zurück zur Kundenakte der angesehenen Firma statt zur Kundenliste.
    $companyId=(int)($_SESSION['admin_preview_company']??0);
    unset($_SESSION['admin_preview_company']);
    redirect($companyId>0?yw_customer_url($companyId):'/admin?tab=companies');
}

/** Künstliches Konto für die Vorschau; null, wenn keine Vorschau aktiv ist. */
function yw_admin_preview_account(array $company): ?array {
    if((int)($_SESSION['admin_preview_company']??0)!==(int)$company['id'])return null;
    if(!admin_logged_in()){unset($_SESSION['admin_preview_company']);return null;}
    return ['id'=>0,'company_id'=>(int)$company['id'],'name'=>'Vorschau (Verwaltung)','email'=>'vorschau@workwearcenter.invalid','role'=>'manager','active'=>1,'registration_status'=>'approved','invite_id'=>0,'equipment_profile_id'=>null,'budget_override_cents'=>null,'department'=>'','cost_center'=>'','location'=>'','employee_number'=>'','auth_version'=>1,'is_preview'=>true];
}

function yw_is_preview(?array $account): bool { return (bool)($account['is_preview']??false); }

function yw_preview_banner(array $company): string {
    return '<div class="preview-banner"><span>Vorschau: So sieht ein Verantwortlicher den Shop von '.e($company['shop_name']).'. Bestellen und Speichern sind abgeschaltet.</span><a href="/admin/vorschau-beenden">Vorschau beenden</a></div>';
}

/* ---------- Datensicherung ---------- */

/**
 * Schreibt den SQL-Dump Zeile für Zeile in $fh. Großkunden: ungepufferte Abfrage und Datei statt String,
 * damit weder eine ganze Tabelle noch der ganze Dump im PHP-Speicher liegt; der Snapshot hält Aufträge und Positionen konsistent.
 * @param resource $fh
 */
function yw_backup_write($fh): void {
    $pdo=db();
    $put=static function(string $s) use($fh): void {if(fwrite($fh,$s)!==strlen($s))throw new RuntimeException('Sicherungsdatei konnte nicht geschrieben werden (Speicherplatz voll?).');};
    // TIMESTAMP-Spalten werden in der Zeitzone dieser Verbindung ausgegeben – beim Einspielen dieselbe setzen, sonst verschieben sie sich um den Berlin-Offset.
    $tz=(string)$pdo->query('SELECT @@session.time_zone')->fetchColumn();
    $put("-- WorkwearCenter Datensicherung ".date('Y-m-d H:i:s')."\nSET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\nSET time_zone=".$pdo->quote($tz).";\n\n");
    $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $buffered=$pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);$rows=null;
    $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
    try{
        foreach($tables as $t){
            $create=$pdo->query('SHOW CREATE TABLE `'.$t.'`')->fetch(PDO::FETCH_NUM);
            $put("DROP TABLE IF EXISTS `$t`;\n".$create[1].";\n\n");
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
            $rows=$pdo->query('SELECT * FROM `'.$t.'`',PDO::FETCH_NUM);$batch=[];
            foreach($rows as $row){
                $vals=[];foreach($row as $v)$vals[]=$v===null?'NULL':$pdo->quote((string)$v);
                $batch[]='('.implode(',',$vals).')';
                if(count($batch)>=200){$put("INSERT INTO `$t` VALUES\n".implode(",\n",$batch).";\n");$batch=[];}
            }
            $rows->closeCursor();$rows=null;
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,$buffered);
            if($batch)$put("INSERT INTO `$t` VALUES\n".implode(",\n",$batch).";\n");
            $put("\n");
        }
        $put("SET FOREIGN_KEY_CHECKS=1;\n");
    }finally{
        if($rows instanceof PDOStatement)$rows->closeCursor();
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,$buffered);
        try{$pdo->exec('COMMIT');}catch(Throwable $ignored){}
    }
}

function yw_backup_sql(): string {
    $fh=fopen('php://temp','w+b');yw_backup_write($fh);rewind($fh);$sql=(string)stream_get_contents($fh);fclose($fh);return $sql;
}

function yw_backup_download(): never {
    require_admin(); yw_block_cross_site_get();
    @set_time_limit(900);
    $name='workwearcenter-sicherung-'.date('Y-m-d-Hi');
    $root=dirname(__DIR__,2);
    // Dump erst in eine temporäre Datei, dann (falls vorhanden) per ZipArchive aus der Datei packen.
    $sqlFile=(string)tempnam(sys_get_temp_dir(),'wwcsql');$tmp='';
    $cleanup=static function() use(&$sqlFile,&$tmp): void {if($sqlFile!=='')@unlink($sqlFile);if($tmp!=='')@unlink($tmp);};
    try{
        $fh=$sqlFile!==''?fopen($sqlFile,'wb'):false;
        if(!$fh)throw new RuntimeException('Temporäre Sicherungsdatei konnte nicht angelegt werden.');
        try{yw_backup_write($fh);}finally{fclose($fh);}
        $file=$sqlFile;$type='application/sql; charset=UTF-8';$ext='.sql';
        if(class_exists('ZipArchive')){
            $tmp=(string)tempnam(sys_get_temp_dir(),'wwc');$zip=new ZipArchive();
            if($tmp!=='' && $zip->open($tmp,ZipArchive::OVERWRITE)===true){
                $zip->addFile($sqlFile,'datenbank.sql');
                $zip->addFromString('LIESMICH.txt',"WorkwearCenter-Sicherung vom ".date('d.m.Y H:i')."\n\nWiederherstellen:\n1. datenbank.sql in phpMyAdmin importieren (vorher die Datenbank leeren).\n2. Ordner uploads/ auf den Webspace kopieren.\n3. config.php bleibt wie sie ist.\n");
                $dir=$root.'/uploads';
                if(is_dir($dir)){$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));foreach($it as $f){if(!$f->isFile())continue;$rel='uploads/'.substr($f->getPathname(),strlen($dir)+1);if(preg_match('/\.(php\d?|phtml|phar)$/i',$rel))continue;$zip->addFile($f->getPathname(),$rel);}}
                if(!$zip->close())throw new RuntimeException('ZIP-Datei konnte nicht geschrieben werden (Speicherplatz voll?).');
                $file=$tmp;$type='application/zip';$ext='.zip';
            }
        }
        clearstatcache();$bytes=(int)filesize($file);
        set_site_setting('last_backup_at',date('Y-m-d H:i:s'));audit_log('system.backup_downloaded','system',null,['bytes'=>$bytes]);
    }catch(Throwable $e){
        $cleanup();error_log('WorkwearCenter backup: '.$e->getMessage());
        flash('error','Die Datensicherung konnte nicht erstellt werden. Bitte freien Speicherplatz beim Hoster prüfen und das Serverprotokoll ansehen.');
        redirect('/admin?tab=system');
    }
    while(ob_get_level()>0)@ob_end_clean(); // große Datei nicht in einen Ausgabepuffer laden
    header('Content-Type: '.$type);header('Content-Disposition: attachment; filename="'.$name.$ext.'"');header('Content-Length: '.$bytes);header('Cache-Control: no-store');
    readfile($file);$cleanup();exit;
}

/** Tage seit der letzten Sicherung, null wenn nie. */
function yw_backup_age_days(): ?int {
    $last=current_site_setting('last_backup_at','');
    if($last==='')return null;
    return (int)floor((time()-strtotime($last))/86400);
}

/* ---------- Erinnerungen an Verantwortliche ---------- */

/** 10.15: Spalte für die Sammel-Erinnerung vorhanden? (vor update.php keine Erinnerungen statt einer Mail je Vorgang) */
function yw_reminder_digest_ready(): bool {
    static $ready=null;
    if($ready===null){try{db()->query('SELECT reminder_digest_at FROM employee_accounts LIMIT 0');$ready=true;}catch(Throwable $e){$ready=false;}}
    return $ready;
}

/**
 * Ist ein fälliger Vorgang neu seit der letzten Sammel-Erinnerung dieser Person? Wie bisher ist jeder Vorgang nur
 * einmal Anlass: fällig (angelegt + $days Tage) nach der letzten Sammel-Erinnerung. Ohne frühere Sammel-Erinnerung
 * zählt nur, woran noch nie erinnert wurde.
 */
function yw_reminder_is_new(?string $lastDigest,string $createdAt,?string $reminderSentAt,int $days): bool {
    if($lastDigest===null||$lastDigest==='')return $reminderSentAt===null||$reminderSentAt==='';
    $due=strtotime($createdAt.' +'.max(1,$days).' days');
    return $due!==false && date('Y-m-d H:i:s',$due)>$lastDigest;
}

/**
 * Betreff und Text der Sammel-Erinnerung in der aktuellen Sprache. $orders/$regs: fällige Vorgänge (älteste zuerst),
 * je Liste höchstens $limit Einträge mit Name, Datum und Betrag; $new: Schlüssel 'o<ID>' bzw. 'r<ID>' neuer Vorgänge.
 * @return array{0:string,1:string}
 */
function yw_reminder_digest_mail(array $person,array $company,array $orders,array $regs,int $days,array $new=[],int $limit=20): array {
    // Freitexte einzeilig und ohne Doppelpunkt: das Mail-Layout macht aus „Bezeichnung: Wert“ Tabellen und Schaltflächen.
    $clean=static fn(string $s): string => trim((string)preg_replace('/[\x00-\x1F\x7F:]+/u',' ',$s));
    $slug=(string)$company['slug'];$total=count($orders)+count($regs);
    $mark=count($new)<$total?' · '.t('mail.digest.new'):''; // „neu“ nur, wenn auch Bekanntes in der Liste steht
    // Neue Vorgänge zuerst (sonst lägen sie bei vielen offenen Vorgängen hinter den ersten 20), danach die ältesten.
    $pick=static fn(array $list,string $p): array => array_slice(array_merge(array_filter($list,fn(array $x)=>isset($new[$p.(int)$x['id']])),array_filter($list,fn(array $x)=>!isset($new[$p.(int)$x['id']]))),0,max(1,$limit));
    $text=t('mail.hello',['name'=>$clean((string)($person['name']??''))])."\n\n".tn('mail.digest.intro',$total,['company'=>$clean((string)($company['legal_name']??'')),'days'=>$days])."\n\n";
    if($orders)$text.=t('mail.digest.orders').': '.count($orders)."\n";
    if($regs)$text.=t('mail.digest.regs').': '.count($regs)."\n";
    if($orders){
        $text.="\n".t('mail.digest.orders_head')."\n";
        foreach($pick($orders,'o') as $o){
            $name=$clean((string)($o['employee_name']??''));$gross=(int)($o['gross_total_cents']??0)>0?(int)$o['gross_total_cents']:(int)($o['total_cents']??0);
            $text.=yw_order_number((int)$o['id']).': '.($name!==''?$name:t('common.unknown')).' · '.yw_date((string)$o['created_at']).' · '.money($gross).(isset($new['o'.(int)$o['id']])?$mark:'')."\n";
        }
        if(count($orders)>$limit)$text.=tn('mail.digest.more',count($orders)-$limit)."\n";
        $text.="\n".t('mail.remind_order.open').': '.workwearcenter_url($slug.'/freigaben')."\n";
    }
    if($regs){
        $text.="\n".t('mail.digest.regs_head')."\n";
        foreach($pick($regs,'r') as $r){
            $name=mb_strimwidth($clean((string)($r['name']??'')),0,32,'…');
            $text.=($name!==''?$name:t('common.unknown')).': '.yw_date((string)$r['created_at']).(isset($new['r'.(int)$r['id']])?$mark:'')."\n";
        }
        if(count($regs)>$limit)$text.=tn('mail.digest.more',count($regs)-$limit)."\n";
        $text.="\n".t('mail.remind_reg.open').': '.workwearcenter_url($slug.'/mitarbeiter')."\n";
    }
    return [tn('mail.digest.subject',$total),$text."\n".t('mail.digest.once')];
}

/**
 * 10.15: Sammel-Erinnerung statt einer Mail je Vorgang und verantwortlicher Person. Jede verantwortliche Person
 * bekommt höchstens eine Mail in 24 Stunden mit allem, was länger als $days Tage auf sie wartet (offene Freigaben
 * ohne eigene Bestellungen beim Vier-Augen-Prinzip, offene Registrierungen ihrer Firma) – und nur, wenn seit ihrer
 * letzten Sammel-Erinnerung etwas Neues fällig geworden ist. Die Sofort-Mail bei neuer Bestellung bleibt unverändert.
 * Rückgabe: Anzahl versendeter Mails.
 */
function yw_send_reminders(int $days=3): int {
    $sent=0;$days=max(1,$days);
    if(!yw_reminder_digest_ready())return 0;
    try{
        // Ein Zeitpunkt für den ganzen Lauf: was bis $now fällig ist, steht in der Mail; der Merker bekommt genau $now.
        [$now,$cutoff]=array_map('strval',db()->query('SELECT NOW(),DATE_SUB(NOW(),INTERVAL '.$days.' DAY)')->fetch(PDO::FETCH_NUM));
        $cq=db()->prepare("SELECT c.id,c.slug,c.legal_name FROM companies c WHERE c.active=1 AND (EXISTS(SELECT 1 FROM orders o WHERE o.company_id=c.id AND o.status='pending' AND o.created_at<=?) OR EXISTS(SELECT 1 FROM employee_accounts p WHERE p.company_id=c.id AND p.registration_status='pending' AND p.anonymized_at IS NULL AND p.created_at<=?)) ORDER BY c.id");
        $cq->execute([$cutoff,$cutoff]);$companies=$cq->fetchAll();
    }catch(Throwable $e){error_log('WorkwearCenter reminders: '.$e->getMessage());return 0;}
    foreach($companies as $company)try{
        $companyId=(int)$company['id'];
        // Nur Personen, deren letzte Sammel-Erinnerung mindestens 24 Stunden zurückliegt.
        $mq=db()->prepare("SELECT ea.id,ea.name,ea.email,ea.language,ea.reminder_digest_at,c.shop_languages FROM employee_accounts ea JOIN companies c ON c.id=ea.company_id WHERE ea.company_id=? AND ea.role='manager' AND ea.active=1 AND ea.registration_status='approved' AND ea.anonymized_at IS NULL AND (ea.employment_start IS NULL OR ea.employment_start<=CURDATE()) AND (ea.employment_end IS NULL OR ea.employment_end>=CURDATE()) AND (ea.reminder_digest_at IS NULL OR ea.reminder_digest_at<=DATE_SUB(NOW(),INTERVAL 24 HOUR)) ORDER BY ea.id");
        $mq->execute([$companyId]);$managers=$mq->fetchAll();
        if(!$managers)continue;
        $oq=db()->prepare("SELECT o.id,o.created_at,o.reminder_sent_at,o.employee_account_id,o.gross_total_cents,o.total_cents,COALESCE(ea.name,i.employee_name,'') employee_name FROM orders o LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id LEFT JOIN invites i ON i.id=o.invite_id WHERE o.company_id=? AND o.status='pending' AND o.created_at<=? ORDER BY o.created_at,o.id");
        $oq->execute([$companyId,$cutoff]);$orders=$oq->fetchAll();
        $rq=db()->prepare("SELECT id,name,created_at,reminder_sent_at FROM employee_accounts WHERE company_id=? AND registration_status='pending' AND anonymized_at IS NULL AND created_at<=? ORDER BY created_at,id");
        $rq->execute([$companyId,$cutoff]);$regs=$rq->fetchAll();
        $remindedOrders=[];$remindedRegs=[];
        foreach($managers as $m){
            $managerId=(int)$m['id'];
            // Vier-Augen-Prinzip: eigene Bestellungen gibt eine andere verantwortliche Person frei.
            $mine=yw_other_manager_count($companyId,$managerId)>0?array_values(array_filter($orders,fn(array $o)=>(int)$o['employee_account_id']!==$managerId)):$orders;
            if(!$mine && !$regs)continue;
            $last=$m['reminder_digest_at']!==null?(string)$m['reminder_digest_at']:null;
            $new=[];
            foreach($mine as $o)if(yw_reminder_is_new($last,(string)$o['created_at'],$o['reminder_sent_at']!==null?(string)$o['reminder_sent_at']:null,$days))$new['o'.(int)$o['id']]=true;
            foreach($regs as $r)if(yw_reminder_is_new($last,(string)$r['created_at'],$r['reminder_sent_at']!==null?(string)$r['reminder_sent_at']:null,$days))$new['r'.(int)$r['id']]=true;
            if(!$new)continue; // nichts Neues seit der letzten Sammel-Erinnerung
            // Atomar vormerken: parallele Läufe (Cronjob, Seitenaufruf) verschicken nie zwei Sammel-Erinnerungen in 24 Stunden.
            $claim=db()->prepare('UPDATE employee_accounts SET reminder_digest_at=? WHERE id=? AND reminder_digest_at<=>? AND (reminder_digest_at IS NULL OR reminder_digest_at<=DATE_SUB(NOW(),INTERVAL 24 HOUR))');
            $claim->execute([$now,$managerId,$last]);
            if($claim->rowCount()!==1)continue;
            if(!yw_mail_to_person($m,fn(array $p)=>yw_reminder_digest_mail($p,$company,$mine,$regs,$days,$new))){
                db()->prepare('UPDATE employee_accounts SET reminder_digest_at=? WHERE id=? AND reminder_digest_at=?')->execute([$last,$managerId,$now]);continue;
            }
            $sent++;
            foreach($mine as $o)$remindedOrders[]=(int)$o['id'];
            foreach($regs as $r)$remindedRegs[]=(int)$r['id'];
        }
        // Merker je Vorgang wie bisher (Grundlage für Personen ohne frühere Sammel-Erinnerung).
        foreach(['orders'=>$remindedOrders,'employee_accounts'=>$remindedRegs] as $table=>$ids)
            foreach(array_chunk(array_values(array_unique($ids)),500) as $chunk)
                db()->exec('UPDATE '.$table.' SET reminder_sent_at=NOW() WHERE reminder_sent_at IS NULL AND id IN ('.implode(',',array_map('intval',$chunk)).')');
    }catch(Throwable $e){error_log('WorkwearCenter reminders (Firma '.(int)$company['id'].'): '.$e->getMessage());} // eine gestörte Firma hält die anderen nicht auf
    return $sent;
}

/* ---------- 10.15: Aufbewahrungsfristen (Aktivitätsprotokoll, Mailwarteschlange) ---------- */

const YW_AUDIT_RETENTION_DEFAULT = 24; // Monate
const YW_AUDIT_RETENTION_MIN = 6;
const YW_AUDIT_RETENTION_MAX = 120;
const YW_RETENTION_BATCH = 5000; // Zeilen je Art und Cronlauf: große Tabellen schrumpfen schrittweise, der Lauf bleibt kurz

/** Aufbewahrungsfrist des Aktivitätsprotokolls in Monaten (Einstellung unter System, sonst 24; nie unter 6). */
function yw_audit_retention_months(?string $value=null): int {
    $value=$value??current_site_setting('audit_retention_months','');
    return preg_match('/^\d{1,4}$/D',$value)?max(YW_AUDIT_RETENTION_MIN,min(YW_AUDIT_RETENTION_MAX,(int)$value)):YW_AUDIT_RETENTION_DEFAULT;
}

/** Index auf audit_log.created_at vorhanden? Ohne ihn (vor update.php) würde jeder Lauf die ganze Tabelle lesen. */
function yw_audit_created_index_ready(): bool {
    static $ready=null;
    if($ready===null){try{$ready=(bool)db()->query("SHOW INDEX FROM audit_log WHERE Key_name='idx_audit_created'")->fetch();}catch(Throwable $e){$ready=false;}}
    return $ready;
}

/**
 * Löscht stapelweise nur Protokoll- und Warteschlangenzeilen (je Art höchstens $batch pro Lauf): Aktivitätsprotokoll
 * nach der eingestellten Frist (Einträge zu Lexware/Rechnungen und Lohnabzug bleiben), versendete Mails nach 90 Tagen,
 * fehlgeschlagene nach 180 Tagen. Aufträge, Positionen, Umtausch und Lexware-Belege werden nie berührt.
 * @return array{audit:int,mail_sent:int,mail_failed:int}
 */
function yw_retention_prune(int $batch=YW_RETENTION_BATCH): array {
    $batch=max(1,min(50000,$batch));$done=['audit'=>0,'mail_sent'=>0,'mail_failed'=>0];
    $run=static function(string $key,string $sql,array $params) use(&$done): void {
        try{$q=db()->prepare($sql);$q->execute($params);$done[$key]=$q->rowCount();}
        catch(Throwable $e){error_log('WorkwearCenter retention '.$key.': '.$e->getMessage());}
    };
    // Datensparsamkeit (DSGVO Art. 5): versendete Mails enthalten Namen und Bestelldaten.
    $run('mail_sent',"DELETE FROM yw_mail_queue WHERE status='sent' AND sent_at<DATE_SUB(NOW(),INTERVAL 90 DAY) ORDER BY sent_at LIMIT ".$batch,[]);
    $run('mail_failed',"DELETE FROM yw_mail_queue WHERE status='failed' AND created_at<DATE_SUB(NOW(),INTERVAL 180 DAY) ORDER BY id LIMIT ".$batch,[]);
    if(yw_audit_created_index_ready())
        $run('audit',"DELETE FROM audit_log WHERE created_at<DATE_SUB(NOW(),INTERVAL ? MONTH) AND action NOT LIKE 'lexware.%' AND action NOT LIKE 'payroll.%' ORDER BY created_at LIMIT ".$batch,[yw_audit_retention_months()]);
    try{
        $now=date('Y-m-d H:i:s');$prev=json_decode(current_site_setting('retention_status',''),true);
        set_site_setting('retention_status',(string)json_encode(['at'=>$now,'last'=>array_sum($done)>0?['at'=>$now]+$done:(is_array($prev['last']??null)?$prev['last']:null)]));
    }catch(Throwable $e){error_log('WorkwearCenter retention status: '.$e->getMessage());}
    return $done;
}

/** Karte „Aufbewahrung“ im Systemstatus: Frist des Aktivitätsprotokolls und letzte Bereinigung. */
function yw_retention_card(): string {
    $status=json_decode(current_site_setting('retention_status',''),true);$status=is_array($status)?$status:[];
    $last=is_array($status['last']??null)?$status['last']:null;$n=static fn($v): string => number_format((int)$v,0,',','.');
    $when=static fn($v): string => ($ts=strtotime((string)$v))?date('d.m.Y H:i',$ts):'–';
    $info=!empty($status['at'])?'Letzte Bereinigung: '.e($when($status['at'])).' Uhr':'Noch keine Bereinigung – sie läuft automatisch mit dem Cronjob';
    if($last)$info.=' · zuletzt gelöscht am '.e($when($last['at']??'')).' Uhr: Protokolleinträge '.$n($last['audit']??0).', versendete E-Mails '.$n($last['mail_sent']??0).', fehlgeschlagene E-Mails '.$n($last['mail_failed']??0); // Zahl hinten: stimmt auch bei „1“
    return '<section class="card"><h2>Aufbewahrung</h2><form method="post" class="filter-bar">'.csrf_field().'<input type="hidden" name="action" value="yw_retention"><label>Aktivitätsprotokoll aufbewahren (Monate)<input type="number" name="audit_retention_months" min="'.YW_AUDIT_RETENTION_MIN.'" max="'.YW_AUDIT_RETENTION_MAX.'" step="1" value="'.yw_audit_retention_months().'" required></label><button class="btn primary">Speichern</button></form>'
        .'<p class="muted">Der Cronjob löscht automatisch Protokolleinträge nach dieser Frist (mindestens '.YW_AUDIT_RETENTION_MIN.' Monate), versendete E-Mails nach 90 und fehlgeschlagene nach 180 Tagen. Aufträge, Rechnungen/Lexware und Lohnabzug-Einträge bleiben immer erhalten.</p><p>'.$info.'.</p></section>';
}

/** Speichern der Aufbewahrungsfrist (Verwaltung → System). */
function yw_retention_save_action(): never {
    $months=filter_var($_POST['audit_retention_months']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>YW_AUDIT_RETENTION_MIN,'max_range'=>YW_AUDIT_RETENTION_MAX]]);
    if(!is_int($months)){flash('error','Aufbewahrung: bitte eine ganze Zahl zwischen '.YW_AUDIT_RETENTION_MIN.' und '.YW_AUDIT_RETENTION_MAX.' Monaten angeben.');redirect('/admin?tab=system');}
    set_site_setting('audit_retention_months',(string)$months);
    audit_log('system.retention_saved','system',null,['months'=>$months]);
    flash('success','Aufbewahrungsfrist gespeichert: Aktivitätsprotokoll '.$months.' Monate.');
    redirect('/admin?tab=system');
}

/* ---------- 10.11: Verlauf lesbar machen ---------- */

/** Konto per DSGVO gelöscht? (anonymized_at gesetzt oder Platzhalter-Adresse) */
function yw_account_anonymized(?string $email,?string $anonymizedAt=null): bool {
    return ($anonymizedAt!==null&&$anonymizedAt!=='')||str_ends_with(strtolower(trim((string)$email)),'@anonymisiert.invalid');
}

/** Bestellstatus, Produktionsstand und Entscheidungen als Klartext. */
function yw_history_status_label(string $value): string {
    $map=['pending'=>'Freigabe offen','approved'=>'Freigegeben','rejected'=>'Abgelehnt','cancelled'=>'Storniert','requested'=>'Beantragt','received'=>'Ware erhalten','done'=>'Erledigt']+yw_production_statuses();
    return $map[$value]??$value;
}

/** Wer hat es getan: Verwaltung, Mitarbeiter/in, Schnittstelle … */
function yw_actor_label(string $type,?int $id=null,array $meta=[],array $people=[]): string {
    $via=(string)($meta['via']??'');
    if(str_starts_with($type,'api:'))return 'Schnittstelle '.substr($type,4);
    if($type==='guest'&&str_starts_with($via,'api:'))return 'Schnittstelle '.substr($via,4);
    return match($type){
        'admin'=>'Verwaltung'.($id&&isset($people['admin'][$id])?' ('.$people['admin'][$id].')':''),
        'employee'=>$id&&isset($people['employee'][$id])?'Mitarbeiter/in · '.$people['employee'][$id]:'Mitarbeiter/in',
        'auftragszentrale'=>'Auftragszentrale',
        'guest','system','cron'=>'System',
        default=>$type,
    };
}

/** Namen der Akteure eines Verlaufs auf einmal laden; gelöschte Konten ohne Namen. */
function yw_actor_people(array $logs): array {
    $ids=['admin'=>[],'employee'=>[]];
    foreach($logs as $l)if(isset($ids[$l['actor_type']??''])&&!empty($l['actor_id']))$ids[$l['actor_type']][(int)$l['actor_id']]=true;
    $people=['admin'=>[],'employee'=>[]];
    try{
        if($ids['admin']){$in=implode(',',array_map('intval',array_keys($ids['admin'])));foreach(db()->query("SELECT id,name,email FROM admins WHERE id IN ($in)") as $r)$people['admin'][(int)$r['id']]=(string)($r['name']?:$r['email']);}
        if($ids['employee']){$in=implode(',',array_map('intval',array_keys($ids['employee'])));foreach(db()->query("SELECT id,name,email,anonymized_at FROM employee_accounts WHERE id IN ($in)") as $r)$people['employee'][(int)$r['id']]=yw_account_anonymized((string)$r['email'],$r['anonymized_at'])?'Konto gelöscht (DSGVO)':(string)$r['name'];}
    }catch(Throwable $e){}
    return $people;
}

/** Objekt eines Protokolleintrags als Klartext. */
function yw_entity_label(?string $type,?int $id): string {
    if(!$type)return '–';
    if($type==='order'&&$id)return 'Auftrag '.yw_order_number($id);
    $labels=['employee'=>'Mitarbeiterkonto','company'=>'Firma','inquiry'=>'Anfrage','product'=>'Produkt','product_category'=>'Kategorie','handover'=>'Übergabe','api_key'=>'API-Schlüssel','site'=>'Website','system'=>'System','admin'=>'Administrator','probeshop'=>'Probeshop','decoration_motif'=>'Veredelungsmotiv','invite'=>'Einladung','company_product'=>'Firmenprodukt'];
    return ($labels[$type]??$type).($id?' #'.$id:'');
}

/** Zusatzangaben (Status, Entscheidung, Grund …) übersetzt; Rückgabe als Text. */
function yw_history_details(array $meta): string {
    $parts=[];
    if(isset($meta['previous_status']))$parts[]='vorher: '.yw_history_status_label((string)$meta['previous_status']);
    if(isset($meta['status'])&&is_string($meta['status']))$parts[]='→ '.yw_history_status_label($meta['status']);
    if(isset($meta['decision'])&&is_string($meta['decision']))$parts[]='→ '.yw_history_status_label($meta['decision']).(!empty($meta['self_decision'])?' (eigene Bestellung)':'');
    if(!empty($meta['tracking'])&&is_string($meta['tracking']))$parts[]='Sendung '.$meta['tracking'];
    if(!empty($meta['reference'])&&is_string($meta['reference']))$parts[]='Referenz '.$meta['reference'];
    if(!empty($meta['reason'])&&is_string($meta['reason']))$parts[]='Grund: '.mb_strimwidth($meta['reason'],0,140,'…');
    // 10.15: Rollenwechsel/Sperre von Verantwortlichen (vorher→nachher)
    if(!empty($meta['changes'])&&is_array($meta['changes'])){$names=['role'=>'Rolle','registration_status'=>'Zugang','active'=>'Aktiv','employment_start'=>'Eintritt','employment_end'=>'Austritt'];$words=['manager'=>'Verantwortlich','employee'=>'Mitarbeiter','approved'=>'freigegeben','rejected'=>'abgelehnt','pending'=>'offen'];foreach($meta['changes'] as $k=>$v)if(is_string($v))$parts[]=($names[$k]??$k).': '.($k==='active'?strtr($v,['1'=>'ja','0'=>'nein']):strtr($v,$words));}
    return implode(' · ',$parts);
}
