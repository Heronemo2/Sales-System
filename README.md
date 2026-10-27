# StrideCraft Footwear Sales Management System

## Scope
This project is the **Sales department module of an ERP system**. Sales owns this database and interface. Inventory is the Sales module's dependent department for **product information and stock availability**.

Sales does **not** build Procurement or Production screens. When a product is unavailable, Inventory handles the internal Procurement/Production process and later tells Sales that the finished product is available.

## Sales flow
Customer request
→ Sales Quotation
→ Inventory stock/product check
→ **Approved** → Sales Purchase Order
→ Sales Order
→ Delivery
→ Sales Invoice
→ Accounts Receivable
→ Collection

If Inventory does not have enough stock:
Quotation → **Rejected / awaiting stock**
→ Inventory/Procurement/Production work outside this module
→ Inventory restocks finished product
→ Sales clicks **Re-check Stock**
→ Approved → Purchase Order

## Inventory integration
`config/inventory_api.php` contains the Inventory endpoint placeholder.

Sales uses:
`GET /api/check_availability.php?product_code=...`

The Inventory response should include at least:
- `product_code`
- `product_id`
- `quantity`
- `available`
- optionally `unit_price`

Sales stores `product_code` and `inventory_product_id` as values/soft references. There are no Inventory tables added to this database.

Until Inventory provides the real endpoint, mock fallback is enabled for demonstrations. Set `$INVENTORY_API_MOCK_FALLBACK = false` after the real endpoint is confirmed.

## Database
`database/schema.sql` is based directly on the supplied `team_sales` schema and its reset demo values.

No additional Sales tables, product tables, inventory tables, or attributes have been added.

The login Terms of Service is handled by the login session only, so no Terms/acceptance tables are added to the supplied database schema.

For a new installation:
1. Import `database/schema.sql`.
2. Configure `config/database.php`.
3. Open the project through XAMPP/Apache/PHP.

For an existing database:
- Back up the database first.
- Use the supplied schema as the authoritative structure if resetting the database.
- `database/migration_update.sql` intentionally adds nothing.

## Login Terms of Service
The login form requires the user to tick **“I have read and agree to the Terms of Service.”**

The legal text is intentionally truncated in the login form, with a **Read full Terms / Show less** toggle so the form stays compact.

Acceptance is required before credentials are accepted for a login session. No extra database table is required.

## Demo login
Sales Staff:
Username: `crisbhert`
Password: `sales123`

Sales Admin:
Username: `salesadmin`
Password: `sales123`

System Admin:
Username: `systemadmin`
Password: `sales123`

Change the password immediately after signing in.

## v1.18 Updates
- Sales Admin no longer sees the sales modules or Sales Report.
- Removed the duplicate Sales Admin Dashboard from the navigation; Sales Admin opens Customer Management and Approvals.
- Moved system-wide User Activity monitoring out of Sales Admin and into the new System Admin area.
- Added the System Admin role and demo account `systemadmin`.
- Added server-side access guards so Sales Admin/System Admin cannot directly open the sales modules or reports.

## v1.17 Updates
- Added search bars to Sales Quotation, Purchase Order, Sales Order, Delivery, Sales Return, Sales Invoice, Accounts Receivable, Collection, and Customer Balance.
- Added **Recheck Stock** to Sales Quotations so Inventory can be checked again before proceeding.
- Added `export_sales_report.php` for a formal A4 print-ready sales summary without the normal dashboard/avatar UI. The Reports page now opens this formal export.
- Added the **Sales Admin** role with a separate admin dashboard.
- Sales Admin features: dashboard metrics, customer management, last sign-in monitoring, activity logs, and quotation/sales-return approval.
- Added `database/migration_admin_search.sql` for existing XAMPP databases. Run it once after backing up the database.
- Fresh installs can use the updated `database/schema.sql`, which includes the Sales Admin role, activity log, last-login tracking, and approval fields.


## Inventory (Standalone Demo)
The Sales project includes a read-only `inventory_products` table for standalone XAMPP demonstrations. Run `database/inventory_table.sql` on an existing `team_sales` database. Quotations check this table automatically while `$INVENTORY_USE_LOCAL_TABLE` is enabled in `config/inventory_api.php`.
