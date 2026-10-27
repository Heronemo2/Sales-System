-- v1.31 migration for an existing v1.30 database. Back up first.

ALTER TABLE inventory_products ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) DEFAULT NULL AFTER status;

-- Reassign legacy administrator-type accounts to Sales Admin before removing the old roles.
UPDATE users SET role='Sales Admin' WHERE role NOT IN ('Sales Staff','Sales Admin','Sales Manager','Inventory Manager','Customer');

ALTER TABLE users MODIFY role ENUM('Sales Staff','Sales Admin','Sales Manager','Inventory Manager','Customer') NOT NULL DEFAULT 'Sales Staff';

ALTER TABLE sales_quotations MODIFY admin_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending';
