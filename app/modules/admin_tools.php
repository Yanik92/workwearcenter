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
        echo '<tr><td><strong>'.e($it['product_name']).'</strong></td><td>'.e($it['size']).'</td><td>'.$qty.'</td><td>'.($decs?implode('',$decs):'<span class="muted">keine</span>').'</td><td>'.money($lineGross).'</td></tr>';
    }
    if((int)($o['shipping_gross_cents']??0)>0)echo '<tr><td colspan="4">Versand</td><td>'.money((int)$o['shipping_gross_cents']).'</td></tr>';
    echo '<tr><td colspan="4"><strong>Gesamt brutto</strong></td><td><strong>'.money($total).'</strong></td></tr>';
    echo '</tbody></table></div>';
    echo '<div class="order-shares"><span>Firmenanteil <strong>'.money((int)($o['company_total_cents']??0)).'</strong></span><span>Mitarbeiteranteil <strong>'.money((int)($o['employee_total_cents']??0)).'</strong></span></div>';
    if(trim((string)($o['note']??''))!=='')echo '<p><strong>Hinweis des Bestellers:</strong> '.e((string)$o['note']).'</p>';
    if(trim((string)($o['decision_note']??''))!=='')echo '<p><strong>Anmerkung zur Freigabe:</strong> '.e((string)$o['decision_note']).'</p>';
    echo '<p class="muted">Korrekturabzüge findest du beim jeweiligen Artikel in der <a href="'.e(yw_customer_url((int)$o['company_id'],'kollektion')).'">Kollektion des Kunden</a>.</p>';
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
    $q=db()->prepare('SELECT slug FROM companies WHERE id=? LIMIT 1');$q->execute([$companyId]);$slug=$q->fetchColumn();
    if(!$slug)redirect('/admin?tab=companies');
    $_SESSION['admin_preview_company']=$companyId;
    unset($_SESSION['employee_account_id'],$_SESSION['employee_auth_version']);
    audit_log('company.preview_started','company',$companyId);
    redirect('/'.$slug);
}

function yw_admin_preview_stop(): never {
    unset($_SESSION['admin_preview_company']);
    redirect('/admin?tab=companies');
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

function yw_backup_sql(): string {
    $pdo=db();$out="-- WorkwearCenter Datensicherung ".date('Y-m-d H:i:s')."\nSET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n";
    $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach($tables as $t){
        $create=$pdo->query('SHOW CREATE TABLE `'.$t.'`')->fetch(PDO::FETCH_NUM);
        $out.="DROP TABLE IF EXISTS `$t`;\n".$create[1].";\n\n";
        $rows=$pdo->query('SELECT * FROM `'.$t.'`');$batch=[];
        foreach($rows as $row){
            $vals=[];foreach($row as $v)$vals[]=$v===null?'NULL':$pdo->quote((string)$v);
            $batch[]='('.implode(',',$vals).')';
            if(count($batch)>=200){$out.="INSERT INTO `$t` VALUES\n".implode(",\n",$batch).";\n";$batch=[];}
        }
        if($batch)$out.="INSERT INTO `$t` VALUES\n".implode(",\n",$batch).";\n";
        $out.="\n";
    }
    return $out."SET FOREIGN_KEY_CHECKS=1;\n";
}

function yw_backup_download(): never {
    require_admin();
    @set_time_limit(300);
    $sql=yw_backup_sql();
    $name='workwearcenter-sicherung-'.date('Y-m-d-Hi');
    $root=dirname(__DIR__,2);
    if(class_exists('ZipArchive')){
        $tmp=tempnam(sys_get_temp_dir(),'wwc');$zip=new ZipArchive();
        if($zip->open($tmp,ZipArchive::OVERWRITE)===true){
            $zip->addFromString('datenbank.sql',$sql);
            $zip->addFromString('LIESMICH.txt',"WorkwearCenter-Sicherung vom ".date('d.m.Y H:i')."\n\nWiederherstellen:\n1. datenbank.sql in phpMyAdmin importieren (vorher die Datenbank leeren).\n2. Ordner uploads/ auf den Webspace kopieren.\n3. config.php bleibt wie sie ist.\n");
            $dir=$root.'/uploads';
            if(is_dir($dir)){$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));foreach($it as $f){if(!$f->isFile())continue;$rel='uploads/'.substr($f->getPathname(),strlen($dir)+1);if(preg_match('/\.(php\d?|phtml|phar)$/i',$rel))continue;$zip->addFile($f->getPathname(),$rel);}}
            $zip->close();
            set_site_setting('last_backup_at',date('Y-m-d H:i:s'));audit_log('system.backup_downloaded','system',null,['bytes'=>filesize($tmp)]);
            header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="'.$name.'.zip"');header('Content-Length: '.filesize($tmp));header('Cache-Control: no-store');
            readfile($tmp);@unlink($tmp);exit;
        }
    }
    set_site_setting('last_backup_at',date('Y-m-d H:i:s'));audit_log('system.backup_downloaded','system',null,['bytes'=>strlen($sql)]);
    header('Content-Type: application/sql; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$name.'.sql"');header('Cache-Control: no-store');
    echo $sql;exit;
}

/** Tage seit der letzten Sicherung, null wenn nie. */
function yw_backup_age_days(): ?int {
    $last=current_site_setting('last_backup_at','');
    if($last==='')return null;
    return (int)floor((time()-strtotime($last))/86400);
}

/* ---------- Erinnerungen an Verantwortliche ---------- */

/** Offene Freigaben und Registrierungen, die länger als $days Tage liegen: einmalig erinnern. Rückgabe: Anzahl Mails. */
function yw_send_reminders(int $days=3): int {
    $sent=0;
    try{
        $q=db()->prepare("SELECT o.id,o.company_id,c.slug,c.shop_name,COALESCE(ea.name,i.employee_name,'') employee_name FROM orders o JOIN companies c ON c.id=o.company_id LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id LEFT JOIN invites i ON i.id=o.invite_id WHERE o.status='pending' AND o.reminder_sent_at IS NULL AND o.created_at<DATE_SUB(NOW(),INTERVAL ? DAY) AND c.active=1 LIMIT 50");
        $q->execute([$days]);
        foreach($q as $o){
            notify_company_managers((int)$o['company_id'],'Erinnerung: Bestellung '.yw_order_number((int)$o['id']).' wartet auf Freigabe',
                "Hallo,\n\ndie Bestellung ".yw_order_number((int)$o['id']).' von '.$o['employee_name'].' wartet seit mehr als '.$days." Tagen auf eine Entscheidung.\n\nFreigaben öffnen: ".workwearcenter_url($o['slug'].'/freigaben')."\n\nDiese Erinnerung wird je Bestellung nur einmal verschickt.");
            db()->prepare('UPDATE orders SET reminder_sent_at=NOW() WHERE id=?')->execute([(int)$o['id']]);$sent++;
        }
        $r=db()->prepare("SELECT ea.id,ea.company_id,ea.name,c.slug FROM employee_accounts ea JOIN companies c ON c.id=ea.company_id WHERE ea.registration_status='pending' AND ea.reminder_sent_at IS NULL AND ea.anonymized_at IS NULL AND ea.created_at<DATE_SUB(NOW(),INTERVAL ? DAY) AND c.active=1 LIMIT 50");
        $r->execute([$days]);
        foreach($r as $a){
            notify_company_managers((int)$a['company_id'],'Erinnerung: Registrierung von '.$a['name'].' wartet auf Freigabe',
                "Hallo,\n\n".$a['name'].' hat sich vor mehr als '.$days." Tagen im Firmenshop registriert und wartet auf die Freigabe.\n\nMitarbeiter verwalten: ".workwearcenter_url($a['slug'].'/mitarbeiter')."\n\nDiese Erinnerung wird je Person nur einmal verschickt.");
            db()->prepare('UPDATE employee_accounts SET reminder_sent_at=NOW() WHERE id=?')->execute([(int)$a['id']]);$sent++;
        }
    }catch(Throwable $e){error_log('WorkwearCenter reminders: '.$e->getMessage());}
    return $sent;
}

/* ---------- 10.11: Verlauf lesbar machen ---------- */

/** Konto per DSGVO gelöscht? (anonymized_at gesetzt oder Platzhalter-Adresse) */
function yw_account_anonymized(?string $email,?string $anonymizedAt=null): bool {
    return ($anonymizedAt!==null&&$anonymizedAt!=='')||str_ends_with(strtolower(trim((string)$email)),'@anonymisiert.invalid');
}

/** Bestellstatus, Produktionsstand und Entscheidungen als Klartext. */
function yw_history_status_label(string $value): string {
    $map=['pending'=>'Freigabe offen','approved'=>'Freigegeben','rejected'=>'Abgelehnt','cancelled'=>'Storniert']+yw_production_statuses();
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
    return implode(' · ',$parts);
}
