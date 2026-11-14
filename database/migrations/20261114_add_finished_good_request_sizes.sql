ALTER TABLE `customer_order_requests`
  ADD COLUMN `size` VARCHAR(30) DEFAULT NULL AFTER `product_code`;

ALTER TABLE `sales_quotations`
  ADD COLUMN `size` VARCHAR(30) DEFAULT NULL AFTER `product_code`;

ALTER TABLE `purchase_orders`
  ADD COLUMN `size` VARCHAR(30) DEFAULT NULL AFTER `product_code`;

ALTER TABLE `sales_orders`
  ADD COLUMN `size` VARCHAR(30) DEFAULT NULL AFTER `product_code`;
