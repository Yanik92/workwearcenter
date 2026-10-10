<?php
declare(strict_types=1);

function schema_table_exists(PDO $pdo, string $table): bool {
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn()>0;
}

function schema_column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $stmt->execute([$table,$column]);
    return (int)$stmt->fetchColumn()>0;
}

function schema_add_column(PDO $pdo, string $table, string $column, string $definition, array &$changes): void {
    if(schema_column_exists($pdo,$table,$column)) return;
    $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    $changes[]="$table.$column";
}

function schema_add_index(PDO $pdo,string $table,string $name,string $columns,array &$changes): void {
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
    $stmt->execute([$table,$name]);
    if((int)$stmt->fetchColumn()>0)return;
    $pdo->exec("ALTER TABLE `$table` ADD INDEX `$name` ($columns)");
    $changes[]=$table.'.'.$name;
}

function schema_mark_version(PDO $pdo,string $version,array &$changes): void {
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM yw_schema_versions WHERE version=?');$stmt->execute([$version]);
    if((int)$stmt->fetchColumn()>0)return;
    $stmt=$pdo->prepare('INSERT INTO yw_schema_versions(version) VALUES(?)');$stmt->execute([$version]);
    $changes[]='Version '.$version;
}

function run_schema_updates(PDO $pdo): array {
    $changes=[];
    // Capture provenance before adding compatibility columns; never prefer a stale V4 value over phase 4.
    $hadPhase4Budget=schema_column_exists($pdo,'companies','annual_budget_company_cents');
    $hadV4Budget=schema_column_exists($pdo,'companies','annual_budget_cents');
    $pdo->exec("CREATE TABLE IF NOT EXISTS product_categories (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS inquiries (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        company VARCHAR(190) NOT NULL,
        name VARCHAR(190) NOT NULL,
        email VARCHAR(190) NOT NULL,
        phone VARCHAR(80) DEFAULT NULL,
        employees INT UNSIGNED NOT NULL DEFAULT 0,
        message TEXT NOT NULL,
        status ENUM('new','contacted','done') NOT NULL DEFAULT 'new',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS employee_accounts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        company_id INT UNSIGNED NOT NULL,
        name VARCHAR(160) NOT NULL,
        email VARCHAR(190) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'employee',
        department VARCHAR(120) DEFAULT NULL,
        cost_center VARCHAR(120) DEFAULT NULL,
        invite_id INT UNSIGNED DEFAULT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        last_login_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_employee_company_email (company_id,email),
        KEY idx_employee_company (company_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        actor_type VARCHAR(30) NOT NULL,
        actor_id INT UNSIGNED DEFAULT NULL,
        company_id INT UNSIGNED DEFAULT NULL,
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(60) DEFAULT NULL,
        entity_id INT UNSIGNED DEFAULT NULL,
        metadata_json JSON DEFAULT NULL,
        ip_hash CHAR(64) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_audit_company_created (company_id,created_at),
        KEY idx_audit_action_created (action,created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $companyColumns=[
        'active'=>"TINYINT(1) NOT NULL DEFAULT 1",
        'registration_token'=>"CHAR(64) DEFAULT NULL",
        'financing_mode'=>"VARCHAR(30) NOT NULL DEFAULT 'company'",
        'employee_share_percent'=>"DECIMAL(5,2) NOT NULL DEFAULT 0",
        'approval_required'=>"TINYINT(1) NOT NULL DEFAULT 1",
        'shipping_enabled'=>"TINYINT(1) NOT NULL DEFAULT 0",
        'shipping_net_cents'=>"INT UNSIGNED NOT NULL DEFAULT 0",
        'shipping_vat_rate'=>"DECIMAL(5,2) NOT NULL DEFAULT 19.00",
        'free_shipping_gross_cents'=>"INT UNSIGNED NOT NULL DEFAULT 0",
        'shipping_payer'=>"VARCHAR(20) NOT NULL DEFAULT 'company'",
        'annual_budget_cents'=>"INT UNSIGNED NOT NULL DEFAULT 0",
        // V8: one technical shop core can later serve WorkwearCenter and Fan-Bande
        // without mixing their public presentation or checkout rules.
        'shop_type'=>"VARCHAR(24) NOT NULL DEFAULT 'corporate'",
        'brand_key'=>"VARCHAR(40) NOT NULL DEFAULT 'workwearcenter'",
        'public_catalog'=>"TINYINT(1) NOT NULL DEFAULT 0",
    ];
    foreach($companyColumns as $column=>$definition) schema_add_column($pdo,'companies',$column,$definition,$changes);
    schema_add_column($pdo,'admins','auth_version','INT NOT NULL DEFAULT 1',$changes);

    $productColumns=[
        'category_id'=>"INT UNSIGNED DEFAULT NULL",
        'vat_rate'=>"DECIMAL(5,2) NOT NULL DEFAULT 19.00",
        'image_path'=>"VARCHAR(255) DEFAULT NULL",
        'decoration_type'=>"VARCHAR(80) NOT NULL DEFAULT ''",
        'decoration_details'=>"TEXT DEFAULT NULL",
        'sku'=>"VARCHAR(100) DEFAULT NULL",
        'brand'=>"VARCHAR(120) DEFAULT NULL",
        'supplier_name'=>"VARCHAR(160) DEFAULT NULL",
        'purchase_price_cents'=>"INT UNSIGNED NOT NULL DEFAULT 0",
    ];
    foreach($productColumns as $column=>$definition) schema_add_column($pdo,'products',$column,$definition,$changes);

    $companyProductColumns=[
        'custom_name'=>"VARCHAR(190) DEFAULT NULL",
        'custom_description'=>"TEXT DEFAULT NULL",
        'price_override_cents'=>"INT UNSIGNED DEFAULT NULL",
        'preview_front_path'=>"VARCHAR(255) DEFAULT NULL",
        'preview_back_path'=>"VARCHAR(255) DEFAULT NULL",
        'decorations_json'=>"LONGTEXT DEFAULT NULL",
    ];
    foreach($companyProductColumns as $column=>$definition) schema_add_column($pdo,'company_products',$column,$definition,$changes);

    $orderColumns=[
        'employee_account_id'=>"INT UNSIGNED DEFAULT NULL",
        'net_total_cents'=>"INT UNSIGNED NOT NULL DEFAULT 0",
        'gross_total_cents'=>"INT UNSIGNED NOT NULL DEFAULT 0",
        'employee_total_cents'=>"INT UNSIGNED NOT NULL DEFAULT 0",
        'company_total_cents'=>"INT UNSIGNED NOT NULL DEFAULT 0",
        'shipping_net_cents'=>"INT UNSIGNED NOT NULL DEFAULT 0",
        'shipping_gross_cents'=>"INT UNSIGNED NOT NULL DEFAULT 0",
        'shipping_employee_cents'=>"INT UNSIGNED NOT NULL DEFAULT 0",
        'shipping_company_cents'=>"INT UNSIGNED NOT NULL DEFAULT 0",
        'order_source'=>"VARCHAR(40) NOT NULL DEFAULT 'workwearcenter'",
        'payment_status'=>"VARCHAR(24) NOT NULL DEFAULT 'not_required'",
        'external_reference'=>"VARCHAR(120) DEFAULT NULL",
    ];
    foreach($orderColumns as $column=>$definition) schema_add_column($pdo,'orders',$column,$definition,$changes);

    $itemColumns=[
        'unit_net_cents'=>"INT UNSIGNED NOT NULL DEFAULT 0",
        'vat_rate'=>"DECIMAL(5,2) NOT NULL DEFAULT 19.00",
        'unit_gross_cents'=>"INT UNSIGNED NOT NULL DEFAULT 0",
        'employee_share_cents'=>"INT UNSIGNED NOT NULL DEFAULT 0",
        'company_share_cents'=>"INT UNSIGNED NOT NULL DEFAULT 0",
    ];
    foreach($itemColumns as $column=>$definition) schema_add_column($pdo,'order_items',$column,$definition,$changes);

    $extra=[
      'companies'=>[
        'budget_enabled'=>'TINYINT NOT NULL DEFAULT 0',
        'annual_budget_company_cents'=>'INT NOT NULL DEFAULT 0',
        'budget_reset_month'=>'TINYINT NOT NULL DEFAULT 1',
        'registration_mode'=>"VARCHAR(24) NOT NULL DEFAULT 'approval'",
        'registration_pin_hash'=>'VARCHAR(255) NULL',
        'delivery_mode'=>"VARCHAR(20) NOT NULL DEFAULT 'company'",
        'delivery_address'=>'TEXT NULL',
      ],
      'employee_accounts'=>[
        'registration_status'=>"VARCHAR(16) NOT NULL DEFAULT 'approved'",
        'budget_override_cents'=>'INT NULL',
        'location'=>'VARCHAR(120) NULL',
        'employee_number'=>'VARCHAR(80) NULL',
        'employment_start'=>'DATE NULL',
        'employment_end'=>'DATE NULL',
        'auth_version'=>'INT NOT NULL DEFAULT 1',
        'equipment_profile_id'=>'INT UNSIGNED NULL',
      ],
      'company_products'=>['yearly_limit_qty'=>'INT NULL'],
      'orders'=>[
        'production_note'=>'TEXT NULL','production_updated_at'=>'DATETIME NULL',
        'submission_token'=>'CHAR(64) NULL','delivery_snapshot_json'=>'TEXT NULL',
        'decision_note'=>'TEXT NULL','decided_by_account_id'=>'INT UNSIGNED NULL','decided_at'=>'DATETIME NULL',
      ],
      'order_items'=>['decoration_snapshot_json'=>'TEXT NULL'],
      'audit_log'=>['metadata_json'=>'LONGTEXT NULL','meta_json'=>'LONGTEXT NULL','ip_hash'=>'CHAR(64) NULL'],
    ];
    foreach($extra as $table=>$columns) foreach($columns as $column=>$definition) schema_add_column($pdo,$table,$column,$definition,$changes);
    $pdo->exec("ALTER TABLE audit_log MODIFY created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
    $pdo->exec("ALTER TABLE orders MODIFY fulfillment_status VARCHAR(32) NOT NULL DEFAULT 'new'");
    $pdo->exec("UPDATE audit_log SET metadata_json=meta_json WHERE metadata_json IS NULL AND meta_json IS NOT NULL");
    if(!$hadPhase4Budget && $hadV4Budget) $pdo->exec('UPDATE companies SET annual_budget_company_cents=annual_budget_cents,budget_enabled=IF(annual_budget_cents>0,1,0)');
    $q=$pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='orders' AND INDEX_NAME='uq_yw_submission'");
    if(!(int)$q->fetchColumn()) $pdo->exec('ALTER TABLE orders ADD UNIQUE KEY uq_yw_submission (submission_token)');
    $pdo->exec("CREATE TABLE IF NOT EXISTS yw_login_attempts (bucket CHAR(64) PRIMARY KEY, attempts INT NOT NULL DEFAULT 0, started_at DATETIME NOT NULL) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS yw_password_resets (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        account_id INT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_yw_reset_token (token_hash),
        KEY idx_yw_reset_account (account_id,expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS yw_admin_password_resets (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_id INT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_yw_admin_reset_token (token_hash),
        KEY idx_yw_admin_reset_account (admin_id,expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS yw_mail_queue (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        to_email VARCHAR(190) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        body MEDIUMTEXT NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'pending',
        attempts INT NOT NULL DEFAULT 0,
        available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        sent_at DATETIME NULL,
        last_error VARCHAR(500) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_yw_mail_due (status,available_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    schema_add_column($pdo,'yw_mail_queue','reply_to','VARCHAR(190) NULL',$changes);
    schema_add_index($pdo,'orders','idx_yw_orders_company_status','`company_id`,`status`,`created_at`',$changes);
    schema_add_index($pdo,'orders','idx_yw_orders_production','`status`,`fulfillment_status`,`created_at`',$changes);
    schema_add_index($pdo,'employee_accounts','idx_yw_employee_access','`company_id`,`registration_status`,`active`',$changes);
    schema_add_index($pdo,'inquiries','idx_yw_inquiries_status','`status`,`created_at`',$changes);
    $pdo->exec("CREATE TABLE IF NOT EXISTS yw_equipment_profiles (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        company_id INT UNSIGNED NOT NULL,
        name VARCHAR(120) NOT NULL,
        description VARCHAR(500) NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        is_default TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_yw_profile_company_name (company_id,name),
        KEY idx_yw_profile_company (company_id,active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS yw_equipment_profile_products (
        profile_id INT UNSIGNED NOT NULL,
        product_id INT UNSIGNED NOT NULL,
        starter_qty INT UNSIGNED NOT NULL DEFAULT 0,
        yearly_target_qty INT UNSIGNED NULL,
        PRIMARY KEY (profile_id,product_id),
        KEY idx_yw_profile_product (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS yw_decoration_motifs (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        company_id INT UNSIGNED NOT NULL,
        name VARCHAR(190) NOT NULL,
        method VARCHAR(24) NOT NULL,
        track_stock TINYINT(1) NOT NULL DEFAULT 1,
        stock_qty INT UNSIGNED NOT NULL DEFAULT 0,
        reorder_level INT UNSIGNED NOT NULL DEFAULT 0,
        notes VARCHAR(500) NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_yw_motif_company_name_method (company_id,name,method),
        KEY idx_yw_motif_company_active (company_id,active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS yw_schema_versions (version VARCHAR(32) PRIMARY KEY, installed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
    $pdo->exec("UPDATE site_settings SET setting_value=REPLACE(REPLACE(setting_value,'workwarescenter.de','workwearcenter.de'),'workwarecenter.de','workwearcenter.de') WHERE setting_key IN ('hero_button_link','footer_text','contact_body','imprint_body')");
    $pdo->exec("UPDATE site_settings SET setting_value='GESCHLOSSENE UNTERNEHMENS-SHOPS' WHERE setting_key='hero_eyebrow' AND setting_value='GESCHLOSSENE UNTERNNEHMENS-SHOPS'");
    $pdo->exec("UPDATE site_settings SET setting_value='WorkwearCenter by Yanik Textildruck · In Salzkotten entwickelt und veredelt.' WHERE setting_key='footer_text' AND setting_value LIKE '%WorkwearCenter bei Yanik Textildruck%'");
    $pdo->exec("UPDATE site_settings SET setting_value='assets/media/workwear-hero.jpg' WHERE setting_key='hero_image' AND setting_value='uploads/branding/hero-b47aae8591b275a7.jpg'");
    $pdo->exec("UPDATE site_settings SET setting_value='assets/media/workwear-jacket-v9.webp' WHERE setting_key='hero_image' AND setting_value IN ('assets/media/workwear-hero.jpg','assets/media/workwear-jacket.jpg')");
    $pdo->exec("INSERT IGNORE INTO site_settings(setting_key,setting_value) VALUES('site_title','WorkwearCenter by Yanik Textildruck')");
    foreach(['5.0.0','5.1.0','6.0.0','6.2.0','6.3.0','6.4.0','6.5.0','7.0.0','8.0.0','8.1.0','8.2.0','8.3.0'] as $version)schema_mark_version($pdo,$version,$changes);
    // Bestehende Inhalte nur dann aktualisieren, wenn sie noch exakt dem alten
    // Auslieferungsstand entsprechen. Individuell gepflegte Kundentexte bleiben erhalten.
    $pdo->exec("UPDATE site_settings SET setting_value='UNTERNEHMENSSHOPS VON YANIK WORKWEAR' WHERE setting_key='hero_eyebrow' AND setting_value IN ('GESCHLOSSENE UNTERNEHMENS-SHOPS','DER FIRMENSHOP FÜR IHR TEAM')");
    $pdo->exec("UPDATE site_settings SET setting_value='Firmenkleidung, die nicht in Excel endet.' WHERE setting_key='hero_title' AND setting_value='Arbeitskleidung auswählen. Intern freigeben. Fertig.'");
    $pdo->exec("UPDATE site_settings SET setting_value='Eine feste Kollektion für Ihr Team. Mitarbeitende bestellen selbst. Sie behalten Budgets und Freigaben im Blick. Wir beschaffen, veredeln und liefern.' WHERE setting_key='hero_text' AND setting_value='Mitarbeiter bestellen ausschließlich die für ihr Unternehmen freigegebene Bekleidung – einfach, sicher und zentral organisiert.'");
    $pdo->exec("UPDATE site_settings SET setting_value='Mustershop ansehen' WHERE setting_key='hero_secondary_text' AND setting_value IN ('Ablauf ansehen','So funktioniert’s')");
    $pdo->exec("UPDATE site_settings SET setting_value='/mustershop' WHERE setting_key='hero_secondary_link' AND (setting_value='' OR setting_value LIKE '%ablauf%')");
    schema_mark_version($pdo,'8.4.0',$changes);
    $pdo->exec("UPDATE site_settings SET setting_value='Firmenkleidung, die im Unternehmen funktioniert.' WHERE setting_key='hero_title' AND setting_value='Firmenkleidung, die nicht in Excel endet.'");
    schema_mark_version($pdo,'9.0.0',$changes);
    schema_mark_version($pdo,'9.0.1',$changes);

    // ---- Version 10: Übergabe an die Auftragsverwaltung, Admin-Sicherheit, DSGVO ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS yw_handover (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id INT UNSIGNED NOT NULL,
        event VARCHAR(40) NOT NULL DEFAULT 'order.approved',
        delivery_id CHAR(32) NOT NULL,
        payload_json LONGTEXT NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'pending',
        attempts INT NOT NULL DEFAULT 0,
        available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_error VARCHAR(500) NULL,
        last_http_status SMALLINT NULL,
        delivered_at DATETIME NULL,
        acknowledged_at DATETIME NULL,
        external_reference VARCHAR(120) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_yw_handover_order_event (order_id,event),
        UNIQUE KEY uq_yw_handover_delivery (delivery_id),
        KEY idx_yw_handover_due (status,available_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    foreach([
        'name'=>"VARCHAR(160) NULL",
        'active'=>"TINYINT(1) NOT NULL DEFAULT 1",
        'totp_secret'=>"VARCHAR(64) NULL",
        'totp_enabled'=>"TINYINT(1) NOT NULL DEFAULT 0",
        'totp_last_step'=>"BIGINT NULL",
        'recovery_codes_json'=>"TEXT NULL",
        'last_login_at'=>"DATETIME NULL",
    ] as $column=>$definition) schema_add_column($pdo,'admins',$column,$definition,$changes);
    schema_add_column($pdo,'employee_accounts','anonymized_at','DATETIME NULL',$changes);
    schema_add_column($pdo,'orders','cancelled_at','DATETIME NULL',$changes);
    schema_add_index($pdo,'yw_mail_queue','idx_yw_mail_cleanup','`status`,`sent_at`',$changes);
    schema_mark_version($pdo,'10.0.0',$changes);

    // ---- Version 10.3: Statusrückmeldung aus der Auftragszentrale ----
    foreach([
        'tracking_carrier'=>'VARCHAR(40) NULL',
        'tracking_number'=>'VARCHAR(80) NULL',
        'tracking_url'=>'VARCHAR(500) NULL',
        'status_updated_by'=>'VARCHAR(40) NULL',
    ] as $column=>$definition) schema_add_column($pdo,'orders',$column,$definition,$changes);
    // 10.3.2: Reparatur – falls eine Tabelle aus älteren Ständen ohne Spalte „id“ angelegt wurde.
    if(schema_table_exists($pdo,'yw_api_nonces') && (!schema_column_exists($pdo,'yw_api_nonces','id') || !schema_column_exists($pdo,'yw_api_nonces','expires_at'))){
        $pdo->exec('DROP TABLE yw_api_nonces'); // enthält nur kurzlebige Einmalwerte, keine Geschäftsdaten
        $changes[]='yw_api_nonces neu angelegt';
    }
    foreach(['yw_handover','yw_mail_queue','yw_password_resets','yw_admin_password_resets','yw_equipment_profiles','yw_decoration_motifs'] as $idTable){
        if(!schema_table_exists($pdo,$idTable) || schema_column_exists($pdo,$idTable,'id'))continue;
        $pk=$pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_TYPE='PRIMARY KEY'");$pk->execute([$idTable]);
        $pdo->exec("ALTER TABLE `$idTable` ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT ".((int)$pk->fetchColumn()>0?'UNIQUE':'PRIMARY KEY').' FIRST');
        $changes[]=$idTable.'.id ergänzt';
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS yw_api_nonces (id VARCHAR(190) PRIMARY KEY, expires_at DATETIME NOT NULL, KEY idx_yw_api_nonces_exp (expires_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    schema_mark_version($pdo,'10.3.0',$changes);

    // ---- Version 10.6: Produktbilder mit Logo (Motivdateien, Ansichten, Druckzonen) ----
    schema_add_column($pdo,'products','image_back_path','VARCHAR(255) NULL',$changes);
    schema_add_column($pdo,'products','image_sleeve_path','VARCHAR(255) NULL',$changes);
    schema_add_column($pdo,'products','print_zones_json','TEXT NULL',$changes);
    schema_add_column($pdo,'yw_decoration_motifs','file_path','VARCHAR(255) NULL',$changes);
    schema_mark_version($pdo,'10.6.0',$changes);

    // ---- Version 10.6.2: neues WorkwearCenter-Logo ist Standard; ein früher hochgeladenes Logo wird einmalig zurückgesetzt ----
    $v=$pdo->prepare('SELECT COUNT(*) FROM yw_schema_versions WHERE version=?');$v->execute(['10.6.2']);
    if((int)$v->fetchColumn()===0 && schema_table_exists($pdo,'site_settings')){
        $pdo->exec("DELETE FROM site_settings WHERE setting_key='site_logo'");
        $changes[]='Logo auf WorkwearCenter-Logo gesetzt';
    }
    schema_mark_version($pdo,'10.6.2',$changes);

    // ---- Version 10.6.3: Systemname WorkwearCenter in gespeicherten Einstellungen ----
    $v=$pdo->prepare('SELECT COUNT(*) FROM yw_schema_versions WHERE version=?');$v->execute(['10.6.3']);
    if((int)$v->fetchColumn()===0 && schema_table_exists($pdo,'site_settings')){
        $n=$pdo->exec("UPDATE site_settings SET setting_value=REPLACE(setting_value,'Yanik Workwear','WorkwearCenter') WHERE setting_key IN ('site_title','footer_text') AND setting_value LIKE '%Yanik Workwear%'");
        if($n)$changes[]='Systemname in Einstellungen auf WorkwearCenter gesetzt';
    }
    schema_mark_version($pdo,'10.6.3',$changes);

    // ---- Version 10.7: Kundenakte, Auftragsseite, Notizen, Erinnerungen ----
    schema_add_column($pdo,'companies','internal_note','TEXT NULL',$changes);
    schema_add_column($pdo,'orders','reminder_sent_at','DATETIME NULL',$changes);
    schema_add_column($pdo,'orders','internal_note','TEXT NULL',$changes);
    schema_add_column($pdo,'employee_accounts','reminder_sent_at','DATETIME NULL',$changes);
    schema_mark_version($pdo,'10.7.0',$changes);

    // ---- Version 10.8: API v1 mit Schlüsseln je System ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS yw_api_keys (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        `system` VARCHAR(40) NOT NULL,
        key_hash CHAR(64) NOT NULL,
        key_prefix VARCHAR(16) NOT NULL,
        scopes VARCHAR(255) NOT NULL,
        ip_allowlist VARCHAR(500) NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT UNSIGNED NULL,
        last_used_at DATETIME NULL,
        last_ip VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_yw_api_key_hash (key_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS yw_api_rate (
        key_id INT UNSIGNED NOT NULL,
        window_start DATETIME NOT NULL,
        requests INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (key_id,window_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    schema_mark_version($pdo,'10.8.0',$changes);

    // ---- Version 10.11: Umtausch & Retouren, Anmeldelink, Freigabe per E-Mail, Jahreswechsel-Mail, Sammelbestellung ----
    foreach([
        'exchange_window_days'=>'SMALLINT UNSIGNED NOT NULL DEFAULT 30',   // 0 = Umtausch/Rückgabe im Shop aus
        'budget_year_mail'=>'TINYINT(1) NOT NULL DEFAULT 1',
        'budget_year_mail_period'=>'DATE NULL',                           // Merker: Budgetjahr, für das die Mail verschickt ist
        'batch_mode'=>"VARCHAR(16) NOT NULL DEFAULT 'off'",                // off | monthly | biweekly
        'batch_day'=>'TINYINT UNSIGNED NOT NULL DEFAULT 1',
        'batch_anchor'=>'DATE NULL',
    ] as $column=>$definition) schema_add_column($pdo,'companies',$column,$definition,$changes);
    schema_add_column($pdo,'employee_accounts','budget_mail_period','DATE NULL',$changes);
    $pdo->exec("CREATE TABLE IF NOT EXISTS yw_exchange_requests (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        company_id INT UNSIGNED NOT NULL,
        order_id INT UNSIGNED NOT NULL,
        order_item_id INT UNSIGNED NOT NULL,
        employee_account_id INT UNSIGNED NULL,
        type VARCHAR(12) NOT NULL,
        quantity INT UNSIGNED NOT NULL DEFAULT 1,
        old_size VARCHAR(40) NOT NULL,
        new_size VARCHAR(40) NULL,
        reason VARCHAR(500) NOT NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'requested',
        unit_company_cents INT NOT NULL DEFAULT 0,
        budget_delta_cents INT NOT NULL DEFAULT 0,
        decision_note VARCHAR(1000) NULL,
        decided_by_account_id INT UNSIGNED NULL,
        decided_at DATETIME NULL,
        received_at DATETIME NULL,
        completed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_yw_exchange_company (company_id,status),
        KEY idx_yw_exchange_order (order_id),
        KEY idx_yw_exchange_status (status,created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS yw_login_links (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        account_id INT UNSIGNED NOT NULL,
        company_id INT UNSIGNED NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_yw_login_link_token (token_hash),
        KEY idx_yw_login_link_account (account_id,expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS yw_decision_tokens (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id INT UNSIGNED NOT NULL,
        account_id INT UNSIGNED NOT NULL,
        company_id INT UNSIGNED NOT NULL,
        nonce_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_yw_decision_nonce (nonce_hash),
        KEY idx_yw_decision_order (order_id,account_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // ---- Version 10.11: Probeshops für Interessenten (Vorschau mit eigenem Logo, läuft nach 30 Tagen ab) ----
    $pdo->exec("CREATE TABLE IF NOT EXISTS yw_probeshops (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        token CHAR(64) NOT NULL,
        inquiry_id INT UNSIGNED NULL,
        company_name VARCHAR(190) NOT NULL,
        logo_path VARCHAR(255) NULL,
        garment_tone VARCHAR(10) NOT NULL DEFAULT 'dark',
        primary_color VARCHAR(7) NULL,
        source VARCHAR(20) NOT NULL DEFAULT 'inquiry',
        created_by INT UNSIGNED NULL,
        view_count INT UNSIGNED NOT NULL DEFAULT 0,
        last_viewed_at DATETIME NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_yw_probeshop_token (token),
        KEY idx_yw_probeshop_inquiry (inquiry_id),
        KEY idx_yw_probeshop_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // ---- Version 10.11: Positionsrundung wie Lexware Office, Lexware-Anbindung ----
    // NULL = Bestellung vor 10.11 (Stückrundung); diese Beträge bleiben unverändert.
    foreach(['line_net_cents','line_gross_cents','line_employee_cents','line_company_cents'] as $column)
        schema_add_column($pdo,'order_items',$column,'INT UNSIGNED NULL',$changes);
    schema_add_column($pdo,'orders','shipping_vat_rate','DECIMAL(5,2) NULL',$changes);
    schema_add_column($pdo,'companies','lexware_contact_id','CHAR(36) NULL',$changes);
    schema_add_column($pdo,'companies','lexware_customer_number','VARCHAR(20) NULL',$changes);
    $pdo->exec("CREATE TABLE IF NOT EXISTS yw_lexware_documents (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        dedupe_key VARCHAR(120) NOT NULL,
        company_id INT UNSIGNED NOT NULL,
        kind VARCHAR(16) NOT NULL,
        order_id INT UNSIGNED NULL,
        period CHAR(7) NULL,
        group_label VARCHAR(190) NULL,
        status VARCHAR(16) NOT NULL DEFAULT 'creating',
        lexware_id CHAR(36) NULL,
        voucher_number VARCHAR(40) NULL,
        voucher_status VARCHAR(20) NULL,
        net_cents INT UNSIGNED NOT NULL DEFAULT 0,
        gross_cents INT UNSIGNED NOT NULL DEFAULT 0,
        line_count INT UNSIGNED NOT NULL DEFAULT 0,
        last_error VARCHAR(500) NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_yw_lexware_dedupe (dedupe_key),
        KEY idx_yw_lexware_company (company_id,created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // Jeder Auftrag steht höchstens in einem Lexware-Beleg (keine Doppelberechnung).
    $pdo->exec("CREATE TABLE IF NOT EXISTS yw_lexware_document_orders (
        order_id INT UNSIGNED NOT NULL PRIMARY KEY,
        document_id INT UNSIGNED NOT NULL,
        KEY idx_yw_lexware_doc (document_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    schema_mark_version($pdo,'10.11.0',$changes);
    // ---- Version 10.12: Mehrsprachiger Firmenshop ----
    // NULL = noch keine Wahl (Sprache aus Cookie/Browser, Mails auf Deutsch).
    schema_add_column($pdo,'employee_accounts','language','VARCHAR(5) NULL',$changes);
    // NULL = alle Sprachen anbieten; sonst kommagetrennte Liste, Deutsch ist immer dabei.
    schema_add_column($pdo,'companies','shop_languages','VARCHAR(40) NULL',$changes);
    // Sprache des Mailrahmens (Gruß, Schaltflächen); Inhalt wird beim Einreihen übersetzt.
    schema_add_column($pdo,'yw_mail_queue','language','VARCHAR(5) NULL',$changes);
    schema_mark_version($pdo,'10.12.0',$changes);
    // ---- Großkunden (1.500 Konten, 20.000+ Aufträge): Indizes ----
    // Budget- und Mengenprüfung je Person (Shop, Checkout) statt aller Firmenaufträge des Jahres.
    schema_add_index($pdo,'orders','idx_yw_orders_account','`employee_account_id`,`status`,`created_at`',$changes);
    schema_add_index($pdo,'orders','idx_yw_orders_invite','`invite_id`',$changes);
    // Verlauf in der Auftragsansicht; audit_log wächst unbegrenzt.
    schema_add_index($pdo,'audit_log','idx_audit_entity','`entity_type`,`entity_id`',$changes);
    schema_add_index($pdo,'yw_exchange_requests','idx_yw_exchange_item','`order_item_id`',$changes);
    // ---- Version 10.14: API-Schlüssel auf eine Firma beschränken (NULL = alle Firmen) ----
    schema_add_column($pdo,'yw_api_keys','company_id','INT UNSIGNED NULL',$changes);
    // 10.15 – Sammel-Erinnerung und Aufbewahrungsfristen
    // Letzte Sammel-Erinnerung je verantwortlicher Person (höchstens eine in 24 Stunden; NULL = noch keine).
    schema_add_column($pdo,'employee_accounts','reminder_digest_at','DATETIME NULL',$changes);
    // Stapelweises Löschen alter Protokolleinträge nach Datum, ohne die ganze Tabelle zu lesen.
    schema_add_index($pdo,'audit_log','idx_audit_created','`created_at`',$changes);
    // 10.15 – Vier-Augen-Schutz für Verantwortliche, Missbrauchsschutz Anfrageformular
    // Standard aus: Die Rolle „Verantwortlicher“ vergibt nur die Verwaltung (Kundenakte → Mitarbeiter).
    schema_add_column($pdo,'companies','managers_can_appoint','TINYINT(1) NOT NULL DEFAULT 0',$changes);
    // Eingangsbestätigung an Interessenten: höchstens 1 je Adresse in 24 Stunden und 30 je Stunde insgesamt.
    schema_add_column($pdo,'inquiries','confirmation_key','CHAR(64) NULL',$changes);
    schema_add_column($pdo,'inquiries','confirmation_sent_at','DATETIME NULL',$changes);
    schema_add_index($pdo,'inquiries','idx_yw_inquiries_confirm','`confirmation_key`,`confirmation_sent_at`',$changes);
    schema_add_index($pdo,'inquiries','idx_yw_inquiries_confirm_sent','`confirmation_sent_at`',$changes);
    schema_mark_version($pdo,'10.14.0',$changes);

    return $changes;
}
