---
name: bootstrap-6-redesign
description: Steps B10-B11 of plans/bootstrap-6-panel.md: token tuning and the redesign of legacy form screens and tables (owner, 2026-11-01)
---

## Which screens are redesigned (B11), and how?
- Done: B10 #4465 #4836 #4867 #5356.
- Legacy edit screens (full-width rows, label/field tables; the B1b screenshot round lists them). Ready to start: the claiming session picks the next screen (most used first), named in its draft; no approval issue (owner, 2026-11-04, #6509): the screen lands when green; a change wanted later is a `bug` work item. The liked screens (`bootstrap-6-panel.md`) are the reference and are not redone.
- Target: the product_builder pattern (`ui/edit-screen-wide-layout.md`: form column, sticky status column, sticky save bar) with v6 `form-field`, form-adorn and cards. One screen per PR, before and after screenshots in the PR body.
- Screens redone, add and edit (forms in `includes/forms/`; `(#n: X)`: X sits in the status column; notes in `ui/`, mostly `<screen>-shared-form.md`; the first ones in `bootstrap-6-redesign-screens-1.md`, the later ones in `bootstrap-6-redesign-screens-2.md`, where new ones are added).
- Sweep of root screens (2026-11-06, after Visit Details #7475): no legacy stacked edit screen left. add_*/edit_*, `includes/forms/` and `includes/templates/` were swept before; the screens that use a layout helper (`pg_*_form_layout()`, `pg_commission_edit_layout()`, `pg_user_form_layout()` as in `import_users.php`) already have the wide layout. Order (`view_order.php`) is #7465. The rest of `view_*` are lists or reports (tables stay); `view_return.php` already has the status column. Without a helper: `api_docs`, `developer_lock`, `device_pairing`, `erp_cash`, `mail_queue`, `marketplace_products`, `software_repair`, `software_update`, `view_bookings`, `legacy_to_designer` are lists, dashboards or centred single-purpose screens, not stacked forms; `welcome` and the workspace screens are liked screens. A new find is a `bug` work item.
- Tables: the owner finds their structure fine. `table.chart` and DataTables stay; density, header, row actions and empty states may change (`.table-card` once B0b is in).
