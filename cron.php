<?php
declare(strict_types=1);

// Ausschließlich für einen serverseitigen Cronjob, niemals als Web-Endpunkt.
if(PHP_SAPI!=='cli'){
    http_response_code(404);
    exit;
}

require __DIR__.'/app/bootstrap.php';
require_once __DIR__.'/app/modules/system.php';
require_once __DIR__.'/app/modules/probeshop.php';

// Programmdateien neuer als die Datenbank: nur Mails versenden, alles andere erst nach update.php.
if(!yw_schema_ready()){
    try{yw_process_mail_queue(50);}catch(Throwable $e){error_log('WorkwearCenter cron: '.$e->getMessage());}
    fwrite(STDERR,"Datenbank nicht aktuell: ".yw_schema_problem()."\n");
    exit(1);
}

try{
    set_site_setting('cron_last_run',date('Y-m-d H:i:s'));
    yw_process_mail_queue(50);
    $handed=yw_process_handover_queue(25);
    yw_cleanup_expired_security_rows();
    $reminded=yw_send_reminders(3);
    $budgetMails=yw_send_budget_year_mails(500);
    yw_orders_cleanup();
    require_once __DIR__.'/app/modules/shop.php';
    if(yw_daiber_enabled()){ // fehlende Produktbilder (vorne, hinten, Ärmel) aus dem Daiber-Shop, höchstens 20 Sekunden je Lauf
        try{
            $daiber=yw_daiber_fetch_missing(20.0);
            fwrite(STDOUT,"Bilder aus dem Daiber-Shop: ".$daiber['loaded']." geladen, ".$daiber['assigned']." zugeordnet".($daiber['remaining']?", ".$daiber['remaining']." Artikel folgen":"").($daiber['offline']?" (Daiber-Shop nicht erreichbar)":"")."\n");
        }catch(Throwable $e){error_log('WorkwearCenter cron Daiber-Bilder: '.$e->getMessage());}
    }
    $zones=yw_zone_auto_backfill(100); // Druckzonen neuer oder geänderter Produktfotos erkennen
    fwrite(STDOUT,"Druckzonen automatisch erkannt: ".$zones."\n");
    fwrite(STDOUT,"Mails zum neuen Budgetjahr: ".$budgetMails."\n");
    $probeshops=yw_probeshop_cleanup(); // abgelaufene Probeshops samt Logo löschen (DSGVO)
    fwrite(STDOUT,"Abgelaufene Probeshops gelöscht: ".$probeshops."\n");
    fwrite(STDOUT,"Erinnerungen an Verantwortliche: ".$reminded."\n");
    fwrite(STDOUT,"Übergaben an die Auftragsverwaltung: ".$handed."\n");
    fwrite(STDOUT,"WorkwearCenter-Mailwarteschlange und Sicherheitsbereinigung verarbeitet.\n");
}catch(Throwable $e){
    error_log('WorkwearCenter cron: '.$e->getMessage());
    fwrite(STDERR,"Mailwarteschlange konnte nicht verarbeitet werden.\n");
    exit(1);
}
