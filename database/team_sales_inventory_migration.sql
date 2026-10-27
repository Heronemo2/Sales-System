-- =========================================================
-- TEAM SALES v1.49 -> TEAM SALES INVENTORY / STOCKOUT FLOW
-- Run this against the existing `team_sales` database.
-- This changes TEAM SALES only. Do NOT run this on inventory_system.
-- =========================================================

USE team_sales;

-- 1. Team Sales users: only Sales Admin can use the internal Team Sales system.
-- Customer records/users may remain in the database for the separate Customer System.
UPDATE users
SET role = 'Sales Admin',
    status = 'Inactive'
WHERE role = 'Sales Staff';

ALTER TABLE users
  MODIFY role ENUM('Sales Admin','Customer') NOT NULL DEFAULT 'Sales Admin';

-- Existing Sales Staff accounts are now inactive Sales Admin records.
-- Only the active Sales Admin account(s) can sign in to Team Sales.

-- 2. Team Sales local inventory.
CREATE TABLE IF NOT EXISTS inventory (
  inventory_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_code VARCHAR(50) NOT NULL,
  product_name VARCHAR(150) NOT NULL,
  brand VARCHAR(100) DEFAULT '',
  size VARCHAR(30) DEFAULT '',
  unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
  stock_quantity DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  reorder_level DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (inventory_id),
  UNIQUE KEY uq_team_sales_inventory_product (product_code),
  KEY idx_team_sales_inventory_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tracks the complete Team Sales <-> Inventory System stockout hand-off.
CREATE TABLE IF NOT EXISTS inventory_stockout_approvals (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  inventory_request_id INT DEFAULT NULL,
  inventory_request_number VARCHAR(50) DEFAULT NULL,
  inventory_stock_out_id INT DEFAULT NULL,
  stock_out_number VARCHAR(50) DEFAULT NULL,
  product_code VARCHAR(50) NOT NULL,
  product_name VARCHAR(150) NOT NULL,
  quantity DECIMAL(12,2) NOT NULL,
  unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
  destination VARCHAR(30) NOT NULL DEFAULT 'Sales',
  request_status ENUM('Requested','Stocked Out','Approved','Rejected') NOT NULL DEFAULT 'Requested',
  requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  stocked_out_at DATETIME DEFAULT NULL,
  reviewed_by INT UNSIGNED DEFAULT NULL,
  reviewed_at DATETIME DEFAULT NULL,
  source_reference VARCHAR(100) DEFAULT NULL,
  remarks VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_team_sales_inventory_request (inventory_request_number),
  UNIQUE KEY uq_team_sales_inventory_stockout (inventory_stock_out_id),
  KEY idx_team_sales_stockout_status (request_status),
  KEY idx_team_sales_stockout_product (product_code),
  KEY idx_team_sales_stockout_reviewer (reviewed_by),
  KEY idx_team_sales_stockout_reference (source_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IMPORTANT:
-- No inventory_system table is changed by this migration.
-- Team Sales requests are written to the existing Inventory System
-- `requests` / `request_items` tables by the PHP application.
-- The Inventory System creates its own `stock_out`.
-- Team Sales imports that stock_out and requires Admin approval before
-- adding the quantity to Team Sales `inventory`.
