CREATE TABLE IF NOT EXISTS `inventory_delivery_approvals` (
  `approval_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inventory_request_number` VARCHAR(50) NOT NULL,
  `inventory_delivery_id` INT DEFAULT NULL,
  `inventory_request_status` VARCHAR(30) NOT NULL,
  `inventory_delivery_status` VARCHAR(40) NOT NULL,
  `items_json` LONGTEXT NOT NULL,
  `approval_status` ENUM('Pending','Approved') NOT NULL DEFAULT 'Pending',
  `inventory_receipt_number` VARCHAR(50) DEFAULT NULL,
  `approved_by` INT UNSIGNED DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`approval_id`),
  UNIQUE KEY `uq_inventory_delivery_request` (`inventory_request_number`),
  KEY `idx_inventory_delivery_approval_status` (`approval_status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
