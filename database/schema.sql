CREATE DATABASE IF NOT EXISTS team_sales
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE team_sales;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    username VARCHAR(60) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'Sales Staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(120),
    phone VARCHAR(30),
    email VARCHAR(150),
    address VARCHAR(255),
    status ENUM('Active','Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sales_quotations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quotation_no VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT UNSIGNED NOT NULL,
    quotation_date DATE NOT NULL,
    valid_until DATE,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('Pending','Approved','Rejected','Expired') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE TABLE IF NOT EXISTS sales_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_no VARCHAR(30) NOT NULL UNIQUE,
    quotation_id INT UNSIGNED NULL,
    customer_id INT UNSIGNED NOT NULL,
    order_date DATE NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('Pending','Processing','Invoiced','Completed','Cancelled') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quotation_id) REFERENCES sales_quotations(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE TABLE IF NOT EXISTS sales_invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(30) NOT NULL UNIQUE,
    order_id INT UNSIGNED NOT NULL,
    invoice_date DATE NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('Unpaid','Partially Paid','Paid') DEFAULT 'Unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES sales_orders(id)
);

CREATE TABLE IF NOT EXISTS sales_returns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    return_no VARCHAR(30) NOT NULL UNIQUE,
    order_id INT UNSIGNED NOT NULL,
    return_date DATE NOT NULL,
    reason VARCHAR(255),
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('Pending','Approved','Rejected','Completed') DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES sales_orders(id)
);

-- Remove the old demo account if this schema was imported before.
DELETE FROM users WHERE username = 'sales';

-- Real account (replaces the old demo login). Password is: sales123
-- Please sign in and change the password from Account Settings right away.
INSERT INTO users (name, username, password, role)
VALUES ('Crisbhert', 'crisbhert', '$2y$10$P0gR6YoT3TX43GxKBcpUVu/v60OD1j9JflccfrkJMarb4o7Vm8/Se', 'Sales Staff')
ON DUPLICATE KEY UPDATE username = username;


CREATE TABLE IF NOT EXISTS deliveries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    delivery_no VARCHAR(30) NOT NULL UNIQUE,
    order_id INT UNSIGNED NOT NULL,
    delivery_date DATE NOT NULL,
    status ENUM('Preparing','In Transit','Delivered') DEFAULT 'Preparing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES sales_orders(id)
);

CREATE TABLE IF NOT EXISTS collections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    collection_no VARCHAR(30) NOT NULL UNIQUE,
    invoice_id INT UNSIGNED NOT NULL,
    collection_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    method ENUM('Cash','GCash','Bank Transfer') DEFAULT 'Cash',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES sales_invoices(id)
);

CREATE TABLE IF NOT EXISTS account_receivable (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    amount_due DECIMAL(12,2) NOT NULL DEFAULT 0,
    due_date DATE NOT NULL,
    status ENUM('Current','Overdue','Paid') DEFAULT 'Current',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES sales_invoices(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);
 
CREATE TABLE IF NOT EXISTS customer_balance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL UNIQUE,
    total_billed DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
    outstanding_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);
