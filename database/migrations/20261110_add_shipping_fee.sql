CREATE TABLE IF NOT EXISTS `sales_settings` (
  `setting_key` varchar(60) NOT NULL,
  `setting_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `sales_settings` (`setting_key`, `setting_value`) VALUES ('shipping_fee', 0.00), ('shipping_rate_per_km', 10.00);

CREATE TABLE IF NOT EXISTS `sales_shipping_distance_cache` (
  `cache_key` char(64) NOT NULL,
  `distance_meters` int(10) UNSIGNED NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cache_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `customer_order_requests`
  ADD COLUMN `shipping_fee` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `quantity`;

ALTER TABLE `sales_quotations`
  ADD COLUMN `shipping_fee` decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `total_amount`;
