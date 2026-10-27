-- Team Sales v1.25 workflow update (MariaDB/MySQL). BACK UP FIRST, then run ONCE in phpMyAdmin
-- against your existing `team_sales` database.

-- 1) Sales Order needs its own Sales Admin approval.
ALTER TABLE sales_orders
  ADD COLUMN admin_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending' AFTER status;
-- Orders that already exist were created before this rule: treat them as approved.
UPDATE sales_orders SET admin_status = 'Approved';

-- 2) Deliveries remember whether Inventory has already been deducted (prevents double deduction).
ALTER TABLE deliveries
  ADD COLUMN inventory_deducted TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
-- Existing deliveries were not created by the new flow; mark them so they are never deducted again.
UPDATE deliveries SET inventory_deducted = 1;

-- 3) Make sure the Inventory table exists (skip if you already created it).
-- SOURCE database/inventory_table.sql;
