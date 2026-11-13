ALTER TABLE `sales_returns`
  ADD COLUMN `return_quantity` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `reason`,
  ADD COLUMN `inventory_sync_status` ENUM('Not Sent','Pending','Sent','Failed') NOT NULL DEFAULT 'Not Sent' AFTER `admin_status`,
  ADD COLUMN `inventory_sync_error` TEXT DEFAULT NULL AFTER `inventory_sync_status`,
  ADD COLUMN `inventory_synced_at` DATETIME DEFAULT NULL AFTER `inventory_sync_error`;
