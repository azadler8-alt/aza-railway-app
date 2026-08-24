-- ============================================================
-- سیستەمی aza — Purchase / Sales / Inventory / POS / Accounting
-- Import this file after selecting the target database (Engine: InnoDB, Charset: utf8mb4).
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- POS terminals (تیرمینالەکانی فرۆشتن)
-- ------------------------------------------------------------
CREATE TABLE pos_terminals (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  location      VARCHAR(150) DEFAULT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Users (یوزەرەکان) + permissions
-- ------------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(150) NOT NULL,
  role          ENUM('admin','it','manager','staff','cashier') NOT NULL DEFAULT 'staff',
  is_cashier    TINYINT(1) NOT NULL DEFAULT 0,
  pos_id        INT UNSIGNED DEFAULT NULL,           -- terminal fixed for cashier
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_by    INT UNSIGNED DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_pos FOREIGN KEY (pos_id) REFERENCES pos_terminals(id) ON DELETE SET NULL,
  CONSTRAINT fk_users_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- module permission keys: dashboard, companies, materials, purchase,
-- purchase_review, warehouse, it, sales, accounting, pos, reports
CREATE TABLE user_permissions (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  module_key    VARCHAR(50) NOT NULL,
  can_view      TINYINT(1) NOT NULL DEFAULT 1,
  can_edit      TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_user_module (user_id, module_key),
  CONSTRAINT fk_perm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Companies / suppliers (کۆمپانیاکان)
-- ------------------------------------------------------------
CREATE TABLE companies (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(150) NOT NULL,
  phone         VARCHAR(30) DEFAULT NULL,
  address       VARCHAR(255) DEFAULT NULL,
  is_blocked    TINYINT(1) NOT NULL DEFAULT 0,       -- soft-deleted / رەشکراوە
  created_by    INT UNSIGNED DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_company_name (name),
  CONSTRAINT fk_company_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Materials / items (مادەکان)
-- ------------------------------------------------------------
CREATE TABLE materials (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_code         VARCHAR(30) NOT NULL UNIQUE,     -- auto-generated ITM-000001
  name              VARCHAR(150) NOT NULL,
  barcode           VARCHAR(50) NOT NULL UNIQUE,
  purchase_price    DECIMAL(15,2) NOT NULL DEFAULT 0,  -- current purchase price (read-only, from last purchase)
  sale_price        DECIMAL(15,2) NOT NULL DEFAULT 0,
  cost              DECIMAL(15,2) NOT NULL DEFAULT 0,  -- weighted-average cost (read-only)
  quantity          DECIMAL(15,3) NOT NULL DEFAULT 0,  -- current stock (read-only, derived)
  image_path        VARCHAR(255) DEFAULT NULL,
  show_on_pos       TINYINT(1) NOT NULL DEFAULT 1,
  is_stopped        TINYINT(1) NOT NULL DEFAULT 0,     -- راگیراوی فرۆشتن (qty <= 0)
  stopped_reason     ENUM('sale','waste') DEFAULT NULL,
  stopped_at        DATETIME DEFAULT NULL,
  created_by        INT UNSIGNED DEFAULT NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_material_name (name),
  KEY idx_material_barcode (barcode),
  CONSTRAINT fk_material_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Purchase invoices (وەسلی کرین)
-- ------------------------------------------------------------
CREATE TABLE purchase_invoices (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  p_number      VARCHAR(30) NOT NULL UNIQUE,          -- auto P-000001
  company_id    INT UNSIGNED NOT NULL,
  total_amount  DECIMAL(15,2) NOT NULL DEFAULT 0,
  is_reviewed   TINYINT(1) NOT NULL DEFAULT 0,        -- پێداچونەوە -> locks the invoice
  reviewed_by   INT UNSIGNED DEFAULT NULL,
  reviewed_at   DATETIME DEFAULT NULL,
  created_by    INT UNSIGNED NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pi_date (created_at),
  CONSTRAINT fk_pi_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_pi_creator FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_pi_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE purchase_invoice_items (
  id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id            INT UNSIGNED NOT NULL,
  material_id           INT UNSIGNED NOT NULL,
  barcode               VARCHAR(50) NOT NULL,
  name                  VARCHAR(150) NOT NULL,
  qty                   DECIMAL(15,3) NOT NULL,
  prev_purchase_price   DECIMAL(15,2) NOT NULL DEFAULT 0,
  current_purchase_price DECIMAL(15,2) NOT NULL DEFAULT 0,
  line_total            DECIMAL(15,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_pii_invoice FOREIGN KEY (invoice_id) REFERENCES purchase_invoices(id) ON DELETE CASCADE,
  CONSTRAINT fk_pii_material FOREIGN KEY (material_id) REFERENCES materials(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Stock movements (log of every quantity change, drives مەغزەن + راپۆرت)
-- ------------------------------------------------------------
CREATE TABLE stock_movements (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  material_id   INT UNSIGNED NOT NULL,
  change_qty    DECIMAL(15,3) NOT NULL,           -- + for purchase/adjust-in, - for sale/waste
  movement_type ENUM('purchase','sale','waste','adjustment') NOT NULL,
  ref_table     VARCHAR(50) DEFAULT NULL,
  ref_id        BIGINT UNSIGNED DEFAULT NULL,
  note          VARCHAR(255) DEFAULT NULL,
  created_by    INT UNSIGNED DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sm_material (material_id),
  KEY idx_sm_date (created_at),
  CONSTRAINT fk_sm_material FOREIGN KEY (material_id) REFERENCES materials(id),
  CONSTRAINT fk_sm_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- POS sales (فرۆشتن لە POS)
-- ------------------------------------------------------------
CREATE TABLE pos_sales (
  id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_number  VARCHAR(30) NOT NULL UNIQUE,       -- S-000001
  pos_id          INT UNSIGNED NOT NULL,
  cashier_id      INT UNSIGNED NOT NULL,
  total_amount    DECIMAL(15,2) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ps_date (created_at),
  KEY idx_ps_cashier (cashier_id),
  CONSTRAINT fk_ps_pos FOREIGN KEY (pos_id) REFERENCES pos_terminals(id),
  CONSTRAINT fk_ps_cashier FOREIGN KEY (cashier_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE pos_sale_items (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id       BIGINT UNSIGNED NOT NULL,
  material_id   INT UNSIGNED NOT NULL,
  barcode       VARCHAR(50) NOT NULL,
  name          VARCHAR(150) NOT NULL,
  qty           DECIMAL(15,3) NOT NULL,
  sale_price    DECIMAL(15,2) NOT NULL,
  cost_at_sale  DECIMAL(15,2) NOT NULL DEFAULT 0,     -- snapshot of cost for profit reports
  line_total    DECIMAL(15,2) NOT NULL,
  CONSTRAINT fk_psi_sale FOREIGN KEY (sale_id) REFERENCES pos_sales(id) ON DELETE CASCADE,
  CONSTRAINT fk_psi_material FOREIGN KEY (material_id) REFERENCES materials(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Accounting: cash received from cashier (وەرگرتنی پارە لە کاشێر)
-- ------------------------------------------------------------
CREATE TABLE cash_receipts (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cashier_id    INT UNSIGNED NOT NULL,
  pos_id        INT UNSIGNED NOT NULL,
  receipt_date  DATE NOT NULL,
  amount_iqd    DECIMAL(15,2) NOT NULL DEFAULT 0,
  amount_usd    DECIMAL(15,2) NOT NULL DEFAULT 0,
  exchange_rate DECIMAL(10,2) NOT NULL DEFAULT 1460,  -- USD->IQD, editable
  received_by   INT UNSIGNED NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cr_date (receipt_date),
  CONSTRAINT fk_cr_cashier FOREIGN KEY (cashier_id) REFERENCES users(id),
  CONSTRAINT fk_cr_pos FOREIGN KEY (pos_id) REFERENCES pos_terminals(id),
  CONSTRAINT fk_cr_receiver FOREIGN KEY (received_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Partner (company) debt payments (پارەدان بە شەریک)
-- ------------------------------------------------------------
CREATE TABLE partner_payments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id    INT UNSIGNED NOT NULL,
  payment_date  DATE NOT NULL,
  amount        DECIMAL(15,2) NOT NULL,
  note          VARCHAR(255) DEFAULT NULL,
  created_by    INT UNSIGNED NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pp_date (payment_date),
  CONSTRAINT fk_pp_company FOREIGN KEY (company_id) REFERENCES companies(id),
  CONSTRAINT fk_pp_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Expenses (مەسروفات)
-- ------------------------------------------------------------
CREATE TABLE expenses (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  expense_date  DATE NOT NULL,
  name          VARCHAR(150) NOT NULL,
  qty           DECIMAL(15,3) NOT NULL DEFAULT 1,
  unit_price    DECIMAL(15,2) NOT NULL DEFAULT 0,
  total_amount  DECIMAL(15,2) NOT NULL DEFAULT 0,
  created_by    INT UNSIGNED NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_exp_date (expense_date),
  CONSTRAINT fk_exp_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Seed data: default admin. Set ADMIN_PASSWORD in the app environment on first boot.
-- ------------------------------------------------------------
INSERT INTO pos_terminals (name, location) VALUES ('POS 1','سەرەکی');

INSERT INTO users (username, password_hash, full_name, role, is_cashier, is_active)
VALUES ('admin', '$2y$10$examplehashreplacedonfirstrun.......................', 'بەڕێوەبەری سیستەم', 'admin', 0, 1);
-- The application replaces this placeholder once when ADMIN_PASSWORD is configured.
