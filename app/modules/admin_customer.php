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
        $stmtAccounts=db()->prepare('SELECT * FROM employee_accounts WHERE company_id=? ORDER BY role DESC,name ASC');
        $stmtAccounts->execute([(int)$c['id']]);
        $companyAccounts=array_values(array_filter($stmtAccounts->fetchAll(),fn($a)=>empty($a['anonymized_at'])));
        $equipmentProfiles=yw_equipment_profile_options((int)$c['id']);

        $hasManager=(bool)array_filter($companyAccounts,fn($a)=>$a['role']==='manager'&&(int)$a['active']===1&&$a['registration_status']==='approved');
        echo '<section class="card"><h2>Mitarbeiter & Rollen <span class="muted">('.count($companyAccounts).')</span></h2><div class="card-body">';
        echo '<form method="post" class="admin-add-person">'.csrf_field().'<input type="hidden" name="action" value="admin_add_manager"><input type="hidden" name="return_tab" value="companies"><input type="hidden" name="company_id" value="'.(int)$c['id'].'"><strong>Person direkt anlegen</strong><p class="muted">Die Person erhält per E-Mail einen Link, mit dem sie ihr Passwort selbst festlegt. Du siehst oder vergibst kein Passwort.</p><div class="grid3"><label>Name<input name="name" maxlength="160" required></label><label>E-Mail<input type="email" name="email" maxlength="190" required></label><label>Rolle<select name="role"><option value="manager">Verantwortlicher</option><option value="employee">Mitarbeiter</option></select></label></div><button class="btn small primary" type="submit">Anlegen und Einladung senden</button></form>';
        if(!$companyAccounts){
            echo '<div class="empty">Noch keine registrierten Mitarbeiter.</div>';
        }else{
            foreach($companyAccounts as $account){
                if(!empty($account['anonymized_at']))continue;
                echo '<form method="post" class="employee-role-form">'.csrf_field()
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
        }
        echo '<p class="muted" style="margin-top:14px">Hinweis: Ein Verantwortlicher registriert sich zunächst ganz normal im Shop. Danach weist du ihm hier die Rolle „Verantwortlicher“ zu.</p>';
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
            .'<hr><h4>Versandkosten</h4>'
            .'<label class="check"><input type="checkbox" name="shipping_enabled" value="1" '.((int)($c['shipping_enabled']??0)===1?'checked':'').'><span>Versandkosten berechnen</span></label>'
            .'<div class="grid2"><label>Versand netto in €<input type="number" min="0" step="0.01" name="shipping_net" value="'.e(number_format(((int)($c['shipping_net_cents']??0))/100,2,'.','')).'"></label>'
            .'<label>Versand-MwSt. in %<input type="number" min="0" max="100" step="0.01" name="shipping_vat_rate" value="'.e(number_format((float)($c['shipping_vat_rate']??19),2,'.','')).'"></label></div>'
            .'<div class="grid2"><label>Versandkostenfrei ab brutto in € <small>0 = deaktiviert</small><input type="number" min="0" step="0.01" name="free_shipping_gross" value="'.e(number_format(((int)($c['free_shipping_gross_cents']??0))/100,2,'.','')).'"></label>'
            .'<label>Versandkostenträger<select name="shipping_payer"><option value="company" '.(((string)($c['shipping_payer']??'company'))==='company'?'selected':'').'>Firma</option><option value="employee" '.(((string)($c['shipping_payer']??'company'))==='employee'?'selected':'').'>Mitarbeiter</option></select></label></div>'
            .'<div class="flash info"><strong>Finanzierung:</strong> '.e(financing_label($c)).'<br><strong>Versand:</strong> '.e(shipping_rule_label($c)).'</div>'
            .'<button class="btn small" type="submit">Shopregeln speichern</button></form></div></section>';
}

function yw_customer_section_collection(array $c,array $products,array $assigned): void {
        echo '<section class="card"><h2>Firmenkollektion</h2><div class="card-body"><p class="muted">Wähle hier die Stammprodukte aus, die in diesem Firmenshop angeboten werden.</p><form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="update_collection"><input type="hidden" name="return_tab" value="companies"><input type="hidden" name="company_id" value="'.(int)$c['id'].'"><div class="checkboxes">';foreach($products as $p)if((int)$p['active'])echo '<label class="check"><input type="checkbox" name="products[]" value="'.(int)$p['id'].'" '.(in_array((int)$p['id'],$assigned,true)?'checked':'').'><span>'.e($p['name']).' · '.money((int)$p['price_cents']).' netto</span></label>';echo '</div><button class="btn small" type="submit">Kollektion speichern</button></form>';

        $shopProducts=shop_company_products((int)$c['id']);
        if($shopProducts){
            echo '<div class="collection-config-section"><div class="collection-config-title"><h3>Shop-Produkte individualisieren</h3><p>Logo-/Mockup-Vorschauen, Shopname, eigener Preis und Veredelungspositionen gelten nur für diesen Kunden.</p></div>';
            foreach($shopProducts as $shopProduct) render_company_product_customization_form($c,$shopProduct);
            echo '</div>';
        }
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
    echo '<a class="btn small" href="/admin/vorschau/'.$id.'" target="_blank" rel="noopener" title="Öffnet den Firmenshop so, wie ein Verantwortlicher ihn sieht – ohne bestellen zu können">Shop ansehen</a>';
    echo '<form method="post" class="inline-form" data-confirm="'.($isActive?'Shop wirklich deaktivieren? Mitarbeitende können sich dann nicht mehr anmelden.':'Shop wieder aktivieren?').'">'.csrf_field()
        .'<input type="hidden" name="action" value="toggle_company"><input type="hidden" name="return_tab" value="companies"><input type="hidden" name="company_id" value="'.$id.'">'
        .'<button class="btn small '.($isActive?'danger':'success').'">'.($isActive?'Deaktivieren':'Aktivieren').'</button></form>';
    echo '</div></div>';

    echo yw_customer_tabbar($id,$tab);
    echo '<div class="customer-body">';
    switch($tab){
        case 'kollektion':
            $q=db()->prepare('SELECT product_id FROM company_products WHERE company_id=?');$q->execute([$id]);
            $assigned=array_map('intval',array_column($q->fetchAll(),'product_id'));
            yw_customer_section_design($company);
            yw_customer_section_collection($company,$products,$assigned);
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
