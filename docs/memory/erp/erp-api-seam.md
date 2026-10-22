---
name: erp-api-seam
description: ERP's only door into the external API, includes/erp/api.php: five hooks, load chain, name clashes, schema pairs, paging, scope groups, owner ceiling, error codes, drafts, wire rules.
---

## What is the only entry point of ERP into the API?
- Everything ERP adds enters through `includes/erp/api.php`, merged by the API's seam `includes/api/modules.php`: `erp_api_routes()`, `erp_webhook_events()`, `erp_openapi_objects()`, `erp_scope_groups()`, `erp_owner_scopes($owner)`. Each is optional.
- ERP edits no file under `includes/api/**`. A missing hook is its own change to `modules.php` and its caller (`owner_scopes` came that way).
- On a name clash the API wins: events, objects and scope groups are `$api + $module`, module routes come after the API's own, so a module never replaces a core name (`order.created`).

## What is the load chain, and what must stay true?
- API entry, the panel (`api_settings.php`) or `api_webhook_enqueue()` → `api_modules_load()` → `includes/erp/api.php`, which loads `bootstrap.php` and its handler files (`api_<name>.php`: resources, documents, stock, cash, reports, purchase_orders) itself; the API entry loads none of them.
- Only while the module is on (`api_modules()` `enabled` = `ERP_ENABLED`): off, it has no endpoints, events or permissions.
- So every caller of `api_webhook_enqueue()` (checkout included) loads the ERP bootstrap when ERP is on: `includes/erp/*` files only define functions; keep it so. Gate: `PG_API_ENTRY || PG_API_PANEL || PG_INIT_LOADED`.

## How are server and schema kept in step?
- Next to `erp_api_<object>_present()` sits `erp_api_<object>_schema()`, one line per field (`'string?'` = may be null, `'number'` = decimal, an inner object is an array, a list is `array(array(...))`). A new server field goes into the schema too.
- `tools/check_api_schema.php` reads the modules' `includes/*/api.php` and `api_*.php` too (returns, objects, 201s, error codes), but compares a presenter with its schema only for pairs in its `$presenters` map (ERP: quote, purchase order, cheque, stock, cash transfer, late fee, reports, dashboard). Account, invoice, receipt, expense, waybill and the rest are kept by hand; a new pair goes into the map.

## How does paging work?
- `erp_api_page($select, $where, $sort, $id, $params, $count_from)`: `$select` supplies a `_sort` column (`updated_at`, `UNIX_TIMESTAMP(doc_date)` or `''` → id only); cursor `api_cursor_encode(v, i)`. `$count_from` is the main table only (WHEREs use that alias).

## Which permission groups exist?
- `erp` and `erp_cash` (the till is a separate panel right). Rows carry `label`, `description` (through `lang()`) and `icon`: the application screen prints a module's row as given (`api_settings_group_text()`), "Erp" with an empty line otherwise.
- Ceiling: `api_load_owner()` carries every permission column (`api_owner_permission_columns()`); `erp_owner_scopes()` gives role < 3 all four; otherwise `erp:read` with `manage_erp`, `erp_cash:read` with `manage_erp_cash` too, each `:write` unless `manage_erp_readonly`. `erp_api_owner()` still reads `manage_erp_cash` itself for the receipt gate.

## Which error codes and wire rules apply?
- A non-409 code comes from `api_error_catalogue()`: `validation_failed` (422, with the field), `not_found`, `forbidden`, `invalid_cursor`, `service_unavailable`, and the ERP's `period_locked` / `credit_limit` (422). A new one is a catalogue row in an API file; the checker fails an unexplained one. A 409 is named by its route and explained in the route's description (`invoice_not_draft`, `invoice_at_provider`; api/api-endpoint-rules.md).
- Drafts stay off the invoice list unless asked for (`status=draft`); a single invoice is returned draft or not, its document only once issued; waybill drafts are not on the wire.
- Money is kurus via `api_money()`, day `Y-m-d` (`erp_api_date()`: 0000-00-00 → null), time ISO via `api_time()`. A `datetime` parameter returns unix; for a DATE column use `erp_api_day()`.

Related: docs/memory/erp/erp-api-events-and-testing.md
