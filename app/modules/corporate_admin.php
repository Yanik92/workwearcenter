<?php
declare(strict_types=1);

function yw_admin_action(): void {
    $action=(string)($_POST['action']??'');
    if(!in_array($action,['yw_company','yw_employee','yw_limit','yw_profile_create','yw_profile_save','yw_profile_products','yw_production','yw_mail_process','yw_mail_test','yw_retention'],true)) return;
    require_admin(); verify_csrf();
    if($action==='yw_retention')yw_retention_save_action(); // 10.15: Aufbewahrungsfrist des Aktivitätsprotokolls
    if($action==='yw_mail_process'){
        try{db()->exec("UPDATE yw_mail_queue SET status='pending',attempts=0,available_at=NOW() WHERE status='failed' ORDER BY id LIMIT 20");yw_process_mail_queue(20);audit_log('mail.queue_processed','system',null);flash('success','Bis zu 20 fällige E-Mails wurden verarbeitet.');}
        catch(Throwable $e){error_log('Mail queue action: '.$e->getMessage());flash('error','Mailwarteschlange konnte nicht verarbeitet werden.');}
        redirect('/admin?tab=system');
    }
    if($action==='yw_mail_test'){
        try{$q=db()->prepare('SELECT email FROM admins WHERE id=? LIMIT 1');$q->execute([(int)$_SESSION['admin_id']]);$email=(string)$q->fetchColumn();$ok=$email!=='' && send_system_mail($email,'WorkwearCenter Testmail',"Hallo,\n\nder professionelle Mailversand von WorkwearCenter funktioniert.\n\nSystem: Version ".app_version()."\nStatus: Versand erfolgreich\n\nAnmeldung: ".workwearcenter_url('admin'),true);audit_log('mail.test_sent','system',null,['success'=>$ok]);flash($ok?'success':'error',$ok?'Testmail wurde an dein Administratorkonto gesendet.':'Testmail konnte nicht versendet werden. Bitte SMTP-Konfiguration und Mailwarteschlange prüfen.');}
        catch(Throwable $e){error_log('Test mail action: '.$e->getMessage());flash('error','Testmail konnte nicht versendet werden. Bitte SMTP-Konfiguration und Mailwarteschlange prüfen.');}
        redirect('/admin?tab=system');
    }
    $productionStatusChanged=false;
    try {
        db()->beginTransaction();
        $companyId=(int)($_POST['company_id']??0);
        $company=yw_locked_company($companyId);
        if($action==='yw_company'){
            $month=(int)($_POST['budget_reset_month']??1);
            if($month<1 || $month>12) throw new DomainException('Budgetmonat ungültig.');
            $budget=yw_money_input((string)($_POST['annual_budget']??'0'));
            $mode=(string)($_POST['registration_mode']??'approval');
            [$requiresPin]=yw_registration_policy($mode);
            $pin=(string)($_POST['registration_pin']??'');
            $hash=$company['registration_pin_hash'];
            if($pin!==''){
                if(strlen($pin)<6 || strlen($pin)>72) throw new DomainException('PIN: mindestens sechs und höchstens 72 Zeichen.');
                $hash=password_hash($pin,PASSWORD_DEFAULT);
            }
            if($requiresPin && !$hash) throw new DomainException('Für diesen Modus eine Firmen-PIN festlegen.');
            $delivery=(string)($_POST['delivery_mode']??'company');
            if(!in_array($delivery,['company','pickup'],true)) throw new DomainException('Lieferart ungültig.');
            $address=trim((string)($_POST['delivery_address']??''));
            if(mb_strlen($address)>1000 || ($delivery==='company' && $address==='')) throw new DomainException('Bitte vollständige Firmenlieferadresse angeben (max. 1000 Zeichen).');
            $q=db()->prepare('UPDATE companies SET budget_enabled=?,annual_budget_company_cents=?,budget_reset_month=?,registration_mode=?,registration_pin_hash=?,delivery_mode=?,delivery_address=? WHERE id=?');
            $q->execute([isset($_POST['budget_enabled'])?1:0,$budget,$month,$mode,$hash,$delivery,$address,$companyId]);
        } elseif($action==='yw_employee'){
            $id=(int)($_POST['account_id']??0); $raw=trim((string)($_POST['budget_override']??''));
            $override=$raw===''?null:yw_money_input($raw);
            $q=db()->prepare('SELECT id FROM employee_accounts WHERE id=? AND company_id=? FOR UPDATE');$q->execute([$id,$companyId]);if(!$q->fetchColumn())throw new DomainException('Mitarbeiter nicht gefunden.');
            $q=db()->prepare('UPDATE employee_accounts SET budget_override_cents=? WHERE id=? AND company_id=?');
            $q->execute([$override,$id,$companyId]);
        } elseif($action==='yw_limit'){
            $id=(int)($_POST['product_id']??0); $raw=trim((string)($_POST['yearly_limit_qty']??''));
            if($raw!=='' && (!ctype_digit($raw) || (int)$raw>100000)) throw new DomainException('Mengenlimit ungültig.');
            $q=db()->prepare('SELECT product_id FROM company_products WHERE company_id=? AND product_id=? FOR UPDATE');$q->execute([$companyId,$id]);if(!$q->fetchColumn())throw new DomainException('Firmenprodukt nicht gefunden.');
            $q=db()->prepare('UPDATE company_products SET yearly_limit_qty=? WHERE company_id=? AND product_id=?');
            $q->execute([$raw===''?null:(int)$raw,$companyId,$id]);
        } elseif($action==='yw_profile_create'){
            $name=trim((string)($_POST['profile_name']??''));$description=trim((string)($_POST['profile_description']??''));
            if($name===''||mb_strlen($name)>120||mb_strlen($description)>500)throw new DomainException('Profilname fehlt oder Beschreibung ist zu lang.');
            $q=db()->prepare('SELECT id FROM yw_equipment_profiles WHERE company_id=? AND name=?');$q->execute([$companyId,$name]);if($q->fetchColumn())throw new DomainException('Ein Ausstattungsprofil mit diesem Namen besteht bereits.');
            $q=db()->prepare('SELECT COUNT(*) FROM yw_equipment_profiles WHERE company_id=? AND active=1');$q->execute([$companyId]);
            $isDefault=(isset($_POST['is_default'])||(int)$q->fetchColumn()===0)?1:0;
            if($isDefault){$q=db()->prepare('UPDATE yw_equipment_profiles SET is_default=0 WHERE company_id=?');$q->execute([$companyId]);}
            $q=db()->prepare('INSERT INTO yw_equipment_profiles(company_id,name,description,active,is_default) VALUES(?,?,?,1,?)');$q->execute([$companyId,$name,$description,$isDefault]);
        } elseif($action==='yw_profile_save'){
            $profileId=(int)($_POST['profile_id']??0);$name=trim((string)($_POST['profile_name']??''));$description=trim((string)($_POST['profile_description']??''));$active=isset($_POST['active'])?1:0;$isDefault=$active&&isset($_POST['is_default'])?1:0;
            if($name===''||mb_strlen($name)>120||mb_strlen($description)>500)throw new DomainException('Profilname fehlt oder Beschreibung ist zu lang.');
            $q=db()->prepare('SELECT id,is_default FROM yw_equipment_profiles WHERE id=? AND company_id=? FOR UPDATE');$q->execute([$profileId,$companyId]);$existingProfile=$q->fetch();if(!$existingProfile)throw new DomainException('Ausstattungsprofil nicht gefunden.');
            $q=db()->prepare('SELECT id FROM yw_equipment_profiles WHERE company_id=? AND name=? AND id<>?');$q->execute([$companyId,$name,$profileId]);if($q->fetchColumn())throw new DomainException('Ein Ausstattungsprofil mit diesem Namen besteht bereits.');
            if(!$active){$q=db()->prepare('SELECT COUNT(*) FROM employee_accounts WHERE company_id=? AND equipment_profile_id=?');$q->execute([$companyId,$profileId]);if((int)$q->fetchColumn()>0)throw new DomainException('Profil ist noch Mitarbeitern zugeordnet. Bitte diese zuerst einem anderen Profil zuweisen.');}
            if($active && (int)$existingProfile['is_default'])$isDefault=1;
            if($isDefault){$q=db()->prepare('UPDATE yw_equipment_profiles SET is_default=0 WHERE company_id=?');$q->execute([$companyId]);}
            $q=db()->prepare('UPDATE yw_equipment_profiles SET name=?,description=?,active=?,is_default=? WHERE id=? AND company_id=?');$q->execute([$name,$description,$active,$isDefault,$profileId,$companyId]);
            if(!$active && (int)$existingProfile['is_default']){$q=db()->prepare('UPDATE yw_equipment_profiles SET is_default=1 WHERE company_id=? AND active=1 ORDER BY id LIMIT 1');$q->execute([$companyId]);}
        } elseif($action==='yw_profile_products'){
            $profileId=(int)($_POST['profile_id']??0);$selected=is_array($_POST['profile_products']??null)?$_POST['profile_products']:[];$starter=is_array($_POST['starter_qty']??null)?$_POST['starter_qty']:[];$targets=is_array($_POST['target_qty']??null)?$_POST['target_qty']:[];
            $q=db()->prepare('SELECT id FROM yw_equipment_profiles WHERE id=? AND company_id=? FOR UPDATE');$q->execute([$profileId,$companyId]);if(!$q->fetchColumn())throw new DomainException('Ausstattungsprofil nicht gefunden.');
            $allowed=[];$q=db()->prepare('SELECT product_id FROM company_products WHERE company_id=?');$q->execute([$companyId]);foreach($q as $row)$allowed[(int)$row['product_id']]=true;
            if(count($selected)>500)throw new DomainException('Ein Profil kann höchstens 500 Produkte enthalten.');
            $rows=[];$seen=[];foreach($selected as $rawId){if(!is_scalar($rawId)||!preg_match('/^[1-9]\d*$/D',(string)$rawId))throw new DomainException('Profil enthält eine ungültige Produktkennung.');$productId=(int)$rawId;if(isset($seen[$productId]))continue;$seen[$productId]=true;if(!isset($allowed[$productId]))throw new DomainException('Profil enthält ein nicht freigegebenes Produkt.');$starterRaw=trim((string)($starter[$productId]??'0'));$targetRaw=trim((string)($targets[$productId]??''));if(!ctype_digit($starterRaw)||(int)$starterRaw>1000||($targetRaw!==''&&(!ctype_digit($targetRaw)||(int)$targetRaw>100000))||($targetRaw!==''&&(int)$starterRaw>(int)$targetRaw))throw new DomainException('Starter- oder Sollmenge ungültig. Die Startermenge darf die Sollmenge nicht überschreiten.');$rows[]=[$profileId,$productId,(int)$starterRaw,$targetRaw===''?null:(int)$targetRaw];}
            $q=db()->prepare('DELETE FROM yw_equipment_profile_products WHERE profile_id=?');$q->execute([$profileId]);
            $q=db()->prepare('INSERT INTO yw_equipment_profile_products(profile_id,product_id,starter_qty,yearly_target_qty) VALUES(?,?,?,?)');foreach($rows as $row)$q->execute($row);
        } else {
            $id=(int)($_POST['order_id']??0);
            $status=(string)($_POST['production_status']??'');
            if(!array_key_exists($status,yw_production_statuses())) throw new DomainException('Status ungültig.');
            $note=trim((string)($_POST['production_note']??''));
            if(mb_strlen($note)>4000) throw new DomainException('Notiz zu lang.');
            $q=db()->prepare("SELECT fulfillment_status FROM orders WHERE id=? AND company_id=? AND status='approved' FOR UPDATE");$q->execute([$id,$companyId]);$oldStatus=$q->fetchColumn();if($oldStatus===false)throw new DomainException('Auftrag nicht gefunden oder nicht freigegeben.');
            $productionStatusChanged=(string)$oldStatus!==$status;
            $q=db()->prepare("UPDATE orders SET fulfillment_status=?,production_note=?,production_updated_at=NOW() WHERE id=? AND company_id=? AND status='approved'");
            $q->execute([$status,$note,$id,$companyId]);
        }
        db()->commit();
        audit_log($action,'company',$companyId,['company_id'=>$companyId]);
        if($action==='yw_production' && $productionStatusChanged) yw_notify_order_status($id,$status,(string)$company['slug']); // 10.12: in der Sprache der Person
        flash('success','Gespeichert.');
    } catch(Throwable $e){
        if(db()->inTransaction()) db()->rollBack();
        if(!$e instanceof DomainException && !$e instanceof InvalidArgumentException) error_log('Corporate action: '.$e->getMessage());
        flash('error',($e instanceof DomainException || $e instanceof InvalidArgumentException)?$e->getMessage():'Speichern fehlgeschlagen.');
    }
    // Früher tab=corporate: der Reiter leitet nur noch weiter – nach Ausgabe des Seitenkopfs scheitert das (leere Seite).
    redirect($action==='yw_production'?'/admin?tab=production&company_id='.$companyId:yw_customer_return_url($companyId,'regeln'));
}

function yw_admin_corporate(): void {
    // Ab 10.7 Teil der Kundenakte – alte Links weiterleiten.
    $id=(int)($_GET['company_id']??0);
    if(!$id){$r=db()->query('SELECT id FROM companies ORDER BY legal_name LIMIT 1')->fetchColumn();$id=(int)($r?:0);}
    redirect($id?('/admin?tab=companies&company='.$id.'&bereich=regeln'):'/admin?tab=companies');
}

/** Budget, Limits und Ausstattungsprofile einer Firma (Reiter „Regeln & Budget“ der Kundenakte). */
function yw_customer_section_budget(array $company): void {
    $id=(int)$company['id'];
    $base=csrf_field().'<input type="hidden" name="company_id" value="'.$id.'">';
    echo '<details class="card admin-toolbox" open><summary>Budget, Registrierung & Lieferung · '.e($company['legal_name']).'</summary><div class="admin-toolbox-body"><p>Ein aktiviertes Budget von 0 € sperrt neue Arbeitgeberausgaben. Deaktiviert bedeutet unbegrenzt. Offene und freigegebene Bestellungen zählen.</p><form method="post" class="form-stack">'.$base.'<input type="hidden" name="action" value="yw_company">';
    echo '<label class="check"><input type="checkbox" name="budget_enabled" '.((int)$company['budget_enabled']?'checked':'').'>Geldbudget aktiv</label>';
    echo '<label>Firmenanteil pro Mitarbeiter / Budgetjahr (€)<input type="number" min="0" max="9999999" step="0.01" name="annual_budget" value="'.e(number_format((int)$company['annual_budget_company_cents']/100,2,'.','')).'" required></label>';
    echo '<label>Startmonat des Budgetjahres<select name="budget_reset_month">';
    foreach([1=>'Januar',2=>'Februar',3=>'März',4=>'April',5=>'Mai',6=>'Juni',7=>'Juli',8=>'August',9=>'September',10=>'Oktober',11=>'November',12=>'Dezember'] as $n=>$label) echo '<option value="'.$n.'" '.($n===(int)$company['budget_reset_month']?'selected':'').'>'.$label.'</option>';
    echo '</select></label><p class="muted">Eine Änderung des Startmonats verändert, welche vorhandenen Bestellungen in den Zeitraum fallen.</p><label>Registrierung<select name="registration_mode">';
    foreach(['open'=>'Offen','approval'=>'Freigabe erforderlich','pin'=>'Firmen-PIN','pin_approval'=>'Firmen-PIN plus Freigabe'] as $key=>$label) echo '<option value="'.$key.'" '.($company['registration_mode']===$key?'selected':'').'>'.$label.'</option>';
    echo '</select></label><label>Neue Firmen-PIN (leer = unverändert)<input type="password" name="registration_pin" minlength="6" maxlength="72" autocomplete="new-password"></label><label>Lieferart<select name="delivery_mode"><option value="company">Firmenlieferung</option><option value="pickup" '.($company['delivery_mode']==='pickup'?'selected':'').'>Abholung bei Yanik Textildruck</option></select></label><label>Firmenlieferadresse<textarea name="delivery_address" maxlength="1000">'.e($company['delivery_address']??'').'</textarea></label><button class="btn primary">Firmenregeln speichern</button></form></div></details>';
    // Großkunden: seitenweise mit Suche; Personen mit eigenem Budget zuerst.
    $list=yw_people_list($id,'budget_override_cents IS NULL,name,id');$employees=$list['rows'];
    echo '<details class="card admin-toolbox"'.(isset($_GET['suche'])||isset($_GET['seite'])?' open':'').'><summary>Individuelle Mitarbeiterbudgets</summary><div class="admin-toolbox-body"><p>Leer = Firmenstandard; 0 = kein Arbeitgeberbudget. Wirkt nur bei aktiviertem Firmenbudget.</p>';
    echo yw_people_list_nav($list,'/admin',['tab'=>'companies','company'=>$id,'bereich'=>'regeln'],['Person suchen','Name, E-Mail, Abteilung, Kostenstelle, Standort oder Personalnummer','Suchen','Zurück','Weiter','Seite '.$list['page'].' von '.$list['pages'],$list['total'].' Treffer','Seiten der Personenliste']);
    if(!$employees)echo '<div class="empty compact">'.($list['search']!==''?'Keine Person gefunden.':'Noch keine Mitarbeiterkonten vorhanden.').'</div>';
    foreach($employees as $p) echo '<form method="post" class="item">'.$base.'<input type="hidden" name="action" value="yw_employee"><input type="hidden" name="account_id" value="'.(int)$p['id'].'"><label>'.e($p['name']).'<input name="budget_override" type="number" min="0" max="9999999" step="0.01" value="'.($p['budget_override_cents']===null?'':e(number_format((int)$p['budget_override_cents']/100,2,'.',''))).'"></label><button class="btn">Speichern</button></form>';
    echo '</div></details><details class="card admin-toolbox"><summary>Stückzahlgrenzen je Firmenprodukt</summary><div class="admin-toolbox-body"><p>Leer = unbegrenzt; 0 = keine weitere Bestellung. Gilt je Mitarbeiter und Budgetjahr.</p>';
    $q=db()->prepare('SELECT cp.product_id,cp.yearly_limit_qty,COALESCE(NULLIF(cp.custom_name,""),p.name) name FROM company_products cp JOIN products p ON p.id=cp.product_id WHERE cp.company_id=?'); $q->execute([$id]);
    $limitedProducts=$q->fetchAll();
    if(!$limitedProducts)echo '<div class="empty compact">Dieser Firma sind noch keine Produkte zugeordnet.</div>';
    foreach($limitedProducts as $p) echo '<form method="post" class="item">'.$base.'<input type="hidden" name="action" value="yw_limit"><input type="hidden" name="product_id" value="'.(int)$p['product_id'].'"><label>'.e($p['name']).'<input name="yearly_limit_qty" type="number" min="0" max="100000" value="'.e($p['yearly_limit_qty']??'').'"></label><button class="btn">Speichern</button></form>';
    echo '</div></details>';
    echo '<details class="card admin-toolbox"><summary>Neues Ausstattungsprofil anlegen</summary><div class="admin-toolbox-body"><p>Profile begrenzen das sichtbare Sortiment nach Tätigkeit. Die Startermenge ist eine Empfehlung; die Sollmenge ist zugleich das verbindliche Limit pro Budgetjahr.</p><form method="post" class="form-stack">'.$base.'<input type="hidden" name="action" value="yw_profile_create"><label>Neues Profil<input name="profile_name" maxlength="120" placeholder="z. B. Montage" required></label><label>Beschreibung<textarea name="profile_description" maxlength="500" placeholder="Einsatzbereich und Hinweise"></textarea></label><label class="check"><input type="checkbox" name="is_default"><span>Standardprofil für neue Registrierungen</span></label><button class="btn primary">Profil anlegen</button></form></div></details>';
    $profiles=yw_equipment_profiles($id,false);$q=db()->prepare('SELECT cp.product_id,COALESCE(NULLIF(cp.custom_name,""),p.name) name FROM company_products cp JOIN products p ON p.id=cp.product_id WHERE cp.company_id=? ORDER BY name');$q->execute([$id]);$companyProducts=$q->fetchAll();
    foreach($profiles as $profile){
        $q=db()->prepare('SELECT product_id,starter_qty,yearly_target_qty FROM yw_equipment_profile_products WHERE profile_id=?');$q->execute([$profile['id']]);$assigned=[];foreach($q as $row)$assigned[(int)$row['product_id']]=$row;
        echo '<details class="card admin-toolbox"><summary>'.e($profile['name']).' · '.(int)$profile['product_count'].' Produkte · '.((int)$profile['active']?'aktiv':'deaktiviert').((int)$profile['is_default']?' · Standardprofil':'').'</summary><div class="admin-toolbox-body">';
        echo '<form method="post" class="form-stack">'.$base.'<input type="hidden" name="action" value="yw_profile_save"><input type="hidden" name="profile_id" value="'.(int)$profile['id'].'"><label>Name<input name="profile_name" maxlength="120" value="'.e($profile['name']).'" required></label><label>Beschreibung<textarea name="profile_description" maxlength="500">'.e($profile['description']??'').'</textarea></label><label class="check"><input type="checkbox" name="active" '.((int)$profile['active']?'checked':'').'><span>Profil aktiv</span></label><label class="check"><input type="checkbox" name="is_default" '.((int)$profile['is_default']?'checked':'').'><span>Standard für neue Registrierungen</span></label><button class="btn">Profileinstellungen speichern</button></form><hr>';
        echo '<form method="post" class="form-stack">'.$base.'<input type="hidden" name="action" value="yw_profile_products"><input type="hidden" name="profile_id" value="'.(int)$profile['id'].'"><div class="table-wrap"><table class="table"><thead><tr><th>Freigabe</th><th>Firmenprodukt</th><th>Startermenge</th><th>Soll / Limit pro Jahr</th></tr></thead><tbody>';
        foreach($companyProducts as $product){$productId=(int)$product['product_id'];$rule=$assigned[$productId]??null;echo '<tr><td><input type="checkbox" name="profile_products[]" value="'.$productId.'" '.($rule?'checked':'').'></td><td>'.e($product['name']).'</td><td><input type="number" name="starter_qty['.$productId.']" min="0" max="1000" value="'.(int)($rule['starter_qty']??0).'" aria-label="Startermenge '.e($product['name']).'"></td><td><input type="number" name="target_qty['.$productId.']" min="0" max="100000" value="'.e($rule['yearly_target_qty']??'').'" placeholder="leer = global" aria-label="Sollmenge '.e($product['name']).'"></td></tr>';}
        echo '</tbody></table></div><button class="btn primary">Produktsortiment speichern</button></form></div></details>';
    }
}


function yw_production_statuses(): array {
    return ['new'=>'Neu','accepted'=>'Angenommen','goods_ordered'=>'Material offen / bestellt','goods_received'=>'Material eingetroffen','processing'=>'In Bearbeitung (Bestand)','production'=>'In Produktion','quality_check'=>'Qualitätskontrolle','ready'=>'Fertig','shipped'=>'Versendet','picked_up'=>'Abgeholt','completed'=>'Abgeschlossen'];
}

function yw_production(): void {
    $companyId=(int)($_GET['company_id']??0);
    $status=(string)($_GET['status']??'');
    if($status!=='' && !array_key_exists($status,yw_production_statuses())) $status='';
    $params=[]; $sql="SELECT o.*,c.legal_name,COALESCE(ea.name,i.employee_name,'Unbekannt') employee_name FROM orders o JOIN companies c ON c.id=o.company_id LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id LEFT JOIN invites i ON i.id=o.invite_id WHERE o.status='approved'";
    if($companyId){$sql.=' AND o.company_id=?';$params[]=$companyId;}
    if($status!==''){$sql.=' AND o.fulfillment_status=?';$params[]=$status;}
    else $sql.=" AND o.fulfillment_status NOT IN ('shipped','picked_up','completed')";
    $sql.=' ORDER BY o.id DESC LIMIT 100';
    $q=db()->prepare($sql);$q->execute($params);$orders=$q->fetchAll();
    echo '<section class="card"><h2>Produktionsarbeitsplatz</h2><p>Bis zu 100 freigegebene Aufträge. Materialübersicht und Packlisten beziehen sich nur auf diese Auswahl; keine automatische Lieferantenbestellung.</p><form method="get"><input type="hidden" name="tab" value="production"><label>Firma<select name="company_id"><option value="0">Alle Firmen</option>';
    foreach(db()->query('SELECT id,legal_name FROM companies ORDER BY legal_name') as $c) echo '<option value="'.(int)$c['id'].'" '.($companyId===(int)$c['id']?'selected':'').'>'.e($c['legal_name']).'</option>';
    echo '</select></label><label>Status<select name="status"><option value="">Offene Produktion</option>';
    foreach(yw_production_statuses() as $key=>$label) echo '<option value="'.$key.'" '.($status===$key?'selected':'').'>'.$label.'</option>';
    echo '</select></label><button class="btn">Filtern</button></form><div class="actions space-top"><a class="btn small" href="/admin/beschaffung.csv">Offenen Beschaffungsbedarf als CSV</a><a class="btn small" href="/admin/export.csv">Arbeits-CSV aller Aufträge</a></div><p class="muted">Beide Exporte sind Arbeitslisten und keine Lexware-Importformate.</p></section>';
    $material=[];$decorationNeeds=[];$details=[];$productMeta=[];$motifStock=[];
    try{foreach(db()->query('SELECT id,sku,brand,supplier_name,color FROM products') as $p)$productMeta[(int)$p['id']]=$p;}catch(Throwable $e){}
    try{foreach(db()->query('SELECT id,company_id,name,method,track_stock,stock_qty,reorder_level,active FROM yw_decoration_motifs') as $motif)$motifStock[(int)$motif['id']]=$motif;}catch(Throwable $e){}
    $details=order_items_grouped(array_column($orders,'id'));
    foreach($orders as $o){
        $details[$o['id']]=$details[(int)$o['id']]??[];
        foreach($details[$o['id']] as $i){
            $snap=json_decode($i['decoration_snapshot_json']??'',true)?:[];$meta=$productMeta[(int)$i['product_id']]??[];
            if(in_array((string)$o['fulfillment_status'],['new','accepted'],true)){
                $signature=hash('sha256',json_encode([$i['product_id'],$i['size']]));
                if(!isset($material[$signature]))$material[$signature]=['name'=>$i['product_name'],'size'=>$i['size'],'color'=>$snap['color']??($meta['color']??'unbekannt'),'supplier'=>$meta['supplier_name']??'','sku'=>$meta['sku']??'','companies'=>[],'qty'=>0];
                $material[$signature]['qty']+=(int)$i['quantity'];
                $material[$signature]['companies'][(string)$o['legal_name']]=true;
            }
            if(in_array((string)$o['fulfillment_status'],['ready','shipped','picked_up','completed'],true))continue;
            $decorations=is_array($snap['decorations']??null)?$snap['decorations']:[];
            if(!$decorations && !empty($snap['base_decoration_type']) && (string)$snap['base_decoration_type']!=='none'){
                $decorations=[['position'=>'other','method'=>(string)$snap['base_decoration_type'],'text'=>(string)($snap['base_decoration_details']??'Veredelung gemäß Auftrag')]];
            }
            foreach($decorations as $decoration){
                if(!is_array($decoration))continue;
                $position=(string)($decoration['position']??'other');$method=(string)($decoration['method']??'other');$motif=trim((string)($decoration['text']??''));$motifId=(int)($decoration['motif_id']??0);
                $resource=($motifId>0&&isset($motifStock[$motifId])&&(int)$motifStock[$motifId]['company_id']===(int)$o['company_id'])?$motifStock[$motifId]:null;
                $decorationKey=$resource?'motif:'.$motifId:hash('sha256',json_encode([(int)$o['company_id'],$position,$method,$motif],JSON_UNESCAPED_UNICODE));
                if(!isset($decorationNeeds[$decorationKey]))$decorationNeeds[$decorationKey]=['company'=>(string)$o['legal_name'],'positions'=>[],'method'=>$resource?(string)$resource['method']:$method,'motif'=>$resource?(string)$resource['name']:$motif,'motif_id'=>$resource?$motifId:0,'track_stock'=>$resource?(int)$resource['track_stock']:0,'stock_qty'=>$resource?(int)$resource['stock_qty']:0,'reorder_level'=>$resource?(int)$resource['reorder_level']:0,'active'=>$resource?(int)$resource['active']:0,'qty'=>0,'orders'=>[]];
                $decorationNeeds[$decorationKey]['positions'][$position]=true;
                $decorationNeeds[$decorationKey]['qty']+=(int)$i['quantity'];
                $decorationNeeds[$decorationKey]['orders'][(int)$o['id']]=true;
            }
        }
    }
    echo '<section class="card"><h2>Noch zu beschaffen</h2><p>Nur freigegebene Aufträge mit Status „Neu“ oder „Angenommen“. Bereits als bestellt markiertes Material wird bewusst nicht erneut vorgeschlagen.</p><div class="table-wrap"><table class="table"><thead><tr><th>Lieferant</th><th>Artikel / SKU</th><th>Farbe</th><th>Größe</th><th>Menge</th><th>Firmen</th></tr></thead><tbody>';
    foreach($material as $m) echo '<tr><td>'.e($m['supplier']?:'Nicht hinterlegt').'</td><td><strong>'.e($m['name']).'</strong><br><span class="muted">'.e($m['sku']?:'ohne SKU').'</span></td><td>'.e($m['color']).'</td><td>'.e($m['size']).'</td><td><strong>'.$m['qty'].'</strong></td><td>'.e(implode(', ',array_keys($m['companies']))).'</td></tr>';
    echo '</tbody></table></div>';
    if(!$material) echo '<p>In dieser Auswahl ist kein neuer Beschaffungsbedarf offen.</p>';
    echo '</section>';
    echo '<section class="card"><h2>Veredelungsbedarf</h2><p>Drucke, Transfers und Stickpositionen aus den gespeicherten Auftragsvorgaben. Damit kannst du vorhandene Motive prüfen, ohne einen Textil-Wareneingang zu führen.</p>';
    if(!$decorationNeeds)echo '<div class="empty compact">In dieser Auswahl sind keine strukturierten Veredelungen hinterlegt.</div>';
    else{
        echo '<div class="table-wrap"><table class="table"><thead><tr><th>Firma</th><th>Verfahren</th><th>Position / Motiv</th><th>Bestand</th><th>Bedarf</th><th>Prüfung</th><th>Aufträge</th></tr></thead><tbody>';
        foreach($decorationNeeds as $need){
            $positions=array_map('decoration_position_label',array_keys($need['positions']));
            $stock='–';$statusText='Produktionsvorgabe prüfen';$statusClass='pending';
            if((int)$need['motif_id']>0 && (int)$need['track_stock']===1){
                $stock=(string)(int)$need['stock_qty'];$remaining=(int)$need['stock_qty']-(int)$need['qty'];
                if($remaining<0){$statusText=abs($remaining).' Stück fehlen';$statusClass='rejected';}
                elseif($remaining<(int)$need['reorder_level']){$statusText='Ausreichend, danach unter Mindestbestand';$statusClass='pending';}
                else{$statusText='Ausreichend';$statusClass='approved';}
                if(!(int)$need['active']){$statusText.=' · Motiv inaktiv';$statusClass='pending';}
            }elseif((int)$need['motif_id']>0){$statusText='Motiv hinterlegt · keine Mengenprüfung';$statusClass='approved';}
            elseif(in_array((string)$need['method'],['print','dtf','flex'],true)){$statusText='Nicht mit Motivbestand verknüpft';$statusClass='pending';}
            echo '<tr><td>'.e($need['company']).'</td><td>'.e(decoration_method_label((string)$need['method'])).'</td><td><strong>'.e(implode(', ',$positions)).'</strong><br><span class="muted">'.e($need['motif']!==''?$need['motif']:'ohne Motivbezeichnung').'</span></td><td><strong>'.e($stock).'</strong></td><td><strong>'.(int)$need['qty'].'</strong></td><td><span class="badge '.e($statusClass).'">'.e($statusText).'</span></td><td>#'.e(implode(', #',array_keys($need['orders']))).'</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</section>';
    foreach($orders as $o){
        $delivery=json_decode($o['delivery_snapshot_json']??'',true)?:[];
        $statusLabel=yw_production_statuses()[(string)$o['fulfillment_status']]??(string)$o['fulfillment_status'];
        echo '<details class="card admin-toolbox production-order"><summary>#'.(int)$o['id'].' · '.e($o['legal_name']).' · '.e($statusLabel).'</summary><div class="admin-toolbox-body"><p><strong>Packliste für '.e($delivery['employee']??$o['employee_name']??'Bestandsauftrag').'</strong></p><p>'.nl2br(e($delivery['address']??'Keine gespeicherte Lieferadresse — vor Versand klären.')).'</p>';
        foreach($details[$o['id']] as $i){
            $snap=json_decode($i['decoration_snapshot_json']??'',true);
            echo '<article class="item"><div><strong>'.(int)$i['quantity'].' × '.e($i['product_name']).' · '.e($i['size']).'</strong>';
            if(!$snap) echo '<p>Bestandsauftrag ohne Veredelungs-Snapshot. Produktionsdetails vor Fertigung prüfen.</p>';
            else echo '<details><summary>Gespeicherte Produktionsvorgaben</summary><pre style="white-space:pre-wrap">'.e(json_encode($snap,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)).'</pre></details>';
            echo '</div></article>';
        }
        echo '<p>Bestellhinweis: '.nl2br(e($o['note'])).'</p><form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="yw_production"><input type="hidden" name="company_id" value="'.(int)$o['company_id'].'"><input type="hidden" name="order_id" value="'.(int)$o['id'].'"><label>Status<select name="production_status">';
        foreach(yw_production_statuses() as $key=>$label) echo '<option value="'.$key.'" '.($o['fulfillment_status']===$key?'selected':'').'>'.$label.'</option>';
        echo '</select></label><label>Interne Produktionsnotiz<textarea name="production_note" maxlength="4000">'.e($o['production_note']??'').'</textarea></label><button class="btn primary">Speichern</button></form></div></details>';
    }
}

/**
 * Prüft von außen, ob interne Dateien wirklich gesperrt sind. Der Schutz hängt an der
 * .htaccess (Apache); bei nginx oder abgeschaltetem AllowOverride wären Programmordner,
 * Lieferantenpreise und Fehlerprotokoll sonst öffentlich. Ergebnis wird 1 Stunde gemerkt.
 * Rückgabe: [true|false|null, Hinweis] – null heißt „konnte nicht geprüft werden“.
 */
function yw_private_exposure_check(bool $force=false): array {
    $cached=json_decode(current_site_setting('exposure_check',''),true);
    if(!$force && is_array($cached) && ($cached['at']??0)>time()-3600)return [$cached['ok'],(string)$cached['hint']];
    $result=[null,'Prüfung nicht möglich (PHP-Erweiterung curl fehlt)'];
    if(function_exists('curl_init')){
        try{
            $token=bin2hex(random_bytes(16));
            $probe=yw_private_dir().'/sperrtest.txt';
            file_put_contents($probe,$token);
            $secure=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || (($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https');
            $host=(string)($_SERVER['HTTP_HOST']??yw_public_host());
            $base=($secure?'https://':'http://').$host;
            // Zusätzlich: Sperre in uploads/ (Datenlisten) und Schutz-Header für SVG-Logos, die direkt aufgerufen werden können
            $uploads=dirname(__DIR__,2).'/uploads';$name='sperrtest-'.$token;
            $uploadProbes=is_dir($uploads)&&is_writable($uploads)&&@file_put_contents($uploads.'/'.$name.'.json',$token)!==false&&@file_put_contents($uploads.'/'.$name.'.svg','<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>')!==false;
            $targets=['/app/private/sperrtest.txt'=>$token,'/app/data/katalog-daiber.json'=>'"articles"','/config.example.php'=>'db_pass','/AENDERUNGEN-V10.md'=>'WorkwearCenter','/uploads/.htaccess'=>'FilesMatch','/.htaccess'=>'RewriteRule'];
            if($uploadProbes)$targets['/uploads/'.$name.'.json']=$token;
            $open=[];$reached=0;$svgUnprotected=false;
            foreach($targets+($uploadProbes?['/uploads/'.$name.'.svg'=>null]:[]) as $path=>$marker){
                $ch=curl_init($base.$path);
                curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_TIMEOUT=>4,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_RANGE=>'0-65535']);
                $body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
                if($body===false||$code===0)continue;
                $reached++;
                if($marker===null){if($code>=200&&$code<300&&!preg_match("/^Content-Security-Policy:[^\r\n]*default-src 'none'/mi",(string)$body))$svgUnprotected=true;continue;}
                if($code>=200&&$code<300&&str_contains((string)$body,$marker))$open[]=$path;
            }
            @unlink($probe);
            if($uploadProbes){@unlink($uploads.'/'.$name.'.json');@unlink($uploads.'/'.$name.'.svg');}
            if($open)$result=[false,'Öffentlich abrufbar: '.implode(', ',$open).' – der Webserver beachtet die .htaccess nicht. Beim Hoster AllowOverride aktivieren oder (nginx) die Sperren aus der Installationsanleitung eintragen.'];
            elseif($svgUnprotected)$result=[false,'SVG-Dateien in uploads/ werden ohne Content-Security-Policy ausgeliefert (mod_headers fehlt oder nginx). Header aus uploads/.htaccess bzw. der Installationsanleitung beim Hoster eintragen.'];
            elseif($reached>0)$result=[true,''];
            else $result=[null,'Die eigene Adresse war vom Server aus nicht erreichbar – bitte die Prüfung später erneut starten.'];
        }catch(Throwable $e){$result=[null,'Prüfung fehlgeschlagen: '.$e->getMessage()];}
    }
    set_site_setting('exposure_check',json_encode(['at'=>time(),'ok'=>$result[0],'hint'=>$result[1]]));
    return $result;
}

function yw_system(): void {
    $age=yw_backup_age_days();
    echo '<section class="card"><div class="title-row"><div><h2>Datensicherung</h2><p class="muted">Lädt Datenbank und hochgeladene Dateien als ZIP herunter. Bitte wöchentlich und vor jedem Update, und die Datei außerhalb des Webspace aufbewahren.</p></div><a class="btn primary" href="/admin/sicherung">Sicherung herunterladen</a></div><p>'.($age===null?'Noch keine Sicherung heruntergeladen.':'Letzte Sicherung: vor '.$age.' Tag'.($age===1?'':'en').' ('.e(current_site_setting('last_backup_at','')).').').'</p></section>';
    echo yw_retention_card(); // 10.15: Aufbewahrungsfristen und letzte automatische Bereinigung
    $mail=['pending'=>0,'failed'=>0];$failedMails=[];
    try{
        foreach(db()->query("SELECT status,COUNT(*) amount FROM yw_mail_queue WHERE status IN ('pending','failed') GROUP BY status") as $r)$mail[$r['status']]=(int)$r['amount'];
        $failedMails=db()->query("SELECT id,to_email,subject,attempts,last_error,created_at FROM yw_mail_queue WHERE status='failed' ORDER BY id DESC LIMIT 10")->fetchAll();
    }catch(Throwable $e){}
    $missingSupplierData=null;
    $duplicateSkus=null;
    try{
        $missingSupplierData=(int)db()->query("SELECT COUNT(*) FROM products WHERE active=1 AND (TRIM(COALESCE(sku,''))='' OR TRIM(COALESCE(supplier_name,''))='')")->fetchColumn();
        $duplicateSkus=(int)db()->query("SELECT COUNT(*) FROM (SELECT LOWER(TRIM(sku)) normalized_sku FROM products WHERE TRIM(COALESCE(sku,''))<>'' GROUP BY LOWER(TRIM(sku)) HAVING COUNT(*)>1) duplicates")->fetchColumn();
    }catch(Throwable $e){}
    $transport=(string)(cfg('mail_transport')?:'mail');
    $mailIssues=yw_mail_config_issues();
    $mailConfigured=$mailIssues===[];
    [$exposureOk,$exposureHint]=yw_private_exposure_check(isset($_GET['pruefen']));
    $cronLast=current_site_setting('cron_last_run','');
    $cronOk=$cronLast!==''&&strtotime($cronLast)>time()-3600;
    $adminsWithout2fa=null;
    try{$adminsWithout2fa=(int)db()->query('SELECT COUNT(*) FROM admins WHERE active=1 AND totp_enabled=0')->fetchColumn();}catch(Throwable $e){}
    $imprintMissing=[];
    foreach(['imprint_vat_id'=>'USt-IdNr.','imprint_email'=>'E-Mail'] as $key=>$label)if(trim(current_site_setting($key,''))==='')$imprintMissing[]=$label;
    $integrity=yw_file_integrity();
    $outdated=$integrity?array_merge($integrity['stale'],$integrity['missing']):[];
    $integrityHint=$integrity===null?'Dateiliste app/data/dateien.json fehlt – Paket vollständig hochladen':'Nicht aktuell oder fehlend: '.implode(', ',array_slice($outdated,0,10)).(count($outdated)>10?' … (+'.(count($outdated)-10).')':'').' – per FTP erneut hochladen, vorhandene Dateien immer überschreiben';
    $checks=[
        ['Interne Dateien gesperrt',$exposureOk===true,$exposureHint.' (Erneut prüfen: Systemstatus mit ?pruefen=1 aufrufen)'],
        ['Cronjob läuft',$cronOk,$cronLast===''?'cron.php ist noch nie gelaufen – beim Hoster alle 5 Minuten einrichten':'Letzter Lauf '.date('d.m.Y H:i',(int)strtotime($cronLast)).' – Cronjob beim Hoster prüfen'],
        ['Datensicherung aktuell',$age!==null&&$age<=7,'Sicherung herunterladen (älter als 7 Tage oder noch nie)'],
        ['Zwei-Faktor für alle Admins',$adminsWithout2fa===0,$adminsWithout2fa===null?'Datenbankschema aktualisieren':$adminsWithout2fa.' aktive Administrator(en) ohne Zwei-Faktor-Anmeldung'],
        ['Impressum vollständig',$imprintMissing===[],'Unter Website → Inhalte ergänzen: '.implode(', ',$imprintMissing)],
        ['Programmdateien vollständig hochgeladen',$integrity!==null&&$outdated===[],$integrityHint],
        ['Datenbankschema '.app_version(),yw_schema_ready(),'Update-Assistent ausführen'],
        ['PHP-Version',PHP_VERSION_ID>=80100,'Mindestens PHP 8.1 beim Hoster aktivieren'],
        ['HTTPS-Verbindung',(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || (($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https'),'SSL/HTTPS beim Hoster aktivieren'],
        ['Mailtransport',$mailConfigured && $mail['failed']===0,$mailIssues?implode(' · ',$mailIssues):'Fehlgeschlagene E-Mails prüfen'],
        ['Datenschutztext',mb_strlen(strip_tags(current_site_setting('privacy_body','')))>250,'Geprüften Datenschutztext unter Inhalte hinterlegen'],
        ['Wartungsmodus aus',!maintenance_enabled(),'Wartungsmodus unter Design deaktivieren'],
        ['Beschaffungsdaten',$missingSupplierData===0,$missingSupplierData===null?'Datenbankschema aktualisieren':$missingSupplierData.' aktive Produkte ohne Lieferant oder Artikelnummer ergänzen'],
        ['Übergabe an Auftragsverwaltung',yw_handover_config()['mode']!=='off' && !yw_handover_config_issues(),yw_handover_config()['mode']==='off'?'Noch nicht verbunden – Aufträge werden gesammelt (siehe „Übergabe“)':implode(' · ',yw_handover_config_issues())],
        ['Eindeutige Artikelnummern',$duplicateSkus===0,$duplicateSkus===null?'Datenbankschema aktualisieren':$duplicateSkus.' mehrfach vergebene Artikelnummern im Produktstamm bereinigen'],
    ];
    $ready=count(array_filter($checks,fn($c)=>$c[1]));
    echo '<section class="card"><div class="title-row"><div><h2>Betriebsprüfung · Version '.e(app_version()).'</h2><p class="muted">'.$ready.' von '.count($checks).' Betriebsprüfungen bestanden.</p></div><span class="badge '.($ready===count($checks)?'approved':'pending').'">'.($ready===count($checks)?'Betriebsbereit':'Prüfung nötig').'</span></div><div class="release-checks">';
    foreach($checks as [$label,$ok,$hint]) echo '<div class="release-check '.($ok?'ok':'warn').'"><strong>'.($ok?'✓':'!').' '.e($label).'</strong><span>'.e($ok?'Erfüllt':$hint).'</span></div>';
    echo '</div><hr><p><strong>Domain:</strong> workwearcenter.de · <strong>PHP:</strong> '.e(PHP_VERSION).' · <strong>Abrechnung:</strong> Lexware Office '.(yw_lexware_enabled()?'per API (Rechnungsentwürfe)':'derzeit manuell').'</p><p><strong>Mailtransport:</strong> '.e(strtoupper($transport)).' · <strong>Warteschlange:</strong> '.$mail['pending'].' wartend · '.$mail['failed'].' fehlgeschlagen</p><p class="muted">Für einen zuverlässigen Versand kann der Hoster <code>cron.php</code> alle fünf Minuten per PHP-CLI ausführen. Der normale Seitenaufruf verarbeitet weiterhin kleine Mengen als Rückfallebene.</p><div class="actions"><form method="post">'.csrf_field().'<input type="hidden" name="action" value="yw_mail_test"><button class="btn primary">Testmail senden</button></form><form method="post">'.csrf_field().'<input type="hidden" name="action" value="yw_mail_process"><button class="btn">Fehlgeschlagene E-Mails erneut senden</button></form><a class="btn" href="/update.php">Update-Assistent</a></div>';
    if($failedMails){
        echo '<hr><h3>Letzte fehlgeschlagene E-Mails</h3><div class="table-wrap"><table class="table"><thead><tr><th>Empfänger</th><th>Betreff</th><th>Versuche</th><th>Fehler</th></tr></thead><tbody>';
        foreach($failedMails as $failed) echo '<tr><td>'.e($failed['to_email']).'</td><td>'.e($failed['subject']).'</td><td>'.(int)$failed['attempts'].'</td><td>'.e($failed['last_error']?:'Keine Angabe').'</td></tr>';
        echo '</tbody></table></div>';
    }
    echo '<hr><h3>Schnittstellenstatus</h3><div class="release-checks"><div class="release-check ok"><strong>✓ Beschaffungsexport</strong><span>L-Shop-relevante Artikelnummern und Mengen stehen als CSV bereit.</span></div><div class="release-check warn"><strong>! L-Shop-Automatik nicht aktiv</strong><span>Erst nach geprüfter API-Dokumentation und Zugangsdaten freischalten; es wird nichts automatisch bestellt.</span></div>'.(static function(): string {[$l,$h,$ok]=yw_lexware_status_line();return '<div class="release-check '.($ok?'ok':'warn').'"><strong>'.e($l).'</strong><span>'.e($h).'</span></div>';})().'<div class="release-check ok"><strong>✓ Keine Textillagerpflicht</strong><span>Beschaffung bleibt auftragsbezogen; nur Veredelungsmotive können als Bestand geprüft werden.</span></div></div></section>';
}

function export_orders_csv(): void {
    require_admin();
    $sql="SELECT o.id,c.legal_name,COALESCE(ea.name,iv.employee_name,'Unbekannt') employee_name,ea.department,ea.cost_center,o.fulfillment_status,o.created_at,i.product_name,i.size,i.quantity,i.unit_price_cents,i.unit_net_cents,i.unit_gross_cents,i.company_share_cents,i.employee_share_cents,i.line_net_cents,i.line_gross_cents,i.line_employee_cents,i.line_company_cents,o.shipping_gross_cents FROM orders o JOIN companies c ON c.id=o.company_id LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id LEFT JOIN invites iv ON iv.id=o.invite_id JOIN order_items i ON i.order_id=o.id WHERE o.status='approved' ORDER BY o.id,i.id";
    header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="yanik-workwear-arbeitsliste.csv"');
    $out=fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF");
    // 10.11: Beträge je Position (Rundung je Position wie in der Rechnung); Stückpreise nur zur Information.
    fputcsv($out,['Auftrag','Firma','Mitarbeiter','Abteilung','Kostenstelle','Produktion','Datum','Artikel','Größe','Menge','Einzel netto EUR','Einzel brutto EUR','Position netto EUR','Position brutto EUR','Firmenanteil Position EUR','Mitarbeiteranteil Position EUR','Versand brutto EUR (nur erste Position)'],';','"','');
    $last=null;$eur=static fn(int $cents): string => number_format($cents/100,2,',','');
    @set_time_limit(300);
    yw_each_row($sql,[],function(array $row) use($out,&$last,$eur): void { // Großkunden: ungepuffert streamen
        $amounts=yw_order_item_amounts($row);
        $values=[$row['id'],$row['legal_name'],$row['employee_name'],$row['department'],$row['cost_center'],$row['fulfillment_status'],$row['created_at'],$row['product_name'],$row['size'],$row['quantity'],$eur((int)$row['unit_net_cents']),$eur((int)$row['unit_gross_cents']),$eur($amounts['net']),$eur($amounts['gross']),$eur($amounts['company']),$eur($amounts['employee']),$last===$row['id']?'':$eur((int)$row['shipping_gross_cents'])];
        $last=$row['id'];
        fputcsv($out,array_map('yw_csv_safe',$values),';','"','');
    });
    fclose($out);
}

function export_procurement_csv(): void {
    require_admin();
    $sql="SELECT o.id order_id,c.legal_name,i.product_id,i.product_name,i.size,i.quantity,i.decoration_snapshot_json,p.sku,p.supplier_name,p.brand,p.color
        FROM orders o
        JOIN companies c ON c.id=o.company_id
        JOIN order_items i ON i.order_id=o.id
        LEFT JOIN products p ON p.id=i.product_id
        WHERE o.status='approved' AND o.fulfillment_status IN ('new','accepted')
        ORDER BY p.supplier_name,p.sku,i.product_name,i.size,o.id";
    $grouped=[];
    foreach(db()->query($sql) as $row){
        $snapshot=json_decode((string)($row['decoration_snapshot_json']??''),true)?:[];
        $sku=trim((string)($snapshot['sku']??$row['sku']??''));
        $color=trim((string)($snapshot['color']??$row['color']??''));
        $supplier=trim((string)($row['supplier_name']??''));
        $key=hash('sha256',json_encode([$supplier,$sku,$row['product_name'],$color,$row['size']],JSON_UNESCAPED_UNICODE));
        if(!isset($grouped[$key]))$grouped[$key]=['supplier'=>$supplier,'sku'=>$sku,'brand'=>(string)($row['brand']??''),'product'=>(string)$row['product_name'],'color'=>$color,'size'=>(string)$row['size'],'quantity'=>0,'orders'=>[],'companies'=>[]];
        $grouped[$key]['quantity']+=(int)$row['quantity'];
        $grouped[$key]['orders'][(int)$row['order_id']]=true;
        $grouped[$key]['companies'][(string)$row['legal_name']]=true;
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="workwearcenter-beschaffung-'.date('Y-m-d').'.csv"');
    $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");
    fputcsv($out,['Lieferant','Artikelnummer / SKU','Marke','Artikel','Farbe','Größe','Menge','Aufträge','Firmen'],';','"','');
    foreach($grouped as $row){
        $values=[$row['supplier'],$row['sku'],$row['brand'],$row['product'],$row['color'],$row['size'],(string)$row['quantity'],'#'.implode(', #',array_keys($row['orders'])),implode(', ',array_keys($row['companies']))];
        fputcsv($out,array_map('yw_csv_safe',$values),';','"','');
    }
    fclose($out);exit;
}
