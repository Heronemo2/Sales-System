ALTER TABLE `sales_orders`
  MODIFY COLUMN `status` enum('Pending','Processing','Invoiced','Completed','Cancelled','Returned') NOT NULL DEFAULT 'Pending';

UPDATE `sales_orders` o
JOIN `sales_returns` r ON r.`order_id` = o.`so_id`
SET o.`status` = 'Returned'
WHERE r.`admin_status` = 'Approved';
