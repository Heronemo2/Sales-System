# QA Test Case: Sales to Inventory API Authentication and Catalog Read

**Test ID:** INT-SALES-INV-001  
**Priority:** High  
**Status:** Blocked in local test run  
**Area:** Sales System → Inventory System API

## Purpose

Verify that Sales can authenticate to Inventory and load its active item and warehouse catalog. This is the first read-only integration step used by Sales product checks and stock request flows.

## Preconditions

1. Inventory System is running and its API is available at `http://localhost/Inventory_System/api` (or the deployment's configured API base URL).
2. The Inventory integration key (`IMS_API_KEY`) is configured in Inventory's server environment.
3. The same key is configured as `INVENTORY_API_KEY` in the Sales web-server environment. Set `INVENTORY_API_BASE_URL` to the Inventory API directory, ending in `/api`.
4. At least one active warehouse and one active Finished Good exist in Inventory.

## Steps

1. From the Sales runtime, load the Inventory catalog using its configured client (`GET items.php`). The request must include `X-API-Key` and `X-API-Team: Sales`.
2. Confirm the response is HTTP 200 JSON with `success: true` and `data.items` and `data.warehouses` arrays.
3. Confirm Sales can identify an active Finished Good by matching its product code to `item_code`.
4. As a negative control, repeat the GET without `X-API-Key`.

## Expected results

- Authenticated request returns HTTP 200 and the active item and warehouse catalog.
- Sales recognizes an existing active Finished Good and reports a missing/non-Finished Good code cleanly.
- Negative control is rejected with HTTP 401; it must not return catalog data.

## Observed local results (2026-11-12)

- `http://localhost/Inventory_System/api/items.php` is reachable.
- GET without authentication returned **HTTP 401** (expected for the negative control).
- `INVENTORY_API_KEY` is absent from the test shell, so the authenticated request could not be run. Authenticated Sales-to-Inventory catalog and request flows remain unverified; configure the shared secret in both server environments and rerun steps 1–3.
- PHP syntax checks passed for the Sales API client/functions and Inventory bootstrap, catalog, and request endpoints.

## QA follow-up

Run the authenticated case in the deployed Sales/Inventory environment. If it fails, record the HTTP status and response message with secrets redacted. Do not submit a POST request as part of this test unless QA explicitly authorizes a test stock request and has a cleanup plan.

## Configuration note

The current Inventory API files are in `Inventory_System/api`. The Sales setup guide currently gives a base URL ending in `/Inventory_System/inventory_management/api`; update that deployment value to the actual API directory (for this local layout, `http://localhost/Inventory_System/api`).
