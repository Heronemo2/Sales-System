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

<<<<<<< HEAD
Sales stores `product_code` and `inventory_product_id` as values/soft references. There are no Inventory tables added to this database.
=======
Sales quotations store `finished_good_code` and `fg_id`; downstream Purchase Orders/Sales Orders retain their existing product reference columns. There are no Inventory tables added to this database.
>>>>>>> origin/main

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

<<<<<<< HEAD
System Admin:
Username: `systemadmin`
=======
>>>>>>> origin/main
Password: `sales123`

Change the password immediately after signing in.

<<<<<<< HEAD
## v1.18 Updates
- Sales Admin no longer sees the sales modules or Sales Report.
- Removed the duplicate Sales Admin Dashboard from the navigation; Sales Admin opens Customer Management and Approvals.
- Moved system-wide User Activity monitoring out of Sales Admin and into the new System Admin area.
- Added the System Admin role and demo account `systemadmin`.
- Added server-side access guards so Sales Admin/System Admin cannot directly open the sales modules or reports.
=======
## v1.25 Updates (workflow)
Quotation (Sales Staff) → Inventory check (automatic, `inventory_products` table) → **Sales Admin approves quotation** → **Purchase Order is created automatically** (PDF / Word export) → Sales Staff creates a **Sales Order from the PO** → **Sales Admin approves the Sales Order** → **Delivery is created automatically and Inventory is deducted** → Sales Staff **invoice** the approved order.

- Fixed: quotations now always save to `sales_quotations` (robust document numbers, errors are shown instead of a blank page; total is computed from the Inventory unit price).
- Fixed: **Recheck Stock** (and every other inventory lookup) used the old external-API code path because `config/inventory_api.php` was loaded inside a function; it now reads the local `inventory_products` table.
- Removed **Create PO** from Sales Staff quotations; POs appear after Sales Admin approval.
- Purchase Order export: `po_export.php?id=…&format=pdf|doc`.
- New Sales Admin queue for Sales Orders (Approvals page). Approval creates the Delivery and deducts stock in one transaction; if stock is short nothing changes and an error is shown.
- Invoices can only be made for Sales Admin-approved orders that have no invoice yet.
- **Existing database:** back up, then run `database/migration_v1_25_workflow.sql` once. Fresh installs just import `database/schema.sql`.

## v1.18 Updates
- Sales Admin no longer sees the sales modules or Sales Report.
- Removed the duplicate Sales Admin Dashboard from the navigation; Sales Admin opens Customer Management and Approvals.
>>>>>>> origin/main

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
<<<<<<< HEAD
=======

## Customer Portal / Ordering Role

The project includes a `Customer` role with a customer-facing ordering workflow:

- Customer Order Request
- Sales creates the quotation
- Customer opens the quotation PDF
- Customer Approves or Rejects the quotation
- Approved quotation automatically creates a Purchase Order
- Check Details + Mode of Payment + Payment + Receipt are shown in one checkout container
- Product Availability

For an existing `team_sales` database, run `database/migration_customer_portal.sql` if the Customer role/portal tables are not yet installed, then run `database/migration_v1_29_customer_quotation.sql`. Back up the database first.

Demo customer account:
- Username: `customer1`
- Password: `customer123`
- Linked customer: `CUS-001` (Patrick Jane)

The payment screen is a school-project/demo workflow. GCash and Bank Transfer accept a reference number but do not connect to a real payment gateway. Payment information is copied to the Sales Order when the Purchase Order is converted to a Sales Order.


## v1.31 Customer Quotation Workflow
- Customer submits an order request; Sales sees the request before creating a quotation.
- Sales creates the quotation from the request.
- The customer receives the quotation as a PDF inside the Customer Portal and can Approve or Reject it.
- Quotation approval is now customer approval; Sales Admin quotation approval was removed.
- Approved quotations automatically create a Purchase Order.
- AR, Collection, and Customer Balance are combined in `billing.php`.
- Sales Invoice records are shown as formal invoice forms instead of a table.
- Customer checkout combines Check Details, Mode of Payment, Payment, and Receipt in one container.
- Sidebar navigation was removed and replaced with top navigation.
- Dashboard shows an individual progress bar for each Sales Order.
- Run `database/migration_v1_29_customer_quotation.sql` once on existing databases.

## v1.31 Update

- System Admin role and System Admin pages removed.
- Schema contains structure only; demo INSERT data was removed.
- Sales Module sidebar restored, including for the Customer ordering area.
- Customer ordering changed to a product-catalog experience with product images, details, price, stock, and order action.
- Inventory Reference supports attaching a product image.
- Sales Quotation approval is displayed as container/card forms instead of a table.
- Sales Admin approval is required before a customer can approve a quotation.

### Existing database
Run `database/migration_v1_31.sql` after backing up the database.

### Fresh database
Import `database/schema.sql`. It contains table structures only and no demo records.
>>>>>>> origin/main
