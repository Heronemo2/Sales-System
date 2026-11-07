ALTER TABLE users
  ADD COLUMN account_status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Approved'
  AFTER status;
