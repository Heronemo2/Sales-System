-- Consolidated data migrations through 2026-11-11.
--
-- Team_Sales_Schema.sql already defines the columns, enums, tables, and
-- default settings introduced by the individual migration files. Those
-- duplicate schema changes are intentionally omitted here.
--
-- Run these backfills after importing Team_Sales_Schema.sql when migrating
-- existing records. The invoice repair appears in two old migration files;
-- it is included once here with the Returned status logic.

-- Recalculate invoice balances and statuses from approved returns.
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

-- Mark sales orders with approved returns as Returned.
UPDATE `sales_orders` o
JOIN `sales_returns` r ON r.`order_id` = o.`so_id`
SET o.`status` = 'Returned'
WHERE r.`admin_status` = 'Approved';
