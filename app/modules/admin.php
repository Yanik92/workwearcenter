<?php
declare(strict_types=1);
require_once __DIR__.'/product_import.php';
require_once __DIR__.'/admin_password_reset.php';
require_once __DIR__.'/admin_account.php';

function yw_admin_product_ids(mixed $raw): array {
    if(!is_array($raw))throw new RuntimeException('Produktauswahl ist ungültig.');
    $ids=[];
    foreach($raw as $value){
        if(!is_scalar($value) || !preg_match('/^[1-9]\d*$/D',(string)$value))throw new RuntimeException('Produktauswahl enthält eine ungültige Kennung.');
        $ids[]=(int)$value;
    }
    $ids=array_values(array_unique($ids));
    if(!$ids)throw new RuntimeException('Bitte mindestens ein Produkt auswählen.');
    if(count($ids)>500)throw new RuntimeException('Es können höchstens 500 Produkte gleichzeitig zugeordnet werden.');
    $placeholders=implode(',',array_fill(0,count($ids),'?'));
    $q=db()->prepare("SELECT COUNT(*) FROM products WHERE active=1 AND id IN ($placeholders)");$q->execute($ids);
    if((int)$q->fetchColumn()!==count($ids))throw new RuntimeException('Mindestens ein ausgewähltes Produkt ist nicht verfügbar.');
    return $ids;
}

function yw_admin_sizes(string $raw): array {
    $sizes=array_values(array_unique(array_filter(array_map('trim',explode(',',$raw)))));
    if(!$sizes || count($sizes)>50 || array_filter($sizes,fn($size)=>mb_strlen($size)>30))throw new RuntimeException('Bitte 1 bis 50 gültige Größen mit höchstens 30 Zeichen eingeben.');
    return $sizes;
}

function yw_admin_assert_unique_sku(string $sku,int $exceptProductId=0): void {
    if($sku==='')return;
    $q=db()->prepare("SELECT id FROM products WHERE LOWER(TRIM(COALESCE(sku,'')))=LOWER(?) AND id<>? LIMIT 1");
    $q->execute([$sku,$exceptProductId]);
    if($q->fetchColumn())throw new RuntimeException('Diese Artikelnummer / SKU ist bereits einem anderen Produkt zugeordnet.');
}

function admin_login_page(): void {
    if (admin_logged_in()) redirect('/admin');
    $error = '';
    if (is_post()) {
        verify_csrf(); $email=strtolower(trim((string)($_POST['email'] ?? ''))); $password=(string)($_POST['password'] ?? '');
        // Pro IP (8 Versuche) und zusätzlich IP-unabhängig je Konto (25 Versuche),
        // damit verteiltes Passwort-Raten über viele Adressen ebenfalls gebremst wird.
        $accountKey='admin-account-'.hash('sha256',$email);
        if (login_throttled('admin') || login_throttled($accountKey,25,false)) $error='Zu viele Versuche. Bitte 15 Minuten warten.';
        else {
            $stmt=db()->prepare('SELECT * FROM admins WHERE email=? LIMIT 1'); $stmt->execute([$email]); $admin=$stmt->fetch();
            $passwordValid=$admin
                ? password_verify($password,(string)$admin['password_hash'])
                : password_verify($password,yw_dummy_password_hash());
            if ($admin && $passwordValid && (int)($admin['active']??1)===1) {
                if(password_needs_rehash((string)$admin['password_hash'],PASSWORD_DEFAULT)){
                    db()->prepare('UPDATE admins SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),(int)$admin['id']]);
                }
                clear_login_failures('admin'); clear_login_failures($accountKey,false);
                if((int)($admin['totp_enabled']??0)===1){
                    // Passwort stimmt – jetzt zweiter Faktor. Noch keine Admin-Sitzung vergeben.
                    session_regenerate_id(true);
                    $_SESSION['admin_2fa']=['id'=>(int)$admin['id'],'time'=>time()];
                    redirect('/admin/2fa');
                }
                yw_admin_complete_login($admin);
            }
            record_login_failure('admin'); record_login_failure($accountKey,false); $error='E-Mail oder Passwort ist nicht korrekt.';
        }
    }
    render_head('Administrator anmelden');
    echo site_brand_style();
    echo '<div class="login-brandbar"><div class="wrap">'.site_brand_html().'</div></div>';
    echo '<main class="login-shell" style="--primary:#101b2d;--accent:#ff6b00"><section class="login-card"><div class="login-accent"></div><div class="login-body"><div class="eyebrow">YANIK WORKWEAR</div><h1>Verwaltung</h1><p>Hier richtest du Firmen-Shops ein und bearbeitest freigegebene Aufträge.</p>';
    render_flash();
    if (isset($_GET['installed'])) echo '<div class="flash success">Installation abgeschlossen. Du kannst dich jetzt anmelden.</div>';
    if ($error) echo '<div class="flash error">'.e($error).'</div>';
    echo '<form method="post" class="form-stack">'.csrf_field().'<label>E-Mail<input type="email" name="email" autocomplete="username" required></label><label>Passwort<input type="password" name="password" autocomplete="current-password" required></label><button class="btn primary" type="submit">Anmelden</button></form><p><a href="/admin/passwort-vergessen">Passwort vergessen?</a></p></div></section></main>';
    global_footer(); render_foot();
}

function admin_page(): void {
    require_admin();
    if (is_post()) { verify_csrf(); handle_admin_action(); }

    $allowedTabs=['dashboard','account','corporate','production','handover','system','inquiries','orders','companies','new','products','content','design','audit','guide','suche','api','katalog'];
    $tab=(string)($_GET['tab'] ?? 'dashboard');
    if(!in_array($tab,$allowedTabs,true))$tab='dashboard';

    $companies=[];$products=[];$orders=[];$inquiries=[];$auditLogs=[];
    $needsCompanies=in_array($tab,['dashboard','companies'],true);
    $needsProducts=in_array($tab,['dashboard','companies','products','new'],true);
    $needsOrders=in_array($tab,['dashboard','orders'],true) && empty($_GET['auftrag']);

    if($needsCompanies){
        $companies=db()->query("SELECT c.*,
            (SELECT COUNT(*) FROM invites i WHERE i.company_id=c.id) invite_count,
            (SELECT COUNT(*) FROM employee_accounts ea WHERE ea.company_id=c.id AND ea.active=1) registered_count,
            (SELECT COUNT(*) FROM orders o WHERE o.company_id=c.id AND o.status='approved') approved_order_count,
            (SELECT COUNT(*) FROM orders o WHERE o.company_id=c.id AND o.status='approved' AND o.fulfillment_status='completed') completed_order_count,
            (SELECT COALESCE(SUM(CASE WHEN o.gross_total_cents>0 THEN o.gross_total_cents ELSE o.total_cents END),0) FROM orders o WHERE o.company_id=c.id AND o.status='approved') revenue_cents
            FROM companies c ORDER BY c.id DESC")->fetchAll();
    }
    if($needsProducts)$products=db()->query('SELECT * FROM products ORDER BY active DESC, category, name')->fetchAll();
    if($needsOrders){
        $orderWhere=$tab==='dashboard'?"WHERE o.status='approved' AND o.fulfillment_status NOT IN ('shipped','picked_up','completed')":'';
        $orderLimit=$tab==='orders'?' LIMIT 500':'';
        $orders=db()->query("SELECT o.*,c.legal_name,COALESCE(ea.name,i.employee_name,'Unbekannt') employee_name
            FROM orders o
            JOIN companies c ON c.id=o.company_id
            LEFT JOIN employee_accounts ea ON ea.id=o.employee_account_id
            LEFT JOIN invites i ON i.id=o.invite_id
            $orderWhere
            ORDER BY o.id DESC$orderLimit")->fetchAll();
    }
    if(in_array($tab,['dashboard','inquiries'],true)){
        try{$inquiries=db()->query("SELECT * FROM inquiries ORDER BY FIELD(status,'new','contacted','done'), id DESC")->fetchAll();}
        catch(Throwable $e){$inquiries=[];}
    }
    if($tab==='audit'){
        try{$auditLogs=db()->query("SELECT a.*,c.legal_name FROM audit_log a LEFT JOIN companies c ON c.id=a.company_id ORDER BY a.id DESC LIMIT 200")->fetchAll();}
        catch(Throwable $e){$auditLogs=[];}
    }


    render_head('WorkwearCenter – Verwaltung');
    echo site_brand_style();
    echo admin_backend_style();

    echo '<header class="admin-topbar"><div class="wrap admin-topbar-inner">'.site_brand_html(false)
        .'<div class="admin-topbar-title"><strong>WorkwearCenter Verwaltung</strong><small>Zentrale Administration</small></div>'
        .'<form method="get" class="admin-search" role="search"><input type="hidden" name="tab" value="suche"><input type="search" name="q" value="'.e($tab==='suche'?(string)($_GET['q']??''):'').'" placeholder="Suchen: Firma, Auftrag, Person, Artikel" aria-label="Suche"></form>'
        .'<a class="btn small" href="/admin?tab=account">Mein Konto</a><form method="post" action="/admin/logout">'.csrf_field().'<button class="btn small" type="submit">Abmelden</button></form>'
        .'</div></header>';

    echo '<div class="admin-shell">';
    echo admin_sidebar($tab);
    echo '<main class="admin-content">';

    echo admin_context_header($tab);
    render_flash();

    if($tab==='corporate') yw_admin_corporate();
    elseif($tab==='production') yw_production();
    elseif($tab==='handover') yw_admin_handover();
    elseif($tab==='account') yw_admin_account_page();
    elseif($tab==='system') yw_system();
    elseif($tab==='companies') render_admin_companies($companies,$products);
    elseif($tab==='products') render_admin_products($products);
    elseif($tab==='new') render_new_company($products);
    elseif($tab==='inquiries') render_admin_inquiries($inquiries);
    elseif($tab==='content') render_content_settings();
    elseif($tab==='design') render_design_settings();
    elseif($tab==='guide') render_guide();
    elseif($tab==='orders'){ if(!empty($_GET['auftrag'])) yw_admin_order_page((int)$_GET['auftrag']); else render_admin_orders($orders); }
    elseif($tab==='suche') yw_admin_search_page((string)($_GET['q']??''));
    elseif($tab==='api') yw_admin_api_page();
    elseif($tab==='katalog') yw_admin_katalog_page();
    elseif($tab==='audit') render_admin_audit($auditLogs);
    else render_admin_dashboard($companies,$products,$orders,$inquiries);

    echo '</main></div>';
    global_footer();
    render_foot();
}

function handle_admin_action(): never {
    yw_admin_action();
    $action=(string)($_POST['action'] ?? '');
    if(str_starts_with($action,'handover_'))yw_admin_handover_action($action);
    if(str_starts_with($action,'account_') || in_array($action,['admin_invite','admin_toggle'],true))yw_admin_account_action($action);
    try {
        if ($action==='inquiry_status') {
            $id=(int)($_POST['inquiry_id']??0);
            $status=(string)($_POST['status']??'new');
            if(!in_array($status,['new','contacted','done'],true)) {
                throw new RuntimeException('Status ungültig.');
            }
            $stmt=db()->prepare('UPDATE inquiries SET status=? WHERE id=?');
            $stmt->execute([$status,$id]);
            if(!$stmt->rowCount())throw new RuntimeException('Anfrage wurde nicht gefunden oder war bereits in diesem Status.');
            audit_log('inquiry.status_updated','inquiry',$id,['status'=>$status]);
            flash('success','Anfragestatus gespeichert.');
            redirect('/admin?tab=inquiries');
        }

        if ($action==='employee_access') {
            $accountId=(int)($_POST['account_id']??0);
            $decision=(string)($_POST['decision']??'');
            if(!in_array($decision,['approved','rejected'],true)) throw new RuntimeException('Freigabeentscheidung ist ungültig.');
            $stmt=db()->prepare('SELECT ea.id,ea.name,ea.email,ea.company_id,c.slug FROM employee_accounts ea JOIN companies c ON c.id=ea.company_id WHERE ea.id=? LIMIT 1');
            $stmt->execute([$accountId]);$account=$stmt->fetch();
            if(!$account) throw new RuntimeException('Mitarbeiterkonto wurde nicht gefunden.');
            $stmt=db()->prepare('UPDATE employee_accounts SET registration_status=?,active=? WHERE id=?');
            $stmt->execute([$decision,$decision==='approved'?1:0,$accountId]);
            $accessText=$decision==='approved'
                ? "Hallo ".$account['name'].",\n\ndein Zugang zum Firmen-Shop wurde freigegeben.\n\nAnmeldung: ".workwearcenter_url($account['slug'].'/login')
                : "Hallo ".$account['name'].",\n\ndeine Registrierung für den Firmen-Shop wurde nicht freigegeben. Bitte wende dich bei Rückfragen an deinen Firmenverantwortlichen.";
            send_system_mail((string)$account['email'],$decision==='approved'?'Dein Zugang wurde freigegeben':'Information zu deiner Registrierung',$accessText);
            audit_log('employee.access_'.$decision,'employee',$accountId,['company_id'=>(int)$account['company_id']]);
            flash('success',$decision==='approved'?'Mitarbeiter wurde freigegeben.':'Registrierung wurde abgelehnt.');
            $returnCompany=(int)($_POST['return_company']??0);
            redirect($returnCompany?'/admin?tab=companies&company='.$returnCompany:'/admin');
        }

        if ($action==='bulk_fulfillment') {
            $ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['order_ids']??[])))));
            $status=(string)($_POST['status']??'');
            if(!$ids || !array_key_exists($status,yw_production_statuses())) throw new RuntimeException('Bitte Aufträge und einen gültigen Status auswählen.');
            $ids=array_slice($ids,0,100);
            $placeholders=implode(',',array_fill(0,count($ids),'?'));
            $find=db()->prepare("SELECT o.id,c.slug FROM orders o JOIN companies c ON c.id=o.company_id WHERE o.status='approved' AND o.fulfillment_status<>? AND o.id IN ($placeholders)");
            $find->execute([$status,...$ids]);$changedOrders=$find->fetchAll();$changedIds=array_map('intval',array_column($changedOrders,'id'));
            if($changedIds){
                $changedPlaceholders=implode(',',array_fill(0,count($changedIds),'?'));
                $stmt=db()->prepare("UPDATE orders SET fulfillment_status=?,production_updated_at=NOW() WHERE status='approved' AND id IN ($changedPlaceholders)");
                $stmt->execute([$status,...$changedIds]);
                foreach($changedOrders as $changedOrder){$orderId=(int)$changedOrder['id'];audit_log('order.fulfillment_updated','order',$orderId,['status'=>$status]);notify_order_employee($orderId,'Status deiner Bestellung #'.$orderId,"Der Bearbeitungsstatus deiner Bestellung wurde geändert: ".(yw_production_statuses()[$status]??$status)."\n\nBestellstatus ansehen: ".workwearcenter_url((string)$changedOrder['slug'].'/login'));}
            }
            flash('success',count($changedIds).' Aufträge wurden aktualisiert.');
            redirect('/admin?tab=orders');
        }

        if ($action==='save_design_settings') {
            $title=trim((string)($_POST['site_title']??'WorkwearCenter'));
            $primary=trim((string)($_POST['site_primary']??'#17324d'));
            $accent=trim((string)($_POST['site_accent']??'#f47b20'));

            if($title===''||mb_strlen($title)>190||!color_ok($primary)||!color_ok($accent)) {
                throw new RuntimeException('Seitentitel oder Farben sind ungültig.');
            }

            $imageKeys=[
                'site_logo'=>'logo',
                'hero_image'=>'hero',
                'section1_image'=>'section1',
                'section2_image'=>'section2',
                'section3_image'=>'section3',
                'services_image'=>'services',
                'about_image'=>'about',
                'how_image'=>'how',
                'contact_image'=>'contact'
            ];

            $fields=[
                'site_title'=>$title,
                'maintenance_mode'=>isset($_POST['maintenance_mode']) && $_POST['maintenance_mode']==='1' ? '1' : '0',
                'site_primary'=>$primary,
                'site_accent'=>$accent,
            ];

            $oldImages=[];
            $newUploads=[];
            try{
                foreach($imageKeys as $key=>$prefix){
                    $old=current_site_setting($key,'');
                    $oldImages[$key]=$old;
                    $base=!empty($_POST['remove_'.$key])?'':$old;

                    if($key==='site_logo' && isset($_FILES[$key])){
                        $value=upload_site_logo($_FILES[$key],$base);
                    } else {
                        $value=isset($_FILES[$key])
                            ? upload_site_image($_FILES[$key],$base,$prefix)
                            : $base;
                    }
                    if($value!=='' && $value!==$old && yw_upload_path_ok($value,'branding'))$newUploads[]=$value;
                    $fields[$key]=$value;
                }

                db()->beginTransaction();
                foreach($fields as $k=>$v)set_site_setting($k,(string)$v);
                db()->commit();
            }catch(Throwable $e){
                if(db()->inTransaction())db()->rollBack();
                foreach(array_unique($newUploads) as $newUpload)delete_site_image($newUpload);
                throw $e;
            }

            foreach($oldImages as $key=>$old){
                if($old!=='' && $old!==($fields[$key]??''))delete_site_image($old);
            }
            audit_log('site.design_updated','site',null,['maintenance_mode'=>$fields['maintenance_mode']]);

            flash(
                'success',
                $fields['maintenance_mode']==='1'
                    ? 'Design gespeichert. Wartungsmodus ist aktiv.'
                    : 'Design gespeichert. Wartungsmodus ist deaktiviert.'
            );
            redirect('/admin?tab=design');
        }

        if ($action==='save_content_settings') {
            $fields=[
                'hero_eyebrow'=>trim((string)($_POST['hero_eyebrow']??'')),
                'hero_title'=>trim((string)($_POST['hero_title']??'')),
                'hero_text'=>sanitize_rich_text((string)($_POST['hero_text']??'')),
                'hero_button_text'=>trim((string)($_POST['hero_button_text']??'')),
                'hero_button_link'=>yw_public_link($_POST['hero_button_link']??'','/anfrage'),
                'hero_secondary_text'=>trim((string)($_POST['hero_secondary_text']??'')),
                'hero_secondary_link'=>yw_public_link($_POST['hero_secondary_link']??'','/so-funktionierts'),
                'intro_title'=>trim((string)($_POST['intro_title']??'')),
                'intro_text'=>sanitize_rich_text((string)($_POST['intro_text']??'')),
                'section1_title'=>trim((string)($_POST['section1_title']??'')),
                'section1_text'=>sanitize_rich_text((string)($_POST['section1_text']??'')),
                'section2_title'=>trim((string)($_POST['section2_title']??'')),
                'section2_text'=>sanitize_rich_text((string)($_POST['section2_text']??'')),
                'section3_title'=>trim((string)($_POST['section3_title']??'')),
                'section3_text'=>sanitize_rich_text((string)($_POST['section3_text']??'')),
                'process_title'=>trim((string)($_POST['process_title']??'')),
                'process_step1_title'=>trim((string)($_POST['process_step1_title']??'')),
                'process_step1_text'=>sanitize_rich_text((string)($_POST['process_step1_text']??'')),
                'process_step2_title'=>trim((string)($_POST['process_step2_title']??'')),
                'process_step2_text'=>sanitize_rich_text((string)($_POST['process_step2_text']??'')),
                'process_step3_title'=>trim((string)($_POST['process_step3_title']??'')),
                'process_step3_text'=>sanitize_rich_text((string)($_POST['process_step3_text']??'')),
                'process_step4_title'=>trim((string)($_POST['process_step4_title']??'')),
                'process_step4_text'=>sanitize_rich_text((string)($_POST['process_step4_text']??'')),
                'faq1_question'=>trim((string)($_POST['faq1_question']??'')),
                'faq1_answer'=>sanitize_rich_text((string)($_POST['faq1_answer']??'')),
                'faq2_question'=>trim((string)($_POST['faq2_question']??'')),
                'faq2_answer'=>sanitize_rich_text((string)($_POST['faq2_answer']??'')),
                'faq3_question'=>trim((string)($_POST['faq3_question']??'')),
                'faq3_answer'=>sanitize_rich_text((string)($_POST['faq3_answer']??'')),
                'faq4_question'=>trim((string)($_POST['faq4_question']??'')),
                'faq4_answer'=>sanitize_rich_text((string)($_POST['faq4_answer']??'')),
                'final_cta_title'=>trim((string)($_POST['final_cta_title']??'')),
                'final_cta_text'=>sanitize_rich_text((string)($_POST['final_cta_text']??'')),
                'final_cta_button'=>trim((string)($_POST['final_cta_button']??'')),
                'final_cta_link'=>yw_public_link($_POST['final_cta_link']??'','/anfrage'),
                'services_title'=>trim((string)($_POST['services_title']??'')),
                'services_intro'=>sanitize_rich_text((string)($_POST['services_intro']??'')),
                'services_body'=>sanitize_rich_text((string)($_POST['services_body']??'')),
                'about_title'=>trim((string)($_POST['about_title']??'')),
                'about_intro'=>sanitize_rich_text((string)($_POST['about_intro']??'')),
                'about_body'=>sanitize_rich_text((string)($_POST['about_body']??'')),
                'how_title'=>trim((string)($_POST['how_title']??'')),
                'how_intro'=>sanitize_rich_text((string)($_POST['how_intro']??'')),
                'how_body'=>sanitize_rich_text((string)($_POST['how_body']??'')),
                'contact_title'=>trim((string)($_POST['contact_title']??'')),
                'contact_intro'=>sanitize_rich_text((string)($_POST['contact_intro']??'')),
                'contact_body'=>sanitize_rich_text((string)($_POST['contact_body']??'')),
                'footer_text'=>trim((string)($_POST['footer_text']??'')),
                'imprint_company'=>trim((string)($_POST['imprint_company']??'')),
                'imprint_owner'=>trim((string)($_POST['imprint_owner']??'')),
                'imprint_street'=>trim((string)($_POST['imprint_street']??'')),
                'imprint_city'=>trim((string)($_POST['imprint_city']??'')),
                'imprint_phone'=>trim((string)($_POST['imprint_phone']??'')),
                'imprint_email'=>trim((string)($_POST['imprint_email']??'')),
                'imprint_vat_id'=>trim((string)($_POST['imprint_vat_id']??'')),
                'imprint_register'=>trim((string)($_POST['imprint_register']??'')),
                'privacy_body'=>sanitize_rich_text((string)($_POST['privacy_body']??'')),
            ];

            foreach($fields as $key=>$value){
                $richField=preg_match('/(?:_text|_intro|_body|_answer)$/D',$key)===1;
                $limit=$key==='privacy_body'?60000:($richField?20000:500);
                if(mb_strlen((string)$value)>$limit)throw new RuntimeException('Der Inhalt im Feld „'.str_replace('_',' ',$key).'“ ist zu lang.');
            }
            if($fields['imprint_email']!=='' && !filter_var($fields['imprint_email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Die E-Mail-Adresse im Impressum ist ungültig.');

            db()->beginTransaction();
            foreach($fields as $k=>$v)set_site_setting($k,(string)$v);
            db()->commit();
            audit_log('site.content_updated','site');

            flash('success','Inhalte wurden gespeichert.');
            redirect('/admin?tab=content');
        }

        if ($action==='content_apply_defaults') {
            $n=yw_content_apply_defaults();
            audit_log('site.content_defaults_applied','site',null,['fields'=>$n]);
            flash('success','Die neuen Texte (Fassung 10.3) wurden übernommen. Die bisherigen Texte sind gesichert und können jederzeit zurückgeholt werden.');
            redirect('/admin?tab=content');
        }
        if ($action==='content_restore_backup') {
            $n=yw_content_restore_backup();
            audit_log('site.content_backup_restored','site',null,['fields'=>$n]);
            flash('success','Die gesicherten Texte wurden wiederhergestellt.');
            redirect('/admin?tab=content');
        }
        if ($action==='create_category') {
            $name=trim((string)($_POST['category_name']??''));
            if(!$name || mb_strlen($name)>120) throw new RuntimeException('Bitte einen Kategorienamen mit höchstens 120 Zeichen eingeben.');
            $duplicate=db()->prepare('SELECT id FROM product_categories WHERE LOWER(name)=LOWER(?) LIMIT 1');$duplicate->execute([$name]);
            if($duplicate->fetchColumn())throw new RuntimeException('Diese Kategorie ist bereits vorhanden.');
            $stmt=db()->prepare('INSERT INTO product_categories(name,sort_order) VALUES(?,?)');
            $stmt->execute([$name,(int)($_POST['sort_order']??0)]);
            audit_log('product_category.created','product_category',(int)db()->lastInsertId());
            flash('success','Kategorie wurde angelegt.');
            redirect('/admin?tab=products');
        }
        if ($action==='import_products_csv') {
            $result=yw_import_products_csv($_FILES['products_csv']??[]);
            audit_log('products.csv_imported','product',null,$result);
            flash('success',$result['rows'].' CSV-Zeilen verarbeitet: '.$result['created'].' Produkte neu, '.$result['updated'].' aktualisiert, '.$result['categories_created'].' Kategorien neu.');
            redirect('/admin?tab=products');
        }

        if ($action==='delete_category') {
            $id=(int)($_POST['category_id']??0);
            $stmt=db()->prepare('SELECT COUNT(*) FROM products WHERE category_id=?');
            $stmt->execute([$id]);
            if((int)$stmt->fetchColumn()>0) throw new RuntimeException('Kategorie kann nicht gelöscht werden, solange Produkte zugeordnet sind.');
            $stmt=db()->prepare('DELETE FROM product_categories WHERE id=?');
            $stmt->execute([$id]);
            if(!$stmt->rowCount())throw new RuntimeException('Kategorie wurde nicht gefunden.');
            audit_log('product_category.deleted','product_category',$id);
            flash('success','Kategorie wurde gelöscht.');
            redirect('/admin?tab=products');
        }

        if ($action==='create_product') {
            $name=trim((string)($_POST['name']??''));$categoryId=(int)($_POST['category_id']??0);$category=trim((string)($_POST['category']??''));$priceCents=yw_money_input((string)($_POST['price']??''));$vatRate=yw_decimal_input((string)($_POST['vat_rate']??'19'),0,100,'MwSt.-Satz');
            $purchaseRaw=trim((string)($_POST['purchase_price']??'0'));$purchaseCents=yw_money_input($purchaseRaw===''?'0':$purchaseRaw);$sku=trim((string)($_POST['sku']??''));$brand=trim((string)($_POST['brand']??''));$supplier=trim((string)($_POST['supplier_name']??''));
            $sizes=yw_admin_sizes((string)($_POST['sizes']??''));
            if (!$name||!$categoryId||$priceCents<=0||!$sizes) throw new RuntimeException('Bitte Produktname, Kategorie, Preise und Größen vollständig eingeben.');
            $categoryName=product_category_name($categoryId,'');if($categoryName==='')throw new RuntimeException('Bitte eine gültige Kategorie auswählen.');
            $description=trim((string)($_POST['description']??''));$color=trim((string)($_POST['color']??''));
            if(mb_strlen($name)>190||mb_strlen($description)>10000||mb_strlen($color)>120||mb_strlen($sku)>100||mb_strlen($brand)>120||mb_strlen($supplier)>160)throw new RuntimeException('Mindestens ein Produktfeld ist zu lang.');
            yw_admin_assert_unique_sku($sku);
            $decorationType=(string)($_POST['decoration_type']??'none');
            if(!in_array($decorationType,['none','print','embroidery','mixed'],true)) $decorationType='none';
            $decorationDetails=trim((string)($_POST['decoration_details']??''));
            if(mb_strlen($decorationDetails)>10000)throw new RuntimeException('Veredelungsbeschreibung ist zu lang.');
            $image=upload_product_image($_FILES['image']??null,'');
            $imageBack='';$imageSleeve='';
            try{$imageBack=upload_product_image($_FILES['image_back']??null,'');$imageSleeve=upload_product_image($_FILES['image_sleeve']??null,'');}
            catch(Throwable $e){foreach([$image,$imageBack,$imageSleeve] as $f)if($f!=='')delete_product_image($f);throw $e;}
            $stmt=db()->prepare('INSERT INTO products(name,category_id,category,description,price_cents,purchase_price_cents,vat_rate,sizes,color,image_path,image_back_path,image_sleeve_path,decoration_type,decoration_details,sku,brand,supplier_name) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            try{$stmt->execute([$name,$categoryId,$categoryName,$description,$priceCents,$purchaseCents,$vatRate,json_encode($sizes,JSON_UNESCAPED_UNICODE),$color,$image,$imageBack?:null,$imageSleeve?:null,$decorationType,$decorationDetails,$sku,$brand,$supplier]);}
            catch(Throwable $e){foreach([$image,$imageBack,$imageSleeve] as $f)if($f!=='')delete_product_image($f);throw $e;}
            audit_log('product.created','product',(int)db()->lastInsertId());
            flash('success','Produkt wurde angelegt.'); redirect('/admin?tab=products');
        }
        if ($action==='toggle_product') {
            $productId=(int)($_POST['product_id']??0);$stmt=db()->prepare('UPDATE products SET active=IF(active=1,0,1) WHERE id=?'); $stmt->execute([$productId]);if(!$stmt->rowCount())throw new RuntimeException('Produkt wurde nicht gefunden.');
            audit_log('product.status_toggled','product',$productId);
            flash('success','Produktstatus wurde geändert.'); redirect('/admin?tab=products');
        }
        if ($action==='update_product') {
            $id=(int)($_POST['product_id']??0);$name=trim((string)($_POST['name']??''));$categoryId=(int)($_POST['category_id']??0);$priceCents=yw_money_input((string)($_POST['price']??''));$vatRate=yw_decimal_input((string)($_POST['vat_rate']??'19'),0,100,'MwSt.-Satz');$sizes=yw_admin_sizes((string)($_POST['sizes']??''));
            $purchaseRaw=trim((string)($_POST['purchase_price']??'0'));$purchaseCents=yw_money_input($purchaseRaw===''?'0':$purchaseRaw);$sku=trim((string)($_POST['sku']??''));$brand=trim((string)($_POST['brand']??''));$supplier=trim((string)($_POST['supplier_name']??''));
            if(!$name||!$categoryId||$priceCents<=0||!$sizes)throw new RuntimeException('Produktname, Kategorie, Preis oder Größen sind ungültig.');
            $categoryName=product_category_name($categoryId,'');if($categoryName==='')throw new RuntimeException('Bitte eine gültige Kategorie auswählen.');
            $description=trim((string)($_POST['description']??''));$color=trim((string)($_POST['color']??''));
            if(mb_strlen($name)>190||mb_strlen($description)>10000||mb_strlen($color)>120||mb_strlen($sku)>100||mb_strlen($brand)>120||mb_strlen($supplier)>160)throw new RuntimeException('Mindestens ein Produktfeld ist zu lang.');
            yw_admin_assert_unique_sku($sku,$id);
            $decorationType=(string)($_POST['decoration_type']??'none');if(!in_array($decorationType,['none','print','embroidery','mixed'],true))$decorationType='none';$decorationDetails=trim((string)($_POST['decoration_details']??''));if(mb_strlen($decorationDetails)>10000)throw new RuntimeException('Veredelungsbeschreibung ist zu lang.');
            $stmt=db()->prepare('SELECT image_path,image_back_path,image_sleeve_path FROM products WHERE id=?');$stmt->execute([$id]);$currentRow=$stmt->fetch();if(!$currentRow)throw new RuntimeException('Produkt wurde nicht gefunden.');$currentImageValue=$currentRow['image_path'];$currentImage=(string)($currentImageValue?:'');
            $image=upload_product_image($_FILES['image']??null,$currentImage);
            $imageBack=yw_store_product_view($_FILES['image_back']??null,(string)($currentRow['image_back_path']??''),!empty($_POST['delete_image_back']));
            $imageSleeve=yw_store_product_view($_FILES['image_sleeve']??null,(string)($currentRow['image_sleeve_path']??''),!empty($_POST['delete_image_sleeve']));
            $stmt=db()->prepare('UPDATE products SET name=?,category_id=?,category=?,description=?,price_cents=?,purchase_price_cents=?,vat_rate=?,sizes=?,color=?,image_path=?,image_back_path=?,image_sleeve_path=?,decoration_type=?,decoration_details=?,sku=?,brand=?,supplier_name=? WHERE id=?');
            try{$stmt->execute([$name,$categoryId,$categoryName,$description,$priceCents,$purchaseCents,$vatRate,json_encode($sizes,JSON_UNESCAPED_UNICODE),$color,$image,$imageBack?:null,$imageSleeve?:null,$decorationType,$decorationDetails,$sku,$brand,$supplier,$id]);}
            catch(Throwable $e){if($image!==$currentImage)delete_product_image($image);throw $e;}
            if($image!==$currentImage)delete_product_image($currentImage);
            audit_log('product.updated','product',$id);
            flash('success','Produkt wurde aktualisiert.');redirect('/admin?tab=products');
        }
        if ($action==='create_company') {
            $name=trim((string)$_POST['legal_name']); $shopName=trim((string)$_POST['shop_name']); $slug=strtolower(trim((string)$_POST['slug']));
            $approver=trim((string)$_POST['approver_name']);
            $primary=trim((string)$_POST['primary_color']);$accent=trim((string)$_POST['accent_color']);$productIds=yw_admin_product_ids($_POST['products']??null);$financingMode=(string)($_POST['financing_mode']??'company');$employeeShare=yw_decimal_input((string)($_POST['employee_share_percent']??'0'),0,100,'Eigenanteil');$approvalRequired=!empty($_POST['approval_required'])?1:0;$shippingEnabled=!empty($_POST['shipping_enabled'])?1:0;$shippingNetCents=yw_money_input((string)($_POST['shipping_net']??'0'));$shippingVat=yw_decimal_input((string)($_POST['shipping_vat_rate']??'19'),0,100,'Versand-MwSt.');$freeShippingGrossCents=yw_money_input((string)($_POST['free_shipping_gross']??'0'));$shippingPayer=(string)($_POST['shipping_payer']??'company');
            $deliveryMode=(string)($_POST['delivery_mode']??'company');$deliveryAddress=trim((string)($_POST['delivery_address']??''));$registrationMode=(string)($_POST['registration_mode']??'approval');
            if(!in_array($deliveryMode,['company','pickup'],true))throw new RuntimeException('Lieferart ist ungültig.');
            if($deliveryMode==='company' && ($deliveryAddress===''||mb_strlen($deliveryAddress)>1000))throw new RuntimeException('Bitte die Firmenlieferadresse angeben (max. 1000 Zeichen). Ohne Adresse kann niemand bestellen.');
            if(!in_array($registrationMode,['approval','open'],true))throw new RuntimeException('Registrierungsmodus ist ungültig. Firmen-PIN bitte nach dem Anlegen unter „Ausstattung & Budgets“ festlegen.');
            if (!$name) throw new RuntimeException('Firmenname fehlt.');
            if (!$shopName) throw new RuntimeException('Angezeigter Shopname fehlt.');
            if (!$approver) throw new RuntimeException('Verantwortlicher fehlt.');
            if (!$slug) throw new RuntimeException('Shop-Kurzname fehlt.');
            if(mb_strlen($name)>190||mb_strlen($shopName)>190||mb_strlen($approver)>190)throw new RuntimeException('Firmenname, Shopname oder Verantwortlicher ist zu lang.');
            if (yw_slug_reserved($slug)) throw new RuntimeException('Dieser Shop-Kurzname ist für eine Systemseite reserviert. Bitte einen anderen wählen, z. B. den Firmennamen.');
            if (!slug_ok($slug)) throw new RuntimeException('Shop-Kurzname ist ungültig. Erlaubt sind nur Kleinbuchstaben, Zahlen und Bindestriche, z. B. musterfirma.');
            if (!color_ok($primary)) throw new RuntimeException('Primärfarbe ist ungültig.');
            if (!color_ok($accent)) throw new RuntimeException('Akzentfarbe ist ungültig.');
            if(!in_array($financingMode,['company','percentage'],true)) throw new RuntimeException('Finanzierungsmodell ist ungültig.');if($financingMode==='company')$employeeShare=0;if(!in_array($shippingPayer,['company','employee'],true))$shippingPayer='company';
            $slugCheck=db()->prepare('SELECT id FROM companies WHERE slug=? LIMIT 1');$slugCheck->execute([$slug]);if($slugCheck->fetchColumn())throw new RuntimeException('Dieser Shop-Kurzname ist bereits vergeben.');
            db()->beginTransaction();
            $stmt=db()->prepare('INSERT INTO companies(slug,legal_name,shop_name,primary_color,accent_color,shop_password_hash,approver_name,approver_password_hash,approver_token,registration_token,financing_mode,employee_share_percent,approval_required,shipping_enabled,shipping_net_cents,shipping_vat_rate,free_shipping_gross_cents,shipping_payer,delivery_mode,delivery_address,registration_mode) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$slug,$name,$shopName,$primary,$accent,password_hash(random_token(),PASSWORD_DEFAULT),$approver,password_hash(random_token(),PASSWORD_DEFAULT),random_token(),random_token(),$financingMode,$employeeShare,$approvalRequired,$shippingEnabled,$shippingNetCents,$shippingVat,$freeShippingGrossCents,$shippingPayer,$deliveryMode,$deliveryAddress,$registrationMode]);
            $companyId=(int)db()->lastInsertId(); $cp=db()->prepare('INSERT INTO company_products(company_id,product_id) VALUES(?,?)'); foreach(array_unique($productIds) as $id)$cp->execute([$companyId,$id]);
            db()->commit();
            audit_log('company.created','company',$companyId,['company_id'=>$companyId]);
            flash('success','Firmen-Shop wurde angelegt. Nächster Schritt: unter „Mitarbeiter & Rollen“ den ersten Verantwortlichen anlegen.'); redirect('/admin?tab=companies&company='.$companyId);
        }
        if ($action==='update_brand') {
            $id=(int)$_POST['company_id']; $stmt=db()->prepare('SELECT * FROM companies WHERE id=?'); $stmt->execute([$id]); $company=$stmt->fetch(); if(!$company)throw new RuntimeException('Firma nicht gefunden.');
            $shopName=trim((string)$_POST['shop_name']); $primary=trim((string)$_POST['primary_color']); $accent=trim((string)$_POST['accent_color']); if(!$shopName||mb_strlen($shopName)>190||!color_ok($primary)||!color_ok($accent))throw new RuntimeException('Shopname oder Farben sind ungültig.');
            $oldLogo=(string)($company['logo_path']??'');$logo=upload_logo($_FILES['logo']??null,$oldLogo);
            $stmt=db()->prepare('UPDATE companies SET shop_name=?,logo_path=?,primary_color=?,accent_color=? WHERE id=?');
            try{$stmt->execute([$shopName,$logo,$primary,$accent,$id]);}
            catch(Throwable $e){if($logo!==$oldLogo)delete_company_logo($logo);throw $e;}
            if($logo!==$oldLogo)delete_company_logo($oldLogo);
            audit_log('company.brand_updated','company',$id,['company_id'=>$id]);
            unset($_SESSION['shop_access'][$id],$_SESSION['approval_access'][$id]); flash('success','Design wurde gespeichert.'); redirect('/admin?tab=companies&company='.$id);
        }
        if ($action==='update_employee_account') {
            $accountId=(int)($_POST['account_id']??0);
            $companyId=(int)($_POST['company_id']??0);
            $role=(string)($_POST['role']??'employee');
            $registrationStatus=(string)($_POST['registration_status']??'approved');
            $active=!empty($_POST['active'])?1:0;
            $profileId=yw_equipment_profile_id($companyId,$_POST['equipment_profile_id']??'',true);

            if(!in_array($role,['employee','manager'],true)) {
                throw new RuntimeException('Benutzerrolle ist ungültig.');
            }
            if(!in_array($registrationStatus,['pending','approved','rejected'],true)) {
                throw new RuntimeException('Registrierungsstatus ist ungültig.');
            }

            $accountCheck=db()->prepare('SELECT id,role,registration_status,active FROM employee_accounts WHERE id=? AND company_id=? LIMIT 1');$accountCheck->execute([$accountId,$companyId]);$currentAccount=$accountCheck->fetch();if(!$currentAccount)throw new RuntimeException('Mitarbeiterkonto wurde nicht gefunden.');
            $losesManagerAccess=(string)$currentAccount['role']==='manager' && !($role==='manager' && $registrationStatus==='approved' && $active===1);
            if($losesManagerAccess){
                $managerCheck=db()->prepare("SELECT COUNT(*) FROM employee_accounts WHERE company_id=? AND id<>? AND role='manager' AND registration_status='approved' AND active=1 AND (employment_start IS NULL OR employment_start<=CURDATE()) AND (employment_end IS NULL OR employment_end>=CURDATE())");
                $managerCheck->execute([$companyId,$accountId]);
                if(!(int)$managerCheck->fetchColumn())throw new RuntimeException('Der letzte aktuell zugangsberechtigte Verantwortliche kann nicht entfernt werden.');
            }

            $stmt=db()->prepare('UPDATE employee_accounts SET role=?,registration_status=?,active=?,equipment_profile_id=? WHERE id=? AND company_id=?');
            $stmt->execute([$role,$registrationStatus,$active,$profileId,$accountId,$companyId]);
            audit_log('employee.account_updated','employee',$accountId,['company_id'=>$companyId,'role'=>$role,'status'=>$registrationStatus,'active'=>$active]);

            flash('success','Mitarbeiterrolle wurde gespeichert.');
            redirect('/admin?tab=companies&company='.$companyId);
        }

        if ($action==='update_shop_rules') {
            $id=(int)($_POST['company_id']??0);
            $mode=(string)($_POST['financing_mode']??'company');
            $share=yw_decimal_input((string)($_POST['employee_share_percent']??'0'),0,100,'Eigenanteil');
            $approval=!empty($_POST['approval_required'])?1:0;

            $shippingEnabled=!empty($_POST['shipping_enabled'])?1:0;
            $shippingNetCents=yw_money_input((string)($_POST['shipping_net']??'0'));
            $shippingVat=yw_decimal_input((string)($_POST['shipping_vat_rate']??'19'),0,100,'Versand-MwSt.');
            $freeShippingGrossCents=yw_money_input((string)($_POST['free_shipping_gross']??'0'));
            $shippingPayer=(string)($_POST['shipping_payer']??'company');

            if(!in_array($mode,['company','percentage'],true)) throw new RuntimeException('Finanzierungsmodell ist ungültig.');
            if($mode==='company') $share=0;
            if(!in_array($shippingPayer,['company','employee'],true)) throw new RuntimeException('Versandkostenträger ist ungültig.');
            $companyCheck=db()->prepare('SELECT id FROM companies WHERE id=? LIMIT 1');$companyCheck->execute([$id]);if(!$companyCheck->fetchColumn())throw new RuntimeException('Firma wurde nicht gefunden.');

            $stmt=db()->prepare('UPDATE companies SET financing_mode=?, employee_share_percent=?, approval_required=?, shipping_enabled=?, shipping_net_cents=?, shipping_vat_rate=?, free_shipping_gross_cents=?, shipping_payer=? WHERE id=?');
            $stmt->execute([
                $mode,$share,$approval,$shippingEnabled,$shippingNetCents,$shippingVat,$freeShippingGrossCents,$shippingPayer,$id
            ]);

            audit_log('company.rules_updated','company',$id,['company_id'=>$id]);

            flash('success','Shopregeln und Versandkosten wurden gespeichert.');
            redirect('/admin?tab=companies&company='.$id);
        }

        if ($action==='update_company_product_customization') {
            $companyId=(int)($_POST['company_id']??0);
            $productId=(int)($_POST['product_id']??0);

            $stmt=db()->prepare('SELECT cp.*,p.name base_name FROM company_products cp JOIN products p ON p.id=cp.product_id WHERE cp.company_id=? AND cp.product_id=? LIMIT 1');
            $stmt->execute([$companyId,$productId]);
            $row=$stmt->fetch();
            if(!$row) throw new RuntimeException('Dieses Produkt ist dem Firmen-Shop nicht zugeordnet.');

            $customName=trim((string)($_POST['custom_name']??''));
            $customDescription=trim((string)($_POST['custom_description']??''));
            if(mb_strlen($customName)>190||mb_strlen($customDescription)>3000)throw new RuntimeException('Shopname oder Beschreibung des Produkts ist zu lang.');

            $priceRaw=trim((string)($_POST['price_override']??''));
            $priceOverride=null;
            if($priceRaw!==''){
                $priceOverride=yw_money_input($priceRaw);
            }

            $oldFront=(string)($row['preview_front_path']??'');
            $oldBack=(string)($row['preview_back_path']??'');

            $positions=(array)($_POST['decoration_position']??[]);
            $methods=(array)($_POST['decoration_method']??[]);
            $texts=(array)($_POST['decoration_text']??[]);
            $motifIds=(array)($_POST['decoration_motif_id']??[]);
            $sizes=(array)($_POST['decoration_size']??[]);
            $positionOptions=decoration_position_options();
            $methodOptions=decoration_method_options();
            $motifMap=[];
            foreach(yw_company_decoration_motifs($companyId,true) as $motif)$motifMap[(int)$motif['id']]=$motif;
            $decorations=[];

            $max=max(count($positions),count($methods),count($texts),count($motifIds));
            if($max>20)throw new RuntimeException('Pro Produkt sind höchstens 20 Veredelungspositionen möglich.');
            for($i=0;$i<$max;$i++){
                foreach([$positions[$i]??'', $methods[$i]??'', $texts[$i]??'', $motifIds[$i]??'0'] as $value)if(!is_scalar($value))throw new RuntimeException('Eine Veredelungsposition ist ungültig.');
                $position=(string)($positions[$i]??'');
                $method=(string)($methods[$i]??'');
                $text=trim((string)($texts[$i]??''));
                $motifRaw=(string)($motifIds[$i]??'0');
                if(!preg_match('/^\d+$/D',$motifRaw))throw new RuntimeException('Die Verknüpfung zum Motivbestand ist ungültig.');
                $motifId=(int)$motifRaw;
                $size=(string)($sizes[$i]??'m');if(!isset(yw_decoration_size_options()[$size]))$size='m';

                if($position==='' && $method==='' && $text==='' && $motifId===0) continue;
                if(!isset($positionOptions[$position])||!isset($methodOptions[$method]))throw new RuntimeException('Eine Veredelungsposition ist ungültig.');
                if(mb_strlen($text)>500)throw new RuntimeException('Eine Motivbezeichnung ist zu lang.');
                if($motifId>0){
                    $motif=$motifMap[$motifId]??null;
                    if(!$motif)throw new RuntimeException('Ein gewähltes Veredelungsmotiv gehört nicht zu dieser Firma.');
                    if((string)$motif['method']!==$method)throw new RuntimeException('Veredelungsmotiv und gewähltes Verfahren passen nicht zusammen.');
                }

                $decorations[]=[
                    'position'=>$position,
                    'method'=>$method,
                    'text'=>$text,
                    'motif_id'=>$motifId,
                    'size'=>$size,
                ];
            }

            $frontBase=!empty($_POST['delete_preview_front'])?'':$oldFront;
            $backBase=!empty($_POST['delete_preview_back'])?'':$oldBack;
            $newUploads=[];
            try{
                $front=upload_company_product_preview($_FILES['preview_front']??null,$frontBase);
                if($front!=='' && $front!==$oldFront)$newUploads[]=$front;
                $back=upload_company_product_preview($_FILES['preview_back']??null,$backBase);
                if($back!=='' && $back!==$oldBack)$newUploads[]=$back;
            }catch(Throwable $e){
                foreach(array_unique($newUploads) as $newUpload)delete_company_product_preview($newUpload);
                throw $e;
            }

            $stmt=db()->prepare('UPDATE company_products SET custom_name=?,custom_description=?,price_override_cents=?,preview_front_path=?,preview_back_path=?,decorations_json=? WHERE company_id=? AND product_id=?');
            try{
                $stmt->execute([
                    $customName,
                    $customDescription,
                    $priceOverride,
                    $front,
                    $back,
                    $decorations ? json_encode($decorations,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : null,
                    $companyId,
                    $productId
                ]);
            }catch(Throwable $e){
                foreach(array_unique($newUploads) as $newUpload)delete_company_product_preview($newUpload);
                throw $e;
            }
            if($oldFront!=='' && $oldFront!==$front)delete_company_product_preview($oldFront);
            if($oldBack!=='' && $oldBack!==$back)delete_company_product_preview($oldBack);
            audit_log('company_product.updated','product',$productId,['company_id'=>$companyId]);

            flash('success','Shop-Produkt „'.($customName!==''?$customName:$row['base_name']).'“ wurde individualisiert.');
            redirect('/admin?tab=companies&company='.$companyId);
        }

        if ($action==='save_decoration_motif') {
            $companyId=(int)($_POST['company_id']??0);
            $motifId=(int)($_POST['motif_id']??0);
            $name=trim((string)($_POST['motif_name']??''));
            $method=(string)($_POST['motif_method']??'');
            $notes=trim((string)($_POST['motif_notes']??''));
            $stockRaw=trim((string)($_POST['stock_qty']??'0'));
            $reorderRaw=trim((string)($_POST['reorder_level']??'0'));
            if($name===''||mb_strlen($name)>190||mb_strlen($notes)>500)throw new RuntimeException('Motivname fehlt oder die Notiz ist zu lang.');
            if(!array_key_exists($method,decoration_method_options()))throw new RuntimeException('Veredelungsverfahren ist ungültig.');
            if(!ctype_digit($stockRaw)||!ctype_digit($reorderRaw)||(int)$stockRaw>1000000||(int)$reorderRaw>1000000)throw new RuntimeException('Bestand oder Mindestbestand ist ungültig.');
            $companyCheck=db()->prepare('SELECT id FROM companies WHERE id=? LIMIT 1');$companyCheck->execute([$companyId]);if(!$companyCheck->fetchColumn())throw new RuntimeException('Firma wurde nicht gefunden.');
            $duplicate=db()->prepare('SELECT id FROM yw_decoration_motifs WHERE company_id=? AND name=? AND method=? AND id<>? LIMIT 1');
            $duplicate->execute([$companyId,$name,$method,$motifId]);if($duplicate->fetchColumn())throw new RuntimeException('Dieses Motiv ist für das Verfahren bereits vorhanden.');
            $oldFile='';
            if($motifId>0){$fq=db()->prepare('SELECT file_path FROM yw_decoration_motifs WHERE id=? AND company_id=?');$fq->execute([$motifId,$companyId]);$oldFile=(string)($fq->fetchColumn()?:'');}
            if(!empty($_POST['delete_motif_file'])&&$oldFile!==''){yw_delete_motif_file($oldFile);$oldFile='';}
            $motifFile=yw_store_motif_file($_FILES['motif_file']??null,$oldFile);
            $values=[$name,$method,isset($_POST['track_stock'])?1:0,(int)$stockRaw,(int)$reorderRaw,$notes,isset($_POST['active'])?1:0,$motifFile];
            if($motifId>0){
                $current=db()->prepare('SELECT method FROM yw_decoration_motifs WHERE id=? AND company_id=? LIMIT 1');$current->execute([$motifId,$companyId]);$currentMethod=$current->fetchColumn();if($currentMethod===false)throw new RuntimeException('Veredelungsmotiv wurde nicht gefunden.');
                if((string)$currentMethod!==$method){
                    $needle='%"motif_id":'.$motifId.'%';
                    $linkedProducts=db()->prepare('SELECT COUNT(*) FROM company_products WHERE company_id=? AND decorations_json LIKE ?');$linkedProducts->execute([$companyId,$needle]);
                    $linkedOrders=db()->prepare('SELECT COUNT(*) FROM order_items i JOIN orders o ON o.id=i.order_id WHERE o.company_id=? AND i.decoration_snapshot_json LIKE ?');$linkedOrders->execute([$companyId,$needle]);
                    if((int)$linkedProducts->fetchColumn()>0||(int)$linkedOrders->fetchColumn()>0)throw new RuntimeException('Das Verfahren eines bereits verknüpften Motivs kann nicht geändert werden. Lege dafür ein neues Motiv an.');
                }
                $stmt=db()->prepare('UPDATE yw_decoration_motifs SET name=?,method=?,track_stock=?,stock_qty=?,reorder_level=?,notes=?,active=?,file_path=? WHERE id=? AND company_id=?');
                $stmt->execute([...$values,$motifId,$companyId]);if(!$stmt->rowCount()){$check=db()->prepare('SELECT id FROM yw_decoration_motifs WHERE id=? AND company_id=?');$check->execute([$motifId,$companyId]);if(!$check->fetchColumn())throw new RuntimeException('Veredelungsmotiv wurde nicht gefunden.');}
                $savedId=$motifId;$auditAction='decoration_motif.updated';
            }else{
                $stmt=db()->prepare('INSERT INTO yw_decoration_motifs(company_id,name,method,track_stock,stock_qty,reorder_level,notes,active,file_path) VALUES(?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$companyId,...$values]);$savedId=(int)db()->lastInsertId();$auditAction='decoration_motif.created';
            }
            audit_log($auditAction,'decoration_motif',$savedId,['company_id'=>$companyId,'stock_qty'=>(int)$stockRaw]);
            flash('success','Veredelungsmotiv wurde gespeichert.');redirect('/admin?tab=companies&company='.$companyId);
        }

        if ($action==='save_print_zones') { yw_save_print_zones_action(); }
        if (str_starts_with($action,'api_key_')) { yw_admin_api_keys_action($action); }
        if (str_starts_with($action,'katalog_')) { yw_admin_katalog_action($action); }
        if ($action==='save_company_note') {
            $companyId=(int)($_POST['company_id']??0);$note=trim((string)($_POST['internal_note']??''));
            if(mb_strlen($note)>5000)throw new RuntimeException('Die Notiz ist zu lang (maximal 5000 Zeichen).');
            $stmt=db()->prepare('UPDATE companies SET internal_note=? WHERE id=?');$stmt->execute([$note!==''?$note:null,$companyId]);
            audit_log('company.note_updated','company',$companyId);flash('success','Notiz gespeichert.');redirect(yw_customer_url($companyId));
        }
        if ($action==='save_order_note') {
            $orderId=(int)($_POST['order_id']??0);$note=trim((string)($_POST['internal_note']??''));
            if(mb_strlen($note)>3000)throw new RuntimeException('Die Notiz ist zu lang (maximal 3000 Zeichen).');
            $stmt=db()->prepare('UPDATE orders SET internal_note=? WHERE id=?');$stmt->execute([$note!==''?$note:null,$orderId]);
            if(!$stmt->rowCount()){$chk=db()->prepare('SELECT id FROM orders WHERE id=?');$chk->execute([$orderId]);if(!$chk->fetchColumn())throw new RuntimeException('Auftrag wurde nicht gefunden.');}
            audit_log('order.note_updated','order',$orderId);flash('success','Notiz gespeichert.');redirect(yw_order_url($orderId));
        }

        if ($action==='update_collection') {
            $id=(int)($_POST['company_id']??0);
            $productIds=yw_admin_product_ids($_POST['products']??null);
            $companyCheck=db()->prepare('SELECT id FROM companies WHERE id=? LIMIT 1');$companyCheck->execute([$id]);if(!$companyCheck->fetchColumn())throw new RuntimeException('Firma wurde nicht gefunden.');

            $stmt=db()->prepare('SELECT product_id,preview_front_path,preview_back_path FROM company_products WHERE company_id=?');
            $stmt->execute([$id]);
            $existingRows=$stmt->fetchAll();
            $existingIds=array_map('intval',array_column($existingRows,'product_id'));
            $previewsToDelete=[];

            db()->beginTransaction();

            foreach($existingRows as $row){
                $pid=(int)$row['product_id'];
                if(!in_array($pid,$productIds,true)){
                    $previewsToDelete[]=(string)($row['preview_front_path']??'');
                    $previewsToDelete[]=(string)($row['preview_back_path']??'');
                    $delProfile=db()->prepare('DELETE FROM yw_equipment_profile_products WHERE product_id=? AND profile_id IN (SELECT id FROM yw_equipment_profiles WHERE company_id=?)');
                    $delProfile->execute([$pid,$id]);
                    $del=db()->prepare('DELETE FROM company_products WHERE company_id=? AND product_id=?');
                    $del->execute([$id,$pid]);
                }
            }

            $insert=db()->prepare('INSERT INTO company_products(company_id,product_id) VALUES(?,?)');
            foreach($productIds as $pid){
                if(!in_array($pid,$existingIds,true)) $insert->execute([$id,$pid]);
            }

            db()->commit();
            foreach(array_unique($previewsToDelete) as $preview)delete_company_product_preview($preview);
            audit_log('company.collection_updated','company',$id,['company_id'=>$id,'product_count'=>count($productIds)]);
            flash('success','Firmenkollektion wurde gespeichert. Bestehende Individualisierungen wurden beibehalten.');
            redirect('/admin?tab=companies&company='.$id);
        }
        if ($action==='add_invite') {
            $name=trim((string)($_POST['employee_name']??'')); if(!$name||mb_strlen($name)>160)throw new RuntimeException('Bitte einen Mitarbeiternamen mit höchstens 160 Zeichen eingeben.');$companyId=(int)($_POST['company_id']??0);$companyCheck=db()->prepare('SELECT id FROM companies WHERE id=? LIMIT 1');$companyCheck->execute([$companyId]);if(!$companyCheck->fetchColumn())throw new RuntimeException('Firma wurde nicht gefunden.'); $stmt=db()->prepare('INSERT INTO invites(company_id,employee_name,token) VALUES(?,?,?)'); $stmt->execute([$companyId,$name,random_token()]);
            audit_log('invite.created','invite',(int)db()->lastInsertId(),['company_id'=>$companyId]);flash('success','Mitarbeiterzugang wurde erstellt.'); redirect('/admin?tab=companies&company='.$companyId);
        }
        if ($action==='fulfillment') {
            $status=(string)($_POST['status']??'');
            if(!array_key_exists($status,yw_production_statuses())) throw new RuntimeException('Status ungültig.');
            $orderId=(int)($_POST['order_id']??0);
            $orderQuery=db()->prepare("SELECT c.slug FROM orders o JOIN companies c ON c.id=o.company_id WHERE o.id=? AND o.status='approved' LIMIT 1");
            $orderQuery->execute([$orderId]);$companySlug=$orderQuery->fetchColumn();
            if($companySlug===false)throw new RuntimeException('Der freigegebene Auftrag wurde nicht gefunden.');
            $stmt=db()->prepare("UPDATE orders SET fulfillment_status=?,production_updated_at=NOW() WHERE id=? AND status='approved' AND fulfillment_status<>?");
            $stmt->execute([$status,$orderId,$status]);
            if($stmt->rowCount()){
                audit_log('order.fulfillment_updated','order',$orderId,['status'=>$status]);
                notify_order_employee($orderId,'Status deiner Bestellung #'.$orderId,'Der Bearbeitungsstatus deiner Bestellung wurde geändert: '.company_portal_fulfillment_status($status)."\n\nBestellstatus ansehen: ".workwearcenter_url((string)$companySlug.'/bestellungen'));
                flash('success','Auftragsstatus gespeichert.');
            }else flash('success','Der Auftrag hatte diesen Status bereits. Es wurde keine neue E-Mail versendet.');
            redirect(!empty($_POST['return_order'])?yw_order_url((int)$_POST['return_order']):'/admin?tab=orders');
        }
        if ($action==='admin_add_manager') {
            $companyId=(int)($_POST['company_id']??0);
            $name=trim((string)($_POST['name']??''));$email=strtolower(trim((string)($_POST['email']??'')));
            $role=(string)($_POST['role']??'manager');if(!in_array($role,['manager','employee'],true))$role='manager';
            if($name===''||mb_strlen($name)>160)throw new RuntimeException('Bitte einen Namen mit höchstens 160 Zeichen eingeben.');
            if(mb_strlen($email)>190||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Bitte eine gültige E-Mail-Adresse eingeben.');
            $q=db()->prepare('SELECT id,slug FROM companies WHERE id=? LIMIT 1');$q->execute([$companyId]);$company=$q->fetch();if(!$company)throw new RuntimeException('Firma wurde nicht gefunden.');
            $q=db()->prepare('SELECT id FROM employee_accounts WHERE company_id=? AND email=? LIMIT 1');$q->execute([$companyId,$email]);if($q->fetchColumn())throw new RuntimeException('Diese E-Mail-Adresse ist in diesem Firmen-Shop bereits registriert. Rolle bitte in der Liste darunter ändern.');
            $profileId=yw_default_equipment_profile_id($companyId);
            $q=db()->prepare("INSERT INTO employee_accounts(company_id,name,email,password_hash,role,registration_status,active,equipment_profile_id) VALUES(?,?,?,?,?,'approved',1,?)");
            $q->execute([$companyId,$name,$email,password_hash(random_token(),PASSWORD_DEFAULT),$role,$profileId]);
            $accountId=(int)db()->lastInsertId();
            yw_send_password_link($accountId,$name,$email,(string)$company['slug'],true,true);
            audit_log('employee.created_by_admin','employee',$accountId,['company_id'=>$companyId,'role'=>$role]);
            flash('success',($role==='manager'?'Verantwortlicher':'Mitarbeiter').' wurde angelegt. Der Link zum Festlegen des Passworts wurde an '.$email.' gesendet (60 Minuten gültig).');
            redirect('/admin?tab=companies&company='.$companyId);
        }
        if ($action==='toggle_company') {
            $companyId=(int)($_POST['company_id']??0);
            $stmt=db()->prepare('UPDATE companies SET active=IF(active=1,0,1) WHERE id=?');$stmt->execute([$companyId]);
            if(!$stmt->rowCount())throw new RuntimeException('Firma wurde nicht gefunden.');
            $q=db()->prepare('SELECT active FROM companies WHERE id=?');$q->execute([$companyId]);$nowActive=(int)$q->fetchColumn()===1;
            audit_log('company.status_toggled','company',$companyId,['company_id'=>$companyId,'active'=>$nowActive]);
            flash('success',$nowActive?'Firmen-Shop ist wieder aktiv.':'Firmen-Shop wurde deaktiviert. Anmeldung, Registrierung und Bestellungen sind gesperrt; alle Daten bleiben erhalten.');
            redirect('/admin?tab=companies&company='.$companyId);
        }
        if ($action==='employee_anonymize') {
            $companyId=(int)($_POST['company_id']??0);$accountId=(int)($_POST['account_id']??0);
            try{yw_anonymize_employee($companyId,$accountId);}catch(DomainException $e){throw new RuntimeException($e->getMessage());}
            flash('success','Personenbezogene Daten wurden entfernt. Bestellungen bleiben für die Buchhaltung erhalten.');
            redirect('/admin?tab=companies&company='.$companyId);
        }
        if ($action==='delete_inquiry') {
            $id=(int)($_POST['inquiry_id']??0);
            $stmt=db()->prepare('DELETE FROM inquiries WHERE id=?');$stmt->execute([$id]);
            if(!$stmt->rowCount())throw new RuntimeException('Anfrage wurde nicht gefunden.');
            audit_log('inquiry.deleted','inquiry',$id);
            flash('success','Anfrage wurde endgültig gelöscht.');
            redirect('/admin?tab=inquiries');
        }
        throw new RuntimeException('Aktion unbekannt.');
    } catch(Throwable $exception) {
        if(db()->inTransaction())db()->rollBack();
        if($exception instanceof PDOException){error_log('WorkwearCenter admin database action failed: '.$exception->getMessage());$message='Speichern fehlgeschlagen. Bitte Eingaben prüfen und erneut versuchen.';}
        else $message=$exception->getMessage();
        flash('error',$message);redirect('/admin?tab=' . urlencode((string)($_POST['return_tab']??'orders')));
    }
}


function admin_backend_style(): string {
    return '<style>
    .admin-topbar{background:#10263a;border-bottom:4px solid var(--accent);position:sticky;top:0;z-index:80;box-shadow:0 5px 18px rgba(16,24,40,.12)}
    .admin-topbar-inner{min-height:88px;display:flex;align-items:center;gap:20px}
    .admin-topbar-title{display:flex;flex-direction:column;line-height:1.2;margin-right:auto;color:#fff}.admin-topbar-title strong{font-size:1.3rem;color:#fff}
    .admin-topbar-title small{color:rgba(255,255,255,.66);margin-top:4px}.admin-topbar .btn{border-color:rgba(255,255,255,.32);background:rgba(255,255,255,.08);color:#fff}.admin-topbar .btn:hover{background:#fff;color:#10263a}
    .admin-shell{display:grid;grid-template-columns:240px minmax(0,1fr);min-height:calc(100vh - 92px);background:linear-gradient(90deg,#fff 239px,#e6e8ec 239px,#e6e8ec 240px,#f7f7f8 240px)}
    .admin-sidebar{background:#fff;color:#172033;padding:24px 16px;position:sticky;top:92px;max-height:calc(100vh - 92px);overflow:auto;box-sizing:border-box;border-right:1px solid #e6e8ec}
    .admin-menu-title{font-size:.75rem;letter-spacing:.12em;text-transform:uppercase;color:#8a93a2;padding:0 12px 10px}
    .admin-menu{display:flex;flex-direction:column;gap:5px}
    .admin-menu a{display:flex;align-items:center;gap:11px;padding:11px 12px;border-radius:10px;text-decoration:none;color:#4d596a;font-weight:650;border-left:3px solid transparent}
    .admin-menu a:hover{background:#f7f8fa;color:#172033}
    .admin-menu a.active{background:var(--accent);color:#fff}
    .admin-menu .sep{height:1px;background:#e7e9ed;margin:10px 8px}
    .admin-menu .count{margin-left:auto;min-width:22px;height:22px;padding:0 6px;border-radius:999px;background:#fff1e8;color:#c94f00;display:grid;place-items:center;font-size:.75rem}
    .admin-menu a.active .count{background:rgba(255,255,255,.22);color:#fff}
    .admin-content{padding:34px;min-width:0;max-width:1600px;width:100%;box-sizing:border-box}
    .admin-page-head{margin-bottom:24px}
    .admin-page-head h1{margin:0 0 7px}
    .admin-page-head p{margin:0;color:#667085}
    .admin-location-head{margin:-4px 0 28px;padding:26px 30px;background:#fff;border:1px solid #e5e9ef;border-left:6px solid var(--accent);border-radius:17px;box-shadow:0 10px 30px rgba(16,24,40,.05)}
    .admin-location-kicker{font-size:.76rem;letter-spacing:.13em;font-weight:900;color:var(--accent);margin-bottom:8px}
    .admin-location-head h1{font-size:2.35rem;line-height:1.05;margin:0 0 8px;color:#172033}
    .admin-location-head p{margin:0;color:#667085;font-size:1rem}

    .admin-dashboard-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px;margin-bottom:26px}
    .admin-dashboard-card{display:flex;flex-direction:column;background:#fff;border:1px solid #e6e9ef;border-radius:16px;padding:22px;box-shadow:0 8px 26px rgba(16,24,40,.05);text-decoration:none;min-height:132px}
    .admin-dashboard-card strong{display:block;font-size:2rem;line-height:1;margin:10px 0 8px;color:#172033}
    .admin-dashboard-card span{color:#667085}.admin-dashboard-card small{color:#8a93a2}.admin-dashboard-card.attention{border-color:#f1b8a0;background:#fff9f5}
    .control-head{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:24px}.control-head h1{font-size:2.35rem;margin:4px 0}.control-head p{margin:0;color:#667085}
    .control-grid{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(310px,.75fr);gap:18px;margin-bottom:24px}.control-grid>.card{height:max-content}
    .work-task{display:grid;grid-template-columns:38px minmax(0,1fr) auto;align-items:center;gap:13px;padding:14px 0;border-top:1px solid #edf0f3}.work-task:first-of-type{border-top:0}.work-task small{display:block;color:#667085;margin-top:3px}.work-task.danger strong{color:#a02a1e}.task-icon{width:36px;height:36px;border-radius:10px;background:#fff2e8;color:#d65d0a;display:grid;place-items:center;font-weight:900}.task-icon svg{width:18px;height:18px}.task-actions{display:flex;gap:7px}.task-actions form{margin:0}.empty.compact{padding:24px 12px}.flow-row{display:grid;grid-template-columns:minmax(112px,132px) minmax(90px,1fr) 28px;gap:12px;align-items:center;padding:8px 0;color:#667085}.flow-row>span:first-child{min-width:0;overflow-wrap:anywhere}.flow-bar{height:8px;border-radius:999px;background:#edf0f3;overflow:hidden}.flow-bar i{display:block;height:100%;border-radius:inherit;background:var(--accent)}
    .filter-bar{display:grid;grid-template-columns:repeat(4,minmax(0,1fr)) auto;gap:10px;align-items:end;padding:16px;background:#f8fafc;border:1px solid #e7eaf0;border-radius:14px;margin-bottom:18px}.filter-bar label{font-size:.78rem;font-weight:750;display:grid;gap:6px}.bulk-bar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:13px;background:#fff7ef;border:1px solid #f5d3b9;border-radius:12px;margin-bottom:12px}.bulk-bar select{width:auto;min-width:210px}.order-select{width:18px;height:18px}.release-checks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.release-check{padding:14px;border:1px solid #e5e9ef;border-radius:12px;background:#f8fafc}.release-check strong,.release-check span{display:block}.release-check span{color:#667085;font-size:.82rem;margin-top:4px}.release-check.ok strong{color:#067647}.release-check.warn{background:#fff8ed;border-color:#f1d2a3}.release-check.warn strong{color:#925a00}
    .inquiry-list{display:flex;flex-direction:column;gap:14px}
    .inquiry-card{background:#fff;border:1px solid #e6e9ef;border-radius:15px;padding:20px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:20px}
    .inquiry-card.new{border-left:5px solid var(--accent)}
    .inquiry-meta{display:flex;gap:12px;flex-wrap:wrap;color:#667085;font-size:.9rem;margin:8px 0 12px}
    .inquiry-message{background:#f7f8fa;border-radius:10px;padding:14px;white-space:pre-wrap}
    .settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}
    .settings-card{background:#fff;border:1px solid #e6e9ef;border-radius:16px;padding:22px}
    .company-kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:16px 0}
    .company-kpi{padding:13px 14px;border:1px solid #e6e9ef;border-radius:12px;background:#f8fafc}
    .company-kpi strong{display:block;font-size:1.15rem;margin-bottom:4px;color:#172033}
    .company-kpi span{display:block;font-size:.78rem;color:#667085;line-height:1.25}
    .shop-performance{margin-top:26px}
    .shop-performance table{width:100%;border-collapse:collapse}
    .shop-performance th,.shop-performance td{padding:13px 14px;border-bottom:1px solid #e7eaf0;text-align:left;vertical-align:middle}
    .shop-performance th{font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:#667085;background:#f8fafc}
    .shop-performance tbody tr:hover{background:#fafbfc}
    .shop-performance .money-cell{font-weight:850;white-space:nowrap}
    .manager-stat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin:22px 0 30px}
    .manager-stat{background:#fff;border:1px solid #e4e8ef;border-radius:15px;padding:18px;box-shadow:0 7px 20px rgba(15,23,42,.04)}
    .manager-stat strong{display:block;font-size:1.5rem;margin-bottom:5px}
    .manager-stat span{color:#667085;font-size:.87rem}
    .employee-role-form{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:12px;padding:16px 0;border-bottom:1px solid #e6e9ef;align-items:end}
    .employee-role-form:last-of-type{border-bottom:0}
    .employee-role-identity{grid-column:1/-1;min-width:0;overflow-wrap:anywhere}
    .employee-role-form label{min-width:0;margin:0}
    .employee-role-form select{width:100%;min-width:0}
    .employee-role-actions{grid-column:1/-1;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
    .employee-role-actions .check{margin:0}
    .employee-quick-actions{display:flex;justify-content:flex-end;gap:8px;padding:10px 0 16px;border-bottom:1px solid #e6e9ef}.employee-quick-actions form{margin:0}.company-detail-head{display:flex;justify-content:space-between;gap:10px;margin-bottom:14px}

    @media(max-width:1050px){
      .admin-shell{grid-template-columns:1fr;background:#f7f7f8}
      .admin-sidebar{position:relative;top:0;height:auto}
      .admin-menu{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))}
      .admin-dashboard-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.company-kpi-grid,.manager-stat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
      .control-grid{grid-template-columns:1fr}.filter-bar{grid-template-columns:repeat(2,minmax(0,1fr))}
      .admin-content{padding:22px}
    }
    @media(max-width:650px){
      .admin-menu{grid-template-columns:1fr}
      .admin-dashboard-grid,.settings-grid,.company-kpi-grid,.manager-stat-grid{grid-template-columns:1fr}
      .inquiry-card{grid-template-columns:1fr}
      .control-head{align-items:flex-start;flex-direction:column}.filter-bar{grid-template-columns:1fr}.work-task{grid-template-columns:36px minmax(0,1fr)}.task-actions{grid-column:1/-1}.release-checks{grid-template-columns:1fr}
      .employee-role-form{grid-template-columns:1fr}
      .employee-role-identity,.employee-role-actions{grid-column:1}
      .employee-role-actions{align-items:stretch;flex-direction:column}
      .employee-role-actions .btn{width:100%}
    }
    
    /* Firmenkollektion: individuelle Produktvarianten */
    .collection-config-section{margin-top:24px;padding-top:22px;border-top:1px solid #e6e9ef}
    .collection-config-title{margin-bottom:14px}
    .collection-config-title h3{margin:0 0 5px;font-size:1.2rem}
    .collection-config-title p{margin:0;color:#667085}
    .collection-product-editor{border:1px solid #e1e6ed;border-radius:14px;background:#fff;margin:10px 0;overflow:hidden}
    .collection-product-editor>summary{cursor:pointer;padding:13px 15px;list-style:none}
    .collection-product-editor>summary::-webkit-details-marker{display:none}
    .collection-product-summary{display:grid;grid-template-columns:52px minmax(0,1fr) auto;gap:12px;align-items:center}
    .collection-product-summary small{display:block;color:#667085;margin-top:3px}
    .collection-product-mini{width:52px;height:52px;border-radius:10px;background:#f4f6f8;display:grid;place-items:center;overflow:hidden}
    .collection-product-mini img{width:100%;height:100%;object-fit:contain}
    .collection-configured,.collection-standard{font-size:.75rem;font-weight:850;padding:6px 9px;border-radius:999px}
    .collection-configured{background:#ecfdf3;color:#067647}
    .collection-standard{background:#f2f4f7;color:#667085}
    .collection-product-body{padding:18px;border-top:1px solid #e8ebf0;background:#fbfcfd}
    .collection-base-info{padding:12px 14px;border-radius:10px;background:#eef5ff;color:#344054;margin-bottom:14px}
    .collection-preview-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
    .collection-preview-card{border:1px solid #e1e6ed;border-radius:13px;padding:13px;background:#fff}
    .collection-preview-image{height:190px;border-radius:10px;background:#f5f7fa;display:grid;place-items:center;overflow:hidden;margin-bottom:10px;color:#98a2b3}
    .collection-preview-image img{width:100%;height:100%;object-fit:contain}
    .collection-preview-card>strong{display:block;margin-bottom:3px}
    .collection-preview-card>small{display:block;color:#667085;margin-bottom:10px}
    .collection-decoration-head{margin-top:6px}
    .collection-decoration-head h4{margin:0 0 4px}
    .collection-decoration-head p{margin:0;color:#667085}
    .collection-decoration-table{display:flex;flex-direction:column;gap:8px}
    .collection-decoration-labels,.collection-decoration-row{display:grid;grid-template-columns:140px 110px minmax(160px,1fr) minmax(200px,1fr) 100px;gap:8px}
    .collection-decoration-labels{font-size:.74rem;text-transform:uppercase;letter-spacing:.06em;color:#667085;font-weight:850;padding:0 3px}
    .collection-decoration-row select,.collection-decoration-row input{height:42px;border:1px solid #d8dee8;border-radius:9px;background:#fff;padding:0 10px;min-width:0}
    .motif-list{display:grid;gap:10px;margin:16px 0}.motif-editor{border:1px solid #e1e6ed;border-radius:10px;background:#fbfcfd}.motif-editor>summary{cursor:pointer;padding:13px 15px;font-weight:800}.motif-editor-body{padding:16px;border-top:1px solid #e6eaf0}.motif-fields{display:grid;grid-template-columns:minmax(180px,1.4fr) minmax(140px,.8fr) repeat(2,minmax(120px,.65fr));gap:10px}.motif-checks{display:flex;gap:18px;flex-wrap:wrap}

    .company-product-visual{position:relative}
    .product-side-image{display:none!important;width:100%;height:100%;object-fit:contain}
    .product-side-image.active{display:block!important}
    .product-view-switch{position:absolute;left:50%;bottom:11px;transform:translateX(-50%);display:flex;gap:4px;padding:4px;background:rgba(255,255,255,.92);border:1px solid #e2e7ee;border-radius:999px;box-shadow:0 6px 18px rgba(15,23,42,.10)}
    .product-view-switch button{border:0;background:transparent;padding:6px 10px;border-radius:999px;font-weight:800;font-size:.75rem;cursor:pointer;color:#667085}
    .product-view-switch button.active{background:var(--shop-primary);color:#fff}
    .company-decoration-structured{display:flex;flex-direction:column;gap:7px}
    .company-decoration-structured>strong{margin-bottom:2px}
    .decoration-line{display:flex;justify-content:space-between;gap:12px;font-size:.82rem}
    .decoration-line span{color:var(--shop-muted)}
    .decoration-line b{text-align:right;font-weight:750}

    /* Bestellbutton explizit setzen, damit keine alte CSS-Regel ihn weiß überschreibt */
    .shop-order-panel .shop-submit{
      width:100%!important;
      min-height:50px!important;
      border:0!important;
      border-radius:12px!important;
      background:var(--shop-accent,#ff6b00)!important;
      color:#fff!important;
      font-weight:850!important;
      font-size:1rem!important;
      cursor:pointer!important;
      display:flex!important;
      align-items:center!important;
      justify-content:center!important;
      padding:0 18px!important;
      box-shadow:0 10px 24px rgba(15,23,42,.11)!important;
    }
    .shop-order-panel .shop-submit:hover{filter:brightness(.96);transform:translateY(-1px)}

    @media(max-width:760px){
      .collection-preview-grid{grid-template-columns:1fr}
      .collection-decoration-labels{display:none}
      .collection-decoration-row{grid-template-columns:1fr}
      .motif-fields{grid-template-columns:1fr}
    }
</style>';
}


function admin_context_header(string $tab): string {
    $data=[
        'dashboard'=>['Übersicht','Alle wichtigen Kennzahlen und offenen Vorgänge auf einen Blick.'],
        'corporate'=>['Regeln & Budget','Jetzt Teil der Kundenakte.'],
        'production'=>['Produktion','Auftragsdetails, Materialübersicht und Packlisten.'],
        'account'=>['Mein Konto & Administratoren','Passwort, Zwei-Faktor-Anmeldung und weitere Admin-Zugänge.'],
        'handover'=>['Übergabe an die Auftragsverwaltung','Freigegebene Aufträge werden automatisch an deine Auftragsverwaltung übertragen.'],
        'system'=>['System','Version und Betriebshinweise.'],
        'inquiries'=>['Anfragen','Neue Interessenten und Shop-Anfragen bearbeiten.'],
        'orders'=>['Aufträge','Bestellungen, Freigaben und Bearbeitungsstatus verwalten.'],
        'companies'=>['Kunden','Firmenshops: Kollektion, Mitarbeiter, Regeln, Aufträge und Zugänge.'],
        'new'=>['Neuen Kunden anlegen','Einen neuen Firmenshop mit Kollektion und Shopregeln einrichten.'],
        'products'=>['Produkte','Produktkatalog, Kategorien, Preise und Veredelungen verwalten.'],
        'content'=>['Inhalte','Texte der öffentlichen Website bearbeiten.'],
        'design'=>['Design','Logo, Farben, Bilder und Wartungsmodus verwalten.'],
        'audit'=>['Aktivitätsprotokoll','Sicherheits- und auftragsrelevante Änderungen nachvollziehen.'],
        'guide'=>['Anleitung','Hinweise zur Bedienung des WorkwearCenters.'],
        'suche'=>['Suche','Kunden, Mitarbeitende, Aufträge und Produkte finden.'],
        'api'=>['API-Zugänge','Schlüssel für Auftragszentrale, Fan-Bande und Partner.'],
        'katalog'=>['Lieferantenkatalog','Produkte aus der Daiber-Liste anlegen und Bilder automatisch zuordnen.'],
    ];
    if($tab==='companies'&&!empty($_GET['company']))$data['companies']=['Kundenakte','Alles zu diesem Kunden an einem Ort.'];
    if($tab==='orders'&&!empty($_GET['auftrag']))$data['orders']=['Auftrag','Positionen, Produktion, Übergabe und Verlauf.'];
    [$title,$subtitle]=$data[$tab]??$data['dashboard'];
    $sub='';
    $groups=['website'=>['content'=>'Inhalte','design'=>'Design'],'produkte'=>['products'=>'Produkte','katalog'=>'Lieferantenkatalog'],'system'=>['system'=>'Systemstatus','handover'=>'Übergabe','api'=>'API-Zugänge','account'=>'Mein Konto & Administratoren','audit'=>'Aktivitäten','guide'=>'Anleitung']];
    foreach($groups as $g=>$items)if(isset($items[$tab])){$sub='<nav class="admin-subtabs">';foreach($items as $k=>$l)$sub.='<a class="'.($k===$tab?'active':'').'" href="/admin?tab='.$k.'">'.e($l).'</a>';$sub.='</nav>';}
    return '<section class="admin-location-head admin-context"><div class="admin-location-kicker">WORKWEARCENTER VERWALTUNG</div><h1>'.e($title).'</h1><p>'.e($subtitle).'</p></section>'.$sub;
}

function admin_sidebar(string $active): string {
    $counts=['inquiries'=>0,'orders'=>0,'companies'=>0,'system'=>0,'handover'=>0];
    try {
        $counts['inquiries']=(int)db()->query("SELECT COUNT(*) FROM inquiries WHERE status='new'")->fetchColumn();
        $counts['orders']=(int)db()->query("SELECT COUNT(*) FROM orders WHERE status='approved' AND fulfillment_status IN ('new','accepted')")->fetchColumn();
        $counts['companies']=(int)db()->query("SELECT COUNT(*) FROM employee_accounts WHERE registration_status='pending'")->fetchColumn();
        $counts['system']=(int)db()->query("SELECT COUNT(*) FROM yw_mail_queue WHERE status='failed'")->fetchColumn();
        $counts['handover']=(int)db()->query("SELECT COUNT(*) FROM yw_handover WHERE status='failed'")->fetchColumn();
    } catch(Throwable $e) {}

    $counts['website']=0;$counts['systemgroup']=$counts['system']+$counts['handover'];
    $groups=[
        'Tagesgeschäft'=>[
            'dashboard'=>['Übersicht','home'],
            'inquiries'=>['Anfragen','mail'],
            'orders'=>['Aufträge','box'],
            'production'=>['Produktion','check'],
        ],
        'Kunden & Katalog'=>[
            'companies'=>['Kunden','building'],
            'products'=>['Produkte','shirt'],
        ],
        'Einstellungen'=>[
            'website'=>['Website','file'],
            'systemgroup'=>['System','settings'],
        ],
    ];
    $activeKey=match(true){in_array($active,['content','design'],true)=>'website',in_array($active,['system','handover','api','account','audit','guide'],true)=>'systemgroup',in_array($active,['new','corporate'],true)=>'companies',$active==='katalog'=>'products',default=>$active};
    $target=['website'=>'content','systemgroup'=>'system'];
    $html='<aside class="admin-sidebar"><nav class="admin-menu" aria-label="Verwaltung">';
    foreach($groups as $group=>$items){
        $html.='<div class="admin-menu-group"><div class="admin-menu-group-title">'.e($group).'</div>';
        foreach($items as $key=>[$label,$icon]){
            $href='/admin'.($key==='dashboard'?'':'?tab='.($target[$key]??$key));
            $html.='<a class="'.($activeKey===$key?'active':'').'" href="'.$href.'"><span class="admin-menu-icon">'.wc_public_icon($icon).'</span><span>'.e($label).'</span>';
            if(($counts[$key]??0)>0)$html.='<span class="count">'.(int)$counts[$key].'</span>';
            $html.='</a>';
        }
        $html.='</div>';
    }
    return $html.'</nav></aside>';
}

function render_admin_dashboard(array $companies,array $products,array $orders,array $inquiries): void {
    $age=yw_backup_age_days();
    if($age===null||$age>7)echo '<div class="flash info"><strong>Datensicherung:</strong> '.($age===null?'Noch keine Sicherung heruntergeladen.':'Die letzte Sicherung ist '.$age.' Tage alt.').' <a href="/admin/sicherung">Jetzt sichern (ZIP)</a> – dauert nur einen Moment.</div>';
    $newInquiries=count(array_filter($inquiries,fn($i)=>(string)($i['status']??'')==='new'));
    $pendingEmployees=[];$pendingEmployeeCount=0;$failedMails=0;
    try{$pendingEmployeeCount=(int)db()->query("SELECT COUNT(*) FROM employee_accounts WHERE registration_status='pending'")->fetchColumn();$pendingEmployees=db()->query("SELECT ea.id,ea.name,ea.email,c.legal_name FROM employee_accounts ea JOIN companies c ON c.id=ea.company_id WHERE ea.registration_status='pending' ORDER BY ea.created_at LIMIT 8")->fetchAll();}catch(Throwable $e){}
    try{$failedMails=(int)db()->query("SELECT COUNT(*) FROM yw_mail_queue WHERE status='failed'")->fetchColumn();}catch(Throwable $e){}
    $failedHandovers=0;try{$failedHandovers=(int)db()->query("SELECT COUNT(*) FROM yw_handover WHERE status='failed'")->fetchColumn();}catch(Throwable $e){}
    $newOrders=count(array_filter($orders,fn($o)=>$o['status']==='approved' && in_array((string)$o['fulfillment_status'],['new','accepted'],true)));
    $production=count(array_filter($orders,fn($o)=>$o['status']==='approved' && in_array((string)$o['fulfillment_status'],['goods_received','processing','production','quality_check'],true)));
    $overdue=count(array_filter($orders,fn($o)=>$o['status']==='approved' && !in_array((string)$o['fulfillment_status'],['shipped','picked_up','completed'],true) && strtotime((string)$o['created_at'])<strtotime('-7 days')));

    echo '<div class="control-head"><div><h2>Heute im Blick</h2><p>Offene Aufgaben und der aktuelle Auftragsfluss.</p></div><div class="actions"><a class="btn" href="/admin?tab=orders">Aufträge öffnen</a><a class="btn primary" href="/admin?tab=new">Neuen Firmen-Shop anlegen</a></div></div>';
    echo '<div class="admin-dashboard-grid">'
        .'<a class="admin-dashboard-card" href="/admin?tab=orders"><span>Neue Aufträge</span><strong>'.$newOrders.'</strong><small>freigegeben und unbearbeitet</small></a>'
        .'<a class="admin-dashboard-card" href="/admin?tab=companies"><span>Freigaben offen</span><strong>'.$pendingEmployeeCount.'</strong><small>Mitarbeiterkonten prüfen</small></a>'
        .'<a class="admin-dashboard-card" href="/admin?tab=production"><span>In Arbeit</span><strong>'.$production.'</strong><small>Material bis Qualitätskontrolle</small></a>'
        .'<a class="admin-dashboard-card '.($overdue||$failedMails||$failedHandovers?'attention':'').'" href="/admin?tab='.($failedHandovers?'handover':'system').'"><span>Handlungsbedarf</span><strong>'.($overdue+$failedMails+$failedHandovers).'</strong><small>'.$overdue.' überfällig · '.$failedMails.' Mailfehler · '.$failedHandovers.' Übergabefehler</small></a>'
        .'</div>';

    echo '<div class="control-grid"><section class="card"><div class="title-row"><div><h2>Heute zu erledigen</h2><p class="muted">Nach Dringlichkeit sortiert.</p></div></div>';
    $me=yw_admin_current();
    if($me && !(int)($me['totp_enabled']??0)) echo '<div class="work-task danger"><span class="task-icon">'.wc_public_icon('lock').'</span><div><strong>Zwei-Faktor-Anmeldung einrichten</strong><small>Schützt die Verwaltung, falls dein Passwort bekannt wird. Dauert zwei Minuten.</small></div><a class="btn small primary" href="/admin?tab=account">Einrichten</a></div>';
    if($failedHandovers) echo '<div class="work-task danger"><span class="task-icon">!</span><div><strong>'.$failedHandovers.' Auftr'.($failedHandovers===1?'ag':'äge').' nicht an die Auftragsverwaltung übergeben</strong><small>Verbindung prüfen und erneut senden.</small></div><a class="btn small" href="/admin?tab=handover">Übergabe</a></div>';
    if($failedMails) echo '<div class="work-task danger"><span class="task-icon">!</span><div><strong>'.$failedMails.' E-Mail'.($failedMails===1?'':'s').' fehlgeschlagen</strong><small>Einladungen oder Statusmeldungen erneut verarbeiten.</small></div><a class="btn small" href="/admin?tab=system">Prüfen</a></div>';
    if($overdue) echo '<div class="work-task danger"><span class="task-icon">!</span><div><strong>'.$overdue.' Aufträge seit mehr als 7 Tagen offen</strong><small>Produktionsstand und Materialverfügbarkeit prüfen.</small></div><a class="btn small" href="/admin?tab=production">Produktion</a></div>';
    foreach($pendingEmployees as $person){
        echo '<div class="work-task"><span class="task-icon">'.wc_public_icon('user').'</span><div><strong>'.e($person['name']).' freigeben?</strong><small>'.e($person['legal_name']).' · '.e($person['email']).'</small></div><div class="task-actions"><form method="post">'.csrf_field().'<input type="hidden" name="action" value="employee_access"><input type="hidden" name="account_id" value="'.(int)$person['id'].'"><input type="hidden" name="decision" value="rejected"><button class="btn small" type="submit">Ablehnen</button></form><form method="post">'.csrf_field().'<input type="hidden" name="action" value="employee_access"><input type="hidden" name="account_id" value="'.(int)$person['id'].'"><input type="hidden" name="decision" value="approved"><button class="btn small primary" type="submit">Freigeben</button></form></div></div>';
    }
    if(!$failedMails&&!$overdue&&!$pendingEmployees&&!$failedHandovers&&(!$me||(int)($me['totp_enabled']??0))) echo '<div class="empty compact">Keine dringenden Aufgaben. Das System ist aufgeräumt.</div>';
    echo '</section><section class="card"><div class="title-row"><div><h2>Auftragsfluss</h2><p class="muted">Freigegebene Bestellungen.</p></div><a class="btn small" href="/admin?tab=production">Öffnen</a></div>';
    $stages=['new'=>'Neu','accepted'=>'Angenommen','goods_ordered'=>'Material offen','goods_received'=>'Material da','production'=>'Produktion','quality_check'=>'Qualitätskontrolle','ready'=>'Fertig'];
    $max=1;foreach($stages as $key=>$label)$max=max($max,count(array_filter($orders,fn($o)=>$o['status']==='approved' && $o['fulfillment_status']===$key)));
    foreach($stages as $key=>$label){$count=count(array_filter($orders,fn($o)=>$o['status']==='approved' && $o['fulfillment_status']===$key));echo '<div class="flow-row"><span>'.e($label).'</span><span class="flow-bar"><i style="width:'.(int)round($count/$max*100).'%"></i></span><strong>'.$count.'</strong></div>';}
    echo '</section></div>';

    echo '<section class="card shop-performance"><div class="title-row"><div><h2>Firmen im Blick</h2><p class="muted">Aktive Konten, Aufträge und Bestellvolumen.</p></div><a class="btn small" href="/admin?tab=companies">Alle Firmen</a></div>';
    if(!$companies) echo '<div class="empty">Noch keine Firmen-Shops vorhanden.</div>';
    else {echo '<div class="table-wrap"><table><thead><tr><th>Firmen-Shop</th><th>Mitarbeiter</th><th>Offene Produktion</th><th>Abgeschlossen</th><th>Bestellvolumen brutto</th><th></th></tr></thead><tbody>';foreach(array_slice($companies,0,8) as $c){$open=max(0,(int)$c['approved_order_count']-(int)$c['completed_order_count']);echo '<tr><td><strong>'.e($c['shop_name']).'</strong><br><span class="muted">'.e($c['legal_name']).'</span></td><td>'.(int)$c['registered_count'].'</td><td>'.$open.'</td><td>'.(int)$c['completed_order_count'].'</td><td class="money-cell">'.money((int)$c['revenue_cents']).'</td><td><a class="btn small" href="/admin?tab=companies&amp;company='.(int)$c['id'].'">Öffnen</a></td></tr>';}echo '</tbody></table></div>';}
    echo '</section>';

    if($newInquiries>0) echo '<div class="flash info space-top"><strong>'.$newInquiries.' neue Shop-Anfrage'.($newInquiries===1?'':'n').'.</strong> <a href="/admin?tab=inquiries">Jetzt ansehen</a></div>';
}

function render_admin_inquiries(array $inquiries): void {
    if(!$inquiries){
        echo '<div class="empty">Noch keine Anfragen vorhanden.</div>';
        return;
    }

    echo '<div class="inquiry-list">';
    foreach($inquiries as $i){
        $status=(string)($i['status']??'new');
        $label=['new'=>'Neu','contacted'=>'Kontaktiert','done'=>'Erledigt'][$status]??$status;
        echo '<article class="inquiry-card '.e($status).'"><div>';
        echo '<div class="product-admin-head"><div><h3>'.e($i['company']).'</h3><span class="badge">'.e($label).'</span></div><strong>'.e(date('d.m.Y H:i',strtotime($i['created_at']))).'</strong></div>';
        echo '<div class="inquiry-meta"><span>'.e($i['name']).'</span><span><a href="mailto:'.e($i['email']).'">'.e($i['email']).'</a></span>';
        if(!empty($i['phone'])) echo '<span><a href="tel:'.e($i['phone']).'">'.e($i['phone']).'</a></span>';
        if(!empty($i['employees'])) echo '<span>'.(int)$i['employees'].' Mitarbeitende</span>';
        echo '</div>';
        echo '<div class="inquiry-message">'.e($i['message']).'</div></div>';
        echo '<div><form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="inquiry_status"><input type="hidden" name="inquiry_id" value="'.(int)$i['id'].'"><label>Status<select name="status"><option value="new" '.($status==='new'?'selected':'').'>Neu</option><option value="contacted" '.($status==='contacted'?'selected':'').'>Kontaktiert</option><option value="done" '.($status==='done'?'selected':'').'>Erledigt</option></select></label><button class="btn small" type="submit">Status speichern</button></form><form method="post" class="space-top" data-confirm="Anfrage endgültig löschen? Das kann nicht rückgängig gemacht werden.">'.csrf_field().'<input type="hidden" name="action" value="delete_inquiry"><input type="hidden" name="return_tab" value="inquiries"><input type="hidden" name="inquiry_id" value="'.(int)$i['id'].'"><button class="btn small danger" type="submit">Löschen (DSGVO)</button></form></div>';
        echo '</article>';
    }
    echo '</div>';
}

function render_admin_orders(array $orders): void {
    $loadedCount=count($orders);
    $search=mb_strtolower(trim((string)($_GET['q']??'')));$approval=(string)($_GET['approval']??'');$fulfillment=(string)($_GET['fulfillment']??'');$companyId=(int)($_GET['company_id']??0);
    if($approval!==''&&!in_array($approval,['pending','approved','rejected','cancelled'],true))$approval='';if($fulfillment!==''&&!array_key_exists($fulfillment,yw_production_statuses()))$fulfillment='';
    $companies=[];foreach($orders as $o)$companies[(int)$o['company_id']]=(string)$o['legal_name'];asort($companies);
    $orders=array_values(array_filter($orders,function($o)use($search,$approval,$fulfillment,$companyId){if($companyId&&(int)$o['company_id']!==$companyId)return false;if($approval!==''&&$o['status']!==$approval)return false;if($fulfillment!==''&&$o['fulfillment_status']!==$fulfillment)return false;return $search===''||str_contains(mb_strtolower('#'.$o['id'].' '.$o['legal_name'].' '.$o['employee_name']),$search);}));
    $itemsByOrder=order_items_grouped(array_column($orders,'id'));
    $handoverMap=yw_handover_status_map(array_column($orders,'id'));
    echo '<section class="card"><div class="title-row"><div><h2>Bestellungen bearbeiten</h2><p class="muted">Suchen, filtern und mehrere Produktionsstände gemeinsam aktualisieren.</p></div><a class="btn small" href="/admin/export.csv">Arbeits-CSV exportieren</a></div>';
    if($loadedCount>=500)echo '<div class="flash info">Aus Leistungsgründen werden hier die letzten 500 Aufträge angezeigt. Der vollständige Bestand bleibt im CSV-Export verfügbar.</div>';
    echo '<form method="get" class="filter-bar"><input type="hidden" name="tab" value="orders"><label>Suche<input name="q" value="'.e((string)($_GET['q']??'')).'" placeholder="Nr., Firma oder Mitarbeiter"></label><label>Firma<select name="company_id"><option value="0">Alle Firmen</option>';foreach($companies as $id=>$name)echo '<option value="'.$id.'" '.($companyId===$id?'selected':'').'>'.e($name).'</option>';echo '</select></label><label>Freigabe<select name="approval"><option value="">Alle</option>';foreach(['pending'=>'Freigabe offen','approved'=>'Freigegeben','rejected'=>'Abgelehnt','cancelled'=>'Zurückgezogen'] as $key=>$label)echo '<option value="'.$key.'" '.($approval===$key?'selected':'').'>'.$label.'</option>';echo '</select></label><label>Produktion<select name="fulfillment"><option value="">Alle</option>';foreach(yw_production_statuses() as $key=>$label)echo '<option value="'.$key.'" '.($fulfillment===$key?'selected':'').'>'.e($label).'</option>';echo '</select></label><button class="btn" type="submit">Filtern</button></form>';
    if(!$orders){echo '<div class="empty">Keine Bestellungen passen zu diesem Filter.</div></section>';return;}
    echo '<form method="post">'.csrf_field().'<input type="hidden" name="action" value="bulk_fulfillment"><div class="bulk-bar"><strong>Auswahl:</strong><select name="status" aria-label="Neuer Produktionsstatus für die Auswahl" required><option value="">Neuen Produktionsstatus wählen</option>';foreach(yw_production_statuses() as $value=>$text)echo '<option value="'.e($value).'">'.e($text).'</option>';echo '</select><button class="btn primary small" type="submit">Ausgewählte aktualisieren</button><span class="muted">Maximal 100 Aufträge gleichzeitig</span></div>';
    echo '<div class="table-wrap"><table class="table"><thead><tr><th><input class="order-select" type="checkbox" data-select-all aria-label="Alle auswählen"></th><th>Auftrag</th><th>Firma / Mitarbeiter</th><th>Freigabe</th><th>Produktion</th><th>Übergabe</th><th>Summe</th><th></th></tr></thead><tbody>';
    foreach($orders as $order){$label=['pending'=>'Freigabe offen','approved'=>'Freigegeben','rejected'=>'Abgelehnt','cancelled'=>'Zurückgezogen'][$order['status']]??$order['status'];$items=$itemsByOrder[(int)$order['id']]??[];$total=(int)($order['gross_total_cents']??0)>0?(int)$order['gross_total_cents']:(int)$order['total_cents'];echo '<tr><td>'.($order['status']==='approved'?'<input class="order-select" type="checkbox" name="order_ids[]" value="'.(int)$order['id'].'" aria-label="Auftrag auswählen">':'').'</td><td><a href="'.e(yw_order_url((int)$order['id'])).'"><strong>'.e(yw_order_number((int)$order['id'])).'</strong></a><br><span class="muted">'.e(date('d.m.Y',strtotime($order['created_at']))).'</span><details class="details"><summary>'.count($items).' Positionen</summary><div class="details-inner">';foreach($items as $item)echo '<div class="muted">'.(int)$item['quantity'].'× '.e($item['product_name']).' · '.e($item['size']).'</div>';echo '</div></details></td><td><strong>'.e($order['legal_name']).'</strong><br>'.e($order['employee_name']).'</td><td><span class="badge '.e($order['status']).'">'.e($label).'</span></td><td>'.e(yw_production_statuses()[$order['fulfillment_status']]??$order['fulfillment_status']).(!empty($order['tracking_number'])?'<br><span class="muted">'.e(trim(($order['tracking_carrier']??'').' '.$order['tracking_number'])).'</span>':'').'</td><td>'.(isset($handoverMap[(int)$order['id']])?(function($h){[$l,$c]=yw_handover_status_label((string)$h['status']);return '<a class="badge '.e($c).'" href="/admin?tab=handover">'.e($l).'</a>'.($h['external_reference']?'<br><span class="muted">Ref. '.e($h['external_reference']).'</span>':'');})($handoverMap[(int)$order['id']]):'<span class="muted">–</span>').'</td><td><strong>'.money($total).'</strong><br><span class="muted">brutto</span></td><td><a class="btn small" href="'.e(yw_order_url((int)$order['id'])).'">Öffnen</a></td></tr>';}
    echo '</tbody></table></div></form></section>';
}

function render_admin_products(array $products): void {
    if(!empty($_GET['zones'])){yw_zone_editor_page((int)$_GET['zones']);return;}
    $categories=product_categories();
    $decorationLabels=['none'=>'Keine Veredelung','print'=>'Bedruckt','embroidery'=>'Gestickt','mixed'=>'Druck & Stick'];

    echo '<details class="card admin-toolbox"><summary>Kategorien verwalten</summary><div class="admin-toolbox-body"><p class="muted">Lege Kategorien an und ordne deine Produkte anschließend zu.</p>';
    echo '<form method="post" class="category-manager">'.csrf_field().'<input type="hidden" name="action" value="create_category"><input type="hidden" name="return_tab" value="products"><label>Kategoriename<input name="category_name" maxlength="120" placeholder="z. B. T-Shirts, Polos, Jacken" required></label><button class="btn primary" type="submit">Kategorie anlegen</button></form>';
    if($categories){
        echo '<div class="category-list">';
        foreach($categories as $cat){
            echo '<span class="category-chip">'.e($cat['name']).'<form method="post" data-confirm="Kategorie wirklich löschen?">'.csrf_field().'<input type="hidden" name="action" value="delete_category"><input type="hidden" name="category_id" value="'.(int)$cat['id'].'"><button class="btn small" type="submit" title="Kategorie löschen">×</button></form></span>';
        }
        echo '</div>';
    }
    echo '</div></details>';

    echo '<details class="card admin-toolbox"><summary>Produktimport und Export</summary><div class="admin-toolbox-body"><div class="title-row"><p class="muted">Neue Produkte per CSV anlegen oder anhand von Produkt-ID beziehungsweise SKU aktualisieren. Der Import läuft vollständig oder gar nicht.</p><div class="actions"><a class="btn" href="/admin/produkte.csv">Produktstamm exportieren</a><a class="btn" href="/admin/produkte-vorlage.csv">Leere Vorlage</a></div></div><form method="post" enctype="multipart/form-data" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="import_products_csv"><label>Produkt-CSV<input type="file" name="products_csv" accept=".csv,text/csv" required></label><p class="muted">Am sichersten: Produktstamm exportieren, in Excel bearbeiten und wieder importieren. Produkt-ID bestehender Zeilen nicht verändern; neue Zeilen erhalten eine eindeutige SKU. Bilder und kundenspezifische Veredelungen bleiben unverändert.</p><button class="btn primary" type="submit">CSV prüfen und importieren</button></form></div></details>';

    echo '<section class="card"><div class="title-row"><div><h2>Stammprodukt-Katalog</h2><p class="muted">Lege jedes Grundprodukt nur einmal an. Firmenlogos, individuelle Veredelung und abweichende Preise werden anschließend im jeweiligen Firmenshop hinterlegt.</p></div><button class="btn primary" type="button" data-open-details="new-product">Produkt hinzufügen</button></div>';

    if(!$products){
        echo '<div class="empty">Noch keine Produkte angelegt.</div>';
    }else{
        echo '<div class="product-admin-list">';
        foreach($products as $p){
            $sizes=implode(', ',json_decode($p['sizes'],true)?:[]);
            $decoration=(string)($p['decoration_type']??'none');
            $decorationLabel=$decorationLabels[$decoration]??'Keine Veredelung';
            $categoryId=(int)($p['category_id']??0);
            $categoryName=product_category_name($categoryId,(string)($p['category']??'Ohne Kategorie'));

            echo '<article class="product-admin-card">';
            echo '<div class="product-admin-thumb">';
            $productImage=yw_local_image_path((string)($p['image_path']??''),['products'],true);
            if($productImage!=='') echo '<img src="/'.e($productImage).'" alt="'.e($p['name']).'">';
            else echo '<span>'.wc_public_icon('shirt').'</span>';
            echo '</div>';

            echo '<div>';
            echo '<div class="product-admin-head"><div><h3>'.e($p['name']).'</h3><div class="muted">'.e($categoryName).'</div></div><strong>'.money((int)$p['price_cents']).' netto<br><small>'.money(gross_from_net((int)$p['price_cents'],product_vat_rate($p))).' brutto</small></strong></div>';
            echo '<div class="product-admin-meta"><span class="product-pill">'.e($p['color']).'</span><span class="product-pill">'.e($decorationLabel).'</span><span class="product-pill">'.((int)$p['active']?'Aktiv':'Deaktiviert').'</span></div>';

            if(!empty($p['decoration_details'])) echo '<div class="product-decoration"><strong>Veredelung:</strong><br>'.nl2br(e($p['decoration_details'])).'</div>';

            echo '<details><summary>Produkt bearbeiten</summary><div class="details-inner">';
            echo '<form method="post" enctype="multipart/form-data" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="update_product"><input type="hidden" name="return_tab" value="products"><input type="hidden" name="product_id" value="'.(int)$p['id'].'">';

            echo '<div class="grid2"><label>Name<input name="name" maxlength="190" value="'.e($p['name']).'" required></label><label>Kategorie<select name="category_id" required><option value="">Bitte wählen</option>';
            foreach($categories as $cat) echo '<option value="'.(int)$cat['id'].'" '.($categoryId===(int)$cat['id']?'selected':'').'>'.e($cat['name']).'</option>';
            echo '</select></label></div>';

            echo '<div class="grid2"><label>Verkaufspreis netto<input name="price" value="'.e(number_format((int)$p['price_cents']/100,2,',','')).'" required></label><label>Einkaufspreis netto<input name="purchase_price" value="'.e(number_format((int)($p['purchase_price_cents']??0)/100,2,',','')).'"></label></div><div class="grid2"><label>MwSt. in %<input type="number" min="0" max="100" step="0.01" name="vat_rate" value="'.e(number_format(product_vat_rate($p),2,'.','')).'" required></label><label>Artikelnummer / SKU<input name="sku" maxlength="100" value="'.e((string)($p['sku']??'')).'"></label></div><div class="grid2"><label>Marke<input name="brand" maxlength="120" value="'.e((string)($p['brand']??'')).'"></label><label>Lieferant<input name="supplier_name" maxlength="160" value="'.e((string)($p['supplier_name']??'')).'"></label></div><label>Farbe<input name="color" maxlength="120" value="'.e($p['color']).'"></label>';
            echo '<label>Größen<input name="sizes" maxlength="1550" value="'.e($sizes).'" required></label>';
            echo '<label>Produktbeschreibung<textarea name="description" maxlength="10000" rows="3">'.e($p['description']).'</textarea></label>';

            echo '<div class="grid2"><label>Veredelungsart<select name="decoration_type"><option value="none" '.($decoration==='none'?'selected':'').'>Keine Veredelung</option><option value="print" '.($decoration==='print'?'selected':'').'>Bedruckt</option><option value="embroidery" '.($decoration==='embroidery'?'selected':'').'>Gestickt</option><option value="mixed" '.($decoration==='mixed'?'selected':'').'>Druck & Stick</option></select></label><label>Produktbild (vorne)<input type="file" name="image" accept="image/png,image/jpeg,image/webp"></label></div>';
            $viewBack=yw_local_image_path((string)($p['image_back_path']??''),['products']);$viewSleeve=yw_local_image_path((string)($p['image_sleeve_path']??''),['products']);
            echo '<div class="grid2 product-views"><label>Rückansicht '.($viewBack!==''?'<small>vorhanden</small>':'<small>optional</small>').'<input type="file" name="image_back" accept="image/png,image/jpeg,image/webp">'.($viewBack!==''?'<span class="check"><input type="checkbox" name="delete_image_back" value="1"> Rückansicht entfernen</span>':'').'</label><label>Ärmelansicht '.($viewSleeve!==''?'<small>vorhanden</small>':'<small>optional</small>').'<input type="file" name="image_sleeve" accept="image/png,image/jpeg,image/webp">'.($viewSleeve!==''?'<span class="check"><input type="checkbox" name="delete_image_sleeve" value="1"> Ärmelansicht entfernen</span>':'').'</label></div>';
            $zoneCount=count(yw_product_zones($p),COUNT_RECURSIVE)-count(yw_product_zones($p));
            echo '<p class="product-zones-link"><a class="btn small" href="/admin?tab=products&amp;zones='.(int)$p['id'].'">Druckzonen festlegen</a> <span class="muted">'.($zoneCount>0?$zoneCount.' Zonen eingerichtet':'Noch keine eigenen Zonen – es gelten Standardzonen').'</span></p>';
            echo '<label>Veredelungsbeschreibung<textarea name="decoration_details" maxlength="10000" rows="4">'.e((string)($p['decoration_details']??'')).'</textarea></label>';
            echo '<button class="btn small" type="submit">Änderungen speichern</button></form></div></details>';
            echo '</div>';

            echo '<div class="product-admin-actions"><form method="post">'.csrf_field().'<input type="hidden" name="action" value="toggle_product"><input type="hidden" name="product_id" value="'.(int)$p['id'].'"><input type="hidden" name="return_tab" value="products"><button class="btn small" type="submit">'.((int)$p['active']?'Deaktivieren':'Aktivieren').'</button></form></div>';

            echo '</article>';
        }
        echo '</div>';
    }
    echo '</section>';

    echo '<details class="card admin-toolbox" id="new-product"><summary>Neues Stammprodukt anlegen</summary><div class="admin-toolbox-body">';
    if(!$categories) echo '<div class="flash info">Lege zuerst mindestens eine Kategorie an.</div>';
    echo '<form method="post" enctype="multipart/form-data" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="create_product"><input type="hidden" name="return_tab" value="products">';
    echo '<label>Produktname<input name="name" maxlength="190" required></label>';
    echo '<div class="grid2"><label>Kategorie<select name="category_id" required><option value="">Bitte wählen</option>';
    foreach($categories as $cat) echo '<option value="'.(int)$cat['id'].'">'.e($cat['name']).'</option>';
    echo '</select></label><label>Verkaufspreis netto<input name="price" inputmode="decimal" placeholder="49,90" required></label></div><div class="grid2"><label>Einkaufspreis netto<input name="purchase_price" inputmode="decimal" value="0,00"></label><label>MwSt. in %<input type="number" min="0" max="100" step="0.01" name="vat_rate" value="19.00" required></label></div><div class="grid2"><label>Artikelnummer / SKU<input name="sku" maxlength="100"></label><label>Marke<input name="brand" maxlength="120"></label></div><label>Lieferant<input name="supplier_name" maxlength="160"></label>';
    echo '<div class="grid2"><label>Farbe<input name="color" maxlength="120" placeholder="Schwarz / Orange"></label><label>Größen<input name="sizes" maxlength="1550" value="S, M, L, XL, 2XL" required></label></div>';
    echo '<label>Produktbeschreibung<textarea name="description" maxlength="10000" rows="3"></textarea></label>';
    echo '<div class="grid2"><label>Veredelungsart<select name="decoration_type"><option value="none">Keine Veredelung</option><option value="print">Bedruckt</option><option value="embroidery">Gestickt</option><option value="mixed">Druck & Stick</option></select></label><label>Produktbild (vorne)<input type="file" name="image" accept="image/png,image/jpeg,image/webp"></label></div>';
    echo '<div class="grid2"><label>Rückansicht <small>optional</small><input type="file" name="image_back" accept="image/png,image/jpeg,image/webp"></label><label>Ärmelansicht <small>optional</small><input type="file" name="image_sleeve" accept="image/png,image/jpeg,image/webp"></label></div>';
    echo '<label>Veredelungsbeschreibung<textarea name="decoration_details" maxlength="10000" rows="4" placeholder="z. B. Logo Brust links, Rückendruck groß, Ärmel rechts"></textarea></label>';
    echo '<button class="btn primary" type="submit" '.(!$categories?'disabled':'').'>Produkt anlegen</button></form></div></details>';
}
function render_new_company(array $products): void {
    if(!array_filter($products,fn($p)=>(int)$p['active']===1)){
        echo '<div class="flash info">Lege zuerst mindestens ein aktives Produkt an.</div>';
        return;
    }

    echo '<section class="card"><h2>Stammdaten und Shopregeln</h2><form method="post" class="form-stack">'.csrf_field().'<input type="hidden" name="action" value="create_company"><input type="hidden" name="return_tab" value="new">';

    echo '<div class="grid2"><label>Firmenname<input name="legal_name" maxlength="190" required></label><label>Angezeigter Shopname<input name="shop_name" maxlength="190" placeholder="Musterbau Mitarbeiter-Shop" required></label></div>';
    echo '<label>Shop-Kurzname <small>Beispiel: „musterbau“ ergibt '.e(workwearcenter_domain()).'/musterbau</small><input name="slug" maxlength="63" pattern="[a-z0-9][a-z0-9-]*[a-z0-9]|[a-z0-9]" required></label>';

    echo '<div class="grid2"><label>Primärfarbe<input type="color" name="primary_color" value="#101b2d"></label><label>Akzentfarbe<input type="color" name="accent_color" value="#ff6b00"></label></div>';

    echo '<label>Interner Ansprechpartner<input name="approver_name" maxlength="190" required></label><p class="muted">Mitarbeiter und Verantwortliche registrieren sich mit persönlichen Konten. Rollen vergibst du anschließend unter „Mitarbeiter & Rollen“.</p>';

    echo '<fieldset><legend>Lieferung & Zugang</legend><div class="grid2"><label>Lieferart<select name="delivery_mode"><option value="company">Lieferung an die Firma</option><option value="pickup">Abholung bei Yanik Textildruck</option></select></label><label>Registrierung neuer Mitarbeiter<select name="registration_mode"><option value="approval">Mit Freigabe durch Verantwortlichen (empfohlen)</option><option value="open">Offen – jeder mit dem Link</option></select></label></div><label>Firmenlieferadresse <small>Pflicht bei Lieferung an die Firma</small><textarea name="delivery_address" maxlength="1000" rows="3" placeholder="Firma&#10;Straße Nr.&#10;PLZ Ort"></textarea></label><p class="muted">Eine Firmen-PIN für die Registrierung kannst du nach dem Anlegen unter „Ausstattung & Budgets“ festlegen.</p></fieldset>';
    echo '<fieldset><legend>Shopregeln & Finanzierung</legend>';
    echo '<div class="grid2"><label>Finanzierung<select name="financing_mode"><option value="company">Firma übernimmt 100 %</option><option value="percentage">Mitarbeiter zahlt prozentualen Eigenanteil</option></select></label>';
    echo '<label>Eigenanteil Mitarbeiter in %<input type="number" min="0" max="100" step="0.01" name="employee_share_percent" value="0"></label></div>';
    echo '<label class="check"><input type="checkbox" name="approval_required" value="1" checked><span>Bestellungen müssen vom Firmenverantwortlichen freigegeben werden</span></label>';
    echo '<hr><h4>Versandkosten</h4>';
    echo '<label class="check"><input type="checkbox" name="shipping_enabled" value="1"><span>Versandkosten berechnen</span></label>';
    echo '<div class="grid2"><label>Versand netto in €<input type="number" min="0" step="0.01" name="shipping_net" value="0.00"></label><label>Versand-MwSt. in %<input type="number" min="0" max="100" step="0.01" name="shipping_vat_rate" value="19.00"></label></div>';
    echo '<div class="grid2"><label>Versandkostenfrei ab Warenwert brutto in € <small>0 = deaktiviert</small><input type="number" min="0" step="0.01" name="free_shipping_gross" value="0.00"></label><label>Wer trägt den Versand?<select name="shipping_payer"><option value="company">Firma</option><option value="employee">Mitarbeiter</option></select></label></div>';
    echo '</fieldset>';

    echo '<fieldset><legend>Freigegebene Produkte</legend><div class="checkboxes">';
    foreach($products as $p){
        if((int)$p['active']){
            $gross=gross_from_net((int)$p['price_cents'],product_vat_rate($p));
            echo '<label class="check"><input type="checkbox" name="products[]" value="'.(int)$p['id'].'"><span>'.e($p['name']).' · '.money((int)$p['price_cents']).' netto · '.money($gross).' brutto</span></label>';
        }
    }
    echo '</div></fieldset><button class="btn primary" type="submit">Firmen-Shop anlegen</button></form></section>';
}

function render_admin_decoration_motifs(array $company): void {
    $companyId=(int)$company['id'];
    $motifs=yw_company_decoration_motifs($companyId,true);
    $methodOptions=decoration_method_options();
    $methodSelect=static function(string $selected) use ($methodOptions): string {
        $html='<select name="motif_method" required>';
        foreach($methodOptions as $value=>$label)$html.='<option value="'.e($value).'" '.($selected===$value?'selected':'').'>'.e($label).'</option>';
        return $html.'</select>';
    };
    echo '<details class="details"><summary>Veredelungsmotive & Druckbestand <span class="muted">('.count($motifs).')</span></summary><div class="details-inner">';
    echo '<p>Hier verwaltest du ausschließlich fertige Drucke, Transfers oder Motivdateien – keinen Textil-Wareneingang. Verknüpfte Motive werden bei freigegebenen Aufträgen automatisch gegen den hinterlegten Bestand geprüft.</p>';
    if($motifs){
        echo '<div class="motif-list">';
        foreach($motifs as $motif){
            $summary=(string)$motif['name'].' · '.decoration_method_label((string)$motif['method']);
            if((int)$motif['track_stock'])$summary.=' · '.(int)$motif['stock_qty'].' vorhanden';
            if(!(int)$motif['active'])$summary.=' · inaktiv';
            echo '<details class="motif-editor"><summary>'.e($summary).'</summary><div class="motif-editor-body"><form method="post" class="form-stack" enctype="multipart/form-data">'.csrf_field().'<input type="hidden" name="action" value="save_decoration_motif"><input type="hidden" name="return_tab" value="companies"><input type="hidden" name="company_id" value="'.$companyId.'"><input type="hidden" name="motif_id" value="'.(int)$motif['id'].'"><div class="motif-fields"><label>Motivname<input name="motif_name" maxlength="190" value="'.e($motif['name']).'" required></label><label>Verfahren'.$methodSelect((string)$motif['method']).'</label><label>Bestand<input type="number" name="stock_qty" min="0" max="1000000" value="'.(int)$motif['stock_qty'].'" required></label><label>Mindestbestand<input type="number" name="reorder_level" min="0" max="1000000" value="'.(int)$motif['reorder_level'].'" required></label></div><label>Interne Notiz<input name="motif_notes" maxlength="500" value="'.e($motif['notes']??'').'"></label>'.(function($motif){$f=yw_motif_file_path($motif);return '<label>Logo-Datei <small>SVG oder PNG, transparent</small><input type="file" name="motif_file" accept=".svg,image/svg+xml,image/png">'.($f!==''?'<span class="motif-file-preview"><img src="/'.e($f).'" alt=""><span class="check"><input type="checkbox" name="delete_motif_file" value="1"> Datei entfernen</span></span>':'<small class="muted">Noch keine Datei – ohne Datei kein automatisches Produktbild.</small>').'</label>';})($motif).'<div class="motif-checks"><label class="check"><input type="checkbox" name="track_stock" value="1" '.((int)$motif['track_stock']?'checked':'').'><span>Mengenbestand prüfen</span></label><label class="check"><input type="checkbox" name="active" value="1" '.((int)$motif['active']?'checked':'').'><span>Motiv aktiv</span></label></div><button class="btn small" type="submit">Motiv speichern</button></form></div></details>';
        }
        echo '</div>';
    }else echo '<div class="empty compact">Noch kein Veredelungsmotiv hinterlegt.</div>';
    echo '<details class="motif-editor"><summary>Neues Motiv anlegen</summary><div class="motif-editor-body"><form method="post" class="form-stack" enctype="multipart/form-data">'.csrf_field().'<input type="hidden" name="action" value="save_decoration_motif"><input type="hidden" name="return_tab" value="companies"><input type="hidden" name="company_id" value="'.$companyId.'"><div class="motif-fields"><label>Motivname<input name="motif_name" maxlength="190" placeholder="z. B. Firmenlogo Brust 9 cm" required></label><label>Verfahren'.$methodSelect('dtf').'</label><label>Bestand<input type="number" name="stock_qty" min="0" max="1000000" value="0" required></label><label>Mindestbestand<input type="number" name="reorder_level" min="0" max="1000000" value="0" required></label></div><label>Interne Notiz<input name="motif_notes" maxlength="500" placeholder="z. B. Ablagefach oder Dateiname"></label><label>Logo-Datei <small>SVG oder PNG, transparent</small><input type="file" name="motif_file" accept=".svg,image/svg+xml,image/png"></label><div class="motif-checks"><label class="check"><input type="checkbox" name="track_stock" value="1" checked><span>Mengenbestand prüfen</span></label><label class="check"><input type="checkbox" name="active" value="1" checked><span>Motiv aktiv</span></label></div><button class="btn primary small" type="submit">Motiv anlegen</button></form></div></details>';
    echo '</div></details>';
}

function render_admin_companies(array $companies,array $products): void {
    $selected=(int)($_GET['company']??0);
    if($selected){
        $match=array_values(array_filter($companies,fn($c)=>(int)$c['id']===$selected));
        if(!$match){echo '<div class="flash error">Kunde wurde nicht gefunden.</div><a class="btn" href="/admin?tab=companies">Zur Kundenliste</a>';return;}
        yw_customer_file($match[0],$products);
        return;
    }
    if(!$companies){echo '<div class="empty">Noch kein Firmen-Shop vorhanden. <a class="btn primary" href="/admin?tab=new">Ersten Kunden anlegen</a></div>';return;}
    $search=mb_strtolower(trim((string)($_GET['q']??'')));
    if($search!==''){
        $companies=array_values(array_filter($companies,fn($c)=>str_contains(mb_strtolower((string)$c['legal_name'].' '.$c['shop_name'].' '.$c['slug']),$search)));
    }
    echo '<section class="card"><div class="title-row"><div><h2>Kunden</h2><p class="muted">Einen Kunden öffnen, um Kollektion, Mitarbeiter, Regeln und Aufträge zu bearbeiten.</p></div><a class="btn primary" href="/admin?tab=new">Neuer Kunde</a></div>';
    echo '<form method="get" class="filter-bar"><input type="hidden" name="tab" value="companies"><label>Suche<input name="q" value="'.e((string)($_GET['q']??'')).'" placeholder="Firma oder Kurzname"></label><button class="btn">Suchen</button>'.($search!==''?' <a class="btn" href="/admin?tab=companies">Zurücksetzen</a>':'').'</form>';
    if(!$companies){echo '<div class="empty compact">Kein Kunde gefunden.</div></section>';return;}
    echo '<div class="table-wrap"><table class="table"><thead><tr><th>Firma</th><th>Einrichtung</th><th>Shop-Adresse</th><th>Mitarbeiter</th><th>Offene Aufträge</th><th></th></tr></thead><tbody>';
    foreach($companies as $c){
        $open=max(0,(int)$c['approved_order_count']-(int)$c['completed_order_count']);
        $st=yw_company_setup_steps($c);$dn=count(array_filter($st,fn($s)=>$s[2]));
        echo '<tr><td><strong>'.e($c['shop_name']).'</strong><br><span class="muted">'.e($c['legal_name']).'</span>'.((int)($c['active']??1)?'':' <span class="badge rejected">deaktiviert</span>').'</td>'
            .'<td><span class="badge '.($dn===count($st)?'approved':'pending').'">'.$dn.'/'.count($st).'</span></td>'
            .'<td><span class="muted">workwearcenter.de/</span>'.e($c['slug']).'</td>'
            .'<td>'.(int)($c['registered_count']??0).'</td>'
            .'<td>'.($open>0?'<strong>'.$open.'</strong>':'–').'</td>'
            .'<td><a class="btn small" href="'.e(yw_customer_url((int)$c['id'])).'">Öffnen</a></td></tr>';
    }
    echo '</tbody></table></div></section>';
}



function yw_audit_labels(): array {
    return [
        'admin.login'=>'Admin-Anmeldung',
        'company.rules_updated'=>'Shopregeln geändert',
        'order.created'=>'Bestellung erstellt',
        'order.decision'=>'Bestellung entschieden',
        'order.fulfillment_updated'=>'Produktionsstatus geändert',
        'employee.access_approved'=>'Mitarbeiter freigegeben',
        'employee.access_rejected'=>'Registrierung abgelehnt',
        'employee.registered'=>'Mitarbeiter registriert',
        'employee.registration_decided'=>'Registrierung bearbeitet',
        'employee.password_reset_requested'=>'Passwort-Link angefordert',
        'employee.password_reset_completed'=>'Passwort geändert',
        'mail.test_sent'=>'Testmail versendet',
        'mail.queue_processed'=>'Mailwarteschlange verarbeitet',
        'products.csv_imported'=>'Produkt-CSV importiert',
        'inquiry.created'=>'Shop-Anfrage eingegangen',
        'inquiry.status_updated'=>'Anfragestatus geändert',
        'site.design_updated'=>'Website-Design geändert',
        'site.content_updated'=>'Website-Inhalte geändert',
        'site.content_defaults_applied'=>'Neue Standardtexte übernommen',
        'site.content_backup_restored'=>'Gesicherte Texte wiederhergestellt',
        'product_category.created'=>'Produktkategorie angelegt',
        'product_category.deleted'=>'Produktkategorie gelöscht',
        'product.created'=>'Produkt angelegt',
        'product.updated'=>'Produkt geändert',
        'product.status_toggled'=>'Produktstatus geändert',
        'company.created'=>'Firmen-Shop angelegt',
        'company.brand_updated'=>'Firmenauftritt geändert',
        'company.collection_updated'=>'Firmenkollektion geändert',
        'company_product.updated'=>'Firmenprodukt individualisiert',
        'decoration_motif.created'=>'Veredelungsmotiv angelegt',
        'decoration_motif.updated'=>'Veredelungsmotiv geändert',
        'employee.account_updated'=>'Mitarbeiterkonto geändert',
        'employee.profile_updated'=>'Mitarbeiterprofil geändert',
        'invite.created'=>'Mitarbeiterzugang angelegt',
        'employee.created_by_admin'=>'Konto durch Admin angelegt',
        'employee.anonymized'=>'Mitarbeiterdaten gelöscht (DSGVO)',
        'order.cancelled'=>'Bestellung zurückgezogen',
        'order.status_from_auftragszentrale'=>'Status aus Auftragszentrale übernommen',
        'order.note_updated'=>'Auftragsnotiz geändert',
        'company.note_updated'=>'Kundennotiz geändert',
        'company.preview_started'=>'Shop-Vorschau geöffnet',
        'system.backup_downloaded'=>'Datensicherung heruntergeladen',
        'api.key_created'=>'API-Schlüssel angelegt','api.key_toggled'=>'API-Schlüssel gesperrt/freigegeben','api.key_deleted'=>'API-Schlüssel gelöscht','api.ip_rejected'=>'API-Zugriff von fremder IP abgelehnt','order.status_from_api'=>'Status über API übernommen','katalog.updated'=>'Daiber-Liste eingelesen','product.images_assigned'=>'Produktbilder aus Katalog zugeordnet',
        'handover.acknowledged'=>'Auftrag von Auftragsverwaltung übernommen',
        'handover.retry'=>'Übergabe erneut gesendet',
        'handover.retry_failed'=>'Fehlgeschlagene Übergaben neu eingeplant',
        'handover.backfill'=>'Ältere Aufträge zur Übergabe vorbereitet',
        'handover.test'=>'Übergabe-Verbindung getestet',
        'admin.password_changed'=>'Admin-Passwort geändert',
        'admin.2fa_enabled'=>'Zwei-Faktor-Anmeldung aktiviert',
        'admin.2fa_disabled'=>'Zwei-Faktor-Anmeldung deaktiviert',
        'admin.recovery_code_used'=>'Wiederherstellungscode verwendet',
        'admin.recovery_codes_renewed'=>'Wiederherstellungscodes erneuert',
        'admin.invited'=>'Administrator eingeladen',
        'admin.deactivated'=>'Administrator deaktiviert',
        'admin.activated'=>'Administrator aktiviert',
        'company.status_toggled'=>'Firmen-Shop aktiviert/deaktiviert',
        'inquiry.deleted'=>'Anfrage gelöscht',
        'admin.password_reset_requested'=>'Admin-Passwort-Link angefordert',
        'admin.password_reset_completed'=>'Admin-Passwort geändert',
        'yw_company'=>'Firmenbudget und Registrierung geändert',
        'yw_employee'=>'Mitarbeiterbudget geändert',
        'yw_limit'=>'Produktlimit geändert',
        'yw_profile_create'=>'Ausstattungsprofil angelegt',
        'yw_profile_save'=>'Ausstattungsprofil geändert',
        'yw_profile_products'=>'Profilsortiment geändert',
        'yw_production'=>'Produktionsauftrag geändert',
        'system.updated'=>'System aktualisiert',
    ];
}

function render_admin_audit(array $logs): void {
    $labels=yw_audit_labels();
    echo '<section class="card"><div class="title-row"><div><h2>Aktivitätsprotokoll</h2><p class="muted">Die letzten 200 sicherheits- und auftragsrelevanten Vorgänge.</p></div></div>';
    if(!$logs){echo '<div class="empty">Noch keine protokollierten Vorgänge.</div></section>';return;}
    echo '<div class="table-wrap"><table class="table"><thead><tr><th>Zeit</th><th>Vorgang</th><th>Firma</th><th>Akteur</th><th>Objekt</th></tr></thead><tbody>';
    foreach($logs as $log){
        echo '<tr><td>'.e(date('d.m.Y H:i',strtotime((string)$log['created_at']))).'</td><td><strong>'.e($labels[$log['action']]??$log['action']).'</strong></td><td>'.e($log['legal_name']??'–').'</td><td>'.e($log['actor_type']).($log['actor_id']?' #'.(int)$log['actor_id']:'').'</td><td>'.e($log['entity_type']??'–').($log['entity_id']?' #'.(int)$log['entity_id']:'').'</td></tr>';
    }
    echo '</tbody></table></div></section>';
}
