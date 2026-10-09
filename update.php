<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
require_admin();
require_once __DIR__.'/app/migrations.php';

$changes=[];
$error='';
if(is_post()){
    verify_csrf();
    $locked=false;
    try {
        if(($_POST['backup_confirmed']??'')!=='yes') throw new RuntimeException('Backup bestätigen.');
        $locked=(int)db()->query("SELECT GET_LOCK('yanik_workwear_schema_update',0)")->fetchColumn()===1;
        if(!$locked) throw new RuntimeException('Ein anderes Update läuft bereits.');
        $changes=run_schema_updates(db());
        // Ab 10.3 werden sitemap.xml und robots.txt automatisch erzeugt. Alte, feste Dateien
        // aus V9/V10 würden sonst Vorrang haben – nur unveränderte Originale werden entfernt.
        foreach(['sitemap.xml'=>'https://workwearcenter.de/leistungen','robots.txt'=>'Sitemap: https://workwearcenter.de/sitemap.xml'] as $staticFile=>$marker){
            $staticPath=__DIR__.'/'.$staticFile;
            if(is_file($staticPath) && strlen((string)@file_get_contents($staticPath))<4000 && str_contains((string)@file_get_contents($staticPath),$marker) && @unlink($staticPath))$changes[]='Veraltete '.$staticFile.' entfernt (wird jetzt automatisch erzeugt)';
        }
        // 10.10: Katalogprodukte mit gemeinsamer SKU eindeutig machen (SKU-FARBE).
        if(function_exists('yw_katalog_fix_duplicate_skus')){$fixedSkus=yw_katalog_fix_duplicate_skus();if($fixedSkus)$changes[]=$fixedSkus.' Katalogprodukte mit eindeutiger Artikelnummer versehen';}
        $versionQuery=db()->prepare('SELECT auth_version FROM admins WHERE id=? LIMIT 1');
        $versionQuery->execute([(int)$_SESSION['admin_id']]);
        $_SESSION['admin_auth_version']=(int)$versionQuery->fetchColumn();
        audit_log('system.updated','system',null,['changes'=>$changes]);
    } catch(Throwable $e) {
        $error='Update fehlgeschlagen. Bitte vorher eine Datenbanksicherung erstellen und das Serverprotokoll prüfen.';
        error_log('WorkwearCenter update failed: '.$e->getMessage());
    } finally {
        if($locked) db()->query("SELECT RELEASE_LOCK('yanik_workwear_schema_update')");
    }
}
render_head('WorkwearCenter aktualisieren');
echo '<main class="install-card"><div class="eyebrow">SYSTEMUPDATE</div><h1>WorkwearCenter aktualisieren</h1>';
if($error) echo '<div class="flash error">'.e($error).'</div>';
elseif(is_post()) echo '<div class="flash success">Update abgeschlossen. '.count($changes).' Datenbankanpassungen wurden ausgeführt.</div><p><a class="btn primary" href="/admin">Zur Verwaltung</a></p>';
else echo '<p><strong>Version '.e(app_version()).'</strong> bringt Sicherheits- und Datenschutzkorrekturen, Auftragsstorno und Verbesserungen am Lieferantenkatalog. Bestehende Firmen, Produkte, Konten, Bestellungen und individuell gepflegte Inhalte bleiben erhalten.</p><p><strong>Vor dem Update Webspace und Datenbank vollständig sichern.</strong></p><form method="post">'.csrf_field().'<label><input type="checkbox" name="backup_confirmed" value="yes" required> Aktuelle Sicherung vorhanden</label><button class="btn primary" type="submit">Datenbank jetzt aktualisieren</button></form>';
// Diagnose: prüft, ob alle Programmteile laden und die Datenbank vollständig ist.
$diag=['Programmversion'=>app_version(),'PHP-Version'=>PHP_VERSION.(version_compare(PHP_VERSION,'8.1.0','<')?' – ZU ALT, mindestens 8.1 nötig':' – in Ordnung')];
$modulesOk=true;
foreach(['corporate','admin','public','demo','shop','system'] as $module){
    try{require_once __DIR__.'/app/modules/'.$module.'.php';}
    catch(Throwable $loadError){$modulesOk=false;$diag['Programmteil '.$module]='FEHLER: '.$loadError->getMessage().' ('.basename($loadError->getFile()).':'.$loadError->getLine().')';}
}
if($modulesOk && function_exists('yw_schema_ready')){
    $ready=yw_schema_ready();
    $diag['Datenbank']=$ready?'vollständig – der Shop sollte laufen':'NICHT vollständig: '.yw_schema_problem();
    if(!$ready && preg_match('/ FROM (\w+) LIMIT 0/',yw_schema_problem(),$tm)){
        try{$cols=db()->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');$cols->execute([$tm[1]]);$diag['Spalten in '.$tm[1]]=implode(', ',$cols->fetchAll(PDO::FETCH_COLUMN))?:'(Tabelle fehlt)';}catch(Throwable $x){}
    }
    try{$diag['Datenbankstände']=(string)db()->query("SELECT GROUP_CONCAT(version SEPARATOR ', ') FROM yw_schema_versions")->fetchColumn();}catch(Throwable $x){$diag['Datenbankstände']='nicht lesbar: '.$x->getMessage();}
    try{$diag['Wartungsmodus']=current_site_setting('maintenance_mode','0')==='1'?'AN (Besucher sehen die Wartungsseite)':'aus';}catch(Throwable $x){}
}
$lastErrorFile=__DIR__.'/app/letzter-fehler.txt';
if(is_file($lastErrorFile))$diag['Letzter schwerer Fehler']=(string)file_get_contents($lastErrorFile,false,null,0,2000);
echo '<h2 style="margin-top:28px">Systemprüfung</h2><table class="table" style="min-width:0"><tbody>';
foreach($diag as $label=>$value)echo '<tr><th style="width:34%">'.e($label).'</th><td style="word-break:break-word">'.e($value).'</td></tr>';
echo '</tbody></table><p class="muted">Bei einem Problem diese Tabelle bitte als Bildschirmfoto an den Support schicken.</p>';
echo '</main>';
render_foot();
