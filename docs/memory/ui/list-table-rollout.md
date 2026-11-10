---
name: list-table-rollout
description: How the panel lists moved from DataTables to the list table driver (TD1-TD14, done with #8253): the driver, the rules that still hold, where each batch is noted, what the removal took out
---

## What replaced DataTables?
- The owner decided on 2026-11-03 that the panel's own list table driver replaces DataTables (`workflow/decisions-made-product-22.md`). DataTables 2.3.7 (578 KB with extensions the panel never configured) loaded on every panel page, and its redraw cleared every ticked row, so a bulk selection did not survive paging.
- Server: `includes/fn/list_table.php` (`list-table-driver.md`); browser: `assets/js/pg_list_table.js` in a bundle, styles in `backend.src.css`.
- The driver: first 100 rows printed, then keyset batches of 100; a browser mode for small lists printed whole (client sort with Turkish collation, `client-list-turkish-search.md`); selection kept by record id with Ctrl+A by condition; sort without a reload; a CSS sticky header; the columns menu and details panel kept per user in localStorage; phone cards below 768 px; quick edit over `pg_list_edit_request()`; roles `table`/`row`/`cell`, `aria-sort`, a live region.

## Which rules bound the move and still hold?
- Lists whose order is their meaning (ledgers, statements, invoice lines) and tables whose rows hold form fields stay plain tables, sortable with `table[data-pg-sort]` when useful (`list-and-table-screens.md`).
- A new list goes on the driver; DataTables, `table.chart` and the `datatable-*` classes do not come back.

## Where is each batch noted?
- The whole driver and the recipe for a new list: `list-table-overview.md`. Lists of the first steps: `list-table-driver-lists.md`; printed lists: `list-table-printed.md`; commerce: `list-table-commerce-lists.md`; settings: `list-table-settings-lists.md`; campaigns and system: `list-table-campaign-system-lists.md`; ERP: `list-table-erp-lists.md`; screens with their own DataTables code: `list-table-own-code-screens.md`; product groups (the last `table.chart`): `list-table-variant-sets.md`; server paging: `server-paged-lists.md`.

## What did the removal (TD14b2, #8253) take out?
- `assets/lib/DataTables/` with its i18n file, `jquery_datatables_checkbox`, their tags in `output_header()`; `clean_up.php` deletes them on upgraded sites.
- In `backend.src.js` the DataTables setup and its helpers (`pgListTurkishSearch`, `pgListState`/`pgListKeep`, `pgListHeadingCopies`, `chart_checkbox_state_change`, `pgListCurtainDone`, `edit_chart_content`); `pgListTurkishMatch()` stays for the driver. The loading curtain no longer holds for a list (`loading-curtain.md`). The `dataTable`/`dt-*`/FixedHeader/`table.chart` CSS went.
- The product group form's own select-all sync shows Modify Selected while a product is ticked; the removed helper had done it.
