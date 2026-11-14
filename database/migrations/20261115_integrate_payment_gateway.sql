ALTER TABLE `sales_orders`
  MODIFY `payment_method` ENUM('GCash','Bank Transfer','Cash on Delivery','Debit','Credit','Debit/Credit Card') NOT NULL DEFAULT 'Cash on Delivery',
  MODIFY `payment_status` ENUM('Pending','Partially Paid','Paid','Failed') NOT NULL DEFAULT 'Pending';

ALTER TABLE `sales_quotations`
  MODIFY `payment_method` ENUM('GCash','Bank Transfer','Cash on Delivery','Debit','Credit','Debit/Credit Card') NOT NULL DEFAULT 'Cash on Delivery',
  MODIFY `payment_status` ENUM('Pending','Partially Paid','Paid','Failed') NOT NULL DEFAULT 'Pending';

ALTER TABLE `collections`
  MODIFY `method` ENUM('Cash','GCash','Bank Transfer','Cash on Delivery','Debit','Credit','Debit/Credit Card') NOT NULL DEFAULT 'Cash';

CREATE TABLE IF NOT EXISTS `gateway_payment_events` (
  `event_id` CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `transaction_ref` VARCHAR(50) NOT NULL,
  `invoice_id` INT(10) UNSIGNED NOT NULL,
  `amount_centavos` BIGINT(20) UNSIGNED NOT NULL,
  `payment_method` ENUM('GCASH','CARD') NOT NULL,
  `status` ENUM('SUCCESS','FAILED') NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`event_id`),
  UNIQUE KEY `uq_gateway_transaction_ref` (`transaction_ref`),
  KEY `idx_gateway_payment_invoice` (`invoice_id`),
  CONSTRAINT `fk_gateway_payment_invoice`
    FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`inv_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
