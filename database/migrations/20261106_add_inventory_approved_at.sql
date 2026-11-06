ALTER TABLE inventory_stockout_approvals
    ADD COLUMN inventory_approved_at DATETIME NULL
    AFTER requested_at;
