<<<<<<< HEAD
ALTER TABLE users MODIFY role ENUM('Sales Staff','Sales Admin','Sales Manager','Inventory Manager','Administrator') NOT NULL DEFAULT 'Sales Staff';
ALTER TABLE users ADD COLUMN last_login DATETIME NULL AFTER avatar_path;
ALTER TABLE sales_quotations ADD COLUMN admin_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending' AFTER status;
ALTER TABLE sales_returns ADD COLUMN admin_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending' AFTER status;
CREATE TABLE IF NOT EXISTS activity_logs (id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,action VARCHAR(80) NOT NULL,module VARCHAR(80) NOT NULL,record_id INT UNSIGNED NULL,description VARCHAR(255) NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY user_id(user_id),KEY module_record(module,record_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
UPDATE sales_quotations SET admin_status='Approved' WHERE status='Approved' AND admin_status='Pending';
UPDATE sales_returns SET admin_status='Approved' WHERE status IN ('Approved','Completed') AND admin_status='Pending';
INSERT INTO users (full_name,username,password,role,status) SELECT 'Sales Admin','salesadmin','$2y$10$P0gR6YoT3TX43GxKBcpUVu/v60OD1j9JflccfrkJMarb4o7Vm8/Se','Sales Admin','Active' WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='salesadmin');

INSERT INTO users (full_name,username,password,role,status) SELECT 'System Admin','systemadmin','$2y$10$P0gR6YoT3TX43GxKBcpUVu/v60OD1j9JflccfrkJMarb4o7Vm8/Se','System Admin','Active' WHERE NOT EXISTS (SELECT 1 FROM users WHERE username='systemadmin');
=======
-- v1.31 access-control migration for existing databases.
-- Run only if the corresponding columns do not already exist.
ALTER TABLE users MODIFY role ENUM('Sales Staff','Sales Admin','Sales Manager','Inventory Manager','Customer') NOT NULL DEFAULT 'Sales Staff';
ALTER TABLE users ADD COLUMN last_login DATETIME NULL AFTER avatar_path;
ALTER TABLE sales_quotations ADD COLUMN admin_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending' AFTER status;
ALTER TABLE sales_returns ADD COLUMN admin_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending' AFTER status;
CREATE TABLE IF NOT EXISTS activity_logs (log_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,action VARCHAR(80) NOT NULL,module VARCHAR(80) NOT NULL,record_id INT UNSIGNED NULL,description VARCHAR(255) NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY user_id(user_id),KEY module_record(module,record_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
>>>>>>> origin/main
