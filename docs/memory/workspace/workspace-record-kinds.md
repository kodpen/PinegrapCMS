---
name: workspace-record-kinds
description: Every list a Workspace record kind (order, product, invoice...) must be in, the kinds each leaves out and why, the scope the assistants read a kind with, and the test that guards it
---

## How is a new record kind added?
- Start in `ws_record_kinds()` (workspace-record-kind-registry.md) and `ws_record_type_keys()` (refs.php), then put it in every list below, or in that list's `except` in `workspace_record_kinds_test_lists()` (tests/workspace_record_kinds_test.php) with a one-line reason. The test lays each kind against each list and names the list it is missing from; a reason left for a kind that is in the list now fails as out of date, a misspelt kind fails as unknown.
- Lists (their name in the test): `ws_ref_types()` (tags), a branch of `ws_refs_resolve()` (resolve), a case of `ws_ref_search()` (search), the `refs` description of the task routes in `ws_api_routes()` (api_refs), a tag of `ws_change_types()` (changes), `ws_quick_edit_types()` (quick_edit), `ws_bulk_types()` (bulk), `ws_events_catalog()` (events), `ws_api_search_record_scopes()` (search_scopes), `ws_ai_type_scope()` (ai_scopes), `pg_audit_tables()` through `ws_change_audit_target()` (audit), `ws_customer_types()` (customers), a `ws_record_button()` call on the record's screen at the product root (screens).
- Held together as well: a change kind has its `ws_change_write_<kind>()`; its right shows its tag in `ws_ref_types()`; it gets an audit line on its table exactly when `pg_audit_tables()` follows that table; the assistants ask the scope the API search asks; each catalogue event's `record` is what `ws_events_record_of()` finds; the drawer's short lists name fields of their kind; workspace.js offers the same customer kinds; no two kinds share a `#` prefix; a change kind and its update fields have a line in `workspace_flows_parity_table()` (`workspace-flows-parity-test.md`).

## Which kinds stay out of a list today, and why?
- ERP documents (invoice, waybill, receipt, edoc, erp_quote) are tag-only: no change kind, so no drawer, bulk or audit line.
- Drawer: user_account (role on the user screen), form (the drawer has no input for its answers), file, page (file manager). Bulk: order, user_account, form.
- Events: only order, product, contact and form have one. Search scopes: user_account (no API scope reads users). Audit: product_group, contact, erp_account, form, calendar_event (tables not in `pg_audit_tables()`). Screens: file and page draw no Workspace button. Customers: only contact, user_account, erp_account.

## Which scope do the assistants read a kind with?
- The one the external API reads it with: `ws_ai_type_scope()` asks what `ws_api_search_record_scopes()` asks, kind by kind, and the test allows no exception. So an assistant's key reads no record it could not read over the API.
- A sales quote (`erp_quote`) asks `erp:read`, as the ERP's quote routes (`test_ws_api_search_record_scopes_name_known_kinds`).
- Calendar events ask `calendars:read` and cash receipts `erp_cash:read`, as the API search, the calendar routes and the ERP's receipt routes do. Only user_account (no API scope reads users) and kinds without an API endpoint go with `workspace:read`.
- Pinegrap AI: `search_records` and `read_record` offer only the kinds its application's scopes reach (`ws_ai_readable_types()`). Claude: `GET /workspace/claude/requests/{id}/records/{type}/{record_id}` is declared with `workspace:read` but asks the kind's scope too, before the record is looked up (403 `insufficient_scope`).
- An application chosen for Pinegrap AI by hand now needs `calendars:read` for events and `erp_cash:read` for receipts. The key Claude's card makes holds both when the administrator may hand them on (every read but five, `api_assistant_scopes()`).

## How does the test read lists built with database probes?
- From source, with `tests/helpers/source_reader.php` (docs/memory/workflow/unit-test-runner.md): the change catalogue, `ws_ai_type_scope()`, the drawer's short lists, the events catalogue, the resolve and search branches.
