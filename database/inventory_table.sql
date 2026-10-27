-- Run this in phpMyAdmin against the existing team_sales database.
CREATE TABLE IF NOT EXISTS `inventory_products` (
  `product_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_code` varchar(40) NOT NULL,
  `product_name` varchar(100) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`product_id`),
  UNIQUE KEY `uq_inventory_product_code` (`product_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `inventory_products` (`product_code`,`product_name`,`quantity`,`unit_price`,`unit`,`status`) VALUES
('STR-101','Street Runner Sneakers',73,2097.00,'pairs','Active'),
('RUN-220','Runner Sport Shoes',65,2577.00,'pairs','Active'),
('TRN-310','Training Shoes',55,2337.00,'pairs','Active'),
('PRD-001','Nike Air Max',20,3500.00,'pairs','Active'),
('PRD-002','Jordan 1',14,5200.00,'pairs','Active'),
('PRD-003','Adidas Ultraboost',8,4800.00,'pairs','Active'),
('PRD-004','Puma Runner',0,2900.00,'pairs','Active')
ON DUPLICATE KEY UPDATE product_name=VALUES(product_name), quantity=VALUES(quantity), unit_price=VALUES(unit_price), unit=VALUES(unit), status=VALUES(status);
