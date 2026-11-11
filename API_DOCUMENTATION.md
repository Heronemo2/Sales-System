# Sales System API Documentation

**Status:** Proposed REST API contract; most endpoints below are not implemented.  
**Current implementation:** Server-rendered PHP pages, plus the customer transaction-history AJAX response documented under [Existing JSON interface](#existing-json-interface).  
**Version:** Draft v1  
**Last updated:** 2026-10-31

This document describes a future JSON API for the Sales System. Do not treat proposed `/api/v1` routes as available until implemented and deployed. Existing pages should remain functional during any API rollout. The contract reflects the current Sales Admin, Sales Staff, and customer-order workflow where known; unresolved business rules are called out explicitly.

## 1. Base URL and conventions

The base URL is deployment-specific:

```text
https://<sales-system-host>/api/v1
```

- Request and response bodies use UTF-8 JSON.
- Dates use `YYYY-MM-DD`; timestamps use ISO 8601 UTC, for example `2026-10-31T04:20:00Z`.
- Monetary values are decimal strings with two fractional digits, for example `"1500.00"`. Do not send or calculate money as floating-point values.
- Resource IDs are positive integers. Human-readable numbers such as `QT-2003` and `INV-3002` are separate display/reference fields.
- List endpoints support `limit` (default `25`, maximum `100`) and `cursor`. Invalid limits or filters return `400`.
- Unknown JSON properties should be rejected or ignored consistently; implementation should choose and test one policy before release.

### Success response

```json
{
  "data": {
    "id": 123
  },
  "meta": {
    "request_id": "01J..."
  }
}
```

List responses use:

```json
{
  "data": [],
  "meta": {
    "request_id": "01J...",
    "next_cursor": null,
    "limit": 25
  }
}
```

### Error response

```json
{
  "error": {
    "code": "validation_error",
    "message": "The request is invalid.",
    "details": [
      {
        "field": "quantity",
        "message": "Must be greater than zero."
      }
    ]
  },
  "meta": {
    "request_id": "01J..."
  }
}
```

Never return stack traces, SQL, database credentials, password hashes, or raw exception messages to clients.

## 2. Authentication and request headers

### Browser sessions

The proposed browser API uses the application's server-side session cookie. Successful sign-in sets the cookie; it should be `Secure`, `HttpOnly`, and `SameSite=Lax` or stricter. Requests that change data must include a CSRF token in `X-CSRF-Token`.

Required headers:

| Header | Use |
|---|---|
| `Accept: application/json` | All API requests |
| `Content-Type: application/json` | Requests with JSON bodies |
| `X-CSRF-Token: <token>` | Cookie-authenticated state-changing requests |
| `Idempotency-Key: <unique-key>` | Creates or actions that could be retried and cause financial, order, or inventory effects |

The session login currently requires Terms of Service acceptance. If the future API shares that requirement, the login request must include `termsAccepted: true` and the agreed terms version; this behavior must be confirmed before implementation.

### Inventory integration authentication

The Inventory System must use a dedicated service identity, not a Sales user session. Authenticate the integration using mutually authenticated TLS or a signed request (HMAC over the timestamp, event ID, and raw body), with secret rotation and replay protection. Do not expose integration routes to browser users.

### Roles

| Role | API permissions |
|---|---|
| Sales Admin | Customer administration, approval actions, and Sales workspace reads |
| Sales Staff | Quotations, order processing, delivery, invoice, collection, return creation, and reporting |
| Customer | Not enabled: current login does not permit Customer users. Customer routes below are disabled until customer authentication and ownership rules are approved. |
| Inventory integration | Approved stockout reconciliation only |

Every request must be authorized on the server. Customer IDs in request bodies do not grant access to a customer's data.

## 3. Endpoint catalog

All `/api/v1` endpoints in this catalog are **proposed and not currently implemented** unless stated otherwise.

### Authentication and user

| Method and path | Access | Request | Success |
|---|---|---|---|
| `GET /auth/csrf-token` | Public | No body | `200` with a CSRF token |
| `POST /auth/session` | Public | `{"username":"...","password":"...","termsAccepted":true}` | `200` with current user summary and session cookie |
| `DELETE /auth/session` | Authenticated | CSRF header | `204`; session invalidated |
| `GET /me` | Authenticated | No body | `200` with current user's ID, name, username, and role |

Do not include password or password hash in any response. Consider rate limiting failed sign-ins and returning the same public error for unknown usernames and incorrect passwords.

### Customers and order requests

| Method and path | Access | Request | Success |
|---|---|---|---|
| `GET /customers` | Sales Admin/Staff | Optional `q`, `status`, `limit`, `cursor` | `200` paged customer summaries |
| `POST /customers` | Sales Admin | Customer fields: `customerName` (required), optional `phone`, `email`, `address`, `city`, `province`, `paymentTerms`, `creditLimit` | `201` created customer |
| `GET /customers/{customerId}` | Sales Admin/Staff | No body | `200` customer or `404` |
| `PATCH /customers/{customerId}` | Sales Admin | Editable customer fields | `200` updated customer |
| `GET /customers/{customerId}/transactions` | Sales Admin/Staff | Optional `from`, `to`, `limit`, `cursor` | `200` newest-first transaction timeline |
| `GET /order-requests` | Sales Admin/Staff | Optional `status`, `customerId`, `limit`, `cursor` | `200` paged request list |
| `POST /order-requests` | Customer route disabled | Proposed body: `productCode`, positive integer `quantity`, optional `notes`, `requestDate` | Proposed `201`; customer identity must come from authenticated session |

`creditLimit` and `paymentTerms` are stored in the database, but the rules for enforcing them are not defined. Do not block orders based on them until the business policy is approved.

### Quotations and purchase orders

| Method and path | Access | Request | Success |
|---|---|---|---|
| `POST /quotations` | Sales Staff | `customerId`, optional `requestId`, `productCode`, positive integer `quantity`, `quotationDate`, optional `validUntil`; unit price is resolved from Team Sales inventory | `201` quotation |
| `GET /quotations/{quotationId}` | Sales Admin/Staff | No body | `200` quotation or `404` |
| `POST /quotations/{quotationId}/decision` | Customer route disabled | `{"decision":"approve"}` or `{"decision":"reject"}` | Proposed `200` updated decision |
| `POST /quotations/{quotationId}/admin-decision` | Sales Admin | `{"decision":"approve","reason":"..."}` or `{"decision":"reject","reason":"..."}` | `200` updated admin decision |
| `GET /purchase-orders` | Sales Admin/Staff | Optional `status`, `customerId`, `limit`, `cursor` | `200` paged purchase orders |
| `POST /purchase-orders/{purchaseOrderId}/convert` | Sales Staff | Empty body; `Idempotency-Key` required | `201` new sales order; matching retry returns existing result |

The current application uses one product and quantity per request, quotation, purchase order, and sales order. The proposed API preserves that single-item model. `unitPrice` is not currently stored as a separate snapshot column; implement that schema change before accepting it through the API. Total and currency/rounding rules require business confirmation.

The existing helper that creates a purchase order from a quotation gates on customer approval. It does not require `admin_status = 'Approved'`. Preserve that behavior unless the business owner changes it explicitly. An expired or rejected quotation must not be converted.

### Sales orders, deliveries, invoices, and collections

| Method and path | Access | Request | Success |
|---|---|---|---|
| `GET /sales-orders` | Sales Admin/Staff | Optional `status`, `customerId`, `limit`, `cursor` | `200` paged orders with delivery/invoice summary |
| `POST /sales-orders/{orderId}/admin-decision` | Sales Admin | `{"decision":"approve","reason":"..."}` or `{"decision":"reject","reason":"..."}`; idempotency key required for approval | `200` updated order; approval atomically records stock deduction and delivery |
| `PATCH /deliveries/{deliveryId}` | Sales Staff | `{"status":"In Transit"}` or `{"status":"Delivered"}` | `200` updated delivery |
| `POST /sales-orders/{orderId}/invoices` | Sales Staff | Optional approved invoice date; idempotency key required | `201` invoice; `409` if an invoice already exists |
| `POST /invoices/{invoiceId}/collections` | Sales Staff | `{"amount":"100.00","method":"Cash","collectionDate":"YYYY-MM-DD","dueDate":"YYYY-MM-DD"}`; idempotency key required | `201` collection and updated invoice balance/status |
| `GET /receivables` | Sales Admin/Staff | Optional `status`, `customerId`, `asOf`, `limit`, `cursor` | `200` paged receivables |

Allowed collection methods are `Cash`, `GCash`, and `Bank Transfer`. Amount must be positive and no greater than the outstanding invoice balance. Concurrent collections must serialize on the invoice so the same remaining balance cannot be collected twice.

Delivery transitions are `Preparing -> In Transit -> Delivered`. Reversals are rejected unless a separate correction process is approved. Order completion depends on both delivery and payment; delivery alone does not complete an order.

### Returns, inventory, and reports

| Method and path | Access | Request | Success |
|---|---|---|---|
| `POST /sales-orders/{orderId}/returns` | Sales Staff | `{"returnDate":"YYYY-MM-DD","reason":"...","amount":"0.00"}` | `201` pending return |
| `POST /returns/{returnId}/admin-decision` | Sales Admin | `{"decision":"approve","reason":"..."}` or `{"decision":"reject","reason":"..."}` | `200` updated return |
| `GET /inventory` | Sales Admin/Staff | Optional `q`, `status`, `limit`, `cursor` | `200` Sales-owned stock and product details |
| `POST /inventory/stockout-reconciliation` | Inventory integration only | Signed event with external stockout ID, product code, positive quantity, approved status, and timestamps | `202` accepted; `200` if already applied |
| `GET /reports/sales` | Sales Admin/Staff | `year`; optional `customerId` | `200` report aggregates |

Return accounting is not fully specified. Approval must not automatically restock inventory, issue a refund, or change invoice totals until the business rules are approved.

Inventory belongs to a separate Inventory System. Sales must not directly decrement Inventory-owned stock. Reconciliation of an approved stockout must be idempotent on the external stockout ID so retries cannot increase Sales stock twice.

## 4. Request and response examples

### Create quotation (proposed)

```http
POST /api/v1/quotations HTTP/1.1
Accept: application/json
Content-Type: application/json
X-CSRF-Token: <token>
Idempotency-Key: quotation-request-<unique-value>
Cookie: TEAM_SALES_SESSID=<session>
```

```json
{
  "customerId": 12,
  "requestId": 91,
  "productCode": "SH-001",
  "quantity": 3,
  "unitPrice": "1250.00",
  "quotationDate": "2026-10-31",
  "validUntil": "2026-11-14"
}
```

```json
{
  "data": {
    "id": 57,
    "quotationNo": "QT-2057",
    "customerId": 12,
    "productCode": "SH-001",
    "quantity": 3,
    "unitPrice": "1250.00",
    "totalAmount": "3750.00",
    "status": "Pending",
    "customerStatus": "Pending",
    "adminStatus": "Pending"
  },
  "meta": {
    "request_id": "01J..."
  }
}
```

The document-number format above is illustrative. The deployed generator determines the actual number.

### Record collection (proposed)

```http
POST /api/v1/invoices/44/collections HTTP/1.1
Accept: application/json
Content-Type: application/json
X-CSRF-Token: <token>
Idempotency-Key: collection-<unique-value>
Cookie: TEAM_SALES_SESSID=<session>
```

```json
{
  "amount": "100.00",
  "method": "Bank Transfer",
  "collectionDate": "2026-10-31",
  "dueDate": "2026-11-30"
}
```

```json
{
  "data": {
    "id": 301,
    "collectionNo": "COL-0301",
    "invoiceId": 44,
    "amount": "100.00",
    "method": "Bank Transfer",
    "invoiceAmountPaid": "600.00",
    "invoiceOutstanding": "400.00",
    "invoiceStatus": "Partially Paid"
  },
  "meta": {
    "request_id": "01J..."
  }
}
```

## 5. HTTP status and error codes

| HTTP status | Meaning | Example error code |
|---|---|---|
| `200` | Successful read or update | — |
| `201` | Resource created | — |
| `202` | Integration event accepted for asynchronous processing | — |
| `204` | Successful operation with no response body | — |
| `400` | Malformed JSON or invalid query parameter | `bad_request` |
| `401` | Missing or invalid authentication | `unauthenticated` |
| `403` | Authenticated but unauthorized, or CSRF check failed | `forbidden`, `csrf_failed` |
| `404` | Resource not found | `not_found` |
| `409` | Invalid state transition, duplicate, stale state, insufficient stock, or idempotency conflict | `conflict`, `insufficient_stock`, `idempotency_conflict` |
| `422` | Field or business validation failed | `validation_error` |
| `429` | Rate limit exceeded | `rate_limited` |
| `500` | Unexpected internal error | `internal_error` |
| `503` | Required dependency unavailable | `dependency_unavailable` |

Error messages must not disclose whether a sensitive record exists when that would enable enumeration. Responses should include a request ID for support and log correlation.

## 6. Idempotency and workflow consistency

- Require `Idempotency-Key` for conversions, order approvals that deduct stock, invoice creation, collection posting, and stockout reconciliation.
- Scope keys to the authenticated principal or integration identity. Store a hash of the normalized request and the result/resource reference.
- A retry with the same key and same request returns the original result. Reusing the key with a different request returns `409 idempotency_conflict`.
- Enforce uniqueness in the database as well as in application code. Existing schema indexes do not currently guarantee one invoice per order, one delivery per order, or one purchase order per quotation; audit historical data before adding such constraints.
- Apply all related Sales database writes in a single InnoDB transaction. Inventory database calls are external to that transaction and require retryable reconciliation.
- Persist an activity record in the same transaction for sensitive or financially significant changes.

## 7. Existing JSON interface

The only JSON interface found in the current repository is an authenticated page-level AJAX action:

```http
GET /admin_customers.php?ajax=transactions&id={customerId}
```

- **Access:** Sales Admin only; PHP session required.
- **Purpose:** Return a customer's quotation, purchase-order, sales-order, invoice, collection, and return timeline, newest first.
- **Success:** `200 application/json`:

```json
{
  "ok": true,
  "customer": "Example Customer",
  "code": "CUS-001",
  "rows": [
    {
      "type": "Invoice",
      "ref": "INV-3002",
      "date": "09 Oct 2026",
      "amount": "₱150,000.00",
      "status": "Paid",
      "badge": "success"
    }
  ]
}
```

- **Not found:** `404 application/json`:

```json
{
  "ok": false,
  "error": "Customer not found."
}
```

This is a page-specific AJAX contract, not part of `/api/v1`. It formats the amount and date for display and does not implement the common API envelope or pagination described above. Keep consumers compatible if it is later replaced.

## 8. Implementation checklist

Before calling the proposed REST API production-ready:

1. Implement routing, JSON parsing, common response/error handling, request IDs, and input validation.
2. Share application services with current PHP forms so business rules do not diverge.
3. Implement secure session/CSRF handling and role/object-level authorization.
4. Add and test schema migrations for unit-price snapshots, idempotency, and inventory movements.
5. Add concurrency-safe uniqueness and transactions for conversions, invoicing, collections, and stock deductions.
6. Define integration authentication, event signature verification, retries, and reconciliation monitoring.
7. Resolve customer portal, pricing/tax, credit, returns, delivery, retention, and availability decisions before enabling their affected routes.
8. Add automated API, integration, concurrency, security, and regression tests.
