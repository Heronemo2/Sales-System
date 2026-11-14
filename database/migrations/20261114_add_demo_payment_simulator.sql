ALTER TABLE `sales_orders`
  MODIFY `payment_method` ENUM('GCash','Bank Transfer','Cash on Delivery','Debit','Credit') NOT NULL DEFAULT 'Cash on Delivery',
  MODIFY `payment_status` ENUM('Pending','Partially Paid','Paid','Failed') NOT NULL DEFAULT 'Pending';

ALTER TABLE `sales_quotations`
  MODIFY `payment_method` ENUM('GCash','Bank Transfer','Cash on Delivery','Debit','Credit') NOT NULL DEFAULT 'Cash on Delivery';

ALTER TABLE `collections`
  MODIFY `method` ENUM('Cash','GCash','Bank Transfer','Cash on Delivery','Debit','Credit') NOT NULL DEFAULT 'Cash';

CREATE TABLE IF NOT EXISTS `demo_payment_attempts` (
  `demo_payment_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_no` VARCHAR(40) NOT NULL,
  `invoice_id` INT UNSIGNED NOT NULL,
  `order_id` INT UNSIGNED NOT NULL,
  `customer_id` INT UNSIGNED NOT NULL,
  `method` ENUM('Cash on Delivery','Debit','Credit','GCash') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `status` ENUM('Pending','Simulated Paid','Simulated Failed','COD Due','COD Collected (Simulated)') NOT NULL,
  `simulated_auth_code` VARCHAR(40) DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `completed_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`demo_payment_id`),
  UNIQUE KEY `uq_demo_payment_reference` (`reference_no`),
  KEY `idx_demo_payment_invoice` (`invoice_id`,`demo_payment_id`),
  KEY `idx_demo_payment_status` (`status`,`created_at`),
  CONSTRAINT `fk_demo_payment_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`inv_id`),
  CONSTRAINT `fk_demo_payment_order` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`so_id`),
  CONSTRAINT `fk_demo_payment_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`cus_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
