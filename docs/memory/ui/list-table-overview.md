---
name: list-table-overview
description: The panel list table driver as a whole: the decision behind it, its parts by label, which file holds what, the recipe for a new list, its filter, where a new screen is registered
module: list_table
---

## What is it?
- Every panel list is drawn by one driver: `includes/fn/list_table.php`, `assets/js/pg_list_table.js` (quick edit `pg_list_table_edit.js`, bundle `pg_list_table.min.js`), CSS in `backend.src.css`. DataTables is gone (#8253, `list-table-rollout.md`); each `list-table-*` batch file names the screens a step moved.
- Two modes: a server list (100 rows, then keyset batches; `list-table-driver.md`) and a small list printed whole, sorted and searched in the browser (`list-table-printed.md`).

## Which decisions bind it?
- Owner 2026-11-03 (`workflow/decisions-made-product-22.md`): no row checkboxes, selection by click as in the File Manager, kept by id; Ctrl+A by condition; a details panel on every list; columns kept in localStorage, no table rows; quick edit as a mode. DataTables and `table.chart` never return.
- Ledgers and tables of form fields stay plain tables (`list-and-table-screens.md`).

## What does a person see?
- The selection bar: count, "Tümünü seç", bulk buttons, "Önceki seçimi geri al" (`list-table-driver-browser.md`); "Sütun" menu, "Detaylar" panel; phone cards (`list-table-layout.md`).
- Heading sort and the toolbar sort menu (`list-table-driver-sort.md`); "Daha fazla göster"; the empty line "Kayıt bulunamadı.", printed by the driver: a screen writes no empty notice.
- "Hızlı düzenleme" (F2), "Son değişikliği geri al", "Seçili N satıra uygula" (`list-table-quick-edit.md`; which values are fields: `list-table-quick-edit-values.md`; one `list-table-quick-edit-<list>.md` per list).

## How is a new list added?
- Printed whole (hundreds of rows): copy `view_short_links.php`: `pg_list_state()` with `$sort_columns`, `pg_list_order_by()`, `db_items()`, `pg_list_table_printed()`, the toolbar's search slot (`pg_list_table_search_box()`, `pg_list_sort_menu()`), `pg_list_table_html()`, `pg_js_bundle_tags('assets/js/pg_list_table.min.js')`. Not its old `PineGrap … LiveSite` header (a new file takes the Pinegrap one, `core/`) and never `mysqli_query()` (`view_calendar_event_locations.php` still has one).
- A list that keeps its sort through `pg_list_state()` is named in `pg_list_table_printed_lists()`, so a heading click is kept; one that never kept a sort stays out (ERP prices, `list-table-commerce-lists.md`).
- Server list: copy `view_users.php` with `includes/fn/user_list.php`: a source function that is the gate (null refuses), named in `pg_list_table_definitions()`, first batch from `pg_list_table_page()`.
- First column the record's name, a link with `data-pg-open`, never hideable; row buttons in an `actions` column, `type="button"`, no form in a row.
- Bulk buttons: `pg_list_table_html()`'s fourth argument. Server list: hidden `selection` + `list_params`, the endpoint calls `pg_<list>_list_selected_ids()` and refuses over 10,000. Printed list with an old endpoint: `ids_field` (`styles[]`).

## Which filter control does a list take?
- A filter the server reads is `pg_filter_select()` in the toolbar (`page-button-bar.md`), with `keep` for the search: the default.
- Filter buttons (`data-pg-list-filter`, `list-table-driver-sort.md`) only narrow rows already printed by a cell's `data-pg-filter`, without a reload: the Site Log types, the All Offers chips (several values, `list-table-own-code-screens.md`).

## Where is a new panel screen registered?
- Help: a row in `get_help_url.php` (`panel-help-links.md`, `tests/help_url_test.php`).
- Menu: an entry in `output_menu()` under the screen's own gate (`main-menu-entry-gates.md`, `main-menu-panels.md`) and its `case` in the active row `switch` there.
- Ctrl+K: a chip in `pg_panel_backend_search()` under `pg_panel_search_chip_gates()` (`command-palette.md`, `command-palette-chips.md`).
- A screen that prints a secret joins `pg_panel_no_store_screens()` (`panel-bfcache.md`).
