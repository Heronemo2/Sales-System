-- Team Sales customer portal / ordering workflow update.
-- Run once against the existing `team_sales` database.

ALTER TABLE users
  ADD COLUMN customer_id INT UNSIGNED NULL AFTER role,
  ADD UNIQUE KEY uq_users_customer_id (customer_id);

ALTER TABLE sales_orders
  ADD COLUMN payment_method ENUM('GCash','Bank Transfer','Cash on Delivery') NOT NULL DEFAULT 'Cash on Delivery' AFTER admin_status,
  ADD COLUMN payment_status ENUM('Pending','Paid','Failed') NOT NULL DEFAULT 'Pending' AFTER payment_method,
  ADD COLUMN payment_reference VARCHAR(100) NULL AFTER payment_status,
  ADD COLUMN receipt_no VARCHAR(30) NULL AFTER payment_reference,
  ADD COLUMN paid_at DATETIME NULL AFTER receipt_no,
  ADD UNIQUE KEY uq_sales_orders_receipt_no (receipt_no);

-- Demo customer account: customer1 / customer123
-- It is linked to CUS-001. Change the password after the demo.
INSERT INTO users (full_name, username, password, role, customer_id, status)
SELECT c.customer_name, 'customer1', '$2y$12$eqGaPfNf62szN.UBBVqOPOG02PXK5fFEtJXvv06AMfFr938zt0a3.', 'Customer', c.id, 'Active'
FROM customers c
WHERE c.customer_code = 'CUS-001'
  AND NOT EXISTS (SELECT 1 FROM users WHERE username = 'customer1');


