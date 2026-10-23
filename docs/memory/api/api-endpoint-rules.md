---
name: api-endpoint-rules
description: Writing an external API endpoint: one route row, one presenter, list paging and batch reads, body checks, nullable fields, error codes, formats, partial updates, mirroring panel changes
---

## Where is an endpoint declared and answered?
- One row in `api_schema()` (`includes/api/schema.php`) or in a module's `includes/<module>/api.php`: the router, the input validator, the OpenAPI document, the MCP tools and the permission screen all read it. Declaring the answer: `api-openapi-declarations.md`.
- A record's shape is built in one place, its presenter `api_*_present()` beside the handler (`includes/api/resources/*.php`); nothing else repeats the field list.
- A handler leaves through `api_ok()`, `api_ok_list()`, `api_fail()` or `api_send()` and never returns: falling through answers 500 `server_error`.

## How does a list endpoint read?
- Cursor paged (`api_cursor_encode()`), `limit` 1-250 (default 50), `next_cursor` null on the last page, `total` only when asked.
- No query per row: images, attributes and groups of the page come from one `WHERE id IN (...)` batch helper (`api_product_extras()`, `api_product_group_extras()`), and the presenter reads the prepared array.
- `updated_since` only where the stamp moves on EVERY writer: `contacts.timestamp` does not, so `GET /customers` has none and pages by id.
- ETag/304 and `?fields=` are applied by `api_send()` to every 200 GET; the handler adds nothing. An answer that bypasses `api_send()` (`api_send_file()`, the console HTML) gets neither.

## How is input checked?
- The router types and bounds every declared parameter before the handler runs (`api_validate_input()`): a wrong value is 422 naming the field, never cast.
- A body key the route does not declare is 422 (`api_request_body_keys()`); the query string is NOT checked (cache busters, analytics tags and proxy marks land there).
- A body field that may be cleared declares `'nullable' => true`: JSON null and `''` both reach the handler as `''`, and the handler decides what clearing means. Without it `""` on a non-string field is 422.
- A text `max_length` must fit its column: `api-text-field-limits.md`.

## Which error codes may a handler use?
- Reuse the generic ones: `validation_failed` (`api_fail_validation()`), `not_found` (`api_fail_not_found()`), `forbidden`, `invalid_cursor`, `service_unavailable`. Every non-409 code must be explained in `api_error_catalogue()` (`includes/api/schema.php`); `tools/check_api_schema.php` fails a build that answers an unexplained one.
- A 409 is named by the endpoint that produces it (`name_taken`, `already_recycled`, `invoice_not_draft`) and described in that route's description, not in the catalogue.

## Which formats?
- Money: integer minor units (`api_money()`); a decimal sent to a `money` parameter is refused, not rounded. A day: `Y-m-d`. A moment: ISO-8601 UTC (`api_time()`, null for 0).

## How is a partial update written?
- Build the full `$data` from the current row first, then lay the sent fields over it (`erp_api_accounts_update()` with `erp_api_account_save_data()`): "only the fields sent change" is every update's contract.
- `api_query()` gets `false` from a failed query (`init.php` turns mysqli reporting off); its try/catch is a second safety, not the only one.

## What if the panel changes a record's structure?
- A new status, link or proof added in the panel is put on the existing API object in the same change: no field readable in the event catalogue but missing on the object (in 2026.4.8 the quote signature and the discussions were left so).
