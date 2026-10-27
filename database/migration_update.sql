-- Legacy access-control migration retained for reference.
-- For a current installation, use database/migration_v1_31.sql instead.
ALTER TABLE users MODIFY role ENUM('Sales Staff','Sales Admin','Sales Manager','Inventory Manager','Customer') NOT NULL DEFAULT 'Sales Staff';
