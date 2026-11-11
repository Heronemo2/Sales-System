# Sales System — Technical Design Document

## 1. Document Header

| Field | Value |
|---|---|
| Project | Sales System |
| Document | Technical Design Document |
| Author | [Author Name] |
| Date | 2026-10-31 |
| Status | Proposed; based on the current repository and schema |
| Source baseline | `FEATURE_SPECIFICATION.md`, PHP application, and `database/Team_Sales_Schema.sql` |

## 2. Executive Summary & Goals

### Executive summary

Sales System is a PHP web application for the Sales department. It tracks the business workflow from customer and order-request records through quotations, purchase orders, sales orders, deliveries, invoices, collections, receivables, returns, and reports. The current implementation is a server-rendered PHP application using PDO and a MariaDB/MySQL database. It also connects to a separate Inventory System database; Inventory owns its product catalog and approval of stockouts, while Sales tracks the inventory it can sell.

This design keeps the existing stack and user experience, but defines a production target that makes workflow transitions transactional, prevents duplicate financial and inventory effects, establishes a consistent REST contract for the application, and protects customer and account data. The REST API below is a proposed contract; the repository currently exposes PHP pages rather than a versioned REST API.

### Business value

- Give Sales staff and administrators a consistent view of customer transactions and fulfillment.
- Reduce errors from duplicate conversions, over-collection, and inconsistent stock balances.
- Improve traceability for approvals, deliveries, payment records, and inventory synchronization.
- Provide a foundation for future integrations without replacing the current PHP/MySQL stack.

### Goals

1. Support the Sales workflow from request/quotation through order, delivery, invoice, collection, and return.
2. Keep financial and stock changes correct under retries, concurrent requests, and partial failures.
3. Enforce least-privilege access and provide auditable, operationally supportable deployment and recovery.

### Confirmed baseline and assumptions

- The schema dump targets MariaDB 10.4.32 and PHP 8.2.12. Production should use a currently supported MariaDB release (recommended: MariaDB 10.11 LTS or later) and upgrade/compatibility-test before deployment.
- The application has Sales Admin, Sales Staff, and Customer roles in the schema. The current login flow accepts Sales Admin and Sales Staff; a complete customer login/portal is not confirmed.
- Current request, quotation, purchase-order, and sales-order records hold one product and quantity each. This document preserves that single-line model.
- The Inventory System is a separate database. A local database transaction cannot atomically commit to both databases.
- The product description and 2–3 project-specific acceptance requirements were not provided separately. Goals above are derived from the repository's feature specification.
- The performance figures in Section 9 are proposed initial service targets, not existing measured guarantees.

## 3. Scope

### In scope

- User sign-in/sign-out and role-restricted Sales Admin and Sales Staff workflows.
- Customer records and transaction history.
- Sales-side customer order request records and quotation processing.
- Sales Admin approvals and quotation-to-purchase-order conversion.
- Purchase-order-to-sales-order conversion and sales-order approval.
- Sales-owned inventory balance, Inventory System product lookup, and stockout synchronization.
- Delivery tracking, invoicing, collections, receivables, and customer balances.
- Return records and approval status.
- Dashboard, reports, and quotation, purchase-order, and report exports.
- Proposed versioned REST endpoints and common API response conventions.
- Security, database integrity, integration resilience, testing, and deployment.

### Out of scope

- Inventory procurement, manufacturing, production planning, and Inventory System administration.
- A complete customer self-service portal, customer authentication, or customer-submitted requests.
- Multi-line sales documents, promotions, tax rules, discounts, returns restocking, or credit-note accounting.
- Payment-gateway integration or automated confirmation of electronic payments.
- Persisting account preference toggles whose storage behavior is not established.
- Replacing PHP with a framework or introducing a separate application platform.

### Business decisions to confirm before enabling affected behavior

- **TODO — Customer access:** Confirm whether Customer login and self-service request/quotation actions are required. Until confirmed, customer-facing API routes remain disabled.
- **Pricing rule:** Team Sales inventory holds each product's fixed unit price. Sales Staff cannot override that price in a quotation. Currency tax and discount policy still require confirmation.
- **TODO — Credit policy:** Confirm whether customer credit limits and payment terms block orders, invoices, or only generate warnings.
- **TODO — Returns:** Confirm return eligibility, item-level quantities, refund/credit behavior, and whether approved returns restock Sales inventory.
- **TODO — Delivery:** Confirm whether partial shipments are permitted. Current data supports one delivery record per order.
- **TODO — Retention:** Define retention periods for customer data, activity logs, uploaded avatars, and financial records.
- **TODO — Availability and volume:** Confirm target concurrent users, transaction volume, recovery point/time objectives, and required service availability.

## 4. Proposed System Architecture

### High-level components

```text
Browser (HTML/CSS/JavaScript)
    | HTTPS; session cookie; CSRF token for writes
    v
Apache + PHP 8.2+ application
    +-- Server-rendered page controllers (existing UX)
    +-- /api/v1 REST controllers (proposed contract)
    +-- Authentication / role authorization
    +-- Application services (workflow, billing, inventory)
    +-- PDO repositories and transaction boundaries
          |                           |
          v                           v
  Sales database                 Inventory System database
  (InnoDB, team_sales)            (separate owner/system)
          ^
          |
 Scheduled/retryable integration reconciliation
 (approved Inventory stockouts -> Sales stock ledger)
```

### Component responsibilities

- **Browser:** Renders HTML/CSS and uses JavaScript only for progressive enhancement. It must not be trusted to enforce authorization, prices, totals, status transitions, or stock availability.
- **PHP web layer:** Keeps current page routes operational and introduces `/api/v1` JSON endpoints using the same domain services. Controllers validate transport input and translate domain errors to HTTP responses.
- **Application services:** Own transition rules such as quote approval, order conversion, collection posting, and stock reconciliation. Both page forms and API requests call the same service methods.
- **Sales database:** Authoritative for customer sales records, invoices, collections, approvals, and Sales-owned stock.
- **Inventory database:** Authoritative for Inventory products and Inventory-side stockout approval. Sales must not directly decrement or modify Inventory-owned stock.
- **Integration reconciler:** Imports approved stockouts idempotently. It retries transient failures and records useful operational status. It never treats a timeout as proof that an external write did not happen.

### Data flow

1. An authorized Sales user creates a quotation from an eligible request or approved manual input.
2. The service validates customer/product/quantity/price and reads product metadata and availability. It stores the quotation and an activity record in one Sales transaction.
3. Customer acceptance is represented in the existing data model; an accepted quotation creates at most one purchase order. Sales Admin approval is tracked separately. The current conversion helper gates on customer approval only; confirm before changing this gate.
4. An eligible purchase order converts to at most one sales order. An administrator approval creates the delivery and debits Sales-owned stock in one Sales transaction.
5. Delivery status and invoice/collection updates are committed with their related Sales records. Completion is derived from approved fulfillment and fully paid status.
6. Inventory-originated approved stockouts are reconciled into Sales stock exactly once, keyed by the external stockout ID.

### Runtime and failure boundaries

- Deploy PHP behind HTTPS on Apache or a compatible PHP-FPM web server. XAMPP is suitable for local development, not as the production security boundary.
- Use separate least-privilege database accounts for Sales and Inventory. Do not rely on a shared root account.
- A local transaction may atomically update Sales tables only. Inventory calls must be treated as an external integration; use idempotency keys, durable status, retries, and reconciliation.
- Preserve the existing server-rendered pages during API rollout. Do not duplicate business rules in JavaScript or separate page/API implementations.

## 5. Data Design & Database Schema

### Database conventions

- Use InnoDB, `utf8mb4`, parameterized PDO statements, and UTC timestamps. Convert to the configured business timezone only for display and date-based business rules.
- Use integer surrogate primary keys internally; human-readable document numbers are unique, externally visible identifiers.
- Store monetary values as `DECIMAL(12,2)`, never binary floating point. Use integer quantities where the existing model requires whole units and `DECIMAL(12,2)` for fractional stock.
- Preserve current table and column names during the initial rollout. Introduce schema changes additively and use explicit migrations rather than editing only the SQL dump.

### Existing table map

The following captures the current schema in `database/Team_Sales_Schema.sql`; it is a schema map, not replacement DDL. All listed existing primary keys are unsigned integer IDs.

| Table | Columns and types | Keys, indexes, and relationships |
|---|---|---|
| `users` | `user_id`; `full_name VARCHAR(120)`; `username VARCHAR(60)`; `password VARCHAR(255)`; `role ENUM('Sales Admin','Sales Staff','Customer')`; nullable `customer_id`; `status ENUM('Active','Inactive')`; nullable `avatar_path VARCHAR(255)`, `last_login DATETIME`; `created_at`, `updated_at` | PK `user_id`; unique `username`, unique nullable `customer_id`; FK `customer_id -> customers.cus_id` |
| `customers` | `cus_id`; `customer_code VARCHAR(30)`; nullable `customer_name VARCHAR(120)`, `phone VARCHAR(30)`, `email VARCHAR(150)`, `address VARCHAR(255)`, `city VARCHAR(100)`, `province VARCHAR(100)`; `payment_terms VARCHAR(60)`; `status ENUM('Active','Inactive')`; `created_at`, `updated_at` | PK `cus_id`; unique `customer_code` |
| `customer_order_requests` | `req_id`; `request_no VARCHAR(30)`; `customer_id`; `request_date DATE`; `product_code VARCHAR(40)`; `quantity INT UNSIGNED`; nullable `notes VARCHAR(500)`; `status ENUM('Pending Quotation','Quotation Sent','Quotation Approved','Quotation Rejected','Converted','Cancelled')`; nullable `quotation_id`; `created_at`, `updated_at` | PK `req_id`; unique `request_no`; index `customer_id`; FK `customer_id -> customers.cus_id`. `quotation_id` is not currently an FK. |
| `sales_quotations` | `sq_id`; `quotation_no VARCHAR(30)`; `customer_id`; nullable `source_request_id`; `quotation_date DATE`; nullable `valid_until DATE`; nullable `product_code VARCHAR(40)`, `fg_id INT`, `quantity INT UNSIGNED`, `inventory_quantity INT UNSIGNED`, `inventory_checked_at DATETIME`; `inventory_status ENUM('Pending','Approved','Rejected')`; `customer_status`, `admin_status ENUM('Pending','Approved','Rejected')`; `status ENUM('Pending','Approved','Rejected','Expired')`; `total_amount DECIMAL(12,2)`; `payment_method ENUM('GCash','Bank Transfer','Cash on Delivery')`; `payment_status ENUM('Pending','Paid','Failed')`; nullable `payment_reference VARCHAR(100)`, `receipt_no VARCHAR(30)`, `paid_at DATETIME`; `created_at` | PK `sq_id`; unique `quotation_no`; indexes `customer_id`, `fg_id`; FK `customer_id -> customers.cus_id`. `source_request_id` is not currently an FK. |
| `purchase_orders` | `po_id`; `po_no VARCHAR(30)`; `quotation_id`, `customer_id`; `product_code VARCHAR(40)`; nullable `inventory_product_id VARCHAR(60)`; `quantity INT UNSIGNED`; `total_amount DECIMAL(12,2)`; `order_date DATE`; `status ENUM('Open','Converted','Cancelled')`; nullable `converted_order_no VARCHAR(30)`, `converted_at DATETIME`; `created_at` | PK `po_id`; unique `po_no`; indexes `quotation_id`, `customer_id`; FKs to `sales_quotations.sq_id` and `customers.cus_id`. The quotation FK is not unique. |
| `sales_orders` | `so_id`; `order_no VARCHAR(30)`; nullable `quotation_id`; `customer_id`; `order_date DATE`; nullable `product_code VARCHAR(40)`, `quantity INT UNSIGNED`; `total_amount DECIMAL(12,2)`; `status ENUM('Pending','Processing','Invoiced','Completed','Cancelled')`; `admin_status ENUM('Pending','Approved','Rejected')`; `payment_method ENUM('GCash','Bank Transfer','Cash on Delivery')`; `payment_status ENUM('Pending','Paid','Failed')`; nullable `payment_reference VARCHAR(100)`, `receipt_no VARCHAR(30)`, `paid_at DATETIME`; `created_at` | PK `so_id`; unique `order_no`, unique `receipt_no`; indexes `quotation_id`, `customer_id`; FKs to quotation and customer. |
| `deliveries` | `del_id`; `delivery_no VARCHAR(30)`; `order_id`; `delivery_date DATE`; `status ENUM('Preparing','In Transit','Delivered')`; `inventory_deducted TINYINT(1)`; `created_at` | PK `del_id`; unique `delivery_no`; index `order_id`; FK `order_id -> sales_orders.so_id`. The order index is not unique. |
| `sales_invoices` | `inv_id`; `invoice_no VARCHAR(30)`; `order_id`; `invoice_date DATE`; `total_amount`, `amount_paid DECIMAL(12,2)`; `status ENUM('Unpaid','Partially Paid','Paid')`; `created_at` | PK `inv_id`; unique `invoice_no`; index `order_id`; FK `order_id -> sales_orders.so_id`. The order index is not unique. |
| `collections` | `col_id`; `collection_no VARCHAR(30)`; `invoice_id`; `collection_date DATE`; nullable `due_date DATE`; `amount DECIMAL(12,2)`; `method ENUM('Cash','GCash','Bank Transfer')`; `created_at` | PK `col_id`; unique `collection_no`; index `invoice_id`; FK `invoice_id -> sales_invoices.inv_id` |
| `account_receivable` | `ar_id`; `invoice_id`, `customer_id`; `amount_due DECIMAL(12,2)`; `due_date DATE`; `status ENUM('Current','Overdue','Paid')`; `created_at` | PK `ar_id`; indexes `invoice_id`, `customer_id`; FKs to invoice and customer. No uniqueness per invoice is defined. |
| `customer_balance` | `cb_id`; `customer_id`; `total_billed`, `total_paid`, `outstanding_balance DECIMAL(12,2)`; `updated_at` | PK `cb_id`; unique `customer_id`; FK `customer_id -> customers.cus_id`. Treat as a derived/cache value, not the accounting ledger. |
| `sales_returns` | `ret_id`; `return_no VARCHAR(30)`; `order_id`; `return_date DATE`; nullable `reason VARCHAR(255)`; `total_amount DECIMAL(12,2)`; `status ENUM('Pending','Approved','Rejected','Completed')`; `admin_status ENUM('Pending','Approved','Rejected')`; `created_at` | PK `ret_id`; unique `return_no`; index `order_id`; FK `order_id -> sales_orders.so_id` |
| `inventory` | `inventory_id`; `product_code VARCHAR(50)`; `product_name VARCHAR(150)`; nullable/defaulted `brand VARCHAR(100)`, `size VARCHAR(30)`; `unit VARCHAR(30)`; `stock_quantity`, `unit_price`, `reorder_level DECIMAL(12,2)`; `status ENUM('Active','Inactive')`; `last_updated`, `created_at` | PK `inventory_id`; unique `product_code`; index `status`. Sales-owned sellable balance and fixed price; product metadata originates in Inventory. |
| `inventory_stockout_approvals` | `id`; nullable `inventory_request_id`, `inventory_stock_out_id INT`; nullable `inventory_request_number`, `stock_out_number VARCHAR(50)`; `product_code VARCHAR(50)`, `product_name VARCHAR(150)`; `quantity DECIMAL(12,2)`; `unit`, `destination VARCHAR(30)`; `request_status ENUM('Requested','Inventory Approved','Stocked Out','Approved','Rejected')`; `requested_at`, nullable `inventory_approved_at`, `stocked_out_at`; nullable `reviewed_by`, `reviewed_at`; nullable `source_reference VARCHAR(100)`, `remarks VARCHAR(500)`; `created_at` | PK `id`; unique nullable `inventory_stock_out_id`; indexes on request number, request status, product, reviewer, source reference. Cross-database IDs do not have Sales FKs. |
| `activity_logs` | `log_id`; `user_id`; `action VARCHAR(80)`; `module VARCHAR(80)`; nullable `record_id`; nullable `description VARCHAR(255)`; `created_at` | PK `log_id`; indexes `user_id`, `(module,record_id)`. No FK is defined; current schema permits system identity conventions such as user ID `0`. |

### Relationship and integrity observations

- The dump defines foreign keys for user/customer, receivable/invoice/customer, collection/invoice, customer balance/customer, request/customer, delivery/order, purchase order/quotation/customer, invoice/order, sales order/quotation/customer, quotation/customer, and return/order.
- Several relationships that are enforced only in PHP are not constrained in the database: invoice-per-order, delivery-per-order, purchase-order-per-quotation, quotation-to-source-request, request-to-quotation, and one receivable-per-invoice.
- Before adding unique constraints, audit and reconcile existing duplicate rows. Then add unique indexes on `sales_invoices.order_id`, `deliveries.order_id`, and `purchase_orders.quotation_id` only if confirmed business rules continue to require one-to-one relationships.
- Avoid using `customer_balance` as the sole source of truth. Recompute from invoice and collection records if inconsistent; record all changes in the underlying invoice/collection transaction.

### Recommended additive tables

To make stock changes auditable and retry-safe, add a movement ledger before relying on a cached stock quantity for financial fulfillment. Backfill an opening balance per product, then require every subsequent adjustment to write the movement and update `inventory.stock_quantity` in the same Sales database transaction.

```sql
CREATE TABLE inventory_movements (
    movement_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_code VARCHAR(50) NOT NULL,
    quantity_delta DECIMAL(12,2) NOT NULL,
    source_type VARCHAR(40) NOT NULL,
    source_reference VARCHAR(100) NOT NULL,
    actor_user_id INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (movement_id),
    UNIQUE KEY uq_inventory_movement_source (source_type, source_reference),
    KEY idx_inventory_movement_product_time (product_code, created_at),
    CONSTRAINT fk_inventory_movement_product
        FOREIGN KEY (product_code) REFERENCES inventory (product_code),
    CONSTRAINT fk_inventory_movement_actor
        FOREIGN KEY (actor_user_id) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_idempotency (
    idempotency_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    principal_key VARCHAR(100) NOT NULL,
    idempotency_key VARCHAR(100) NOT NULL,
    request_hash CHAR(64) NOT NULL,
    response_status SMALLINT UNSIGNED NULL,
    response_body JSON NULL,
    resource_type VARCHAR(60) NULL,
    resource_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (idempotency_id),
    UNIQUE KEY uq_api_idempotency_principal_key (principal_key, idempotency_key),
    KEY idx_api_idempotency_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Implementation notes:

- The ledger's `source_reference` is stable and unique within `source_type` (for example `delivery:123`, `inventory-stockout:456`, or `opening:SH-001`). Use a negative delta for a Sales shipment and a positive delta for a received stockout.
- Keep `inventory.stock_quantity` as a transactionally updated, query-efficient balance. Compare it to the movement sum in scheduled integrity checks and alert on drift.
- Store only minimal, non-sensitive idempotency response data; expire records according to the retry policy. Reject reuse of a key with a different request hash.
- JSON column support and constraint names must be verified against the selected production database version.

The current quotation/order tables persist `total_amount`, but not a separate unit-price snapshot. The web quotation flow reads the fixed unit price from Team Sales inventory and stores the calculated total; historical totals remain unchanged if inventory prices are later updated. The following nullable snapshot columns are an optional future audit enhancement; they are not required for the current fixed-price web flow.

```sql
ALTER TABLE sales_quotations
    ADD COLUMN unit_price DECIMAL(12,2) NULL AFTER quantity;

ALTER TABLE purchase_orders
    ADD COLUMN unit_price DECIMAL(12,2) NULL AFTER quantity;

ALTER TABLE sales_orders
    ADD COLUMN unit_price DECIMAL(12,2) NULL AFTER quantity;
```

If the optional snapshots are added, populate them from the fixed inventory price and treat them as read-only. New quotations must calculate `total_amount` from the fixed inventory unit price and quantity using the approved rounding policy. Do not overwrite historical totals when inventory prices change.

## 6. API & Interface Definitions

### API conventions

- Base path: `/api/v1`; JSON request and response bodies use UTF-8.
- Authentication: existing secure server-side session cookie for browser clients. Use `Authorization: Bearer` only if a separately approved machine-client authentication scheme is implemented.
- Requests accept `Accept: application/json`; writes use `Content-Type: application/json`, a CSRF token in `X-CSRF-Token` for cookie-authenticated requests, and an `Idempotency-Key` for operations that create financial or inventory effects. The server generates a `request_id` and may accept a validated client correlation ID separately.
- The Inventory integration must authenticate as a service identity, not a browser user. Use mutually authenticated TLS or an HMAC signature over the timestamp, event ID, and raw request body; enforce a short timestamp window, secret rotation, rate limits, and event-ID deduplication.
- `GET` endpoints are safe and paginated. Use `limit` (default 25, max 100) and opaque or numeric `cursor` pagination. Do not expose password hashes or internal database credentials.
- Date-only values use `YYYY-MM-DD`; timestamps use ISO 8601 UTC; monetary amounts are decimal strings with two fractional digits.
- Common success envelope:

```json
{
  "data": { "id": 123 },
  "meta": { "request_id": "01J..." }
}
```

- Common error envelope:

```json
{
  "error": {
    "code": "validation_error",
    "message": "The request is invalid.",
    "details": [{ "field": "quantity", "message": "Must be greater than zero." }]
  },
  "meta": { "request_id": "01J..." }
}
```

### Endpoint contract

All endpoints below are proposed. Existing PHP page URLs remain supported during migration and should call the same application services.

| Method and path | Access | Request | Success |
|---|---|---|---|
| `GET /api/v1/auth/csrf-token` | Public | — | `200` with a short-lived CSRF token for login and subsequent cookie-authenticated writes. |
| `POST /api/v1/auth/session` | Public | `{ "username": "...", "password": "..." }` | `200` with user ID, name, role; set session cookie. |
| `DELETE /api/v1/auth/session` | Authenticated | CSRF header | `204`; invalidate server-side session and cookie. |
| `GET /api/v1/me` | Authenticated | — | `200` with current user profile and role. |
| `GET /api/v1/customers` | Sales Admin/Staff | `q`, `status`, pagination | `200` paged customer summaries. |
| `POST /api/v1/customers` | Sales Admin | customer fields | `201` with created customer. |
| `GET /api/v1/customers/{customerId}` | Sales Admin/Staff | — | `200` customer details; `404` if absent. |
| `PATCH /api/v1/customers/{customerId}` | Sales Admin | editable customer fields | `200` updated customer. |
| `GET /api/v1/customers/{customerId}/transactions` | Sales Admin/Staff | `from`, `to`, pagination | `200` unified, chronologically ordered transaction list. |
| `GET /api/v1/order-requests` | Sales Admin/Staff | `status`, `customerId`, pagination | `200` request list. |
| `POST /api/v1/order-requests` | Disabled until customer portal is approved; otherwise Customer | `productCode`, positive `quantity`, optional `notes`, requested date | `201` request. Customer identity must come from the session, never the body. |
| `POST /api/v1/quotations` | Sales Staff | `customerId`, optional `requestId`, product, quantity, unit price, validity date | `201` quotation and calculated total. |
| `GET /api/v1/quotations/{quotationId}` | Sales Admin/Staff | — | `200` quotation. |
| `POST /api/v1/quotations/{quotationId}/decision` | Customer portal only; disabled until approved | `{ "decision": "approve" \| "reject" }` | `200` updated decision; approval creates/reuses one purchase order. |
| `POST /api/v1/quotations/{quotationId}/admin-decision` | Sales Admin | `{ "decision": "approve" \| "reject", "reason": "..." }` | `200` updated admin status. |
| `GET /api/v1/purchase-orders` | Sales Admin/Staff | `status`, `customerId`, pagination | `200` paged purchase orders. |
| `POST /api/v1/purchase-orders/{purchaseOrderId}/convert` | Sales Staff | Idempotency key; empty body | `201` created sales order, or `200` existing converted resource on a matching retry. |
| `GET /api/v1/sales-orders` | Sales Admin/Staff | `status`, `customerId`, pagination | `200` paged orders with delivery/invoice summary. |
| `POST /api/v1/sales-orders/{orderId}/admin-decision` | Sales Admin | `{ "decision": "approve" \| "reject", "reason": "..." }` | `200` updated order; approval atomically records stock debit and delivery. |
| `PATCH /api/v1/deliveries/{deliveryId}` | Sales Staff | `{ "status": "In Transit" \| "Delivered" }` | `200` updated delivery. Only allowed forward transitions are accepted. |
| `POST /api/v1/sales-orders/{orderId}/invoices` | Sales Staff | Idempotency key; invoice date if permitted | `201` invoice; `409` if one already exists. |
| `POST /api/v1/invoices/{invoiceId}/collections` | Sales Staff | Idempotency key; `{ "amount": "100.00", "method": "...", "collectionDate": "...", "dueDate": "..." }` | `201` collection plus recalculated invoice balance/status. |
| `GET /api/v1/receivables` | Sales Admin/Staff | `status`, `customerId`, `asOf`, pagination | `200` receivables with current outstanding amounts. |
| `POST /api/v1/sales-orders/{orderId}/returns` | Sales Staff | `{ "returnDate": "...", "reason": "...", "amount": "0.00" }` | `201` pending return. |
| `POST /api/v1/returns/{returnId}/admin-decision` | Sales Admin | `{ "decision": "approve" \| "reject", "reason": "..." }` | `200` updated return status. |
| `GET /api/v1/inventory` | Sales Admin/Staff | `q`, `status`, pagination | `200` Sales-owned stock and product details. |
| `POST /api/v1/inventory/stockout-reconciliation` | Inventory integration identity only | Signed event headers plus external stockout event ID, product code, quantity, approved status, timestamps | `202` accepted for idempotent reconciliation; `200` if already applied. Do not expose this to browser sessions. |
| `GET /api/v1/reports/sales` | Sales Admin/Staff | `year`, optional customer ID | `200` report aggregates; export remains a separate download response. |

### HTTP status and error handling

- `400` malformed JSON or invalid query parameters; `401` missing/invalid session; `403` insufficient role or CSRF failure; `404` unknown or inaccessible resource.
- `409` invalid state transition, duplicate conversion, stale update, key/request-hash mismatch, or insufficient stock.
- `422` syntactically valid input that fails field/business validation.
- `429` rate limit; `500` unexpected internal failure; `503` dependency unavailable.
- Return stable error codes and a request ID. Never return exception messages, SQL statements, credentials, stack traces, or external database details to the client.

## 7. Core Business Logic & Edge Cases

### Shared workflow rules

- Validate authorization, input, and current persisted state on the server for every action.
- Generate document numbers inside the transaction using a concurrency-safe sequence or database-generated identifier. Do not depend on `MAX(number) + 1` without locking; unique constraints are the final duplicate guard.
- Use decimal arithmetic in the database/PHP decimal-safe handling. Validate positive amount/quantity and date ordering.
- Every state transition must be explicit, auditable, and idempotent where a retry can repeat an effect.
- When a requested state change is no longer valid, return a conflict and leave all records unchanged.

### Create quotation

```text
require authenticated Sales Staff
validate basic input and fetch active product metadata from Inventory outside a Sales transaction
if Inventory is unavailable, fail without claiming a fresh stock check
begin Sales transaction
    lock and validate active customer
    if requestId is supplied:
        lock request row
        require status == Pending Quotation and quotation_id is null
        require request customer/product/quantity match submitted quotation
    validate quantity > 0, unit price > 0, valid_until >= quotation_date
    compute total from validated line data using DECIMAL-safe arithmetic
    persist quotation with pending decision statuses and unit-price snapshot
    link request and set request status to Quotation Sent
    insert activity log in same transaction
commit
return quotation
```

If Inventory is unavailable, do not claim a fresh stock check succeeded. Return a dependency error or persist an explicitly marked pending check according to the selected business policy; do not invent an available quantity. A retry must not create another quotation for the same request.

### Approve quotation and create purchase order

```text
begin Sales transaction
    lock quotation row
    require customer decision is Approved and quotation is not expired/rejected
    apply the approved admin/customer decision rules
    if purchase order already exists for quotation:
        return that order (idempotent)
    insert exactly one purchase order with immutable price/quantity snapshot
    set request status to Converted when linked
    insert activity log
commit
```

The repository currently has both customer and Sales Admin status fields. The existing conversion helper requires customer approval and does not require `admin_status = 'Approved'`. Preserve customer approval as the conversion gate unless the business owner explicitly changes that rule; continue recording administrative review separately. Rejected/expired quotations cannot be converted.

### Convert purchase order to sales order

```text
begin Sales transaction
    select purchase order FOR UPDATE
    require status == Open
    lock/re-read source quotation and customer
    verify source records and quantities are valid
    insert one sales order using purchase-order snapshot
    set purchase order Converted, converted_order_no, converted_at
    insert activity log
commit
```

The availability check at quotation time is informational. Recheck stock at the fulfillment/approval boundary. If the order has already converted, return its existing sales order for a matching idempotent retry; otherwise reject invalid or cancelled source state.

### Approve sales order and reserve/deduct stock

```text
begin Sales transaction
    select order FOR UPDATE; require admin_status == Pending
    require order is not cancelled and has valid product/quantity
    select Sales inventory row FOR UPDATE
    require active product and stock_quantity >= requested quantity
    insert unique inventory movement(source_type='delivery', source_reference=order ID,
                                     quantity_delta=-requested quantity)
    decrement inventory.stock_quantity with a guarded update
    create one delivery in Preparing state
    mark inventory deduction/approval and order state
    insert activity log
commit
```

If stock is insufficient, roll back the entire transaction and return `409 insufficient_stock`; the order stays pending and no delivery or movement is created. A unique delivery-per-order constraint and movement source key protect concurrent/retried approval.

### Update delivery

- Allow only `Preparing -> In Transit -> Delivered`; repeated writes of the current status may return success without duplicate side effects.
- Reject reverse transitions and updates to a delivery for a cancelled/unapproved order unless an authorized correction workflow exists.
- Derive order completion from delivery and payment/invoice facts in one service. Do not mark an order completed only because delivery is marked Delivered.

### Create invoice and collect payment

```text
begin Sales transaction
    lock order; require approved and eligible for invoicing
    insert invoice with total copied from the approved order
    enforce one invoice per order with a unique database constraint
    create/update receivable and customer balance projection
    insert activity log
commit
```

For a collection:

```text
begin Sales transaction
    lock invoice row
    paid = sum of committed collections (or verified stored amount_paid)
    remaining = invoice.total_amount - paid
    require amount > 0 and amount <= remaining
    insert collection using unique collection number/idempotency key
    update amount_paid and invoice status from exact accumulated total
    update receivable status/amount and customer balance projection
    derive order payment/completion state
    insert activity log
commit
```

Excess payment, duplicate retries, a missing invoice, and concurrent collections must not overpay or create duplicate collection records. Return `409` for an amount exceeding the locked remaining balance. An external payment gateway confirmation must not be implied by manually recording a collection.

### Inventory stockout reconciliation

- Inventory owns the approval; Sales accepts only a confirmed approved stockout with a stable external stockout ID.
- Upsert the Sales-side reconciliation record keyed by `inventory_stock_out_id`.
- In one Sales transaction, insert the positive inventory movement, update the Sales stock balance, set synchronization state/timestamps, and log the reconciliation.
- If the same event arrives again with the same payload, return success without another stock increase. If an event ID is reused with conflicting product/quantity data, quarantine and alert rather than silently overwrite.
- Retry transient connection/time-out failures with bounded exponential backoff and jitter. Keep an observable pending/failed state and a manual reconciliation path. Do not hold a Sales DB transaction open while calling Inventory.
- The current schema has a nullable unique stockout ID and status fields, but does not define a separate movement ledger. The migration and reconciliation process must deduplicate historical stockouts before enabling automatic ledger posting.

### Returns and cancellation

- Create a pending return only for an existing order and valid return date/reason.
- Admin decisions must be atomic, authorization-checked, and idempotent.
- Do not restock inventory or alter invoice/collection balances until return accounting and inventory policies are approved. Preserve the audit record for rejected returns.
- TODO decisions in Scope must be resolved before implementing refunds, credit notes, partial returns, or inventory restocking.

### Failure handling

- Database deadlock: retry the entire idempotent transaction a small bounded number of times; surface a retryable error after exhaustion.
- Unique-key conflict: treat as duplicate only after loading and validating the existing resource; otherwise report conflict.
- Inventory dependency timeout: keep the Sales workflow available for reads and unrelated local work; show stock freshness and reject actions requiring an unverified stock result.
- Logging failure for a financial/stock transition: roll back the business transaction when the audit event is required. Do not silently report successful high-impact actions without their audit record.

## 8. Security & Compliance

- **Authentication:** Use server-side sessions and `password_hash`/`password_verify`. On successful login, regenerate the session ID; enforce idle/absolute timeouts, invalidate sessions on logout/password change, and rate-limit failed attempts. Do not enable Customer login until onboarding, recovery, and account-linking behavior is defined.
- **Authorization:** Enforce role checks in endpoint/controller and service layer. Apply object-level checks (including customer ownership if customer access is enabled); never trust a submitted customer ID or role.
- **Session/CSRF:** Cookies must be `Secure`, `HttpOnly`, and `SameSite=Lax` or stricter; enable strict session mode. Require synchronizer CSRF tokens on state-changing cookie-authenticated requests. Restrict CORS to explicitly approved origins.
- **Input/output:** Use prepared statements; validate types, lengths, enum values, and file uploads; escape output for HTML context. Use allow-lists for sort/filter fields. Apply content security policy and safe download headers.
- **Secrets:** Remove credentials from source/config files in production; load secrets from environment or a secret manager. Use separate least-privilege credentials per database and restrict network access. The current local config uses hard-coded localhost/root-style development values and must not be copied to production.
- **Encryption in transit:** HTTPS/TLS for browser and service connections; verify certificates for database connections where supported.
- **Encryption at rest:** Encrypt database volumes and backups with managed keys. Keep key material outside the database host. Consider application-level encryption only for fields with an explicitly approved threat model and searchable-data requirements.
- **Sensitive data:** Treat customer names, email, phone, address, payment references, and financial records as confidential. Redact secrets and personal data from logs and error responses. Restrict exports and protect uploaded avatar paths from executable content.
- **Audit:** Record actor, action, entity, timestamp, and correlation/request ID for approvals, financial changes, inventory changes, and account administration. Make logs append-only to application roles; define access and retention policy. The existing helper swallows logging errors, so high-impact workflows must not use that behavior for required audit writes.
- **Operational security:** Disable display_errors in production, expose generic error responses, patch PHP/database/OS, protect backups, and monitor authentication abuse, permission denials, integration errors, and unusual financial/stock changes.
- **Compliance:** No jurisdiction or regulatory framework was specified. Determine applicable privacy, accounting, retention, and breach-notification requirements before production sign-off.

## 9. Performance & Scalability

### Initial targets (proposed; validate with load testing)

- PHP page/API read requests: p95 under 500 ms for normal indexed queries.
- Sales database mutation: p95 under 1 second excluding external Inventory dependency time.
- Inventory integration: accept and persist a reconciliation event within 2 seconds when the Sales database is healthy; completion of an external retry is asynchronous and has no unverified SLA.
- Initial capacity: validate at 100 concurrent authenticated sessions and the forecast peak write rate. Replace this assumption once user/traffic estimates are supplied.

### Query and database strategy

- Keep indexes on foreign keys and frequently filtered workflow statuses; add composite indexes only after inspecting query plans and production-like data.
- For common lists, use cursor pagination and bounded result sizes. Avoid unbounded transaction-history/report reads.
- Add indexes for actual query patterns such as `(status, created_at)`, `(customer_id, created_at)`, or `(invoice_id, collection_date)` only after profiling.
- Add uniqueness constraints for one invoice/delivery per order and one purchase order per quotation after auditing duplicates and confirming rules.
- Run dashboard/report aggregates against appropriate date/customer indexes. If reporting load becomes material, use pre-aggregated daily summaries or a read replica; do not weaken financial consistency for dashboard caching.
- Reconcile balance/stock projections against source records periodically and alert on drift.

### Caching and bottleneck mitigation

- Cache low-risk, frequently read product metadata briefly; invalidate on Inventory update/reconciliation. Never use cached stock as the final fulfillment authorization check.
- Do not cache authorization decisions, invoice balances, or payment status for writes. Read authoritative database state inside locked transactions.
- Keep external Inventory calls out of long-running Sales transactions. Use bounded connection/query timeouts and retry only idempotent operations.
- Monitor PHP request latency/error rates, DB connection usage, slow queries, lock waits, deadlocks, disk growth, failed integrations, and queue/reconciliation age.
- Scale PHP horizontally behind a load balancer only after sessions are shared/sticky or moved to a shared session store. Use shared storage/object storage for avatars and generated exports if multiple app nodes are used.
- For larger workloads, separate reporting/read workloads and use a durable job runner for exports and integration retries; do not introduce these components until observed load requires them.

## 10. Testing & Deployment Strategy

### Test strategy

- **Unit tests:** Input validation, role policy, state-transition graph, document numbering, decimal total/balance calculations, payment status, return status, and idempotency-key/request-hash behavior.
- **Integration tests:** PDO repositories and transactions against the selected MariaDB/MySQL version; foreign keys and unique constraints; create/approve/convert workflows; invoice and collection accounting; inventory ledger/balance consistency.
- **Concurrency tests:** Simultaneous order approval for limited stock; duplicate conversion; two collections competing for the final invoice balance; repeated inventory webhook/reconciliation; verify exactly one side effect.
- **Failure tests:** Database rollback, deadlock retry exhaustion, Inventory timeout, partial integration failure, malformed/duplicate events, invalid roles, CSRF failure, session expiry, and upload rejection.
- **API/contract tests:** Endpoint schemas, status codes, stable error codes, pagination, role access, CSRF, and idempotent retries.
- **UI regression tests:** Existing Sales Admin and Sales Staff page routes, forms, exports, dashboard totals, and redirects remain functional while pages are migrated to shared services.
- **Security tests:** SQL injection/XSS/CSRF defenses, object-level authorization, session fixation, rate limits, file upload handling, secrets scanning, dependency checks, and production error redaction.
- **Load/operational tests:** Forecast workload, slow-query review, backup/restore, migration duration, reconciliation backlog recovery, and monitoring/alert delivery.
- No test framework is assumed by this document. Select one compatible with the repository's current PHP setup and run tests in CI against the same supported database version as production.

### Zero-downtime rollout

1. **Prepare:** Confirm unresolved business decisions, supported database/PHP versions, user-volume targets, secrets management, backup/restore, and rollback owner. Capture baseline data counts and workflow metrics.
2. **Harden configuration:** Move secrets to environment/secret storage, create least-privilege DB users, enable HTTPS and secure session configuration, and ensure production errors are not displayed to users.
3. **Schema expand:** Take and verify a restorable backup. Apply additive tables/nullable columns/indexes using online DDL where supported. Audit duplicates before unique constraints. Do not rename or drop columns in this phase.
4. **Backfill:** Create opening stock movements and reconcile existing sales/invoices/collections/stockout IDs. Produce exception reports; stop if totals do not reconcile.
5. **Deploy compatible application:** Deploy code that can run with both old and expanded schema. Keep existing PHP page behavior; route both pages and new API through shared services. Keep new customer and integration features behind explicit feature flags.
6. **Shadow/observe:** Run reconciliation in dry-run or compare-only mode. Compare computed totals and stock deltas to current records; inspect conflicts and API metrics before enabling writes.
7. **Enable gradually:** Enable the REST API for internal users, then idempotent inventory reconciliation, then other approved behavior by role/feature flag. Monitor errors, duplicate conflicts, stock/AR drift, latency, and backlog.
8. **Contract later:** After a stable release and rollback window, remove obsolete paths/columns only in a separate reviewed migration. Never combine destructive schema cleanup with the initial release.
9. **Rollback:** Revert application code/feature flags first. Preserve additive schema and event records; do not roll back committed financial or stock movements with destructive database restoration. Correct business data through compensating entries and approved operational procedures.
10. **Recovery:** Test point-in-time database recovery and restore of uploaded files. Define RPO/RTO with the business owner before treating the service as production-ready.

### Release gates

- All critical workflow, authorization, idempotency, and reconciliation tests pass.
- Existing page and export regressions are resolved.
- Database migration is rehearsed against a production-sized copy and has a verified rollback/forward-recovery plan.
- Backup restore is verified; monitoring, alerting, and on-call ownership are assigned.
- The business decisions in Scope that affect enabled functionality have explicit sign-off.
