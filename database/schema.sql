CREATE DATABASE IF NOT EXISTS team_sales;

USE team_sales;

-- =========================================================
-- PRIMARY KEY NAMING
--   account_receivable      -> ar_id
--   collections             -> col_id
--   customers               -> cus_id
--   customer_balance        -> cb_id
--   customer_order_requests -> req_id
--   deliveries              -> del_id
--   purchase_orders         -> po_id
--   sales_invoices          -> inv_id
--   sales_orders            -> so_id
--   sales_quotations        -> sq_id
--   sales_returns           -> ret_id
--   users                   -> user_id
--   activity_logs           -> log_id
-- =========================================================

-- Local Inventory reference table for standalone Sales/XAMPP operation.
-- In a multi-team deployment this can be replaced by the Inventory team's API.
/* CREATE TABLE `inventory_products` (
    `product_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `finished_good_id` int(10) NOT NULL,
    `product_name` varchar(100) NOT NULL,
    `brand` varchar(100) DEFAULT NULL,
    `size` varchar(50) DEFAULT NULL,
    `unit` varchar(20) DEFAULT 'pcs',
    `reorder_level` int(10) UNSIGNED NOT NULL DEFAULT 0,
    `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
    `image_path` varchar(255) DEFAULT NULL,
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp()
        ON UPDATE current_timestamp(),
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),

    PRIMARY KEY (`product_id`),
    UNIQUE KEY `uq_inventory_finished_good_id` (`finished_good_id`)
) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
*/

/*
CREATE TABLE `available_stock` (
  `as_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` int(10) UNSIGNED NOT NULL,
  `product_code` varchar(40) NOT NULL,
  `product_name` varchar(50) DEFAULT NULL,
  `stock_available` int(20) NOT NULL DEFAULT 0,
  `unit` enum('pcs','pairs') NOT NULL DEFAULT 'pairs',
  PRIMARY KEY (`as_id`),
  FOREIGN KEY (`product_id`) REFERENCES `inventory_products` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/


-- =========================================================
-- TABLES
-- (Primary keys are defined inline, so no separate
--  "ADD PRIMARY KEY" statements are needed afterwards.)
-- =========================================================

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `cus_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(30) NOT NULL,
  `customer_name` varchar(120) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `payment_terms` varchar(60) NOT NULL DEFAULT '30 Days',
  `credit_limit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cus_id`),
  UNIQUE KEY `customer_code` (`customer_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` varchar(120) NOT NULL,
  `username` varchar(60) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Sales Admin','Sales Staff','Customer') NOT NULL DEFAULT 'Sales Admin',
  `customer_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `avatar_path` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `uq_users_customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `customer_order_requests`
--

CREATE TABLE `customer_order_requests` (
  `req_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_no` varchar(30) NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `request_date` date NOT NULL,
  `product_code` varchar(40) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `status` enum('Pending Quotation','Quotation Sent','Quotation Approved','Quotation Rejected','Converted','Cancelled') NOT NULL DEFAULT 'Pending Quotation',
  `quotation_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`req_id`),
  UNIQUE KEY `uq_customer_request_no` (`request_no`),
  KEY `idx_customer_request_customer` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `sales_quotations`
--

CREATE TABLE `sales_quotations` (
  `sq_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `quotation_no` varchar(30) NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `source_request_id` int(10) UNSIGNED DEFAULT NULL,
  `quotation_date` date NOT NULL,
  `valid_until` date DEFAULT NULL,
  `product_code` varchar(40) DEFAULT NULL,
  `fg_id` int(10) DEFAULT NULL,
  `quantity` int(10) UNSIGNED DEFAULT NULL,
  `inventory_quantity` int(10) UNSIGNED DEFAULT NULL,
  `inventory_checked_at` datetime DEFAULT NULL,
  `inventory_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `customer_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('Pending','Approved','Rejected','Expired') NOT NULL DEFAULT 'Pending',
  `admin_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `payment_method` enum('GCash','Bank Transfer','Cash on Delivery') NOT NULL DEFAULT 'Cash on Delivery',
  `payment_status` enum('Pending','Paid','Failed') NOT NULL DEFAULT 'Pending',
  `payment_reference` varchar(100) DEFAULT NULL,
  `receipt_no` varchar(30) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`sq_id`),
  UNIQUE KEY `uq_sales_quotation_no` (`quotation_no`),
  KEY `idx_sales_quotation_customer` (`customer_id`),
  KEY `idx_sales_quotation_fg_id` (`fg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `po_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `po_no` varchar(30) NOT NULL,
  `quotation_id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `product_code` varchar(40) NOT NULL,
  `inventory_product_id` varchar(60) DEFAULT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `order_date` date NOT NULL,
  `status` enum('Open','Converted','Cancelled') NOT NULL DEFAULT 'Open',
  `converted_order_no` varchar(30) DEFAULT NULL,
  `converted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`po_id`),
  UNIQUE KEY `po_no` (`po_no`),
  KEY `quotation_id` (`quotation_id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `sales_orders`
--

CREATE TABLE `sales_orders` (
  `so_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `order_no` varchar(30) NOT NULL,
  `quotation_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `order_date` date NOT NULL,
  `product_code` varchar(40) DEFAULT NULL,
  `quantity` int(10) UNSIGNED DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('Pending','Processing','Invoiced','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `admin_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `payment_method` enum('GCash','Bank Transfer','Cash on Delivery') NOT NULL DEFAULT 'Cash on Delivery',
  `payment_status` enum('Pending','Paid','Failed') NOT NULL DEFAULT 'Pending',
  `payment_reference` varchar(100) DEFAULT NULL,
  `receipt_no` varchar(30) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`so_id`),
  UNIQUE KEY `order_no` (`order_no`),
  UNIQUE KEY `receipt_no` (`receipt_no`),
  KEY `quotation_id` (`quotation_id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `deliveries`
--

CREATE TABLE `deliveries` (
  `del_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `delivery_no` varchar(30) NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `delivery_date` date NOT NULL,
  `status` enum('Preparing','In Transit','Delivered') NOT NULL DEFAULT 'Preparing',
  `inventory_deducted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`del_id`),
  UNIQUE KEY `delivery_no` (`delivery_no`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `sales_invoices`
--

CREATE TABLE `sales_invoices` (
  `inv_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(30) NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `invoice_date` date NOT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('Unpaid','Partially Paid','Paid') NOT NULL DEFAULT 'Unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`inv_id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `account_receivable`
--

CREATE TABLE `account_receivable` (
  `ar_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `amount_due` decimal(12,2) NOT NULL DEFAULT 0.00,
  `due_date` date NOT NULL,
  `status` enum('Current','Overdue','Paid') NOT NULL DEFAULT 'Current',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ar_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `collections`
--

CREATE TABLE `collections` (
  `col_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `collection_no` varchar(30) NOT NULL,
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `collection_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `method` enum('Cash','GCash','Bank Transfer') NOT NULL DEFAULT 'Cash',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`col_id`),
  UNIQUE KEY `collection_no` (`collection_no`),
  KEY `invoice_id` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `customer_balance`
--

CREATE TABLE `customer_balance` (
  `cb_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `customer_id` int(10) UNSIGNED NOT NULL,
  `total_billed` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_paid` decimal(12,2) NOT NULL DEFAULT 0.00,
  `outstanding_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`cb_id`),
  UNIQUE KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `sales_returns`
--

CREATE TABLE `sales_returns` (
  `ret_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `return_no` varchar(30) NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `return_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('Pending','Approved','Rejected','Completed') NOT NULL DEFAULT 'Pending',
  `admin_status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ret_id`),
  UNIQUE KEY `return_no` (`return_no`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `action` varchar(80) NOT NULL,
  `module` varchar(80) NOT NULL,
  `record_id` int(10) UNSIGNED DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`),
  KEY `module_record` (`module`,`record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =========================================================
-- TEAM SALES INVENTORY
-- Local inventory belongs to Team Sales.
-- It is replenished ONLY after an Inventory System stock-out
-- has been approved by the Team Sales Admin.
-- =========================================================

CREATE TABLE `inventory` (
  `inventory_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `brand` varchar(100) DEFAULT '',
  `size` varchar(30) DEFAULT '',
  `unit` varchar(30) NOT NULL DEFAULT 'pcs',
  `stock_quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reorder_level` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `last_updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`inventory_id`),
  UNIQUE KEY `uq_team_sales_inventory_product` (`product_code`),
  KEY `idx_team_sales_inventory_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_stockout_approvals` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `inventory_request_id` int(10) DEFAULT NULL,
  `inventory_request_number` varchar(50) DEFAULT NULL,
  `inventory_stock_out_id` int(10) DEFAULT NULL,
  `stock_out_number` varchar(50) DEFAULT NULL,
  `product_code` varchar(50) NOT NULL,
  `product_name` varchar(150) NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit` varchar(30) NOT NULL DEFAULT 'pcs',
  `destination` varchar(30) NOT NULL DEFAULT 'Sales',
  `request_status` enum('Requested','Stocked Out','Approved','Rejected') NOT NULL DEFAULT 'Requested',
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `stocked_out_at` datetime DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `source_reference` varchar(100) DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_team_sales_inventory_request` (`inventory_request_number`),
  UNIQUE KEY `uq_team_sales_inventory_stockout` (`inventory_stock_out_id`),
  KEY `idx_team_sales_stockout_status` (`request_status`),
  KEY `idx_team_sales_stockout_product` (`product_code`),
  KEY `idx_team_sales_stockout_reviewer` (`reviewed_by`),
  KEY `idx_team_sales_stockout_reference` (`source_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================================================
-- FOREIGN KEY CONSTRAINTS
-- (all now point to the renamed primary key columns)
-- =========================================================

ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`cus_id`);

ALTER TABLE `customer_order_requests`
  ADD CONSTRAINT `customer_order_requests_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`cus_id`);

ALTER TABLE `sales_quotations`
  ADD CONSTRAINT `sales_quotations_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`cus_id`);

ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`quotation_id`) REFERENCES `sales_quotations` (`sq_id`),
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`cus_id`);

ALTER TABLE `sales_orders`
  ADD CONSTRAINT `sales_orders_ibfk_1` FOREIGN KEY (`quotation_id`) REFERENCES `sales_quotations` (`sq_id`),
  ADD CONSTRAINT `sales_orders_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`cus_id`);

ALTER TABLE `deliveries`
  ADD CONSTRAINT `deliveries_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`so_id`);

ALTER TABLE `sales_invoices`
  ADD CONSTRAINT `sales_invoices_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`so_id`);

ALTER TABLE `account_receivable`
  ADD CONSTRAINT `account_receivable_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`inv_id`),
  ADD CONSTRAINT `account_receivable_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`cus_id`);

ALTER TABLE `collections`
  ADD CONSTRAINT `collections_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`inv_id`);

ALTER TABLE `customer_balance`
  ADD CONSTRAINT `customer_balance_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`cus_id`);

ALTER TABLE `sales_returns`
  ADD CONSTRAINT `sales_returns_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `sales_orders` (`so_id`);


-- =========================================================
-- SAMPLE DATA
-- =========================================================

-- =========================================================
-- 1. CUSTOMERS
-- =========================================================

INSERT INTO customers
(
    cus_id,
    customer_code,
    customer_name,
    phone,
    email,
    address,
    city,
    province,
    payment_terms,
    credit_limit,
    status
)
VALUES
(
    1,
    'CUS-001',
    'Banaag Studio',
    '09171234567',
    'banaagstudio@gmail.com',
    'Rizal Avenue',
    'San Pablo City',
    'Laguna',
    '30 Days',
    50000.00,
    'Active'
),
(
    2,
    'CUS-002',
    'Ilagan Print Co.',
    '09181234567',
    'ilaganprint@gmail.com',
    'Maharlika Highway',
    'San Pablo City',
    'Laguna',
    '30 Days',
    75000.00,
    'Active'
),
(
    3,
    'CUS-003',
    'San Pablo Supplies',
    '09191234567',
    'sanpablosupplies@gmail.com',
    'Barangay San Rafael',
    'San Pablo City',
    'Laguna',
    '15 Days',
    100000.00,
    'Active'
);


-- =========================================================
-- 2. USERS
-- =========================================================
-- Password for the sample accounts: 123456
-- The value below is a bcrypt hash of "123456" (verified),
-- so PHP's password_verify('123456', $hash) will return true.
-- =========================================================

INSERT INTO users
(
    user_id,
    full_name,
    username,
    password,
    role,
    customer_id,
    status
)
VALUES
(
    1,
    'Sales Admin User',
    'salesadmin',
    '$2y$10$xKsjFtJ3esppT.rjOD4MwuNrnjy9S8TFX2tNaMHLgLD9W0vsL2si2',
    'Sales Admin',
    NULL,
    'Active'
),
(
    2,
    'Sales Staff User',
    'salesstaff',
    '$2y$10$xKsjFtJ3esppT.rjOD4MwuNrnjy9S8TFX2tNaMHLgLD9W0vsL2si2',
    'Sales Staff',
    NULL,
    'Active'
),
(
    3,
    'Customer User',
    'customer',
    '$2y$10$xKsjFtJ3esppT.rjOD4MwuNrnjy9S8TFX2tNaMHLgLD9W0vsL2si2',
    'Customer',
    1,
    'Active'
);


-- =========================================================
-- 3. CUSTOMER ORDER REQUESTS
-- =========================================================

INSERT INTO customer_order_requests
(
    req_id,
    request_no,
    customer_id,
    request_date,
    product_code,
    quantity,
    notes,
    status,
    quotation_id
)
VALUES
(
    1,
    'REQ-0001',
    1,
    '2026-10-01',
    'FG-001',
    10,
    'Customer requested standard packaging.',
    'Pending Quotation',
    NULL
),
(
    2,
    'REQ-0002',
    2,
    '2026-10-02',
    'FG-002',
    20,
    'Customer needs the items before the end of the month.',
    'Pending Quotation',
    NULL
),
(
    3,
    'REQ-0003',
    3,
    '2026-10-03',
    'FG-003',
    15,
    'Please confirm stock availability.',
    'Pending Quotation',
    NULL
);


-- =========================================================
-- 4. SALES QUOTATIONS
-- =========================================================

INSERT INTO sales_quotations
(
    sq_id,
    quotation_no,
    customer_id,
    source_request_id,
    quotation_date,
    valid_until,
    product_code,
    fg_id,
    quantity,
    inventory_quantity,
    inventory_checked_at,
    inventory_status,
    customer_status,
    total_amount,
    status,
    admin_status,
    payment_method,
    payment_status
)
VALUES
(
    1,
    'QT-2000',
    1,
    1,
    '2026-10-01',
    '2026-10-15',
    'FG-001',
    1,
    10,
    50,
    '2026-10-01 09:00:00',
    'Approved',
    'Pending',
    5000.00,
    'Pending',
    'Pending',
    'Cash on Delivery',
    'Pending'
),
(
    2,
    'QT-2001',
    2,
    2,
    '2026-10-02',
    '2026-10-16',
    'FG-002',
    2,
    20,
    100,
    '2026-10-02 10:00:00',
    'Approved',
    'Approved',
    12000.00,
    'Approved',
    'Approved',
    'GCash',
    'Paid'
);


-- =========================================================
-- 5. PURCHASE ORDERS
-- =========================================================

INSERT INTO purchase_orders
(
    po_id,
    po_no,
    quotation_id,
    customer_id,
    product_code,
    inventory_product_id,
    quantity,
    total_amount,
    order_date,
    status,
    converted_order_no,
    converted_at
)
VALUES
(
    1,
    'PO-0001',
    2,
    2,
    'FG-002',
    '2',
    20,
    12000.00,
    '2026-10-03',
    'Converted',
    'SO-0001',
    '2026-10-03 11:00:00'
);


-- =========================================================
-- 6. SALES ORDERS
-- =========================================================

INSERT INTO sales_orders
(
    so_id,
    order_no,
    quotation_id,
    customer_id,
    order_date,
    product_code,
    quantity,
    total_amount,
    status,
    admin_status,
    payment_method,
    payment_status,
    payment_reference,
    receipt_no,
    paid_at
)
VALUES
(
    1,
    'SO-0001',
    2,
    2,
    '2026-10-03',
    'FG-002',
    20,
    12000.00,
    'Completed',
    'Approved',
    'GCash',
    'Paid',
    'GC-20261003-001',
    'RCPT-0001',
    '2026-10-03 12:00:00'
);


-- =========================================================
-- 7. DELIVERIES
-- =========================================================

INSERT INTO deliveries
(
    del_id,
    delivery_no,
    order_id,
    delivery_date,
    status,
    inventory_deducted
)
VALUES
(
    1,
    'DEL-0001',
    1,
    '2026-10-04',
    'Delivered',
    1
);


-- =========================================================
-- 8. SALES INVOICES
-- =========================================================

INSERT INTO sales_invoices
(
    inv_id,
    invoice_no,
    order_id,
    invoice_date,
    total_amount,
    amount_paid,
    status
)
VALUES
(
    1,
    'INV-0001',
    1,
    '2026-10-04',
    12000.00,
    12000.00,
    'Paid'
);


-- =========================================================
-- 9. ACCOUNT RECEIVABLE
-- =========================================================

INSERT INTO account_receivable
(
    ar_id,
    invoice_id,
    customer_id,
    amount_due,
    due_date,
    status
)
VALUES
(
    1,
    1,
    2,
    12000.00,
    '2026-11-03',
    'Paid'
);


-- =========================================================
-- 10. COLLECTIONS
-- =========================================================

INSERT INTO collections
(
    col_id,
    collection_no,
    invoice_id,
    collection_date,
    due_date,
    amount,
    method
)
VALUES
(
    1,
    'COL-0001',
    1,
    '2026-10-04',
    '2026-11-03',
    12000.00,
    'GCash'
);


-- =========================================================
-- 11. CUSTOMER BALANCE
-- =========================================================

INSERT INTO customer_balance
(
    cb_id,
    customer_id,
    total_billed,
    total_paid,
    outstanding_balance
)
VALUES
(
    1,
    1,
    5000.00,
    0.00,
    5000.00
),
(
    2,
    2,
    12000.00,
    12000.00,
    0.00
),
(
    3,
    3,
    0.00,
    0.00,
    0.00
);


-- =========================================================
-- 12. SALES RETURNS
-- =========================================================

INSERT INTO sales_returns
(
    ret_id,
    return_no,
    order_id,
    return_date,
    reason,
    total_amount,
    status,
    admin_status
)
VALUES
(
    1,
    'RET-0001',
    1,
    '2026-10-05',
    'Customer reported damaged packaging.',
    600.00,
    'Pending',
    'Pending'
);
