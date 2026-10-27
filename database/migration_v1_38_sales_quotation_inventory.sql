-- v1.38: Sales quotation references the Inventory finished_goods table.
-- Run this against team_sales after backing up the database.

ALTER TABLE sales_quotations
    MODIFY COLUMN fg_id INT(10) NULL;

ALTER TABLE sales_quotations
    ADD INDEX idx_sales_quotations_fg_id (fg_id);

-- If this foreign key already exists, do not add it twice.
-- For a fresh/clean table, use:
-- ALTER TABLE sales_quotations
--     ADD CONSTRAINT fk_inventory_sales
--     FOREIGN KEY (fg_id)
--     REFERENCES inventory_management.finished_goods(id)
--     ON UPDATE CASCADE
--     ON DELETE SET NULL;
