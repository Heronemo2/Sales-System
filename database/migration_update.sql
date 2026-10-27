<<<<<<< HEAD
-- Team Sales v1.18 access-control update for MariaDB/MySQL
-- Run this once on an existing v1.17 database after backing it up.
ALTER TABLE users
  MODIFY role ENUM('Sales Staff','Sales Admin','Sales Manager','Inventory Manager','Administrator','System Admin')
  NOT NULL DEFAULT 'Sales Staff';

INSERT INTO users (full_name, username, password, role, status)
SELECT 'System Admin', 'systemadmin',
       '$2y$10$P0gR6YoT3TX43GxKBcpUVu/v60OD1j9JflccfrkJMarb4o7Vm8/Se',
       'System Admin', 'Active'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='systemadmin');
=======
-- Legacy access-control migration retained for reference.
-- For a current installation, use database/migration_v1_31.sql instead.
ALTER TABLE users MODIFY role ENUM('Sales Staff','Sales Admin','Sales Manager','Inventory Manager','Customer') NOT NULL DEFAULT 'Sales Staff';
>>>>>>> origin/main
