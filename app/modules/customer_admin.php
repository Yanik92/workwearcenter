<?php
declare(strict_types=1);

function yw_customer_text(mixed $value,int $max): string {
    $value=trim(is_string($value)?$value:'');
    if(mb_strlen($value)>$max) throw new DomainException(t('people.err.too_long'));
    return $value;
}

function yw_customer_date(mixed $value): ?string {
    $value=trim(is_string($value)?$value:''); if($value==='') return null;
    foreach(['Y-m-d','d.m.Y'] as $format){$d=DateTimeImmutable::createFromFormat('!'.$format,$value);if($d && $d->format($format)===$value)return $d->format('Y-m-d');}
    throw new DomainException(t('people.err.date'));
}

function yw_customer_assert_date_range(?string $start,?string $end): void {
    if($start!==null && $end!==null && $start>$end)throw new DomainException(t('people.err.date_range'));
}

function yw_customer_assert_manager(array $company,array $actor): void {
    $q=db()->prepare("SELECT id FROM employee_accounts WHERE id=? AND company_id=? AND role='manager' AND active=1 AND registration_status='approved' FOR UPDATE");
    $q->execute([$actor['id'],$company['id']]); if(!$q->fetchColumn()) throw new DomainException(t('err.no_permission'));
}

function yw_customer_csv_rows(array $file): array {
    if((int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new DomainException(t('people.csv.choose'));
    $size=(int)($file['size']??0);$tmp=(string)($file['tmp_name']??'');$name=(string)($file['name']??'');
    if($size<=0||$size>1_000_000)throw new DomainException(t('people.csv.size'));
    if($tmp===''||!is_uploaded_file($tmp))throw new DomainException(t('people.csv.upload'));
    if(strtolower((string)pathinfo($name,PATHINFO_EXTENSION))!=='csv')throw new DomainException(t('people.csv.extension'));
    $mime=(string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if(!in_array($mime,['text/plain','text/csv','application/csv','application/vnd.ms-excel','application/octet-stream'],true))throw new DomainException(t('people.csv.format'));
    $h=fopen($tmp,'rb'); if(!$h) throw new DomainException(t('people.csv.read'));
    $first=fgets($h); if($first===false){fclose($h);throw new DomainException(t('people.csv.empty'));}
    $counts=[';'=>substr_count($first,';'),','=>substr_count($first,','),"\t"=>substr_count($first,"\t")]; arsort($counts); $delimiter=(string)array_key_first($counts); rewind($h);
    $header=fgetcsv($h,0,$delimiter,'"',''); if(!$header){fclose($h);throw new DomainException(t('people.csv.header'));}
    $aliases=['name'=>'name','e-mail'=>'email','email'=>'email','abteilung'=>'department','kostenstelle'=>'cost_center','standort'=>'location','personalnummer'=>'employee_number','eintritt'=>'employment_start','eintrittsdatum'=>'employment_start','austritt'=>'employment_end','austrittsdatum'=>'employment_end','ausstattungsprofil'=>'equipment_profile'];
    $map=[]; foreach($header as $i=>$cell){$key=mb_strtolower(trim(str_replace("\xEF\xBB\xBF",'',(string)$cell)));if(isset($aliases[$key]))$map[$aliases[$key]]=$i;}
    if(!isset($map['name'],$map['email'])){fclose($h);throw new DomainException(t('people.csv.columns'));}
    $rows=[]; $line=1;
    while(($cells=fgetcsv($h,0,$delimiter,'"',''))!==false){$line++;if(count($rows)>=200){fclose($h);throw new DomainException(t('people.csv.max'));}if(count(array_filter($cells,fn($v)=>trim((string)$v)!==''))===0)continue;
        $get=fn(string $key):string=>isset($map[$key])?trim((string)($cells[$map[$key]]??'')):'';
        $name=yw_customer_text($get('name'),160);$email=strtolower(yw_customer_text($get('email'),190));
        if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)){fclose($h);throw new DomainException(t('people.csv.row',['line'=>$line]));}
        $employmentStart=yw_customer_date($get('employment_start'));$employmentEnd=yw_customer_date($get('employment_end'));yw_customer_assert_date_range($employmentStart,$employmentEnd);
        $rows[]=['name'=>$name,'email'=>$email,'department'=>yw_customer_text($get('department'),120),'cost_center'=>yw_customer_text($get('cost_center'),120),'location'=>yw_customer_text($get('location'),120),'employee_number'=>yw_customer_text($get('employee_number'),80),'employment_start'=>$employmentStart,'employment_end'=>$employmentEnd,'equipment_profile'=>yw_customer_text($get('equipment_profile'),120),'equipment_profile_present'=>isset($map['equipment_profile'])];
    }
    fclose($h); return $rows;
}

function yw_customer_employee_action(array $company,array $actor,string $action): never {
    verify_csrf(); $created=[]; $message=t('people.saved');$accessNotice=null;
    try{
        $importRows=$action==='customer_employee_import'?yw_customer_csv_rows($_FILES['employees_csv']??[]):[];
        db()->beginTransaction(); $company=yw_locked_company((int)$company['id']); yw_customer_assert_manager($company,$actor);
        if($action==='customer_employee_add'){
            $name=yw_customer_text($_POST['name']??'',160);$email=strtolower(yw_customer_text($_POST['email']??'',190));if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new DomainException(t('people.err.name_email'));
            $profileId=yw_equipment_profile_id((int)$company['id'],$_POST['equipment_profile_id']??'',true);
            $employmentStart=yw_customer_date($_POST['employment_start']??'');$employmentEnd=yw_customer_date($_POST['employment_end']??'');yw_customer_assert_date_range($employmentStart,$employmentEnd);
            $q=db()->prepare('SELECT id FROM employee_accounts WHERE company_id=? AND email=? FOR UPDATE');$q->execute([$company['id'],$email]);if($q->fetchColumn())throw new DomainException(t('people.err.email_exists'));
            $q=db()->prepare("INSERT INTO employee_accounts(company_id,name,email,password_hash,role,registration_status,active,department,cost_center,location,employee_number,employment_start,employment_end,equipment_profile_id) VALUES(?,?,?,?, 'employee','approved',1,?,?,?,?,?,?,?)");
            $q->execute([$company['id'],$name,$email,password_hash(random_token(),PASSWORD_DEFAULT),yw_customer_text($_POST['department']??'',120),yw_customer_text($_POST['cost_center']??'',120),yw_customer_text($_POST['location']??'',120),yw_customer_text($_POST['employee_number']??'',80),$employmentStart,$employmentEnd,$profileId]);
            $created[]=['id'=>(int)db()->lastInsertId(),'name'=>$name,'email'=>$email];$message=t('people.created');
        }elseif($action==='customer_employee_save'){
            $id=(int)($_POST['account_id']??0);$q=db()->prepare('SELECT * FROM employee_accounts WHERE id=? AND company_id=? AND anonymized_at IS NULL FOR UPDATE');$q->execute([$id,$company['id']]);$target=$q->fetch();if(!$target)throw new DomainException(t('people.err.not_found')); // anonymisierte Konten bleiben endgültig gesperrt
            $role=(string)($_POST['role']??'employee');$status=(string)($_POST['registration_status']??'approved');$active=isset($_POST['active'])?1:0;
            if(!in_array($role,['employee','manager'],true)||!in_array($status,['pending','approved','rejected'],true))throw new DomainException(t('people.err.role'));
            if($id===(int)$actor['id']){$role='manager';$status='approved';$active=1;}
            $employmentStart=yw_customer_date($_POST['employment_start']??'');$employmentEnd=yw_customer_date($_POST['employment_end']??'');yw_customer_assert_date_range($employmentStart,$employmentEnd);$today=date('Y-m-d');
            $remainsEligible=$role==='manager' && $active===1 && $status==='approved' && ($employmentStart===null||$employmentStart<=$today) && ($employmentEnd===null||$employmentEnd>=$today);
            if($target['role']==='manager' && !$remainsEligible){$q=db()->prepare("SELECT COUNT(*) FROM employee_accounts WHERE company_id=? AND role='manager' AND active=1 AND registration_status='approved' AND (employment_start IS NULL OR employment_start<=CURDATE()) AND (employment_end IS NULL OR employment_end>=CURDATE()) AND id<>?");$q->execute([$company['id'],$id]);if(!(int)$q->fetchColumn())throw new DomainException(t('people.err.last_manager'));}
            $name=yw_customer_text($_POST['name']??'',160);if($name==='')throw new DomainException(t('people.err.name_empty'));
            $profileId=yw_equipment_profile_id((int)$company['id'],$_POST['equipment_profile_id']??'',true);
            $q=db()->prepare('UPDATE employee_accounts SET name=?,department=?,cost_center=?,location=?,employee_number=?,employment_start=?,employment_end=?,equipment_profile_id=?,role=?,registration_status=?,active=? WHERE id=? AND company_id=?');
            $q->execute([$name,yw_customer_text($_POST['department']??'',120),yw_customer_text($_POST['cost_center']??'',120),yw_customer_text($_POST['location']??'',120),yw_customer_text($_POST['employee_number']??'',80),$employmentStart,$employmentEnd,$profileId,$role,$status,$active,$id,$company['id']]);
            if((string)$target['registration_status']!==$status && in_array($status,['approved','rejected'],true))$accessNotice=['email'=>(string)$target['email'],'name'=>$name,'status'=>$status,'active'=>$active,'language'=>$target['language']??null,'shop_languages'=>$company['shop_languages']??null];
        }elseif($action==='customer_employee_anonymize'){
            $id=(int)($_POST['account_id']??0);
            if($id===(int)$actor['id'])throw new DomainException(t('people.err.anonymize_self'));
            yw_anonymize_employee((int)$company['id'],$id);
            $message=t('people.anonymized');
        }elseif($action==='customer_employee_link'){
            $id=(int)($_POST['account_id']??0);$q=db()->prepare("SELECT id,name,email FROM employee_accounts WHERE id=? AND company_id=? AND active=1 AND registration_status='approved' AND anonymized_at IS NULL FOR UPDATE");$q->execute([$id,$company['id']]);$target=$q->fetch();if(!$target)throw new DomainException(t('people.err.link'));$created[]=['id'=>(int)$target['id'],'name'=>$target['name'],'email'=>$target['email']];$message=t('people.link_sent');
        }else{
            $profileNames=[];foreach(yw_equipment_profile_options((int)$company['id']) as $profile)$profileNames[mb_strtolower(trim((string)$profile['name']))]=(int)$profile['id'];
            $find=db()->prepare('SELECT id FROM employee_accounts WHERE company_id=? AND email=? FOR UPDATE');
            $insert=db()->prepare("INSERT INTO employee_accounts(company_id,name,email,password_hash,role,registration_status,active,department,cost_center,location,employee_number,employment_start,employment_end,equipment_profile_id) VALUES(?,?,?,?, 'employee','approved',1,?,?,?,?,?,?,?)");
            $update=db()->prepare('UPDATE employee_accounts SET name=?,department=?,cost_center=?,location=?,employee_number=?,employment_start=?,employment_end=?,equipment_profile_id=? WHERE id=? AND company_id=?');
            $updateWithoutProfile=db()->prepare('UPDATE employee_accounts SET name=?,department=?,cost_center=?,location=?,employee_number=?,employment_start=?,employment_end=? WHERE id=? AND company_id=?');
            foreach($importRows as $row){$profileName=mb_strtolower(trim($row['equipment_profile']));if($row['equipment_profile_present']&&$profileName!==''&&!isset($profileNames[$profileName]))throw new DomainException(t('people.csv.profile',['profile'=>$row['equipment_profile']]));$profileId=$row['equipment_profile_present']?($profileName===''?null:$profileNames[$profileName]):yw_default_equipment_profile_id((int)$company['id']);$find->execute([$company['id'],$row['email']]);$id=(int)$find->fetchColumn();$fields=[$row['name'],$row['department'],$row['cost_center'],$row['location'],$row['employee_number'],$row['employment_start'],$row['employment_end']];if($id){if($row['equipment_profile_present'])$update->execute([...$fields,$profileId,$id,$company['id']]);else $updateWithoutProfile->execute([...$fields,$id,$company['id']]);}else{$insert->execute([$company['id'],$row['name'],$row['email'],password_hash(random_token(),PASSWORD_DEFAULT),$row['department'],$row['cost_center'],$row['location'],$row['employee_number'],$row['employment_start'],$row['employment_end'],$profileId]);$created[]=['id'=>(int)db()->lastInsertId(),'name'=>$row['name'],'email'=>$row['email']];}}
            $q=db()->prepare("SELECT COUNT(*) FROM employee_accounts WHERE company_id=? AND role='manager'");$q->execute([$company['id']]);$managerCount=(int)$q->fetchColumn();
            if($managerCount>0){$q=db()->prepare("SELECT COUNT(*) FROM employee_accounts WHERE company_id=? AND role='manager' AND active=1 AND registration_status='approved' AND (employment_start IS NULL OR employment_start<=CURDATE()) AND (employment_end IS NULL OR employment_end>=CURDATE())");$q->execute([$company['id']]);if(!(int)$q->fetchColumn())throw new DomainException(t('people.err.import_managers'));}
            $message=t('people.imported',['rows'=>count($importRows),'created'=>count($created)]);
        }
        db()->commit();
        if($accessNotice){
            $approved=$accessNotice['status']==='approved' && (int)$accessNotice['active']===1;
            yw_mail_to_person($accessNotice,yw_access_mail($approved,$company)); // 10.12: in der Sprache der Person
        }
        $linkFailures=0;foreach($created as $new){try{yw_send_password_link($new['id'],$new['name'],$new['email'],(string)$company['slug'],$action!=='customer_employee_link',false);}catch(Throwable $mailError){$linkFailures++;error_log('Account setup mail: '.$mailError->getMessage());}}
        if($linkFailures)$message.=' '.t('people.link_failures',['n'=>$linkFailures]);
        audit_log($action,'company',(int)$company['id'],['company_id'=>(int)$company['id'],'created'=>count($created)]);flash('success',$message);
    }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();if(!$e instanceof DomainException)error_log('Customer employee action: '.$e->getMessage());flash('error',$e instanceof DomainException?$e->getMessage():t('people.err.save_failed'));}
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
        $q=db()->prepare('SELECT id,name,email,invite_id,role,active,registration_status,anonymized_at FROM employee_accounts WHERE id=? AND company_id=? FOR UPDATE');
        $q->execute([$accountId,$companyId]);$target=$q->fetch();
        if(!$target)throw new DomainException(t('people.err.not_found'));
        if($target['anonymized_at'])throw new DomainException(t('people.err.already_anonymized'));
        if($target['role']==='manager'){
            $c=db()->prepare("SELECT COUNT(*) FROM employee_accounts WHERE company_id=? AND id<>? AND role='manager' AND active=1 AND registration_status='approved' AND anonymized_at IS NULL");
            $c->execute([$companyId,$accountId]);
            if(!(int)$c->fetchColumn())throw new DomainException(t('people.err.anonymize_last'));
        }
        $placeholder='geloescht-'.$accountId.'-'.bin2hex(random_bytes(4)).'@anonymisiert.invalid';
        db()->prepare("UPDATE employee_accounts SET name=?,email=?,password_hash=?,role='employee',active=0,registration_status='rejected',location=NULL,employee_number=NULL,employment_start=NULL,employment_end=NULL,budget_override_cents=NULL,last_login_at=NULL,auth_version=auth_version+1,anonymized_at=NOW() WHERE id=? AND company_id=?")
            ->execute(['Gelöschtes Konto #'.$accountId,$placeholder,password_hash(random_token(),PASSWORD_DEFAULT),$accountId,$companyId]);
        db()->prepare('DELETE FROM yw_password_resets WHERE account_id=?')->execute([$accountId]);
        yw_anonymize_employee_traces($accountId,(string)$target['name'],(string)$target['email'],(int)($target['invite_id']??0),'Gelöschtes Konto #'.$accountId,$placeholder);
        if($ownTransaction)db()->commit();
    }catch(Throwable $e){if($ownTransaction && db()->inTransaction())db()->rollBack();throw $e;}
    audit_log('employee.anonymized','employee',$accountId,['company_id'=>$companyId]);
}

/**
 * Name und E-Mail stecken auch in Bestell-Schnappschüssen, Übergabe-Datensätzen,
 * Einladungen und noch nicht versendeten Mails. Beträge, Artikel und Kostenstellen
 * bleiben für die Aufbewahrungspflichten erhalten.
 */
function yw_anonymize_employee_traces(int $accountId,string $name,string $email,int $inviteId,string $newName,string $newEmail): void {
    $pairs=[];
    foreach([[$name,$newName],[$email,$newEmail]] as [$old,$new]){
        if(trim($old)==='')continue;
        $pairs[$old]=$new;
        $escaped=substr((string)json_encode($old),1,-1);if($escaped!==$old)$pairs[$escaped]=substr((string)json_encode($new),1,-1);
        $escapedSlashes=substr((string)json_encode($old,JSON_UNESCAPED_UNICODE),1,-1);if($escapedSlashes!==$old)$pairs[$escapedSlashes]=$new;
    }
    if($pairs){
        uksort($pairs,fn($a,$b)=>strlen($b)<=>strlen($a));
        $orders=db()->prepare('SELECT id,delivery_snapshot_json FROM orders WHERE employee_account_id=?');$orders->execute([$accountId]);
        $updateOrder=db()->prepare('UPDATE orders SET delivery_snapshot_json=? WHERE id=?');
        $handover=db()->prepare('SELECT id,payload_json FROM yw_handover WHERE order_id=?');
        $updateHandover=db()->prepare('UPDATE yw_handover SET payload_json=? WHERE id=?');
        foreach($orders->fetchAll() as $o){
            if(($o['delivery_snapshot_json']??'')!=='')$updateOrder->execute([strtr((string)$o['delivery_snapshot_json'],$pairs),(int)$o['id']]);
            $handover->execute([(int)$o['id']]);
            foreach($handover->fetchAll() as $h)if(($h['payload_json']??'')!=='')$updateHandover->execute([strtr((string)$h['payload_json'],$pairs),(int)$h['id']]);
        }
    }
    if($inviteId>0)db()->prepare('UPDATE invites SET employee_name=?,active=0 WHERE id=?')->execute([$newName,$inviteId]);
    if(trim($email)!=='')db()->prepare("DELETE FROM yw_mail_queue WHERE to_email=? AND status IN ('pending','failed')")->execute([$email]);
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
    // Großkunden: eine Abfrage (statt IN-Liste mit einem Platzhalter je Bestellung, max. 65.535), ungepuffert gestreamt.
    $sql="SELECT o.id,o.created_at,o.status,o.fulfillment_status,o.shipping_gross_cents,o.shipping_employee_cents,o.shipping_company_cents,
            COALESCE(ea.name,'') name,COALESCE(ea.employee_number,'') employee_number,COALESCE(ea.department,'') department,COALESCE(ea.cost_center,'') cost_center,COALESCE(ea.location,'') location,
            i.id item_id,i.product_name,i.size,i.quantity,i.unit_price_cents,i.unit_gross_cents,i.employee_share_cents,i.company_share_cents,i.line_net_cents,i.line_gross_cents,i.line_employee_cents,i.line_company_cents
        FROM orders o LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id LEFT JOIN order_items i ON i.order_id=o.id
        WHERE o.company_id=? AND o.status IN ('pending','approved') AND o.created_at>=? AND o.created_at<? ORDER BY o.id,i.id";
    audit_log('customer.report_export','company',(int)$company['id'],['company_id'=>(int)$company['id'],'period_start'=>$start]);
    $statusLabels=['pending'=>'Wartet auf Freigabe','approved'=>'Freigegeben'];
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="auswertung-'.preg_replace('/[^a-z0-9-]/','',$slug).'-'.substr($start,0,10).'.csv"');
    header('Cache-Control: no-store');
    $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");
    fputcsv($out,['Datum','Bestellung','Status','Produktionsstand','Mitarbeiter','Personalnummer','Abteilung','Kostenstelle','Standort','Artikel','Größe','Menge','Einzelpreis brutto','Summe brutto','Mitarbeiteranteil','Firmenanteil'],';','"','');
    // Versandzeile folgt wie bisher nach der letzten Position einer Bestellung.
    $shipping=static function(?array $o,array $base) use($out): void {
        if($o && (int)$o['shipping_gross_cents']>0)fputcsv($out,array_map('yw_csv_safe',array_merge($base,['Versand','',1,yw_report_money((int)$o['shipping_gross_cents']),yw_report_money((int)$o['shipping_gross_cents']),yw_report_money((int)$o['shipping_employee_cents']),yw_report_money((int)$o['shipping_company_cents'])])),';','"','');
    };
    $prev=null;$base=[];
    @set_time_limit(300);
    yw_each_row($sql,[(int)$company['id'],$start,$end],function(array $o) use($out,$statusLabels,$shipping,&$prev,&$base): void {
        if(!$prev || (int)$prev['id']!==(int)$o['id']){
            $shipping($prev,$base);$prev=$o;
            $base=[date('d.m.Y',strtotime((string)$o['created_at'])),yw_order_number((int)$o['id']),$statusLabels[$o['status']]??$o['status'],$o['status']==='approved'?company_portal_fulfillment_status((string)($o['fulfillment_status']??'')):'',$o['name'],$o['employee_number'],$o['department'],$o['cost_center'],$o['location']];
        }
        if($o['item_id']===null)return;
        $qty=(int)$o['quantity'];$amounts=yw_order_item_amounts($o);
        fputcsv($out,array_map('yw_csv_safe',array_merge($base,[$o['product_name'],$o['size'],$qty,yw_report_money((int)$o['unit_gross_cents']),yw_report_money($amounts['gross']),yw_report_money($amounts['employee']),yw_report_money($amounts['company'])])),';','"','');
    });
    $shipping($prev,$base);
    fclose($out);exit;
}

function yw_customer_report_card(array $company): void {
    $previous=($_GET['jahr']??'')==='vorjahr';
    [$start,$end]=yw_report_period($company,$previous);
    $q=db()->prepare("SELECT i.product_name,SUM(i.quantity) qty,SUM(COALESCE(i.line_company_cents,i.quantity*i.company_share_cents)) company_spend,SUM(COALESCE(i.line_employee_cents,i.quantity*i.employee_share_cents)) employee_spend FROM order_items i JOIN orders o ON o.id=i.order_id WHERE o.company_id=? AND o.status IN ('pending','approved') AND o.created_at>=? AND o.created_at<? GROUP BY i.product_name ORDER BY qty DESC,i.product_name LIMIT 15");
    $q->execute([(int)$company['id'],$start,$end]);$articles=$q->fetchAll();
    $label=t('budget.year_range',['from'=>yw_date($start),'to'=>yw_date(strtotime($end)-1)]);
    $slug=e($company['slug']);
    echo '<section class="cp-card cp-report"><div class="cp-report-head"><div><h2>'.e(t('report.title')).'</h2><p>'.e($label).' · '.e(t('report.scope')).'</p></div>';
    echo '<div class="cp-report-actions"><a class="cp-btn cp-btn-light" href="/'.$slug.'/mitarbeiter'.($previous?'':'?jahr=vorjahr').'">'.e($previous?t('report.current_year'):t('report.previous_year')).'</a>';
    echo '<a class="cp-btn" href="/'.$slug.'/auswertung.csv'.($previous?'?jahr=vorjahr':'').'">'.e(t('report.download')).'</a></div></div>';
    if(!$articles){echo '<p class="muted">'.e(t('report.none')).'</p></section>';return;}
    echo '<div style="overflow:auto"><table><thead><tr><th>'.e(t('report.item')).'</th><th>'.e(t('report.pieces')).'</th><th>'.e(t('report.company_share')).'</th><th>'.e(t('report.employee_share')).'</th></tr></thead><tbody>';
    foreach($articles as $a)echo '<tr><td>'.e($a['product_name']).'</td><td>'.(int)$a['qty'].'</td><td>'.money((int)$a['company_spend']).'</td><td>'.money((int)$a['employee_spend']).'</td></tr>';
    echo '</tbody></table></div><p class="muted">'.e(t('report.note')).'</p></section>';
}

function yw_customer_people_page(array $company,array $account): void {
    $profiles=yw_equipment_profile_options((int)$company['id']);
    $q=db()->prepare('SELECT * FROM employee_accounts WHERE company_id=? ORDER BY active DESC,registration_status,name');$q->execute([$company['id']]);$people=$q->fetchAll();
    // Aktiv = freigegeben und nicht anonymisiert (offene Registrierungen haben active=1).
    $active=count(array_filter($people,fn($p)=>(int)$p['active']===1 && ($p['registration_status']??'approved')==='approved' && empty($p['anonymized_at'])));$pending=count(array_filter($people,fn($p)=>$p['registration_status']==='pending'));
    $q=db()->prepare("SELECT COUNT(*) FROM orders WHERE company_id=? AND status='pending'");$q->execute([$company['id']]);$openOrders=(int)$q->fetchColumn();
    $previous=($_GET['jahr']??'')==='vorjahr';
    [$periodStart,$periodEnd]=yw_report_period($company,$previous);
    $q=db()->prepare("SELECT NULLIF(ea.department,'') department,NULLIF(ea.cost_center,'') cost_center,NULLIF(ea.location,'') location,COUNT(DISTINCT ea.id) employees,COALESCE(SUM(os.company_spend),0) company_spend FROM employee_accounts ea LEFT JOIN (SELECT employee_account_id,SUM(company_total_cents) company_spend FROM orders WHERE company_id=? AND status IN ('pending','approved') AND created_at>=? AND created_at<? GROUP BY employee_account_id) os ON os.employee_account_id=ea.id WHERE ea.company_id=? AND ea.active=1 GROUP BY ea.department,ea.cost_center,ea.location ORDER BY department,cost_center,location");$q->execute([$company['id'],$periodStart,$periodEnd,$company['id']]);$groups=$q->fetchAll();
    echo '<div class="cp-section-head"><div><h1>'.e(t('people.title')).'</h1><p>'.e(t('people.intro')).'</p></div><strong>'.e(t('people.head_counts',['active'=>$active,'pending'=>$pending])).'</strong></div>';
    echo '<section class="cp-kpis"><article class="cp-kpi"><div class="icon">'.wc_public_icon('user').'</div><strong>'.$active.'</strong><span>'.e(t('people.kpi_active')).'</span></article><article class="cp-kpi"><div class="icon">'.wc_public_icon('check').'</div><strong>'.$openOrders.'</strong><span>'.e(t('people.kpi_orders')).'</span></article><article class="cp-kpi"><div class="icon">'.wc_public_icon('lock').'</div><strong>'.$pending.'</strong><span>'.e(t('people.kpi_registrations')).'</span></article></section>';
    echo '<section class="cp-card"><h2>'.e(t('people.usage_title')).'</h2><p>'.e($previous?t('people.usage_previous'):t('people.usage_current')).'</p><div style="overflow:auto"><table><thead><tr><th>'.e(t('people.department')).'</th><th>'.e(t('people.cost_center')).'</th><th>'.e(t('people.location')).'</th><th>'.e(t('people.employees')).'</th><th>'.e(t('report.company_share')).'</th></tr></thead><tbody>';
    foreach($groups as $group)echo '<tr><td>'.e($group['department']??t('people.no_department')).'</td><td>'.e($group['cost_center']??t('people.no_cost_center')).'</td><td>'.e($group['location']??t('people.no_location')).'</td><td>'.(int)$group['employees'].'</td><td>'.money((int)$group['company_spend']).'</td></tr>';
    echo '</tbody></table></div></section>';
    yw_customer_report_card($company);
    yw_payroll_manager_card($company);
    $fields=fn(array $p=[]): string => '<label>'.e(t('people.department')).'<input name="department" maxlength="120" value="'.e($p['department']??'').'"></label><label>'.e(t('people.cost_center')).'<input name="cost_center" maxlength="120" value="'.e($p['cost_center']??'').'"></label><label>'.e(t('people.location')).'<input name="location" maxlength="120" value="'.e($p['location']??'').'"></label><label>'.e(t('people.employee_number')).'<input name="employee_number" maxlength="80" value="'.e($p['employee_number']??'').'"></label><label>'.e(t('people.start')).'<input type="date" name="employment_start" value="'.e($p['employment_start']??'').'"></label><label>'.e(t('people.end')).'<input type="date" name="employment_end" value="'.e($p['employment_end']??'').'"></label>';
    echo '<section class="cp-card"><h2>'.e(t('people.add_title')).'</h2><form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="customer_employee_add"><div class="grid2"><label>'.e(t('common.name')).'<input name="name" maxlength="160" required></label><label>'.e(t('common.email')).'<input type="email" name="email" maxlength="190" required></label>'.$fields().yw_equipment_profile_select($profiles,null).'</div><button class="btn primary">'.e(t('people.add_button')).'</button></form></section>';
    echo '<section class="cp-card"><h2>'.e(t('people.csv_title')).'</h2><p>'.e(t('people.csv_text')).'</p><form method="post" enctype="multipart/form-data" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="customer_employee_import"><label>'.e(t('people.csv_file')).'<input type="file" name="employees_csv" accept=".csv,text/csv" required></label><button class="btn">'.e(t('people.csv_import')).'</button></form><p><a href="/'.e($company['slug']).'/mitarbeiter.csv">'.e(t('people.csv_export')).'</a></p></section>';
    $statusLabels=['pending'=>t('people.status_pending'),'approved'=>t('status.approved'),'rejected'=>t('status.rejected')];
    foreach($people as $person){
        if(!empty($person['anonymized_at']))continue; // anonymisierte Konten nicht mehr anzeigen
        $status=(string)($person['registration_status']??'approved');
        echo '<details class="cp-card"><summary><strong>'.e($person['name']).'</strong> · '.e($person['email']).' · '.e($statusLabels[$status]??$status).'</summary>';
        if($status==='pending'){
            echo '<div class="cp-action-row"><form method="post">'.csrf_field().'<input type="hidden" name="action" value="registration_decision"><input type="hidden" name="account_id" value="'.(int)$person['id'].'"><input type="hidden" name="decision" value="rejected"><button class="cp-btn reject" type="submit">'.e(t('approvals.reject')).'</button></form>';
            echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="registration_decision"><input type="hidden" name="account_id" value="'.(int)$person['id'].'"><input type="hidden" name="decision" value="approved"><button class="cp-btn approve" type="submit">'.e(t('people.approve_access')).'</button></form></div>';
        }
        echo '<form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="customer_employee_save"><input type="hidden" name="account_id" value="'.(int)$person['id'].'"><div class="grid2"><label>'.e(t('common.name')).'<input name="name" maxlength="160" value="'.e($person['name']).'" required></label><label>'.e(t('common.email')).'<input value="'.e($person['email']).'" disabled></label>'.$fields($person).yw_equipment_profile_select($profiles,isset($person['equipment_profile_id'])?(int)$person['equipment_profile_id']:null)
            .'<label>'.e(t('people.role')).'<select name="role"><option value="employee">'.e(t('people.role_employee')).'</option><option value="manager" '.($person['role']==='manager'?'selected':'').'>'.e(t('people.role_manager')).'</option></select></label><label>'.e(t('people.status')).'<select name="registration_status"><option value="pending" '.($status==='pending'?'selected':'').'>'.e(t('people.status_waiting')).'</option><option value="approved" '.($status==='approved'?'selected':'').'>'.e(t('status.approved')).'</option><option value="rejected" '.($status==='rejected'?'selected':'').'>'.e(t('status.rejected')).'</option></select></label></div><label class="check"><input type="checkbox" name="active" value="1" '.((int)$person['active']?'checked':'').'><span>'.e(t('people.active')).'</span></label><button class="btn">'.e(t('people.save')).'</button> <button class="btn" name="action" value="customer_employee_link">'.e(t('people.send_link')).'</button></form>'
            .((int)$person['id']!==(int)$account['id']?'<form method="post" class="space-top" data-confirm="'.e(t('people.anonymize_confirm',['name'=>$person['name']])).'">'.csrf_field().'<input type="hidden" name="action" value="customer_employee_anonymize"><input type="hidden" name="account_id" value="'.(int)$person['id'].'"><button class="btn small danger">'.e(t('people.anonymize')).'</button> <small class="muted">'.e(t('people.anonymize_hint')).'</small></form>':'').'</details>';
    }
}
