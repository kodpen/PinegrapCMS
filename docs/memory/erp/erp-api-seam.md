---
name: erp-api-seam
description: How ERP plugs into the external API only via includes/erp/api.php: load chain, schema pairs, paging, scope groups, error codes, wire-format rules.
---

## What is the only entry point of ERP into the API?
- Everything ERP adds to the API enters ONLY through `includes/erp/api.php` (the API's `includes/api/modules.php` seam): `erp_api_routes()`, `erp_webhook_events()`, `erp_openapi_objects()`, `erp_scope_groups()`, plus `erp_owner_scopes($owner)` (the API ceiling hook).
- `includes/api/**` belongs to the API agent; ERP edits no file there. A missing hook is requested via `dev/_handoff/` (the owner-ceiling hook was requested that way, see `ERP-API-istek.md`).

## What is the load chain, and what must stay true?
- API entry, or the panel (`api_settings.php`), or `api_webhook_enqueue()` → `modules.php` → `require_once includes/erp/api.php` → which loads `bootstrap.php` + `api_resources.php` itself.
- So every caller of `api_webhook_enqueue()` (checkout included) loads the ERP bootstrap when ERP is on. Therefore `includes/erp/*` files only define functions and run nothing at load; keep it so.
- Gate is the triple `PG_API_ENTRY || PG_API_PANEL || PG_INIT_LOADED`.

## How are server and schema kept in step?
- Next to `erp_api_<object>_present()` sits `erp_api_<object>_schema()`, one line per field (`'string?'` = may be null, `'number'` = decimal, an inner object is an array, a list is `array(array(...))`). Add a field to the server → add it to the schema.
- `tools/check_api_schema.php` does NOT scan module files (only `includes/api/**`); the contract is kept by hand.

## How does paging work?
- `erp_api_page($select, $where, $sort, $id, $params, $count_from)`: `$select` must supply a `_sort` column (`updated_at`, `UNIX_TIMESTAMP(doc_date)` or `''` → id only); cursor `api_cursor_encode(v, i)`. `$count_from` is the main table only (WHEREs are written against that alias).

## Which permission groups exist?
- Two: `erp` / `erp_cash` (till is a separate panel right). `api_load_owner()` does not know ERP columns, so `erp_api_owner()` reads `user.manage_erp_cash` itself (`POST /erp/receipts`). When the hook arrives both coexist, no harm.
- Label/description via `lang()` (`api_settings_group_text()` translates core rows, prints module rows as-is).

## Which error codes and wire rules apply?
- Error codes come from the catalogue only: `validation_failed` (422, with field name), `not_found`, `forbidden`, `invalid_cursor`, `service_unavailable`. A new code = a row in `api_error_catalogue()` = an API file; do not open one.
- Drafts are not on the wire: `status <> 'draft'` in both list and single (404).
- Money is kuruş via `api_money()`, day `Y-m-d` (`erp_api_date()`: 0000-00-00 → null), time ISO via `api_time()`. A `datetime` parameter returns unix; for a DATE column use `erp_api_day()`.

Related: docs/memory/erp/erp-api-events-and-testing.md
