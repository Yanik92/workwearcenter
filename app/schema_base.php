<?php
declare(strict_types=1);

/**
 * Grundtabellen für eine Neuinstallation. Bestehende Installationen besitzen diese
 * Tabellen bereits; alle weiteren Felder ergänzt run_schema_updates().
 */
function yw_create_base_schema(PDO $pdo): void {
    $opts='ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, email VARCHAR(190) NOT NULL, password_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_admin_email (email)) $opts");
    $pdo->exec("CREATE TABLE IF NOT EXISTS companies (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(63) NOT NULL, legal_name VARCHAR(190) NOT NULL, shop_name VARCHAR(190) NOT NULL, logo_path VARCHAR(255) DEFAULT NULL, primary_color CHAR(7) NOT NULL DEFAULT '#101b2d', accent_color CHAR(7) NOT NULL DEFAULT '#ff6b00', shop_password_hash VARCHAR(255) NOT NULL, approver_name VARCHAR(190) NOT NULL, approver_password_hash VARCHAR(255) NOT NULL, approver_token CHAR(64) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_company_slug (slug)) $opts");
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(190) NOT NULL, category VARCHAR(120) NOT NULL DEFAULT '', description TEXT NULL, price_cents INT UNSIGNED NOT NULL, sizes TEXT NOT NULL, color VARCHAR(120) NOT NULL DEFAULT '', active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) $opts");
    $pdo->exec("CREATE TABLE IF NOT EXISTS company_products (company_id INT UNSIGNED NOT NULL, product_id INT UNSIGNED NOT NULL, PRIMARY KEY (company_id,product_id), KEY idx_cp_product (product_id)) $opts");
    $pdo->exec("CREATE TABLE IF NOT EXISTS invites (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id INT UNSIGNED NOT NULL, employee_name VARCHAR(160) NOT NULL, token CHAR(64) NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_invite_company (company_id)) $opts");
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, company_id INT UNSIGNED NOT NULL, invite_id INT UNSIGNED DEFAULT NULL, total_cents INT UNSIGNED NOT NULL DEFAULT 0, status VARCHAR(16) NOT NULL DEFAULT 'pending', fulfillment_status VARCHAR(32) NOT NULL DEFAULT 'new', note TEXT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_order_company (company_id)) $opts");
    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_id INT UNSIGNED NOT NULL, product_id INT UNSIGNED NOT NULL, product_name VARCHAR(190) NOT NULL, size VARCHAR(30) NOT NULL, quantity INT UNSIGNED NOT NULL, unit_price_cents INT UNSIGNED NOT NULL, KEY idx_item_order (order_id)) $opts");
}
