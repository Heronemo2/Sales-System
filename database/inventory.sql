CREATE DATABASE IF NOT EXISTS team_inventory_test
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE team_inventory_test;

CREATE TABLE inventory_products (
    product_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_code VARCHAR(40) NOT NULL,
    product_name VARCHAR(100) NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 0,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    unit VARCHAR(20) NOT NULL DEFAULT 'pcs',
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id),
    UNIQUE KEY uq_product_code (product_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO inventory_products
(product_code, product_name, quantity, unit_price, unit, status)
VALUES
('PRD-001', 'Nike Air Max', 20, 3500.00, 'pairs', 'Active'),
('PRD-002', 'Jordan 1', 14, 5200.00, 'pairs', 'Active'),
('PRD-003', 'Adidas Ultraboost', 8, 4800.00, 'pairs', 'Active'),
('PRD-004', 'Puma Runner', 0, 2900.00, 'pairs', 'Active');