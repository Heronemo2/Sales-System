ALTER TABLE inventory
    ADD COLUMN unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER stock_quantity;
