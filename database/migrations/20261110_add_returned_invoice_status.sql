ALTER TABLE `sales_invoices`
  MODIFY COLUMN `status` enum('Unpaid','Partially Paid','Paid','Returned') NOT NULL DEFAULT 'Unpaid';

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
