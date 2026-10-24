---
name: workspace-record-kinds
description: Every list a Workspace record kind (order, product, contact, invoice...) must be in, the kinds each leaves out and why, and the test that names the list a new kind was missed in
---

## How is a new record kind added?
- Start in `ws_record_type_keys()` (includes/workspace/refs.php), then put it in every list below, or in that list's `except` in `workspace_record_kinds_test_lists()` (tests/workspace_record_kinds_test.php) with a one-line reason. The test lays each kind against each list and names the list it is missing from; a reason left for a kind that is in the list now fails as out of date, a misspelt kind fails as unknown.
- Lists (their name in the test): `ws_ref_types()` (tags), a branch of `ws_refs_resolve()` (resolve), a case of `ws_ref_search()` (search), the `refs` description of the task routes in `ws_api_routes()` (api_refs), a tag of `ws_change_types()` (changes), `ws_quick_edit_types()` (quick_edit), `ws_bulk_types()` (bulk), `ws_events_catalog()` (events), `ws_api_search_record_scopes()` (search_scopes), `ws_ai_type_scope()` (ai_scopes), `pg_audit_tables()` through `ws_change_audit_target()` (audit), `ws_customer_types()` (customers), a `ws_record_button()` call on the record's screen at the product root (screens).
- Held together as well: a change kind has its `ws_change_write_<kind>()`; its right shows its tag in `ws_ref_types()`; it gets an audit line on its table exactly when `pg_audit_tables()` follows that table; the assistants ask the scope the API search asks; each catalogue event's `record` is what `ws_events_record_of()` finds; the drawer's short lists name fields of their kind; workspace.js offers the same customer kinds.

## Which kinds stay out of a list today, and why?
- ERP documents (invoice, waybill, receipt, edoc) are tag-only: no change kind, so no drawer, bulk or audit line.
- Drawer: user_account (role on the user screen), form (the drawer has no input for its answers), file, page (file manager). Bulk: order, user_account, form.
- Events: only order, product, contact and form have one. Search scopes: user_account (no API scope reads users). Audit: product_group, contact, erp_account, form, calendar_event (tables not in `pg_audit_tables()`). Screens: file and page draw no Workspace button. Customers: only contact, user_account, erp_account.

## Which differences does the test keep as they are?
- `ws_ai_type_scope()` has no calendar_event, so the assistants read events under `workspace:read` while the API search asks `calendars:read`; for receipts it asks `erp:read` where the search asks `erp_cash:read`. Both are exceptions in the test: aligning them changes what Pinegrap AI may read, so it is a change of its own.

## How does the test read lists built with database probes?
- From source, with `tests/helpers/source_reader.php` (docs/memory/workflow/unit-test-runner.md): the change catalogue, `ws_ai_type_scope()`, the drawer's short lists, the events catalogue, the resolve and search branches.
