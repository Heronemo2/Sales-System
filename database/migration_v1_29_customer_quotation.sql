-- Team Sales v1.29 customer quotation workflow
-- Run once on the existing team_sales database.

CREATE TABLE IF NOT EXISTS customer_order_requests (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_no VARCHAR(30) NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  request_date DATE NOT NULL,
  product_code VARCHAR(40) NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  notes VARCHAR(500) NULL,
  status ENUM('Pending Quotation','Quotation Sent','Quotation Approved','Quotation Rejected','Converted','Cancelled') NOT NULL DEFAULT 'Pending Quotation',
  quotation_id INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customer_request_no (request_no),
  KEY idx_customer_request_customer (customer_id),
  KEY idx_customer_request_quotation (quotation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE sales_quotations
  ADD COLUMN source_request_id INT UNSIGNED NULL AFTER customer_id,
  ADD COLUMN inventory_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending' AFTER inventory_checked_at,
  ADD COLUMN customer_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending' AFTER inventory_status;

-- For existing quotations, preserve the old status as the customer-facing decision.
UPDATE sales_quotations
SET customer_status = CASE
    WHEN status IN ('Approved','Rejected') THEN status
    ELSE 'Pending'
END
WHERE customer_status = 'Pending';

-- Old admin approval is no longer used for quotations.
UPDATE sales_quotations SET admin_status='Approved' WHERE admin_status='Pending' AND status='Approved';

ALTER TABLE sales_quotations
  ADD KEY idx_sales_quotations_source_request (source_request_id);

-- The quotation PDF is the customer-facing document. Existing quotation data remains intact.
