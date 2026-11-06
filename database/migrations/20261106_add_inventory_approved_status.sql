ALTER TABLE inventory_stockout_approvals
    MODIFY request_status ENUM(
        'Requested',
        'Inventory Approved',
        'Stocked Out',
        'Approved',
        'Rejected'
    ) NOT NULL DEFAULT 'Requested';
