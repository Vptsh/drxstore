-- ============================================================
-- DRXStore v2.0.0 — Full MySQL Schema
-- Developed by Vineet | psvineet@zohomail.in
-- Auto-generated from MySQLDB::tableSchema()
-- Run this to create all tables on a fresh database.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Settings ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `store_name` VARCHAR(255),
  `store_address` TEXT,
  `store_phone` VARCHAR(50),
  `store_email` VARCHAR(255),
  `store_gst` VARCHAR(50),
  `store_dl` VARCHAR(50),
  `currency` VARCHAR(20) DEFAULT '&#8377;',
  `low_qty` INT DEFAULT 10,
  `expiry_days` INT DEFAULT 90,
  `smtp_host` VARCHAR(255),
  `smtp_port` INT DEFAULT 587,
  `smtp_user` VARCHAR(255),
  `smtp_pass` VARCHAR(255),
  `smtp_from` VARCHAR(255),
  `smtp_name` VARCHAR(255),
  `smtp_secure` VARCHAR(10) DEFAULT 'tls',
  `setup_done` TINYINT(1) DEFAULT 0,
  `storage` VARCHAR(20) DEFAULT 'json',
  `updated_at` DATETIME,
  `created_at` DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Users ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255),
  `username` VARCHAR(100),
  `email` VARCHAR(255),
  `password` VARCHAR(255),
  `role` VARCHAR(50) DEFAULT 'staff',
  `permissions` TEXT,
  `active` TINYINT(1) DEFAULT 1,
  `last_login` DATETIME,
  `updated_at` DATETIME,
  `created_at` DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Medicines ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `medicines` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255),
  `generic_name` VARCHAR(255),
  `company` VARCHAR(255),
  `category` VARCHAR(100),
  `custom_category` VARCHAR(100),
  `hsn_code` VARCHAR(50),
  `gst_percent` DECIMAL(5,2) DEFAULT 12,
  `description` TEXT,
  `rack_location` VARCHAR(100),
  `updated_at` DATETIME,
  `created_at` DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Categories ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100),
  `type` VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Batches ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `batches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `medicine_id` INT,
  `batch_no` VARCHAR(100),
  `mfg_date` DATE,
  `expiry_date` DATE,
  `quantity` INT DEFAULT 0,
  `purchase_price` DECIMAL(10,2),
  `mrp` DECIMAL(10,2),
  `supplier_id` INT,
  `updated_at` DATETIME,
  `created_at` DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Suppliers ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255),
  `contact` VARCHAR(255),
  `phone` VARCHAR(50),
  `email` VARCHAR(255),
  `address` TEXT,
  `gst_no` VARCHAR(50),
  `dl_no` VARCHAR(50),
  `updated_at` DATETIME,
  `created_at` DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Supplier Users ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `supplier_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_id` INT,
  `name` VARCHAR(255),
  `username` VARCHAR(100),
  `email` VARCHAR(255),
  `phone` VARCHAR(50),
  `password` VARCHAR(255),
  `active` TINYINT(1) DEFAULT 1,
  `verified` TINYINT DEFAULT 0,
  `last_login` DATETIME,
  `updated_at` DATETIME,
  `created_at` DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Customers ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255),
  `phone` VARCHAR(50),
  `email` VARCHAR(255),
  `address` TEXT,
  `dob` DATE,
  `password` VARCHAR(255),
  `active` TINYINT(1) DEFAULT 1,
  `verified` TINYINT(1) DEFAULT 0,
  `verify_token` VARCHAR(100),
  `verify_sent_at` DATETIME,
  `verified_at` DATETIME,
  `last_login` DATETIME,
  `updated_at` DATETIME,
  `created_at` DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Sales ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `sales` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT,
  `sale_date` DATE,
  `total_amount` DECIMAL(12,2),
  `gst_amount` DECIMAL(12,2),
  `discount_amount` DECIMAL(12,2),
  `discount_id` INT,
  `grand_total` DECIMAL(12,2),
  `payment_method` VARCHAR(50),
  `upi_ref` VARCHAR(100),
  `cheque_no` VARCHAR(100),
  `cheque_bank` VARCHAR(100),
  `cheque_date` DATE,
  `created_by` INT,
  `created_at` DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Sales Items ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `sales_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT,
  `medicine_id` INT,
  `batch_id` INT,
  `quantity` INT,
  `mrp` DECIMAL(10,2),
  `price` DECIMAL(12,2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Purchase Orders ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_id` INT,
  `po_date` DATE,
  `status` VARCHAR(50) DEFAULT 'pending',
  `total` DECIMAL(12,2),
  `notes` TEXT,
  `shipped_at` DATETIME,
  `received_at` DATETIME,
  `updated_at` DATETIME,
  `created_at` DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── PO Items ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `po_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `po_id` INT,
  `medicine_id` INT,
  `quantity` INT,
  `price` DECIMAL(10,2),
  `mrp` DECIMAL(10,2),
  `received_qty` INT DEFAULT 0,
  `updated_at` DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Returns ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `returns` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `sale_id` INT,
  `reason` TEXT,
  `refund_amount` DECIMAL(12,2),
  `status` VARCHAR(50) DEFAULT 'pending',
  `stock_adjusted` TINYINT(1) DEFAULT 0,
  `requested_by` VARCHAR(50),
  `customer_id` INT,
  `created_by` INT,
  `updated_at` DATETIME,
  `created_at` DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Return Items ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `return_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `return_id` INT,
  `sale_item_id` INT,
  `quantity` INT,
  `price` DECIMAL(12,2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Discounts ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `discounts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255),
  `type` VARCHAR(20),
  `value` DECIMAL(10,2),
  `min_amount` DECIMAL(10,2) DEFAULT 0,
  `active` TINYINT(1) DEFAULT 1,
  `updated_at` DATETIME,
  `created_at` DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Stock Adjustments ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `stock_adjustments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `batch_id` INT,
  `medicine_id` INT,
  `type` VARCHAR(20),
  `quantity` INT,
  `reason` VARCHAR(255),
  `old_qty` INT,
  `new_qty` INT,
  `user_id` INT,
  `created_at` DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Login Attempts ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ip` VARCHAR(45),
  `context` VARCHAR(50),
  `ts` DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Customer Purchase Log ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `customer_purchase_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT,
  `sale_id` INT,
  `amount` DECIMAL(12,2),
  `date` DATE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Supplier Messages ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `supplier_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_id` INT,
  `supplier_name` VARCHAR(255),
  `sender_email` VARCHAR(255),
  `subject` VARCHAR(255),
  `message` TEXT,
  `direction` VARCHAR(10) DEFAULT 'in',
  `reply` TEXT,
  `replied_at` DATETIME,
  `status` VARCHAR(20) DEFAULT 'unread',
  `created_at` DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Patient Messages ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `patient_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_id` INT NOT NULL,
  `direction` VARCHAR(10) DEFAULT 'in',
  `message` TEXT,
  `file_path` VARCHAR(500),
  `file_name` VARCHAR(255),
  `file_type` VARCHAR(50),
  `is_read` TINYINT(1) DEFAULT 0,
  `created_by` INT,
  `created_at` DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ════════════════════════════════════════════════════════════
-- INDEXES (Performance)
-- ════════════════════════════════════════════════════════════
CREATE INDEX IF NOT EXISTS idx_sale_date ON sales(sale_date);
CREATE INDEX IF NOT EXISTS idx_customer_id ON sales(customer_id);
CREATE INDEX IF NOT EXISTS idx_created_at ON sales(created_at);

CREATE INDEX IF NOT EXISTS idx_sale_id ON sales_items(sale_id);
CREATE INDEX IF NOT EXISTS idx_medicine_id ON sales_items(medicine_id);
CREATE INDEX IF NOT EXISTS idx_batch_id ON sales_items(batch_id);

CREATE INDEX IF NOT EXISTS idx_medicine_id ON batches(medicine_id);
CREATE INDEX IF NOT EXISTS idx_expiry_date ON batches(expiry_date);
CREATE INDEX IF NOT EXISTS idx_quantity ON batches(quantity);

CREATE INDEX IF NOT EXISTS idx_name ON medicines(name);
CREATE INDEX IF NOT EXISTS idx_category ON medicines(category);

CREATE INDEX IF NOT EXISTS idx_supplier_id ON purchase_orders(supplier_id);
CREATE INDEX IF NOT EXISTS idx_po_date ON purchase_orders(po_date);
CREATE INDEX IF NOT EXISTS idx_status ON purchase_orders(status);

CREATE INDEX IF NOT EXISTS idx_po_id ON po_items(po_id);
CREATE INDEX IF NOT EXISTS idx_medicine_id_po ON po_items(medicine_id);

CREATE INDEX IF NOT EXISTS idx_sale_id ON `returns`(sale_id);
CREATE INDEX IF NOT EXISTS idx_customer_id ON `returns`(customer_id);
CREATE INDEX IF NOT EXISTS idx_status ON `returns`(status);
CREATE INDEX IF NOT EXISTS idx_created_at ON `returns`(created_at);

CREATE INDEX IF NOT EXISTS idx_return_id ON return_items(return_id);
CREATE INDEX IF NOT EXISTS idx_sale_item_id ON return_items(sale_item_id);

CREATE INDEX IF NOT EXISTS idx_email ON customers(email);
CREATE INDEX IF NOT EXISTS idx_phone ON customers(phone);
CREATE INDEX IF NOT EXISTS idx_verified ON customers(verified);

CREATE INDEX IF NOT EXISTS idx_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_role ON users(role);

CREATE INDEX IF NOT EXISTS idx_supplier_id ON supplier_users(supplier_id);

CREATE INDEX IF NOT EXISTS idx_batch_id ON stock_adjustments(batch_id);
CREATE INDEX IF NOT EXISTS idx_medicine_id ON stock_adjustments(medicine_id);
CREATE INDEX IF NOT EXISTS idx_created_at ON stock_adjustments(created_at);

CREATE INDEX IF NOT EXISTS idx_ip_context ON login_attempts(ip, context);
CREATE INDEX IF NOT EXISTS idx_ts ON login_attempts(ts);

CREATE INDEX IF NOT EXISTS idx_customer_id ON customer_purchase_log(customer_id);
CREATE INDEX IF NOT EXISTS idx_sale_id ON customer_purchase_log(sale_id);

CREATE INDEX IF NOT EXISTS idx_customer_id ON patient_messages(customer_id);
CREATE INDEX IF NOT EXISTS idx_is_read ON patient_messages(is_read);

CREATE INDEX IF NOT EXISTS idx_supplier_id ON supplier_messages(supplier_id);
CREATE INDEX IF NOT EXISTS idx_status ON supplier_messages(status);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Schema complete. Tables auto-migrate on first boot via MySQLDB.
-- ============================================================
