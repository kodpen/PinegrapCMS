---
name: api-customer-rhythm-report
description: GET /reports/customer-rhythm (RY5, orders:read, an MCP tool): customers per stage, the customers a page at a time, ?stage= and ?contact_id=, names with customers:read and the user role's reach
---

## What does it answer?
- `api_reports_customer_rhythm()` in `includes/api/resources/reports.php`, route `reports.customer_rhythm`, scope `orders:read`, returns `array('list' => 'CustomerRhythmCustomer', 'rhythm' => 'CustomerRhythmReport')` through `api_ok_list()`'s extra. Offered as an MCP tool by the route rule.
- `rhythm` (`api_customer_rhythm_present()`): `ready`, `as_of` (the summary's `at`), `progress` (as the Customers Report's), `names_shown`, `currency` (`BASE_CURRENCY_CODE`), `store_median_days`, `customers`, `stages` (the screen's `pg_customer_rhythm_stage_rows()`: six stages, `none` last only when any), `due`.
- `data` (`api_customer_rhythm_customer_present()`): the row's figures, `stage` and `stage_before` as words (`none` for 0), `last_order_number`, `own_interval` (orders > 1), `reminded_at`, `held_back`, `came_back_order_id`. Never `customer_key`.

## How is it paged and filtered?
- Most expected value first, then id, both descending; the cursor carries `expected_value` and the id (`api_customer_rhythm_page_query()`, pure, tested). `stage` takes the six words and `none` (`api_customer_rhythm_stage_keys()`).
- `contact_id`: the card's row (`pg_customer_rhythm_contact_row()` with the contact's e-mail), alone, and `rhythm.due` its products due now with names (`pg_customer_rhythm_contact_due()`). An unknown contact or one without a row gives an empty list.
- Commerce off, no table or no summary yet: `ready` false, an empty page, `progress` tells the pass.

## Who sees names?
- Only a key that also holds `customers:read`. For an owner with the user role (3) the query marks `name_ok` with `pg_contact_owner_sql()` on `customer_rhythm.contact_id`: a row outside its contact groups keeps its figures (order data) and answers `name` null. The rows are not narrowed.

## Tests?
- `tests/api_customer_rhythm_report_test.php`; `php tools/check_api_schema.php` pairs `api_customer_rhythm_customer_schema()` with its presenter (the report object is checked by the test: nested lists defeat the checker's key scan).

Related: docs/memory/commerce/customer-rhythm-report.md, docs/memory/commerce/customer-rhythm-store.md, docs/memory/commerce/customer-value-groups-csv-api.md
