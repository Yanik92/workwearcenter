<?php
declare(strict_types=1);

function yw_customer_text(mixed $value,int $max): string {
    $value=trim(is_string($value)?$value:'');
    if(mb_strlen($value)>$max) throw new DomainException('Ein Eingabefeld ist zu lang.');
    return $value;
}

function yw_customer_date(mixed $value): ?string {
    $value=trim(is_string($value)?$value:''); if($value==='') return null;
    foreach(['Y-m-d','d.m.Y'] as $format){$d=DateTimeImmutable::createFromFormat('!'.$format,$value);if($d && $d->format($format)===$value)return $d->format('Y-m-d');}
    throw new DomainException('Datum bitte als JJJJ-MM-TT oder TT.MM.JJJJ angeben.');
}

function yw_customer_assert_date_range(?string $start,?string $end): void {
    if($start!==null && $end!==null && $start>$end)throw new DomainException('Das Austrittsdatum darf nicht vor dem Eintrittsdatum liegen.');
}

function yw_customer_assert_manager(array $company,array $actor): void {
    $q=db()->prepare("SELECT id FROM employee_accounts WHERE id=? AND company_id=? AND role='manager' AND active=1 AND registration_status='approved' FOR UPDATE");
    $q->execute([$actor['id'],$company['id']]); if(!$q->fetchColumn()) throw new DomainException('Keine Berechtigung.');
}

function yw_customer_csv_rows(array $file): array {
    if((int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new DomainException('Bitte eine CSV-Datei auswählen.');
    $size=(int)($file['size']??0);$tmp=(string)($file['tmp_name']??'');$name=(string)($file['name']??'');
    if($size<=0||$size>1_000_000)throw new DomainException('CSV ist leer oder größer als 1 MB.');
    if($tmp===''||!is_uploaded_file($tmp))throw new DomainException('CSV wurde nicht als gültiger Upload erkannt.');
    if(strtolower((string)pathinfo($name,PATHINFO_EXTENSION))!=='csv')throw new DomainException('Bitte eine Datei mit der Endung .csv auswählen.');
    $mime=(string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if(!in_array($mime,['text/plain','text/csv','application/csv','application/vnd.ms-excel','application/octet-stream'],true))throw new DomainException('Das Dateiformat wurde nicht als CSV erkannt.');
    $h=fopen($tmp,'rb'); if(!$h) throw new DomainException('CSV konnte nicht gelesen werden.');
    $first=fgets($h); if($first===false){fclose($h);throw new DomainException('CSV ist leer.');}
    $counts=[';'=>substr_count($first,';'),','=>substr_count($first,','),"\t"=>substr_count($first,"\t")]; arsort($counts); $delimiter=(string)array_key_first($counts); rewind($h);
    $header=fgetcsv($h,0,$delimiter,'"',''); if(!$header){fclose($h);throw new DomainException('CSV-Kopfzeile fehlt.');}
    $aliases=['name'=>'name','e-mail'=>'email','email'=>'email','abteilung'=>'department','kostenstelle'=>'cost_center','standort'=>'location','personalnummer'=>'employee_number','eintritt'=>'employment_start','eintrittsdatum'=>'employment_start','austritt'=>'employment_end','austrittsdatum'=>'employment_end','ausstattungsprofil'=>'equipment_profile'];
    $map=[]; foreach($header as $i=>$cell){$key=mb_strtolower(trim(str_replace("\xEF\xBB\xBF",'',(string)$cell)));if(isset($aliases[$key]))$map[$aliases[$key]]=$i;}
    if(!isset($map['name'],$map['email'])){fclose($h);throw new DomainException('CSV benötigt mindestens die Spalten Name und E-Mail.');}
    $rows=[]; $line=1;
    while(($cells=fgetcsv($h,0,$delimiter,'"',''))!==false){$line++;if(count($rows)>=200){fclose($h);throw new DomainException('Pro Import sind höchstens 200 Mitarbeiter möglich.');}if(count(array_filter($cells,fn($v)=>trim((string)$v)!==''))===0)continue;
        $get=fn(string $key):string=>isset($map[$key])?trim((string)($cells[$map[$key]]??'')):'';
        $name=yw_customer_text($get('name'),160);$email=strtolower(yw_customer_text($get('email'),190));
        if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)){fclose($h);throw new DomainException('Ungültiger Name oder E-Mail in CSV-Zeile '.$line.'.');}
        $employmentStart=yw_customer_date($get('employment_start'));$employmentEnd=yw_customer_date($get('employment_end'));yw_customer_assert_date_range($employmentStart,$employmentEnd);
        $rows[]=['name'=>$name,'email'=>$email,'department'=>yw_customer_text($get('department'),120),'cost_center'=>yw_customer_text($get('cost_center'),120),'location'=>yw_customer_text($get('location'),120),'employee_number'=>yw_customer_text($get('employee_number'),80),'employment_start'=>$employmentStart,'employment_end'=>$employmentEnd,'equipment_profile'=>yw_customer_text($get('equipment_profile'),120),'equipment_profile_present'=>isset($map['equipment_profile'])];
    }
    fclose($h); return $rows;
}

function yw_customer_employee_action(array $company,array $actor,string $action): never {
    verify_csrf(); $created=[]; $message='Gespeichert.';$accessNotice=null;
    try{
        $importRows=$action==='customer_employee_import'?yw_customer_csv_rows($_FILES['employees_csv']??[]):[];
        db()->beginTransaction(); $company=yw_locked_company((int)$company['id']); yw_customer_assert_manager($company,$actor);
        if($action==='customer_employee_add'){
            $name=yw_customer_text($_POST['name']??'',160);$email=strtolower(yw_customer_text($_POST['email']??'',190));if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new DomainException('Name oder E-Mail ungültig.');
            $profileId=yw_equipment_profile_id((int)$company['id'],$_POST['equipment_profile_id']??'',true);
            $employmentStart=yw_customer_date($_POST['employment_start']??'');$employmentEnd=yw_customer_date($_POST['employment_end']??'');yw_customer_assert_date_range($employmentStart,$employmentEnd);
            $q=db()->prepare('SELECT id FROM employee_accounts WHERE company_id=? AND email=? FOR UPDATE');$q->execute([$company['id'],$email]);if($q->fetchColumn())throw new DomainException('Diese E-Mail ist bereits vorhanden.');
            $q=db()->prepare("INSERT INTO employee_accounts(company_id,name,email,password_hash,role,registration_status,active,department,cost_center,location,employee_number,employment_start,employment_end,equipment_profile_id) VALUES(?,?,?,?, 'employee','approved',1,?,?,?,?,?,?,?)");
            $q->execute([$company['id'],$name,$email,password_hash(random_token(),PASSWORD_DEFAULT),yw_customer_text($_POST['department']??'',120),yw_customer_text($_POST['cost_center']??'',120),yw_customer_text($_POST['location']??'',120),yw_customer_text($_POST['employee_number']??'',80),$employmentStart,$employmentEnd,$profileId]);
            $created[]=['id'=>(int)db()->lastInsertId(),'name'=>$name,'email'=>$email];$message='Mitarbeiter angelegt; Einrichtungslink wurde für den Versand eingeplant.';
        }elseif($action==='customer_employee_save'){
            $id=(int)($_POST['account_id']??0);$q=db()->prepare('SELECT * FROM employee_accounts WHERE id=? AND company_id=? FOR UPDATE');$q->execute([$id,$company['id']]);$target=$q->fetch();if(!$target)throw new DomainException('Mitarbeiter nicht gefunden.');
            $role=(string)($_POST['role']??'employee');$status=(string)($_POST['registration_status']??'approved');$active=isset($_POST['active'])?1:0;
            if(!in_array($role,['employee','manager'],true)||!in_array($status,['pending','approved','rejected'],true))throw new DomainException('Rolle oder Status ungültig.');
            if($id===(int)$actor['id']){$role='manager';$status='approved';$active=1;}
            $employmentStart=yw_customer_date($_POST['employment_start']??'');$employmentEnd=yw_customer_date($_POST['employment_end']??'');yw_customer_assert_date_range($employmentStart,$employmentEnd);$today=date('Y-m-d');
            $remainsEligible=$role==='manager' && $active===1 && $status==='approved' && ($employmentStart===null||$employmentStart<=$today) && ($employmentEnd===null||$employmentEnd>=$today);
            if($target['role']==='manager' && !$remainsEligible){$q=db()->prepare("SELECT COUNT(*) FROM employee_accounts WHERE company_id=? AND role='manager' AND active=1 AND registration_status='approved' AND (employment_start IS NULL OR employment_start<=CURDATE()) AND (employment_end IS NULL OR employment_end>=CURDATE()) AND id<>?");$q->execute([$company['id'],$id]);if(!(int)$q->fetchColumn())throw new DomainException('Der letzte aktuell zugangsberechtigte Verantwortliche kann nicht entfernt oder zeitlich gesperrt werden.');}
            $name=yw_customer_text($_POST['name']??'',160);if($name==='')throw new DomainException('Name darf nicht leer sein.');
            $profileId=yw_equipment_profile_id((int)$company['id'],$_POST['equipment_profile_id']??'',true);
            $q=db()->prepare('UPDATE employee_accounts SET name=?,department=?,cost_center=?,location=?,employee_number=?,employment_start=?,employment_end=?,equipment_profile_id=?,role=?,registration_status=?,active=? WHERE id=? AND company_id=?');
            $q->execute([$name,yw_customer_text($_POST['department']??'',120),yw_customer_text($_POST['cost_center']??'',120),yw_customer_text($_POST['location']??'',120),yw_customer_text($_POST['employee_number']??'',80),$employmentStart,$employmentEnd,$profileId,$role,$status,$active,$id,$company['id']]);
            if((string)$target['registration_status']!==$status && in_array($status,['approved','rejected'],true))$accessNotice=['email'=>(string)$target['email'],'name'=>$name,'status'=>$status,'active'=>$active];
        }elseif($action==='customer_employee_anonymize'){
            $id=(int)($_POST['account_id']??0);
            if($id===(int)$actor['id'])throw new DomainException('Das eigene Konto kann hier nicht anonymisiert werden. Bitte den Betreiber kontaktieren.');
            yw_anonymize_employee((int)$company['id'],$id);
            $message='Personenbezogene Daten wurden entfernt. Bestellungen bleiben für die Buchhaltung erhalten.';
        }elseif($action==='customer_employee_link'){
            $id=(int)($_POST['account_id']??0);$q=db()->prepare("SELECT id,name,email FROM employee_accounts WHERE id=? AND company_id=? AND active=1 AND registration_status='approved' FOR UPDATE");$q->execute([$id,$company['id']]);$target=$q->fetch();if(!$target)throw new DomainException('Nur aktive, freigegebene Konten können einen Passwort-Link erhalten.');$created[]=['id'=>(int)$target['id'],'name'=>$target['name'],'email'=>$target['email']];$message='Neuer Passwort-Link wurde für den Versand eingeplant.';
        }else{
            $profileNames=[];foreach(yw_equipment_profile_options((int)$company['id']) as $profile)$profileNames[mb_strtolower(trim((string)$profile['name']))]=(int)$profile['id'];
            $find=db()->prepare('SELECT id FROM employee_accounts WHERE company_id=? AND email=? FOR UPDATE');
            $insert=db()->prepare("INSERT INTO employee_accounts(company_id,name,email,password_hash,role,registration_status,active,department,cost_center,location,employee_number,employment_start,employment_end,equipment_profile_id) VALUES(?,?,?,?, 'employee','approved',1,?,?,?,?,?,?,?)");
            $update=db()->prepare('UPDATE employee_accounts SET name=?,department=?,cost_center=?,location=?,employee_number=?,employment_start=?,employment_end=?,equipment_profile_id=? WHERE id=? AND company_id=?');
            $updateWithoutProfile=db()->prepare('UPDATE employee_accounts SET name=?,department=?,cost_center=?,location=?,employee_number=?,employment_start=?,employment_end=? WHERE id=? AND company_id=?');
            foreach($importRows as $row){$profileName=mb_strtolower(trim($row['equipment_profile']));if($row['equipment_profile_present']&&$profileName!==''&&!isset($profileNames[$profileName]))throw new DomainException('Unbekanntes Ausstattungsprofil in CSV: '.$row['equipment_profile']);$profileId=$row['equipment_profile_present']?($profileName===''?null:$profileNames[$profileName]):yw_default_equipment_profile_id((int)$company['id']);$find->execute([$company['id'],$row['email']]);$id=(int)$find->fetchColumn();$fields=[$row['name'],$row['department'],$row['cost_center'],$row['location'],$row['employee_number'],$row['employment_start'],$row['employment_end']];if($id){if($row['equipment_profile_present'])$update->execute([...$fields,$profileId,$id,$company['id']]);else $updateWithoutProfile->execute([...$fields,$id,$company['id']]);}else{$insert->execute([$company['id'],$row['name'],$row['email'],password_hash(random_token(),PASSWORD_DEFAULT),$row['department'],$row['cost_center'],$row['location'],$row['employee_number'],$row['employment_start'],$row['employment_end'],$profileId]);$created[]=['id'=>(int)db()->lastInsertId(),'name'=>$row['name'],'email'=>$row['email']];}}
            $q=db()->prepare("SELECT COUNT(*) FROM employee_accounts WHERE company_id=? AND role='manager'");$q->execute([$company['id']]);$managerCount=(int)$q->fetchColumn();
            if($managerCount>0){$q=db()->prepare("SELECT COUNT(*) FROM employee_accounts WHERE company_id=? AND role='manager' AND active=1 AND registration_status='approved' AND (employment_start IS NULL OR employment_start<=CURDATE()) AND (employment_end IS NULL OR employment_end>=CURDATE())");$q->execute([$company['id']]);if(!(int)$q->fetchColumn())throw new DomainException('Der Import würde alle aktuell zugangsberechtigten Verantwortlichen sperren.');}
            $message=count($importRows).' Zeilen verarbeitet, '.count($created).' neue Konten angelegt.';
        }
        db()->commit();
        if($accessNotice){
            $approved=$accessNotice['status']==='approved' && (int)$accessNotice['active']===1;
            $text=$approved
                ? "Hallo ".$accessNotice['name'].",\n\ndein Zugang zum Firmen-Shop wurde freigegeben.\n\nAnmeldung: ".workwearcenter_url((string)$company['slug'].'/login')
                : "Hallo ".$accessNotice['name'].",\n\ndein Zugang zum Firmen-Shop ist derzeit nicht freigegeben. Bitte wende dich bei Rückfragen an deinen Firmenverantwortlichen.";
            send_system_mail($accessNotice['email'],$approved?'Dein Zugang wurde freigegeben':'Information zu deinem Zugang',$text);
        }
        $linkFailures=0;foreach($created as $new){try{yw_send_password_link($new['id'],$new['name'],$new['email'],(string)$company['slug'],$action!=='customer_employee_link',false);}catch(Throwable $mailError){$linkFailures++;error_log('Account setup mail: '.$mailError->getMessage());}}
        if($linkFailures)$message.=' Für '.$linkFailures.' Konten konnte kein Einrichtungslink angelegt werden.';
        audit_log($action,'company',(int)$company['id'],['company_id'=>(int)$company['id'],'created'=>count($created)]);flash('success',$message);
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if(!$e instanceof DomainException)error_log('Customer employee action: '.$e->getMessage());flash('error',$e instanceof DomainException?$e->getMessage():'Mitarbeiter konnten nicht gespeichert werden.');}
    redirect('/'.$company['slug'].'/mitarbeiter');
}

/**
 * DSGVO: Personenbezogene Kontodaten unwiderruflich entfernen (Art. 17).
 * Bestellungen bleiben als Geschäftsunterlagen erhalten (Aufbewahrungspflichten
 * nach HGB/AO); das Konto selbst ist danach weder nutzbar noch einer Person zuordenbar.
 */
function yw_anonymize_employee(int $companyId,int $accountId): void {
    $ownTransaction=!db()->inTransaction();
    if($ownTransaction)db()->beginTransaction();
    try{
        $q=db()->prepare('SELECT id,role,active,registration_status,anonymized_at FROM employee_accounts WHERE id=? AND company_id=? FOR UPDATE');
        $q->execute([$accountId,$companyId]);$target=$q->fetch();
        if(!$target)throw new DomainException('Mitarbeiterkonto wurde nicht gefunden.');
        if($target['anonymized_at'])throw new DomainException('Dieses Konto wurde bereits anonymisiert.');
        if($target['role']==='manager'){
            $c=db()->prepare("SELECT COUNT(*) FROM employee_accounts WHERE company_id=? AND id<>? AND role='manager' AND active=1 AND registration_status='approved' AND anonymized_at IS NULL");
            $c->execute([$companyId,$accountId]);
            if(!(int)$c->fetchColumn())throw new DomainException('Der letzte Verantwortliche kann nicht anonymisiert werden. Bitte zuerst eine andere Person zum Verantwortlichen machen.');
        }
        $placeholder='geloescht-'.$accountId.'-'.bin2hex(random_bytes(4)).'@anonymisiert.invalid';
        db()->prepare("UPDATE employee_accounts SET name=?,email=?,password_hash=?,role='employee',active=0,registration_status='rejected',location=NULL,employee_number=NULL,employment_start=NULL,employment_end=NULL,budget_override_cents=NULL,last_login_at=NULL,auth_version=auth_version+1,anonymized_at=NOW() WHERE id=? AND company_id=?")
            ->execute(['Gelöschtes Konto #'.$accountId,$placeholder,password_hash(random_token(),PASSWORD_DEFAULT),$accountId,$companyId]);
        db()->prepare('DELETE FROM yw_password_resets WHERE account_id=?')->execute([$accountId]);
        if($ownTransaction)db()->commit();
    }catch(Throwable $e){if($ownTransaction && db()->inTransaction())db()->rollBack();throw $e;}
    audit_log('employee.anonymized','employee',$accountId,['company_id'=>$companyId]);
}

function yw_customer_employee_export(string $slug): never {
    $q=db()->prepare('SELECT * FROM companies WHERE slug=? AND active=1');$q->execute([$slug]);$company=$q->fetch();if(!$company)redirect('/');$actor=company_portal_account($company);if(!$actor||!account_is_manager($actor)){http_response_code(403);exit('Kein Zugriff.');}
    $q=db()->prepare('SELECT ea.name,ea.email,ea.department,ea.cost_center,ea.location,ea.employee_number,ea.employment_start,ea.employment_end,ep.name equipment_profile,ea.role,ea.registration_status,ea.active FROM employee_accounts ea LEFT JOIN yw_equipment_profiles ep ON ep.id=ea.equipment_profile_id WHERE ea.company_id=? ORDER BY ea.name');$q->execute([$company['id']]);
    header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="mitarbeiter-'.preg_replace('/[^a-z0-9-]/','',$slug).'-'.date('Y-m-d').'.csv"');$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Name','E-Mail','Abteilung','Kostenstelle','Standort','Personalnummer','Eintritt','Austritt','Ausstattungsprofil','Rolle','Status','Aktiv'],';','"','');foreach($q as $row)fputcsv($out,array_map('yw_csv_safe',array_values($row)),';','"','');fclose($out);exit;
}

/** Budgetjahr für Auswertungen: aktuelles oder vorheriges. */
function yw_report_period(array $company,bool $previous): array {
    [$start,$end]=yw_period((int)$company['budget_reset_month']);
    if($previous){$end=$start;$start=(new DateTimeImmutable($start))->modify('-1 year')->format('Y-m-d H:i:s');}
    return [$start,$end];
}

function yw_report_money(int $cents): string { return number_format($cents/100,2,',',''); }

/**
 * Auswertung für Verantwortliche: eine Zeile je Bestellposition (plus Versand),
 * damit sich in Excel per Filter/Pivot nach Kostenstelle, Abteilung oder Person summieren lässt.
 */
function yw_customer_report_export(string $slug): never {
    $q=db()->prepare('SELECT * FROM companies WHERE slug=? AND active=1');$q->execute([$slug]);$company=$q->fetch();if(!$company)redirect('/');
    $actor=company_portal_account($company);if(!$actor||!account_is_manager($actor)){http_response_code(403);exit('Kein Zugriff.');}
    $previous=($_GET['jahr']??'')==='vorjahr';
    [$start,$end]=yw_report_period($company,$previous);
    $q=db()->prepare("SELECT o.id,o.created_at,o.status,o.fulfillment_status,o.shipping_gross_cents,o.shipping_employee_cents,o.shipping_company_cents,
            COALESCE(ea.name,'') name,COALESCE(ea.employee_number,'') employee_number,COALESCE(ea.department,'') department,COALESCE(ea.cost_center,'') cost_center,COALESCE(ea.location,'') location
        FROM orders o LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id
        WHERE o.company_id=? AND o.status IN ('pending','approved') AND o.created_at>=? AND o.created_at<? ORDER BY o.id");
    $q->execute([(int)$company['id'],$start,$end]);$orders=$q->fetchAll();
    $items=[];
    if($orders){
        $ids=array_map(fn($o)=>(int)$o['id'],$orders);
        $in=implode(',',array_fill(0,count($ids),'?'));
        $iq=db()->prepare("SELECT order_id,product_name,size,quantity,unit_gross_cents,employee_share_cents,company_share_cents FROM order_items WHERE order_id IN ($in) ORDER BY id");
        $iq->execute($ids);foreach($iq as $row)$items[(int)$row['order_id']][]=$row;
    }
    audit_log('customer.report_export','company',(int)$company['id'],['company_id'=>(int)$company['id'],'period_start'=>$start]);
    $statusLabels=['pending'=>'Wartet auf Freigabe','approved'=>'Freigegeben'];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="auswertung-'.preg_replace('/[^a-z0-9-]/','',$slug).'-'.substr($start,0,10).'.csv"');
    header('Cache-Control: no-store');
    $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");
    fputcsv($out,['Datum','Bestellung','Status','Produktionsstand','Mitarbeiter','Personalnummer','Abteilung','Kostenstelle','Standort','Artikel','Größe','Menge','Einzelpreis brutto','Summe brutto','Mitarbeiteranteil','Firmenanteil'],';','"','');
    foreach($orders as $o){
        $base=[date('d.m.Y',strtotime((string)$o['created_at'])),yw_order_number((int)$o['id']),$statusLabels[$o['status']]??$o['status'],$o['status']==='approved'?company_portal_fulfillment_status((string)($o['fulfillment_status']??'')):'',$o['name'],$o['employee_number'],$o['department'],$o['cost_center'],$o['location']];
        foreach($items[(int)$o['id']]??[] as $it){
            $qty=(int)$it['quantity'];
            fputcsv($out,array_map('yw_csv_safe',array_merge($base,[$it['product_name'],$it['size'],$qty,yw_report_money((int)$it['unit_gross_cents']),yw_report_money($qty*(int)$it['unit_gross_cents']),yw_report_money($qty*(int)$it['employee_share_cents']),yw_report_money($qty*(int)$it['company_share_cents'])])),';','"','');
        }
        if((int)$o['shipping_gross_cents']>0){
            fputcsv($out,array_map('yw_csv_safe',array_merge($base,['Versand','',1,yw_report_money((int)$o['shipping_gross_cents']),yw_report_money((int)$o['shipping_gross_cents']),yw_report_money((int)$o['shipping_employee_cents']),yw_report_money((int)$o['shipping_company_cents'])])),';','"','');
        }
    }
    fclose($out);exit;
}

function yw_customer_report_card(array $company): void {
    $previous=($_GET['jahr']??'')==='vorjahr';
    [$start,$end]=yw_report_period($company,$previous);
    $q=db()->prepare("SELECT i.product_name,SUM(i.quantity) qty,SUM(i.quantity*i.company_share_cents) company_spend,SUM(i.quantity*i.employee_share_cents) employee_spend FROM order_items i JOIN orders o ON o.id=i.order_id WHERE o.company_id=? AND o.status IN ('pending','approved') AND o.created_at>=? AND o.created_at<? GROUP BY i.product_name ORDER BY qty DESC,i.product_name LIMIT 15");
    $q->execute([(int)$company['id'],$start,$end]);$articles=$q->fetchAll();
    $label='Budgetjahr '.date('d.m.Y',strtotime($start)).' – '.date('d.m.Y',strtotime($end)-1);
    $slug=e($company['slug']);
    echo '<section class="cp-card cp-report"><div class="cp-report-head"><div><h2>Auswertung</h2><p>'.e($label).' · freigegebene und offene Bestellungen</p></div>';
    echo '<div class="cp-report-actions"><a class="cp-btn cp-btn-light" href="/'.$slug.'/mitarbeiter'.($previous?'':'?jahr=vorjahr').'">'.($previous?'Aktuelles Jahr':'Vorjahr ansehen').'</a>';
    echo '<a class="cp-btn" href="/'.$slug.'/auswertung.csv'.($previous?'?jahr=vorjahr':'').'">Für Excel herunterladen</a></div></div>';
    if(!$articles){echo '<p class="muted">In diesem Zeitraum gibt es noch keine Bestellungen.</p></section>';return;}
    echo '<div style="overflow:auto"><table><thead><tr><th>Artikel</th><th>Stück</th><th>Firmenanteil</th><th>Mitarbeiteranteil</th></tr></thead><tbody>';
    foreach($articles as $a)echo '<tr><td>'.e($a['product_name']).'</td><td>'.(int)$a['qty'].'</td><td>'.money((int)$a['company_spend']).'</td><td>'.money((int)$a['employee_spend']).'</td></tr>';
    echo '</tbody></table></div><p class="muted">Die Excel-Datei enthält jede Bestellposition mit Mitarbeiter, Abteilung, Kostenstelle und Standort – ideal zum Filtern oder für die Weiterberechnung an Kostenstellen.</p></section>';
}

function yw_customer_people_page(array $company,array $account): void {
    $profiles=yw_equipment_profile_options((int)$company['id']);
    $q=db()->prepare('SELECT * FROM employee_accounts WHERE company_id=? ORDER BY active DESC,registration_status,name');$q->execute([$company['id']]);$people=$q->fetchAll();
    $active=count(array_filter($people,fn($p)=>(int)$p['active']===1));$pending=count(array_filter($people,fn($p)=>$p['registration_status']==='pending'));
    $q=db()->prepare("SELECT COUNT(*) FROM orders WHERE company_id=? AND status='pending'");$q->execute([$company['id']]);$openOrders=(int)$q->fetchColumn();
    [$periodStart,$periodEnd]=yw_report_period($company,($_GET['jahr']??'')==='vorjahr');
    $q=db()->prepare("SELECT COALESCE(NULLIF(ea.department,''),'Ohne Abteilung') department,COALESCE(NULLIF(ea.cost_center,''),'Ohne Kostenstelle') cost_center,COALESCE(NULLIF(ea.location,''),'Ohne Standort') location,COUNT(DISTINCT ea.id) employees,COALESCE(SUM(os.company_spend),0) company_spend FROM employee_accounts ea LEFT JOIN (SELECT employee_account_id,SUM(company_total_cents) company_spend FROM orders WHERE company_id=? AND status IN ('pending','approved') AND created_at>=? AND created_at<? GROUP BY employee_account_id) os ON os.employee_account_id=ea.id WHERE ea.company_id=? AND ea.active=1 GROUP BY ea.department,ea.cost_center,ea.location ORDER BY department,cost_center,location");$q->execute([$company['id'],$periodStart,$periodEnd,$company['id']]);$groups=$q->fetchAll();
    echo '<div class="cp-section-head"><div><h1>Firmenkunden-Cockpit</h1><p>Mitarbeiter, Rollen und Zuordnungen verwalten.</p></div><strong>'.$active.' aktiv · '.$pending.' offen</strong></div>';
    echo '<section class="cp-kpis"><article class="cp-kpi"><div class="icon">'.wc_public_icon('user').'</div><strong>'.$active.'</strong><span>Aktive Konten</span></article><article class="cp-kpi"><div class="icon">'.wc_public_icon('check').'</div><strong>'.$openOrders.'</strong><span>Offene Bestellfreigaben</span></article><article class="cp-kpi"><div class="icon">'.wc_public_icon('lock').'</div><strong>'.$pending.'</strong><span>Offene Registrierungen</span></article></section>';
    echo '<section class="cp-card"><h2>Verbrauch nach Organisation</h2><p>Firmenanteil inklusive offener Reservierungen im '.((($_GET['jahr']??'')==='vorjahr')?'vorherigen':'aktuellen').' Budgetjahr.</p><div style="overflow:auto"><table><thead><tr><th>Abteilung</th><th>Kostenstelle</th><th>Standort</th><th>Mitarbeiter</th><th>Firmenanteil</th></tr></thead><tbody>';foreach($groups as $group)echo '<tr><td>'.e($group['department']).'</td><td>'.e($group['cost_center']).'</td><td>'.e($group['location']).'</td><td>'.(int)$group['employees'].'</td><td>'.money((int)$group['company_spend']).'</td></tr>';echo '</tbody></table></div></section>';
    yw_customer_report_card($company);
    echo '<section class="cp-card"><h2>Mitarbeiter hinzufügen</h2><form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="customer_employee_add"><div class="grid2"><label>Name<input name="name" maxlength="160" required></label><label>E-Mail<input type="email" name="email" maxlength="190" required></label><label>Abteilung<input name="department" maxlength="120"></label><label>Kostenstelle<input name="cost_center" maxlength="120"></label><label>Standort<input name="location" maxlength="120"></label><label>Personalnummer<input name="employee_number" maxlength="80"></label><label>Eintritt<input type="date" name="employment_start"></label><label>Austritt<input type="date" name="employment_end"></label>'.yw_equipment_profile_select($profiles,null).'</div><button class="btn primary">Anlegen und Einrichtungslink senden</button></form></section>';
    echo '<section class="cp-card"><h2>CSV-Import</h2><p>Spalten: Name, E-Mail, Abteilung, Kostenstelle, Standort, Personalnummer, Eintritt, Austritt, Ausstattungsprofil. Profilnamen müssen exakt einem aktiven Profil entsprechen. Bestehende E-Mails werden aktualisiert; Rollen bleiben dabei erhalten. Maximal 200 Zeilen.</p><form method="post" enctype="multipart/form-data" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="customer_employee_import"><label>Mitarbeiter-CSV<input type="file" name="employees_csv" accept=".csv,text/csv" required></label><button class="btn">CSV importieren</button></form><p><a href="/'.e($company['slug']).'/mitarbeiter.csv">Aktuelle Mitarbeiter als CSV exportieren</a></p></section>';
    $statusLabels=['pending'=>'Wartet auf Freigabe','approved'=>'Freigegeben','rejected'=>'Abgelehnt'];
    foreach($people as $person){
        if(!empty($person['anonymized_at']))continue; // anonymisierte Konten nicht mehr anzeigen
        $status=(string)($person['registration_status']??'approved');
        echo '<details class="cp-card"><summary><strong>'.e($person['name']).'</strong> · '.e($person['email']).' · '.e($statusLabels[$status]??$status).'</summary>';
        if($status==='pending'){
            echo '<div class="cp-action-row"><form method="post">'.csrf_field().'<input type="hidden" name="action" value="registration_decision"><input type="hidden" name="account_id" value="'.(int)$person['id'].'"><input type="hidden" name="decision" value="rejected"><button class="cp-btn reject" type="submit">Ablehnen</button></form>';
            echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="registration_decision"><input type="hidden" name="account_id" value="'.(int)$person['id'].'"><input type="hidden" name="decision" value="approved"><button class="cp-btn approve" type="submit">Zugang freigeben</button></form></div>';
        }
        echo '<form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="customer_employee_save"><input type="hidden" name="account_id" value="'.(int)$person['id'].'"><div class="grid2"><label>Name<input name="name" maxlength="160" value="'.e($person['name']).'" required></label><label>E-Mail<input value="'.e($person['email']).'" disabled></label><label>Abteilung<input name="department" maxlength="120" value="'.e($person['department']??'').'"></label><label>Kostenstelle<input name="cost_center" maxlength="120" value="'.e($person['cost_center']??'').'"></label><label>Standort<input name="location" maxlength="120" value="'.e($person['location']??'').'"></label><label>Personalnummer<input name="employee_number" maxlength="80" value="'.e($person['employee_number']??'').'"></label><label>Eintritt<input type="date" name="employment_start" value="'.e($person['employment_start']??'').'"></label><label>Austritt<input type="date" name="employment_end" value="'.e($person['employment_end']??'').'"></label>'.yw_equipment_profile_select($profiles,isset($person['equipment_profile_id'])?(int)$person['equipment_profile_id']:null).'<label>Rolle<select name="role"><option value="employee">Mitarbeiter</option><option value="manager" '.($person['role']==='manager'?'selected':'').'>Verantwortlicher</option></select></label><label>Status<select name="registration_status"><option value="pending" '.($status==='pending'?'selected':'').'>Wartet</option><option value="approved" '.($status==='approved'?'selected':'').'>Freigegeben</option><option value="rejected" '.($status==='rejected'?'selected':'').'>Abgelehnt</option></select></label></div><label class="check"><input type="checkbox" name="active" value="1" '.((int)$person['active']?'checked':'').'><span>Aktiv</span></label><button class="btn">Speichern</button> <button class="btn" name="action" value="customer_employee_link">Neuen Passwort-Link senden</button></form>'.((int)$person['id']!==(int)$account['id']?'<form method="post" class="space-top" data-confirm="Alle persönlichen Daten von '.e($person['name']).' endgültig entfernen? Das kann nicht rückgängig gemacht werden.">'.csrf_field().'<input type="hidden" name="action" value="customer_employee_anonymize"><input type="hidden" name="account_id" value="'.(int)$person['id'].'"><button class="btn small danger">Daten löschen (DSGVO)</button> <small class="muted">Für ausgeschiedene Mitarbeiter. Bestellungen bleiben erhalten.</small></form>':'').'</details>';
    }
}
