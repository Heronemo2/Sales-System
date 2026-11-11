INSERT IGNORE INTO `sales_settings` (`setting_key`, `setting_value`)
VALUES ('shipping_rate_per_km', 10.00);

CREATE TABLE IF NOT EXISTS `sales_shipping_distance_cache` (
  `cache_key` char(64) NOT NULL,
  `distance_meters` int(10) UNSIGNED NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cache_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
