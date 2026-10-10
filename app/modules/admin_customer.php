<?php
declare(strict_types=1);

/*
 * 10.7: Kundenakte – alles zu einer Firma an einem Ort, aufgeteilt in Reiter.
 * Ersetzt die frühere Mammut-Seite und den eigenen Menüpunkt „Ausstattung & Budgets“.
 * Die Inhalte der Abschnitte sind unverändert übernommen; nur die Anordnung ist neu.
 */

/** Reiter der Kundenakte: Schlüssel => [Beschriftung, Kurzbeschreibung]. */
function yw_customer_tabs(): array {
    return [
        'uebersicht'=>['Übersicht','Status, Einrichtung und Notizen'],
        'kollektion'=>['Kollektion & Design','Artikel, Logo, Farben und Veredelungsmotive'],
        'mitarbeiter'=>['Mitarbeiter','Personen, Rollen und Registrierungen'],
        'regeln'=>['Regeln & Budget','Finanzierung, Budgets, Limits und Ausstattungsprofile'],
        'auftraege'=>['Aufträge','Bestellungen dieser Firma'],
        'zugaenge'=>['Zugänge & Links','Shop-, Registrierungs- und Freigabe-Links'],
    ];
}

function yw_customer_tab(): string {
    $t=(string)($_GET['bereich'] ?? 'uebersicht');
    return array_key_exists($t,yw_customer_tabs()) ? $t : 'uebersicht';
}

function yw_customer_url(int $companyId,string $tab='uebersicht'): string {
    return '/admin?tab=companies&company='.$companyId.($tab==='uebersicht'?'':'&bereich='.$tab);
}

/**
 * Ziel nach dem Speichern in der Kundenakte: derselbe Reiter, von dem das Formular kam
 * (Referer derselben Firma), sonst der übergebene Standardreiter.
 * 10.15: mit Suche, Seite und Filtern der Liste (zuerst return_to des Formulars, dann Referer)
 * und optionalem Sprungziel, z. B. „person-12“.
 */
function yw_customer_return_url(int $companyId,string $fallbackTab='uebersicht',string $anchor=''): string {
    $hash=preg_match('/^[a-z][a-z0-9-]{0,40}$/D',$anchor)?'#'.$anchor:'';
    [$backCompany,$backTab,$back]=yw_customer_return_to(is_string($_POST['return_to']??null)?$_POST['return_to']:'');
    if($back!=='' && $backCompany===$companyId && array_key_exists($backTab,yw_customer_tabs()))return $back.$hash;
    $ref=(string)($_SERVER['HTTP_REFERER']??'');
    if($ref!=='' && (string)parse_url($ref,PHP_URL_PATH)==='/admin'){
        parse_str((string)parse_url($ref,PHP_URL_QUERY),$q);
        if(($q['tab']??'')==='companies' && (int)($q['company']??0)===$companyId){
            $tab=is_string($q['bereich']??null)?$q['bereich']:'uebersicht';
            if(array_key_exists($tab,yw_customer_tabs()))return yw_customer_state_url($companyId,$tab,$q).$hash;
        }
    }
    return yw_customer_url($companyId,array_key_exists($fallbackTab,yw_customer_tabs())?$fallbackTab:'uebersicht').$hash;
}

/** Reiterleiste. */
function yw_customer_tabbar(int $companyId,string $active): string {
    $html='<nav class="customer-tabs" aria-label="Bereiche der Kundenakte">';
    foreach(yw_customer_tabs() as $key=>[$label,$hint]){
        $html.='<a class="'.($active===$key?'active':'').'" href="'.e(yw_customer_url($companyId,$key)).'" title="'.e($hint).'">'.e($label).'</a>';
    }
    return $html.'</nav>';
}

/* ---------- Abschnitte (Inhalte wie bisher) ---------- */

function yw_customer_section_design(array $c): void {
        echo '<section class="card"><h2>Design</h2><div class="card-body"><form method="post" enctype="multipart/form-data" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="update_brand"><input type="hidden" name="return_tab" value="companies"><input type="hidden" name="company_id" value="'.(int)$c['id'].'"><label>Shopname<input name="shop_name" maxlength="190" value="'.e($c['shop_name']).'" required></label><label>Logo<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label><div class="grid2"><label>Primärfarbe<input type="color" name="primary_color" value="'.e(color_ok((string)($c['primary_color']??''))?(string)$c['primary_color']:'#17324d').'"></label><label>Akzentfarbe<input type="color" name="accent_color" value="'.e(color_ok((string)($c['accent_color']??''))?(string)$c['accent_color']:'#f28c28').'"></label></div><button class="btn small" type="submit">Design speichern</button></form></div></section>';
}

function yw_customer_section_people(array $c): void {
        // Großkunden: 50 je Seite mit Suche statt aller Konten (1.500 Formulare = 3 MB HTML).
        $list=yw_people_list((int)$c['id'],"registration_status='pending' DESC,role DESC,name ASC,id");
        $companyAccounts=$list['rows'];
        $equipmentProfiles=yw_equipment_profile_options((int)$c['id']);
        $nav=fn(bool $withSearch): string => yw_people_list_nav($list,'/admin',['tab'=>'companies','company'=>(int)$c['id'],'bereich'=>'mitarbeiter'],['Person suchen','Name, E-Mail, Abteilung, Kostenstelle, Standort oder Personalnummer','Suchen','Zurück','Weiter','Seite '.$list['page'].' von '.$list['pages'],$list['total'].' Treffer','Seiten der Personenliste'],$withSearch);
        echo '<section class="card"><h2>Mitarbeiter & Rollen <span class="muted">('.$list['total'].')</span></h2><div class="card-body">';
        echo '<form method="post" class="admin-add-person">'.csrf_field().'<input type="hidden" name="action" value="admin_add_manager"><input type="hidden" name="return_tab" value="companies"><input type="hidden" name="company_id" value="'.(int)$c['id'].'"><strong>Person direkt anlegen</strong><p class="muted">Die Person erhält per E-Mail einen Link, mit dem sie ihr Passwort selbst festlegt. Du siehst oder vergibst kein Passwort.</p><div class="grid3"><label>Name<input name="name" maxlength="160" required></label><label>E-Mail<input type="email" name="email" maxlength="190" required></label><label>Rolle<select name="role"><option value="manager">Verantwortlicher</option><option value="employee">Mitarbeiter</option></select></label></div><button class="btn small primary" type="submit">Anlegen und Einladung senden</button></form>';
        echo '<div id="personen" class="list-anchor"></div>';
        if($list['total']>0||$list['search']!=='')echo $nav(true);
        if(!$companyAccounts){
            echo '<div class="empty">'.($list['search']!==''?'Keine Person gefunden.':'Noch keine registrierten Mitarbeiter.').'</div>';
        }else{
            foreach($companyAccounts as $account){
                if(!empty($account['anonymized_at']))continue;
                echo '<form method="post" class="employee-role-form" id="person-'.(int)$account['id'].'">'.csrf_field()
                    .'<input type="hidden" name="action" value="update_employee_account">'
                    .'<input type="hidden" name="company_id" value="'.(int)$c['id'].'">'
                    .'<input type="hidden" name="account_id" value="'.(int)$account['id'].'">'
                    .'<div class="employee-role-identity"><strong>'.e($account['name']).'</strong><br><span class="muted">'.e($account['email']).'</span></div>'
                    .'<label>Rolle<select name="role">'
                        .'<option value="employee" '.(((string)($account['role']??'employee'))==='employee'?'selected':'').'>Mitarbeiter</option>'
                        .'<option value="manager" '.(((string)($account['role']??'employee'))==='manager'?'selected':'').'>Verantwortlicher</option>'
                    .'</select></label>'
                    .'<label>Zugang<select name="registration_status">'
                        .'<option value="pending" '.(((string)($account['registration_status']??'approved'))==='pending'?'selected':'').'>Wartet auf Freigabe</option>'
                        .'<option value="approved" '.(((string)($account['registration_status']??'approved'))==='approved'?'selected':'').'>Freigegeben</option>'
                        .'<option value="rejected" '.(((string)($account['registration_status']??'approved'))==='rejected'?'selected':'').'>Abgelehnt</option>'
                    .'</select></label>'
                    .yw_equipment_profile_select($equipmentProfiles,isset($account['equipment_profile_id'])?(int)$account['equipment_profile_id']:null)
                    .'<div class="employee-role-actions"><label class="check"><input type="checkbox" name="active" value="1" '.((int)$account['active']===1?'checked':'').'><span>Aktiv</span></label>'
                    .'<button class="btn small" type="submit">Rolle und Zugang speichern</button></div>'
                    .'</form>'
                    .'<form method="post" class="employee-anonymize" data-confirm="Alle persönlichen Daten von '.e($account['name']).' endgültig entfernen? Bestellungen bleiben erhalten.">'.csrf_field().'<input type="hidden" name="action" value="employee_anonymize"><input type="hidden" name="return_tab" value="companies"><input type="hidden" name="company_id" value="'.(int)$c['id'].'"><input type="hidden" name="account_id" value="'.(int)$account['id'].'"><button class="btn small danger" type="submit">Daten löschen (DSGVO)</button></form>';
                if((string)$account['registration_status']==='pending'){
                    echo '<div class="employee-quick-actions"><form method="post">'.csrf_field().'<input type="hidden" name="action" value="employee_access"><input type="hidden" name="account_id" value="'.(int)$account['id'].'"><input type="hidden" name="return_company" value="'.(int)$c['id'].'"><input type="hidden" name="decision" value="rejected"><button class="btn small" type="submit">Registrierung ablehnen</button></form><form method="post">'.csrf_field().'<input type="hidden" name="action" value="employee_access"><input type="hidden" name="account_id" value="'.(int)$account['id'].'"><input type="hidden" name="return_company" value="'.(int)$c['id'].'"><input type="hidden" name="decision" value="approved"><button class="btn small primary" type="submit">Mitarbeiter jetzt freigeben</button></form></div>';
                }
            }
            if($list['pages']>1)echo $nav(false);
        }
        echo '<p class="muted" style="margin-top:14px">Hinweis: Ein Verantwortlicher registriert sich zunächst ganz normal im Shop. Danach weist du ihm hier die Rolle „Verantwortlicher“ zu.</p>';
        echo '</div></section>';
        yw_customer_section_manager_changes($c);
}

/* ---------- 10.15: Rollenänderungen bei Verantwortlichen (still protokolliert, keine Mail) ---------- */

/** Vorgänge der letzten 14 Tage: ernannt, Rolle entzogen, gesperrt, wieder freigeschaltet, abgewiesener Versuch. $excludeAdminId blendet eigene Änderungen aus. */
function yw_manager_role_changes(?int $companyId,int $limit=8,int $excludeAdminId=0): array {
    try{
        $where="a.action IN ('employee.role_granted','employee.role_revoked','employee.manager_deactivated','employee.manager_reactivated','employee.manager_change_blocked') AND a.created_at>=DATE_SUB(NOW(),INTERVAL 14 DAY)";$params=[];
        if($companyId!==null){$where.=' AND a.company_id=?';$params[]=$companyId;}
        if($excludeAdminId>0){$where.=" AND NOT (a.actor_type='admin' AND a.actor_id<=>?)";$params[]=$excludeAdminId;}
        $q=db()->prepare("SELECT a.action,a.actor_type,a.actor_id,a.company_id,a.entity_id,a.metadata_json,a.created_at,c.shop_name,ea.name,ea.email,ea.anonymized_at FROM audit_log a LEFT JOIN companies c ON c.id=a.company_id LEFT JOIN employee_accounts ea ON ea.id=a.entity_id WHERE $where ORDER BY a.id DESC LIMIT ".max(1,min(50,$limit)));
        $q->execute($params);return $q->fetchAll();
    }catch(Throwable $e){return [];}
}

/** Eine Zeile als [Überschrift, Zusatz] (Klartext, noch nicht maskiert). */
function yw_manager_role_change_text(array $r,array $people,bool $withCompany): array {
    $labels=['employee.role_granted'=>'zum Verantwortlichen ernannt','employee.role_revoked'=>'Rolle „Verantwortlicher“ entzogen','employee.manager_deactivated'=>'als Verantwortlicher gesperrt','employee.manager_reactivated'=>'als Verantwortlicher wieder freigeschaltet','employee.manager_change_blocked'=>'Änderung abgewiesen (Vier-Augen-Schutz)'];
    $meta=json_decode((string)($r['metadata_json']??''),true)?:[];
    $name=yw_account_anonymized((string)($r['email']??''),$r['anonymized_at']??null)?'Gelöschtes Konto #'.(int)$r['entity_id']:((string)($r['name']??'')!==''?(string)$r['name']:'Konto #'.(int)$r['entity_id']);
    $actorId=$r['actor_id']!==null?(int)$r['actor_id']:null;
    $by=$r['actor_type']==='employee'?'im Shop durch '.($people['employee'][$actorId]??'Verantwortliche/n'):yw_actor_label((string)$r['actor_type'],$actorId,$meta,$people);
    if($r['action']==='employee.manager_change_blocked')$by='Versuch im Shop durch '.($people['employee'][$actorId]??'Verantwortliche/n');
    return [$name.' '.($labels[$r['action']]??$r['action']),($withCompany?(string)($r['shop_name']??'').' · ':'').date('d.m.Y H:i',strtotime((string)$r['created_at'])).' · '.$by.(!empty($meta['reason'])&&is_string($meta['reason'])?' · '.$meta['reason']:'')];
}

/** Kundenakte → Mitarbeiter: Einstellung und Rollenänderungen der letzten 14 Tage. */
function yw_customer_section_manager_changes(array $c): void {
    $rows=yw_manager_role_changes((int)$c['id'],20);$people=yw_actor_people($rows);
    echo '<section class="card"><h2>Rollen der Verantwortlichen</h2><div class="card-body"><p class="muted">'.(yw_managers_can_appoint($c)?'Verantwortliche dürfen in diesem Shop weitere Verantwortliche ernennen. Herabstufen, sperren oder löschen kannst nur du.':'Nur du vergibst und entziehst die Rolle „Verantwortlicher“. Verantwortliche können sich im Shop gegenseitig weder herabstufen noch sperren oder löschen.').' Einstellung im Reiter <a href="'.e(yw_customer_url((int)$c['id'],'regeln')).'">Regeln &amp; Budget</a>.</p>';
    if(!$rows)echo '<div class="empty compact">Keine Rollenänderungen in den letzten 14 Tagen.</div>';
    else{echo '<ul class="manager-changes" style="list-style:none;margin:12px 0 0;padding:0;display:grid;gap:10px">';foreach($rows as $r){[$head,$sub]=yw_manager_role_change_text($r,$people,false);echo '<li><strong>'.e($head).'</strong><br><span class="muted">'.e($sub).'</span></li>';}echo '</ul>';}
    echo '</div></section>';
}

function yw_customer_section_rules(array $c): void {
        echo '<section class="card"><h2>Shopregeln & Finanzierung</h2><div class="card-body"><form method="post" class="form-stack">'.csrf_field()
            .'<input type="hidden" name="action" value="update_shop_rules">'
            .'<input type="hidden" name="return_tab" value="companies">'
            .'<input type="hidden" name="company_id" value="'.(int)$c['id'].'">'
            .'<div class="grid2"><label>Finanzierungsmodell<select name="financing_mode">'
                .'<option value="company" '.(((string)($c['financing_mode']??'company'))==='company'?'selected':'').'>Firma übernimmt 100 %</option>'
                .'<option value="percentage" '.(((string)($c['financing_mode']??'company'))==='percentage'?'selected':'').'>Mitarbeiter zahlt prozentualen Eigenanteil</option>'
            .'</select></label>'
            .'<label>Eigenanteil Mitarbeiter in %<input type="number" min="0" max="100" step="0.01" name="employee_share_percent" value="'.e(number_format((float)($c['employee_share_percent']??0),2,'.','')).'"></label></div>'
            .'<p class="muted">Geld- und Mengenbudgets werden eindeutig im Reiter „Firmenregeln“ verwaltet.</p>'
            .'<label class="check"><input type="checkbox" name="approval_required" value="1" '.((int)($c['approval_required']??1)===1?'checked':'').'><span>Freigabe durch Firmenverantwortlichen erforderlich</span></label>'
            .(array_key_exists('managers_can_appoint',$c)?'<input type="hidden" name="managers_can_appoint_present" value="1"><label class="check"><input type="checkbox" name="managers_can_appoint" value="1" '.(yw_managers_can_appoint($c)?'checked':'').'><span>Verantwortliche dürfen weitere Verantwortliche ernennen</span></label>'
                .'<p class="muted">Standard: aus – dann vergibst und entziehst nur du die Rolle (Kundenakte → Mitarbeiter). Eingeschaltet können Verantwortliche im Shop Mitarbeiter zu Verantwortlichen machen, andere Verantwortliche aber weiterhin nicht herabstufen, sperren oder löschen. Achtung: Wer ein zweites Konto zum Verantwortlichen macht, kann damit eigene Bestellungen freigeben. Jede Rollenänderung steht im Aktivitätsprotokoll.</p>'
                :'<p class="muted">Einstellung „Verantwortliche dürfen weitere Verantwortliche ernennen“: erst nach dem Update-Assistenten verfügbar (bis dahin ernennt nur die Verwaltung).</p>')
            .'<hr><h4>Versandkosten</h4>'
            .'<label class="check"><input type="checkbox" name="shipping_enabled" value="1" '.((int)($c['shipping_enabled']??0)===1?'checked':'').'><span>Versandkosten berechnen</span></label>'
            .'<div class="grid2"><label>Versand netto in €<input type="number" min="0" step="0.01" name="shipping_net" value="'.e(number_format(((int)($c['shipping_net_cents']??0))/100,2,'.','')).'"></label>'
            .'<label>Versand-MwSt. in %<input type="number" min="0" max="100" step="0.01" name="shipping_vat_rate" value="'.e(number_format((float)($c['shipping_vat_rate']??19),2,'.','')).'"></label></div>'
            .'<div class="grid2"><label>Versandkostenfrei ab brutto in € <small>0 = deaktiviert</small><input type="number" min="0" step="0.01" name="free_shipping_gross" value="'.e(number_format(((int)($c['free_shipping_gross_cents']??0))/100,2,'.','')).'"></label>'
            .'<label>Versandkostenträger<select name="shipping_payer"><option value="company" '.(((string)($c['shipping_payer']??'company'))==='company'?'selected':'').'>Firma</option><option value="employee" '.(((string)($c['shipping_payer']??'company'))==='employee'?'selected':'').'>Mitarbeiter</option></select></label></div>'
            .'<div class="flash info"><strong>Finanzierung:</strong> '.e(financing_label($c)).'<br><strong>Versand:</strong> '.e(shipping_rule_label($c)).'</div>'
            .'<button class="btn small" type="submit">Shopregeln speichern</button></form></div></section>';
}

/* ---------- 10.15: Artikellisten für große Kollektionen (Suche, Kategorie, Seiten) ---------- */

/**
 * Abfrageteile für die Artikellisten der Kundenakte. $mode: 'katalog' = aktive Stammprodukte mit
 * Kollektionsstatus, 'firma' = alle Firmenprodukte (wie bisher in Regeln), 'profil' = Firmenprodukte
 * mit Zuordnung zum Ausstattungsprofil $profileId. Suche in Name, Name im Shop, SKU und Farbe.
 */
function yw_customer_product_sql(int $companyId,string $mode,int $profileId,string $search,string $category): array {
    $from='FROM products p LEFT JOIN product_categories pc ON pc.id=p.category_id '.($mode==='katalog'?'LEFT ':'').'JOIN company_products cp ON cp.company_id=? AND cp.product_id=p.id'.($mode==='profil'?' LEFT JOIN yw_equipment_profile_products epp ON epp.profile_id=? AND epp.product_id=p.id':'');
    $params=$mode==='profil'?[$companyId,$profileId]:[$companyId];
    $where=$mode==='katalog'?'p.active=1':'1=1';
    if($search!==''){$like='%'.addcslashes($search,'%_\\').'%';$where.=' AND (p.name LIKE ? OR cp.custom_name LIKE ? OR p.sku LIKE ? OR p.color LIKE ?)';array_push($params,$like,$like,$like,$like);}
    if($category==='-')$where.=" AND COALESCE(pc.name,NULLIF(p.category,'')) IS NULL";
    elseif($category!==''){$where.=" AND COALESCE(pc.name,NULLIF(p.category,''))=?";$params[]=$category;}
    return [$from,$where,$params];
}

/** Bedingung je Ansicht: Katalog (in der Kollektion / noch nicht / alle), Profil (freigegeben / offen / alle). */
function yw_customer_product_view_sql(string $mode,string $view): string {
    if($mode==='katalog')return ['kollektion'=>' AND cp.product_id IS NOT NULL','katalog'=>' AND cp.product_id IS NULL'][$view]??'';
    if($mode==='profil')return ['freigegeben'=>' AND epp.product_id IS NOT NULL','offen'=>' AND epp.product_id IS NULL'][$view]??'';
    return '';
}

/** Produkt-IDs aller Treffer (für Sammelaktionen „alle Treffer …“). */
function yw_customer_product_ids(int $companyId,string $mode,int $profileId,string $search,string $category,string $view): array {
    [$from,$where,$params]=yw_customer_product_sql($companyId,$mode,$profileId,$search,$category);
    $q=db()->prepare("SELECT p.id $from WHERE $where".yw_customer_product_view_sql($mode,$view).' ORDER BY p.id');$q->execute($params);
    return array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Seitenweise Artikelliste. $keys benennt die GET-Parameter ['q','cat','page','view'], damit mehrere
 * Listen auf einer Seite (Regeln: Stückzahlgrenzen und Profil) unabhängig blättern.
 */
function yw_customer_product_list(int $companyId,array $keys,string $mode,int $profileId=0,int $perPage=50): array {
    $state=yw_customer_list_state($_GET);
    $search=(string)($state[$keys['q']]??'');$category=(string)($state[$keys['cat']]??'');
    [$from,$where,$params]=yw_customer_product_sql($companyId,$mode,$profileId,$search,$category);
    $flag=$mode==='katalog'?'cp.product_id IS NOT NULL':($mode==='profil'?'epp.product_id IS NOT NULL':'1');
    $q=db()->prepare("SELECT COUNT(*) n,COALESCE(SUM($flag),0) marked $from WHERE $where");$q->execute($params);$c=$q->fetch();
    $counts=['alle'=>(int)$c['n'],'mit'=>(int)$c['marked'],'ohne'=>(int)$c['n']-(int)$c['marked']];
    $views=$mode==='katalog'?['kollektion'=>'mit','katalog'=>'ohne','alle'=>'alle']:($mode==='profil'?['alle'=>'alle','freigegeben'=>'mit','offen'=>'ohne']:[]);
    $view=(string)($state[$keys['view']??'']??'');
    if(!isset($views[$view])){
        $view=$mode==='profil'?'alle':'';
        // Katalog: Firmen mit Kollektion sehen zuerst ihre Artikel, neue Firmen den Katalog zum Auswählen.
        if($mode==='katalog'){$a=db()->prepare('SELECT COUNT(*) FROM company_products cp JOIN products p ON p.id=cp.product_id WHERE cp.company_id=? AND p.active=1');$a->execute([$companyId]);$view=(int)$a->fetchColumn()>0?'kollektion':'katalog';}
    }
    $total=$views?$counts[$views[$view]]:$counts['alle'];
    $pages=max(1,(int)ceil($total/$perPage));$page=min(max(1,(int)($state[$keys['page']]??1)),$pages);
    $cols='p.*,COALESCE(pc.name,NULLIF(p.category,\'\')) category_name,cp.product_id cp_product_id,cp.custom_name,cp.custom_description,cp.price_override_cents,cp.preview_front_path,cp.preview_back_path,cp.decorations_json,cp.yearly_limit_qty'.($mode==='profil'?',epp.product_id epp_product_id,epp.starter_qty,epp.yearly_target_qty':'');
    $q=db()->prepare("SELECT $cols $from WHERE $where".yw_customer_product_view_sql($mode,$view)." ORDER BY category_name IS NULL,category_name,COALESCE(NULLIF(cp.custom_name,''),p.name),p.id LIMIT $perPage OFFSET ".(($page-1)*$perPage));$q->execute($params);
    [$catFrom,$catWhere,$catParams]=yw_customer_product_sql($companyId,$mode,$profileId,'','');
    $q2=db()->prepare("SELECT COALESCE(pc.name,NULLIF(p.category,'')) c,COUNT(*) n $catFrom WHERE $catWhere GROUP BY c ORDER BY c IS NULL,c");$q2->execute($catParams);
    return ['rows'=>$q->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages,'search'=>$search,'category'=>$category,'view'=>$view,'views'=>$views,'counts'=>$counts,'categories'=>$q2->fetchAll(),'keys'=>$keys];
}

/**
 * Suche, Kategorie, Ansicht und Blättern zur Artikelliste. $keep = übrige GET-Parameter (Reiter und
 * Zustand der anderen Listen), $viewLabels = Ansicht => Beschriftung.
 */
function yw_customer_product_nav(array $list,array $keep,array $viewLabels=[],bool $withFilter=true): string {
    $k=$list['keys'];
    $own=fn(array $extra=[]): array => ($list['search']!==''?[$k['q']=>$list['search']]:[])+($list['category']!==''?[$k['cat']=>$list['category']]:[])+(isset($k['view'])&&$list['view']!==''?[$k['view']=>$list['view']]:[])+$extra;
    $url=fn(array $params): string => '/admin?'.http_build_query($keep+$params);
    $html='';
    if($withFilter){
        if($viewLabels){
            $html.='<nav class="list-views" aria-label="Ansicht">';
            foreach($viewLabels as $view=>$label)$html.='<a class="btn small'.($list['view']===$view?' primary':'').'" href="'.e($url(($list['search']!==''?[$k['q']=>$list['search']]:[])+($list['category']!==''?[$k['cat']=>$list['category']]:[])+[$k['view']=>$view])).'"'.($list['view']===$view?' aria-current="true"':'').'>'.e($label).' <span>('.(int)$list['counts'][$list['views'][$view]].')</span></a>';
            $html.='</nav>';
        }
        $html.='<form method="get" action="/admin" class="list-filter" role="search">';
        foreach($keep+(isset($k['view'])&&$list['view']!==''?[$k['view']=>$list['view']]:[]) as $name=>$value)$html.='<input type="hidden" name="'.e($name).'" value="'.e($value).'">';
        $html.='<label>Artikel suchen<input type="search" name="'.e($k['q']).'" maxlength="80" value="'.e($list['search']).'" placeholder="Name, SKU oder Farbe"></label>';
        $html.='<label>Kategorie<select name="'.e($k['cat']).'"><option value="">Alle Kategorien</option>';
        foreach($list['categories'] as $cat){$value=$cat['c']===null?'-':(string)$cat['c'];$html.='<option value="'.e($value).'"'.($list['category']===$value?' selected':'').'>'.e($cat['c']===null?'Ohne Kategorie':(string)$cat['c']).' ('.(int)$cat['n'].')</option>';}
        $html.='</select></label><button class="btn small" type="submit">Filtern</button>';
        if($list['search']!==''||$list['category']!=='')$html.='<a class="btn small" href="'.e($url(isset($k['view'])&&$list['view']!==''?[$k['view']=>$list['view']]:[])).'">Filter zurücksetzen</a>';
        $html.='</form>';
    }
    if($list['pages']>1){
        $html.='<nav class="list-pager" aria-label="Seiten der Artikelliste">';
        if($list['page']>1)$html.='<a class="btn small" href="'.e($url($own($list['page']>2?[$k['page']=>$list['page']-1]:[]))).'">← Zurück</a>';
        $html.='<span>Seite '.$list['page'].' von '.$list['pages'].' · '.$list['total'].' Artikel</span>';
        if($list['page']<$list['pages'])$html.='<a class="btn small" href="'.e($url($own([$k['page']=>$list['page']+1]))).'">Weiter →</a>';
        $html.='</nav>';
    }
    return $html;
}

/** Bisherige GET-Parameter des Reiters ohne die eigenen Schlüssel einer Liste (zum Blättern/Filtern). */
function yw_customer_keep(int $companyId,string $tab,array $ownKeys): array {
    return ['tab'=>'companies','company'=>$companyId,'bereich'=>$tab]+array_diff_key(yw_customer_list_state($_GET),array_flip($ownKeys));
}

/** Ein Firmenprodukt mit allen Feldern für das Bearbeitungsformular (wie shop_company_products()). */
function yw_company_product_row(int $companyId,int $productId): ?array {
    $q=db()->prepare("SELECT p.*,COALESCE(pc.name,NULLIF(p.category,'')) category_name,cp.product_id cp_product_id,cp.custom_name,cp.custom_description,cp.price_override_cents,cp.preview_front_path,cp.preview_back_path,cp.decorations_json,cp.yearly_limit_qty FROM company_products cp JOIN products p ON p.id=cp.product_id LEFT JOIN product_categories pc ON pc.id=p.category_id WHERE cp.company_id=? AND cp.product_id=? AND p.active=1");
    $q->execute([$companyId,$productId]);return $q->fetch()?:null;
}

/**
 * Firmenkollektion: kompakte Liste (50 je Seite) mit Suche, Kategorie und Ansicht; das vollständige
 * Bearbeitungsformular erscheint nur für den gewählten Artikel (?produkt=) direkt unter seiner Zeile.
 * Die Häkchen speichern nur die Artikel der angezeigten Seite (scope_products), andere bleiben unverändert.
 */
function yw_customer_section_collection(array $c): void {
        $id=(int)$c['id'];
        $keys=['q'=>'artikel','cat'=>'kategorie','page'=>'aseite','view'=>'anzeige'];
        $list=yw_customer_product_list($id,$keys,'katalog');
        $keep=yw_customer_keep($id,'kollektion',['artikel','kategorie','aseite','anzeige','produkt']);
        $state=yw_customer_list_state($_GET);
        $openId=(int)($state['produkt']??0);
        $listState=array_intersect_key($state,array_flip(['artikel','kategorie','aseite','anzeige']));
        $listUrl=fn(array $extra=[]): string => yw_customer_state_url($id,'kollektion',$listState+$extra);
        $q=db()->prepare('SELECT COUNT(*) FROM company_products cp JOIN products p ON p.id=cp.product_id WHERE cp.company_id=? AND p.active=1');$q->execute([$id]);$inShop=(int)$q->fetchColumn();
        echo '<section class="card" id="kollektion"><h2>Firmenkollektion <span class="muted">('.$inShop.' Artikel im Shop)</span></h2><div class="card-body"><p class="muted">Häkchen = Artikel wird in diesem Firmenshop angeboten. „Bearbeiten“ öffnet Name, Preis, Bilder und Veredelung – das gilt nur für diesen Kunden, das Stammprodukt bleibt unverändert.</p>';
        echo yw_customer_product_nav($list,$keep,['kollektion'=>'In der Kollektion','katalog'=>'Weitere Katalogartikel','alle'=>'Alle']);
        // Sammelaktionen für alle Treffer der Suche/Kategorie (nicht nur diese Seite)
        $filter=($list['search']!==''?'<input type="hidden" name="artikel" value="'.e($list['search']).'">':'').($list['category']!==''?'<input type="hidden" name="kategorie" value="'.e($list['category']).'">':'');
        $scopeText=$list['search']!==''||$list['category']!==''?' (aktuelle Suche/Kategorie)':'';
        if($list['counts']['ohne']>0||$list['counts']['mit']>0){
            echo '<div class="list-bulk"><span class="muted">Alle Treffer'.e($scopeText).':</span>';
            if($list['counts']['ohne']>0)echo '<form method="post" class="inline-form" data-confirm="'.e($list['counts']['ohne'].' Artikel'.$scopeText.' in die Kollektion aufnehmen?').'">'.csrf_field().'<input type="hidden" name="action" value="collection_bulk"><input type="hidden" name="bulk" value="add"><input type="hidden" name="return_tab" value="companies"><input type="hidden" name="company_id" value="'.$id.'">'.$filter.'<button class="btn small" type="submit">'.$list['counts']['ohne'].' Artikel aufnehmen</button></form>';
            if($list['counts']['mit']>0)echo '<form method="post" class="inline-form" data-confirm="'.e($list['counts']['mit'].' Artikel'.$scopeText.' aus der Kollektion entfernen? Eigene Namen, Preise, Bilder, Veredelungen und Profilfreigaben dieser Artikel werden gelöscht.').'">'.csrf_field().'<input type="hidden" name="action" value="collection_bulk"><input type="hidden" name="bulk" value="remove"><input type="hidden" name="return_tab" value="companies"><input type="hidden" name="company_id" value="'.$id.'">'.$filter.'<button class="btn small danger" type="submit">'.$list['counts']['mit'].' Artikel entfernen</button></form>';
            echo '</div>';
        }
        $rows=$list['rows'];
        $onPage=in_array($openId,array_map('intval',array_column($rows,'id')),true);
        // Gewählter Artikel nicht auf dieser Seite (z. B. direkter Link): Formular über der Liste
        // (Sprungmarke produkt-ID, damit „#produkt-ID“ nach dem Speichern auch hier landet – z. B. nach Umbenennen auf anderer Seite)
        $editorOpen=false;
        if($openId && !$onPage && ($open=yw_company_product_row($id,$openId))){echo '<div class="list-anchor" id="produkt-'.$openId.'"></div>';render_company_product_customization_form($c,$open,$listUrl());$editorOpen=true;}
        echo '<form method="post" id="kollektion-auswahl" data-unsaved-warning>'.csrf_field().'<input type="hidden" name="action" value="update_collection"><input type="hidden" name="return_tab" value="companies"><input type="hidden" name="company_id" value="'.$id.'"></form>';
        if(!$rows){
            echo '<div class="empty compact">'.($list['search']!==''||$list['category']!==''?'Keine Artikel gefunden.':($list['view']==='katalog'?'Alle aktiven Stammprodukte sind bereits in der Kollektion.':'Noch keine Artikel – oben „Weitere Katalogartikel“ wählen und Häkchen setzen.')).'</div>';
        }else{
            echo '<div class="collection-list"><div class="collection-list-head"><label class="check"><input type="checkbox" form="kollektion-auswahl" data-check-all="products[]"><span>Alle auf dieser Seite</span></label></div>';
            foreach($rows as $p){
                $pid=(int)$p['id'];$in=$p['cp_product_id']!==null;$name=company_product_display_name($p);
                $img=$in?company_product_front_image($p):yw_local_image_path((string)($p['image_path']??''),['products'],true);
                $configured=$in && (yw_local_image_path((string)($p['preview_front_path']??''),['company-products'])!=='' || company_product_back_image($p)!=='' || company_product_decorations($p) || $p['price_override_cents']!==null);
                $meta=array_filter([(string)($p['sku']??''),(string)($p['color']??''),(string)($p['category_name']??''),$name!==(string)$p['name']?'Stammprodukt: '.$p['name']:''],fn($v)=>$v!=='');
                $price=$in&&$p['price_override_cents']!==null?(int)$p['price_override_cents']:(int)$p['price_cents'];
                echo '<div class="collection-row'.($openId===$pid&&$in?' is-open':'').'" id="produkt-'.$pid.'">'
                    .'<input type="hidden" form="kollektion-auswahl" name="scope_products[]" value="'.$pid.'">'
                    .'<label class="collection-pick"><input type="checkbox" form="kollektion-auswahl" name="products[]" value="'.$pid.'"'.($in?' checked':'').' aria-label="'.e($name).' im Firmenshop anbieten"></label>'
                    .'<span class="collection-product-mini">'.($img!==''?'<img src="/'.e($img).'" alt="" loading="lazy">':'<span class="collection-product-fallback">'.wc_public_icon('shirt').'</span>').'</span>'
                    .'<div class="collection-row-main"><strong>'.e($name).'</strong><small>'.e(implode(' · ',$meta)).'</small></div>'
                    .'<div class="collection-row-meta"><span class="collection-row-price">'.money($price).' netto</span>'
                    .'<span class="'.($in?($configured?'collection-configured':'collection-standard'):'collection-out').'">'.($in?($configured?'Individualisiert':'Standard'):'Nicht im Shop').'</span></div>'
                    .($in?($openId===$pid?'<a class="btn small" href="'.e($listUrl()).'#produkt-'.$pid.'">Schließen</a>':'<a class="btn small" href="'.e($listUrl(['produkt'=>(string)$pid])).'#produkt-'.$pid.'">Bearbeiten</a>'):'<span class="collection-row-hint">erst aufnehmen</span>')
                    .'</div>';
                if($in && $openId===$pid){render_company_product_customization_form($c,$p,$listUrl());$editorOpen=true;}
            }
            echo '</div>';
            // Bei geöffnetem Artikel klebt die Leiste nicht über dem Formular – sonst wird „Auswahl speichern“ leicht für „Shop-Produkt speichern“ gehalten.
            echo '<div class="collection-savebar'.($editorOpen?' is-static':'').'"><span class="muted"><span class="savebar-long">Die Häkchen gelten für die '.count($rows).' Artikel dieser Seite – Artikel auf anderen Seiten bleiben unverändert.</span><span class="savebar-short">Gilt nur für diese Seite ('.count($rows).' Artikel).</span></span><button class="btn primary" type="submit" form="kollektion-auswahl">Auswahl speichern</button></div>';
        }
        if($list['pages']>1)echo yw_customer_product_nav($list,$keep,[],false);
        echo '</div></section>';
}

function yw_customer_section_links(array $c): void {
        $base=workwearcenter_url($c['slug']);
        $shop=$base;
        $registration=$base.'/registrieren';
        $login=$base.'/login';
        $approval=$base.'/freigaben';
        echo '<section class="card"><h2>Zugänge & Links</h2><div class="card-body">';
        echo '<strong class="muted">Shop</strong><div class="copy">'.e($shop).'</div><button type="button" class="btn small" data-copy="'.e($shop).'">Shop-Link kopieren</button><hr>';
        echo '<strong class="muted">Registrierung für Mitarbeiter</strong><div class="copy">'.e($registration).'</div><button type="button" class="btn small" data-copy="'.e($registration).'">Registrierungslink kopieren</button><hr>';
        echo '<strong class="muted">Mitarbeiter-Login</strong><div class="copy">'.e($login).'</div><button type="button" class="btn small" data-copy="'.e($login).'">Login-Link kopieren</button><hr>';
        echo '<strong class="muted">Freigaben / Verantwortlicher</strong><div class="copy">'.e($approval).'</div><button type="button" class="btn small" data-copy="'.e($approval).'">Freigabe-Link kopieren</button>';
        echo '</div></section>';
        echo '<section class="card"><div class="title-row"><div><h2>QR-Code-Aushang</h2><p class="muted">Druckfertige A4-Seite für Pausenraum, Werkstatt oder Spind: Firmenlogo, „Deine Arbeitskleidung bestellen“, QR-Code und die wichtigsten Schritte.</p></div><div class="actions"><a class="btn primary" href="/admin/aushang/'.(int)$c['id'].'" target="_blank" rel="noopener">Aushang mit Registrierungslink</a><a class="btn" href="/admin/aushang/'.(int)$c['id'].'?ziel=login" target="_blank" rel="noopener">Aushang mit Login-Link</a></div></div></section>';
}

/** Aufträge dieser Firma (Reiter „Aufträge“). */
function yw_customer_section_orders(array $c): void {
    $id=(int)$c['id'];
    $q=db()->prepare("SELECT o.*,COALESCE(ea.name,i.employee_name,'Unbekannt') employee_name FROM orders o LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id LEFT JOIN invites i ON i.id=o.invite_id WHERE o.company_id=? ORDER BY o.id DESC LIMIT 100");
    $q->execute([$id]);$orders=$q->fetchAll();
    $labels=['pending'=>'Freigabe offen','approved'=>'Freigegeben','rejected'=>'Abgelehnt','cancelled'=>'Zurückgezogen'];
    echo '<section class="card"><div class="title-row"><div><h2>Aufträge</h2><p class="muted">Die letzten 100 Bestellungen dieser Firma. Ein Klick öffnet den Auftrag.</p></div><a class="btn" href="/admin?tab=production&amp;company_id='.$id.'">Produktionsansicht</a></div>';
    if(!$orders){echo '<div class="empty compact">Noch keine Bestellungen.</div></section>';return;}
    echo '<div class="table-wrap"><table class="table"><thead><tr><th>Auftrag</th><th>Mitarbeiter</th><th>Freigabe</th><th>Produktion</th><th>Betrag</th><th></th></tr></thead><tbody>';
    foreach($orders as $o){
        $total=(int)($o['gross_total_cents']??0)>0?(int)$o['gross_total_cents']:(int)$o['total_cents'];
        echo '<tr><td><strong>'.e(yw_order_number((int)$o['id'])).'</strong><br><span class="muted">'.e(date('d.m.Y',strtotime((string)$o['created_at']))).'</span></td>'
            .'<td>'.e((string)$o['employee_name']).'</td>'
            .'<td><span class="badge '.e((string)$o['status']).'">'.e($labels[$o['status']]??$o['status']).'</span></td>'
            .'<td>'.e(yw_production_statuses()[$o['fulfillment_status']]??(string)$o['fulfillment_status']).'</td>'
            .'<td><strong>'.money($total).'</strong></td>'
            .'<td><a class="btn small" href="/admin?tab=orders&amp;auftrag='.(int)$o['id'].'">Öffnen</a></td></tr>';
    }
    echo '</tbody></table></div></section>';
}

/** Übersicht: Status, Einrichtung, Notizen, Shop-Vorschau. */
function yw_customer_section_overview(array $c): void {
    $id=(int)$c['id'];
    echo '<div class="company-kpi-grid">'
        .'<div class="company-kpi"><strong>'.(int)($c['registered_count']??0).'</strong><span>Registriert</span></div>'
        .'<div class="company-kpi"><strong>'.(int)($c['approved_order_count']??0).'</strong><span>Freigegebene Bestellungen</span></div>'
        .'<div class="company-kpi"><strong>'.(int)($c['completed_order_count']??0).'</strong><span>Ausgeliefert</span></div>'
        .'<div class="company-kpi"><strong>'.money((int)($c['revenue_cents']??0)).'</strong><span>Bestellvolumen brutto</span></div>'
        .'</div>';

    $steps=yw_company_setup_steps($c);$done=count(array_filter($steps,fn($s)=>$s[2]));
    echo '<section class="card setup-progress"><div class="title-row"><div><h2>Einrichtung '.($done===count($steps)?'abgeschlossen ✓':$done.' von '.count($steps).' erledigt').'</h2><p class="muted">Diese Schritte sollten vor dem Start erledigt sein.</p></div><div class="setup-meter" aria-hidden="true"><span style="width:'.(int)round($done/count($steps)*100).'%"></span></div></div><ol class="setup-steps-grid">';
    foreach($steps as [$label,$hint,$ok,$link])echo '<li class="'.($ok?'done':'open').'"><span class="setup-dot">'.($ok?'✓':'').'</span><div><strong>'.e($label).'</strong><small>'.e($hint).'</small></div>'.($ok?'':'<a class="btn small" href="'.e($link).'">Erledigen</a>').'</li>';
    echo '</ol></section>';
    foreach(yw_company_setup_warnings($c) as $warning)echo '<div class="flash info">'.$warning.'</div>';

    echo '<section class="card"><h2>Interne Notizen</h2><p class="muted">Nur für dich und dein Team sichtbar – zum Beispiel Absprachen zu Logo, Rechnung oder Ansprechpartner.</p>'
        .'<form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="save_company_note"><input type="hidden" name="return_tab" value="companies"><input type="hidden" name="company_id" value="'.$id.'">'
        .'<textarea name="internal_note" rows="5" maxlength="5000" placeholder="z. B. Logo nur Brust links, Rechnung an die Buchhaltung, Ansprechpartner Frau Meier">'.e((string)($c['internal_note']??'')).'</textarea>'
        .'<button class="btn primary">Notiz speichern</button></form></section>';
}

/** Hauptseite der Kundenakte. */
function yw_customer_file(array $company,array $products): void {
    $id=(int)$company['id'];
    $tab=yw_customer_tab();
    $isActive=(int)($company['active']??1)===1;
    $logo=yw_company_logo_path($company);
    $primary=color_ok((string)($company['primary_color']??''))?(string)$company['primary_color']:'#17324d';
    $accent=color_ok((string)($company['accent_color']??''))?(string)$company['accent_color']:'#f47b20';

    echo '<div class="customer-head" style="--company-primary:'.e($primary).';--company-accent:'.e($accent).'">';
    echo '<a class="btn small" href="/admin?tab=companies">← Alle Kunden</a>';
    echo '<div class="customer-ident">'.($logo!==''?'<img src="/'.e($logo).'" alt="">':'<span class="logo logo-fallback">'.e(mb_strtoupper(mb_substr((string)$company['legal_name'],0,1))).'</span>')
        .'<div><h2>'.e($company['shop_name']).'</h2><p>'.e($company['legal_name']).' · workwearcenter.de/'.e($company['slug']).'</p></div></div>';
    echo '<div class="customer-head-actions"><span class="badge '.($isActive?'approved':'rejected').'">'.($isActive?'aktiv':'deaktiviert').'</span>';
    echo '<form method="post" action="/admin/vorschau/'.$id.'" target="_blank" class="inline-form">'.csrf_field().'<button class="btn small" type="submit" title="Öffnet den Firmenshop so, wie ein Verantwortlicher ihn sieht – ohne bestellen zu können">Shop ansehen</button></form>';
    echo '<form method="post" class="inline-form" data-confirm="'.($isActive?'Shop wirklich deaktivieren? Mitarbeitende können sich dann nicht mehr anmelden.':'Shop wieder aktivieren?').'">'.csrf_field()
        .'<input type="hidden" name="action" value="toggle_company"><input type="hidden" name="return_tab" value="companies"><input type="hidden" name="company_id" value="'.$id.'">'
        .'<button class="btn small '.($isActive?'danger':'success').'">'.($isActive?'Deaktivieren':'Aktivieren').'</button></form>';
    echo '</div></div>';

    echo yw_customer_tabbar($id,$tab);
    echo '<div class="customer-body">';
    switch($tab){
        case 'kollektion':
            yw_customer_section_design($company);
            yw_customer_section_collection($company); // 10.15: Liste mit Suche und Seiten statt aller Formulare
            render_admin_decoration_motifs($company);
            break;
        case 'mitarbeiter': yw_customer_section_people($company); break;
        case 'regeln':
            yw_customer_section_rules($company);
            yw_customer_section_budget($company);
            yw_customer_section_order_options($company); // 10.11: app/modules/order_schedule.php
            break;
        case 'auftraege': yw_customer_section_orders($company); yw_lexware_customer_card($company); yw_payroll_admin_card($company); break;
        case 'zugaenge':  yw_customer_section_links($company); break;
        default:          yw_customer_section_overview($company);
    }
    echo '</div>';
}

/* ---------- 10.11: QR-Code-Aushang je Firma (druckbare A4-Seite) ---------- */

/** Schritte auf dem Aushang – abhängig von Ziel und Registrierungsregeln der Firma. */
function yw_poster_steps(array $company,string $target): array {
    if($target==='login')return [
        'QR-Code mit der Handykamera scannen.',
        'Mit deiner E-Mail-Adresse und deinem Passwort anmelden. Passwort vergessen? Auf der Anmeldeseite einfach neu anfordern.',
        'Artikel und Größe wählen, bestellen – den Stand deiner Bestellung siehst du jederzeit im Shop.',
    ];
    try{[$pin,$approval]=yw_registration_policy((string)($company['registration_mode']??'approval'));}catch(Throwable $e){[$pin,$approval]=[false,true];}
    $steps=['QR-Code mit der Handykamera scannen.','Konto anlegen: Name, dienstliche E-Mail-Adresse und ein eigenes Passwort'.($pin?' – dazu die Firmen-PIN, die du von deiner verantwortlichen Person bekommst.':'.')];
    if($approval)$steps[]='Kurz warten: Deine verantwortliche Person schaltet dich frei. Du bekommst dann eine E-Mail.';
    $steps[]='Größe wählen und bestellen – fertig. Den Stand deiner Bestellung siehst du jederzeit im Shop.';
    return $steps;
}

function yw_admin_poster_page(int $companyId): void {
    require_admin();
    $q=db()->prepare('SELECT * FROM companies WHERE id=? LIMIT 1');$q->execute([$companyId]);$c=$q->fetch();
    if(!$c){http_response_code(404);simple_message('Firma nicht gefunden','Diese Firma gibt es nicht (mehr).');return;}
    $target=($_GET['ziel']??'')==='login'?'login':'registrieren';
    $url=workwearcenter_url($c['slug'].'/'.$target);
    $logo=yw_company_logo_path($c);
    $primary=color_ok((string)($c['primary_color']??''))?(string)$c['primary_color']:'#17324d';
    $accent=color_ok((string)($c['accent_color']??''))?(string)$c['accent_color']:'#f47b20';
    render_head('Aushang – '.$c['shop_name']);
    echo '<style>
    @page{size:A4 portrait;margin:0}
    body{background:#e9ecf0;margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#14233a}
    .poster-tools{position:sticky;top:0;z-index:5;display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:12px 18px;background:#10263a;color:#fff}
    .poster-tools .btn{border-color:rgba(255,255,255,.32);background:rgba(255,255,255,.08);color:#fff}.poster-tools .btn.primary{background:var(--accent);border-color:var(--accent)}.poster-tools .btn.active{background:#fff;color:#10263a}
    .poster-tools .poster-tools-hint{margin-left:auto;font-size:.85rem;opacity:.75}
    .poster-warn{background:#fff3cd;color:#6b4e00;padding:10px 18px;font-weight:600}
    .poster{box-sizing:border-box;width:210mm;min-height:297mm;margin:24px auto;background:#fff;box-shadow:0 10px 40px rgba(16,24,40,.18);display:flex;flex-direction:column;border-top:10mm solid var(--poster-primary)}
    .poster-inner{flex:1;display:flex;flex-direction:column;padding:14mm 18mm 12mm}
    .poster-brand{display:flex;align-items:center;gap:4mm 6mm;flex-wrap:wrap;min-height:24mm}.poster-brand img{max-height:24mm;max-width:min(80mm,100%);object-fit:contain}.poster-brand strong{font-size:15pt;color:#526171;font-weight:700}.poster-brand-text strong{font-size:26pt;color:var(--poster-primary);font-weight:800}
    .poster h1{font-size:34pt;line-height:1.05;letter-spacing:-.02em;margin:12mm 0 4mm;color:var(--poster-primary)}
    .poster-lead{font-size:14pt;color:#4a5568;margin:0 0 9mm}
    .poster-main{display:grid;grid-template-columns:82mm 1fr;gap:12mm;align-items:start}
    .poster-qr{border:2mm solid var(--poster-accent);border-radius:5mm;padding:4mm;background:#fff}
    .poster-qr [data-qr]{width:100%;aspect-ratio:1/1}.poster-qr svg{width:100%;height:100%;display:block}
    .poster-url{margin-top:3mm;font-size:9.5pt;word-break:break-all;color:#4a5568;text-align:center}
    .poster-steps{list-style:none;counter-reset:s;margin:0;padding:0;display:grid;gap:6mm}
    .poster-steps li{counter-increment:s;display:grid;grid-template-columns:11mm 1fr;gap:4mm;align-items:start;font-size:13pt;line-height:1.4}
    .poster-steps li::before{content:counter(s);display:grid;place-items:center;width:11mm;height:11mm;border-radius:50%;background:var(--poster-primary);color:#fff;font-weight:800;font-size:13pt}
    .poster-foot{margin-top:auto;padding-top:8mm;border-top:.4mm solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;gap:6mm;font-size:10pt;color:#6b7686}
    .poster-foot img{height:7mm;width:auto}
    @media print{body{background:#fff}.poster-tools,.poster-warn{display:none!important}.poster{margin:0;box-shadow:none;width:210mm;height:297mm;min-height:0;page-break-after:avoid}}
    @media screen and (max-width:820px){.poster{width:100%;min-height:0;margin:0;border-top-width:6mm}.poster-inner{padding:22px 18px}.poster h1{font-size:26pt}.poster-main{grid-template-columns:minmax(0,1fr)}.poster-qr{max-width:300px}.poster-tools-hint{display:none}}
    </style>';
    echo '<div class="poster-tools"><a class="btn small" href="'.e(yw_customer_url($companyId,'zugaenge')).'">← Zurück zur Kundenakte</a>';
    echo '<span>QR-Code führt zu:</span><a class="btn small'.($target==='registrieren'?' active':'').'" href="/admin/aushang/'.$companyId.'">Registrierung</a><a class="btn small'.($target==='login'?' active':'').'" href="/admin/aushang/'.$companyId.'?ziel=login">Anmeldung</a>';
    echo '<button type="button" class="btn small primary" data-print>Drucken</button><span class="poster-tools-hint">Tipp: Im Druckdialog „Ränder: keine“ und „Hintergrundgrafiken“ wählen.</span></div>';
    if((int)($c['active']??1)!==1)echo '<div class="poster-warn">Achtung: Dieser Firmenshop ist deaktiviert – der QR-Code funktioniert erst nach dem Aktivieren.</div>';
    echo '<main class="poster" style="--poster-primary:'.e($primary).';--poster-accent:'.e($accent).'"><div class="poster-inner">';
    echo '<div class="poster-brand'.($logo===''?' poster-brand-text':'').'">'.($logo!==''?'<img src="/'.e($logo).'" alt="'.e($c['shop_name']).'">':'').'<strong>'.e($c['shop_name']).'</strong></div>';
    echo '<h1>Deine Arbeitskleidung bestellen</h1><p class="poster-lead">'.($target==='login'?'Einfach im Firmenshop anmelden – am Handy oder am Computer.':'Einmal registrieren, dann selbst bestellen – am Handy oder am Computer.').'</p>';
    echo '<div class="poster-main"><div><div class="poster-qr"><div data-qr="'.e($url).'" role="img" aria-label="QR-Code zu '.e($url).'"></div></div><div class="poster-url">'.e(preg_replace('#^https://#','',$url)).'</div></div>';
    echo '<ol class="poster-steps">';foreach(yw_poster_steps($c,$target) as $step)echo '<li><span>'.e($step).'</span></li>';echo '</ol></div>';
    echo '<div class="poster-foot"><span>Fragen zur Bestellung? Wende dich an deine verantwortliche Person.</span><img src="/assets/media/workwearcenter-logo-dunkel.png" alt="WorkwearCenter"></div>';
    echo '</div></main>';
    echo '<script src="/assets/vendor/qrcode.js" defer></script>';
    render_foot();
}
