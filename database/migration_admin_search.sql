-- v1.31 access-control migration for existing databases.
-- Run only if the corresponding columns do not already exist.
ALTER TABLE users MODIFY role ENUM('Sales Staff','Sales Admin','Sales Manager','Inventory Manager','Customer') NOT NULL DEFAULT 'Sales Staff';
ALTER TABLE users ADD COLUMN last_login DATETIME NULL AFTER avatar_path;
ALTER TABLE sales_quotations ADD COLUMN admin_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending' AFTER status;
ALTER TABLE sales_returns ADD COLUMN admin_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending' AFTER status;
CREATE TABLE IF NOT EXISTS activity_logs (log_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,action VARCHAR(80) NOT NULL,module VARCHAR(80) NOT NULL,record_id INT UNSIGNED NULL,description VARCHAR(255) NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY user_id(user_id),KEY module_record(module,record_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
