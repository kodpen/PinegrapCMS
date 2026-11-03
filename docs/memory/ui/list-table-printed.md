---
name: list-table-printed
description: Browser mode of the list table driver: a small list printed whole (pg_list_table_printed()), its sort kept by list_table_sort, the toolbar search box
module: list_table
---

## How does a small list print every row (browser mode)?
- `pg_list_table_printed(array('list', 'columns', 'levels', 'rows'))` answers `source` and `page` for `pg_list_table_html()`: no SQL, `all` on, every column sortable in the browser unless `'sortable' => false` or kind `actions`/`image`. The screen reads its records with its own query and rights and builds the rows; a cell may carry `data-pg-sort-value` (e.g. a Unix time behind a relative date).
- Its name must be in `pg_list_table_printed_lists()`: the root then carries `data-pg-list-printed`, and a heading click posts `list_table_sort` (`includes/panel/lists.php`), which keeps the levels in the session the way `pg_list_state()` does (`pg_list_table_printed_sort()`: key form checked here, the columns by `pg_list_state()` when the screen reads them). The toolbar's `pg_list_sort_menu()` still works: the browser sorts the printed rows by the kept levels on load.
- `pg_list_table_search_box($list)` is the toolbar search box the driver hears.

## Why a session action for the sort?
- The screens kept their sort through `pg_list_state()` and heading links that reloaded the page; a heading click now sorts without a reload, so the action keeps the same session entry and a later visit (or the toolbar sort menu) comes back sorted the same way.

Related: docs/memory/ui/list-table-driver.md, docs/memory/ui/list-table-driver-browser.md, docs/memory/ui/list-table-driver-lists.md
