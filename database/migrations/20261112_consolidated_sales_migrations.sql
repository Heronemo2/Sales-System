-- Consolidated Team Sales migrations through 2026-11-11.
--
-- Run this once, after importing database/Team_Sales_Schema.sql into a fresh
-- database. Do not also run the individual migration files in this directory.
-- This file assumes the base tables from Team_Sales_Schema.sql already exist.

-- 2026-11-06: Track Inventory approval time and supported request statuses.
ALTER TABLE `inventory_stockout_approvals`
  ADD COLUMN `inventory_approved_at` DATETIME NULL AFTER `requested_at`;

ALTER TABLE `inventory_stockout_approvals`
  MODIFY COLUMN `request_status` ENUM(
    'Requested',
    'Inventory Approved',
    'Stocked Out',
    'Approved',
    'Rejected'
  ) NOT NULL DEFAULT 'Requested';

-- 2026-11-07: Customer account verification state.
ALTER TABLE `users`
  ADD COLUMN `account_status` ENUM('Pending', 'Approved', 'Rejected')
  NOT NULL DEFAULT 'Approved' AFTER `status`;

-- 2026-11-10: Fixed Sales inventory prices.
ALTER TABLE `inventory`
  ADD COLUMN `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `stock_quantity`;

-- 2026-11-10: Shipping settings, rates, distance cache, and order shipping fees.
CREATE TABLE IF NOT EXISTS `sales_settings` (
  `setting_key` varchar(60) NOT NULL,
  `setting_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `sales_settings` (`setting_key`, `setting_value`)
VALUES ('shipping_fee', 0.00), ('shipping_rate_per_km', 10.00);

CREATE TABLE IF NOT EXISTS `sales_shipping_distance_cache` (
  `cache_key` char(64) NOT NULL,
  `distance_meters` int(10) UNSIGNED NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cache_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sales_shipping_rates` (
  `rate_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `province` varchar(100) NOT NULL,
  `city` varchar(100) NOT NULL,
  `shipping_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`rate_id`),
  UNIQUE KEY `uq_shipping_destination` (`province`,`city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `customer_order_requests`
  ADD COLUMN `shipping_fee` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `quantity`;

ALTER TABLE `sales_quotations`
  ADD COLUMN `shipping_fee` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `total_amount`;

-- 2026-11-10: Notifications for Sales users.
CREATE TABLE IF NOT EXISTS `user_notifications` (
  `notification_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `event_key` varchar(160) NOT NULL,
  `title` varchar(120) NOT NULL,
  `message` varchar(255) NOT NULL,
  `link` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`notification_id`),
  UNIQUE KEY `uq_user_notifications_event` (`user_id`,`event_key`),
  KEY `idx_user_notifications_unread` (`user_id`,`read_at`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2026-11-10: Returned invoice support and approved-return reconciliation.
-- The separate apply_existing_approved_returns migration is consolidated into
-- this single backfill so zero-balance invoices retain the Returned status.
ALTER TABLE `sales_invoices`
  MODIFY COLUMN `status` ENUM('Unpaid','Partially Paid','Paid','Returned') NOT NULL DEFAULT 'Unpaid';

UPDATE `sales_invoices` i
JOIN `sales_orders` o ON o.`so_id` = i.`order_id`
JOIN (
  SELECT `order_id`, SUM(`total_amount`) AS `approved_return_total`
  FROM `sales_returns`
  WHERE `admin_status` = 'Approved'
  GROUP BY `order_id`
) r ON r.`order_id` = i.`order_id`
SET i.`total_amount` = GREATEST(0.00, o.`total_amount` - r.`approved_return_total`),
    i.`status` = CASE
      WHEN GREATEST(0.00, o.`total_amount` - r.`approved_return_total`) <= 0 THEN 'Returned'
      WHEN i.`amount_paid` >= GREATEST(0.00, o.`total_amount` - r.`approved_return_total`) THEN 'Paid'
      WHEN i.`amount_paid` > 0 THEN 'Partially Paid'
      ELSE 'Unpaid'
    END;

-- 2026-11-11: Returned sales-order status and reconciliation.
ALTER TABLE `sales_orders`
  MODIFY COLUMN `status` ENUM('Pending','Processing','Invoiced','Completed','Cancelled','Returned') NOT NULL DEFAULT 'Pending';

UPDATE `sales_orders` o
JOIN `sales_returns` r ON r.`order_id` = o.`so_id`
SET o.`status` = 'Returned'
WHERE r.`admin_status` = 'Approved';
