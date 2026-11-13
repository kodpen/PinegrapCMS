---
name: list-table-driver
description: List table driver server core (includes/fn/list_table.php): source and column model, first rows printed, keyset batches, the list_table_rows action, the selection resolver
module: list_table
---

## What does a list give the driver?
- A source (`pg_list_table_source()`): `list` (name and `pg_list_state()` key), `columns` (key => heading, width, kind, `sort` SQL, sortable, editable, hideable, hidden), `from`, `where` with the user's rights and its bound `params`, `id` and `id_order`, `select`, `group`, `search` (`pg_list_search_where()` columns and labels), `levels`, `created` (Unix seconds SQL, for a selection's "as of"), `rows` (records => rows with `id`, `class`, `cells` keyed by column key), `all` (small list printed whole: browser mode), `params_out` (sent back by the browser).
- A sort expression is used in WHERE by the keyset, so it is a real expression, never a select alias. Only `pg_list_table_client_columns()` (no SQL) reaches the page.
- The first column is the record's name: never hideable or hidden.

## How do the rows come?
- `pg_list_table_html()` prints the first batch (100, `pg_list_table_batch()`) with the column model, params, sort, next cursor and total on `data-pg-list-*` attributes, roles `table`/`row`/`cell`, `aria-sort` on the leading sort, an empty line and a live region.
- The `list_table_rows` action (`includes/panel/lists.php`, any_role + token) looks the list up in `pg_list_table_definitions()` (name => `file`, `source`); the source function is the screen's gate and answers null to refuse (403). It applies `sort` (a heading click, no reload) and keeps it in the session, then answers `pg_list_table_answer()`: `rows`, `next`, `total` only on the first batch (counted once per condition), `restarted` when a cursor could not be read.
- Keyset, not offset: the cursor (base64url JSON) holds the sort key, the last row's sort values and id; a cursor of another sort restarts. NULL sorts lowest in MySQL, so `pg_list_table_keyset_where()` writes `IS NOT NULL` / `IS NULL` alternatives; the id gets none.

## What does a selection resolve to?
- `pg_list_table_selection_parse()`: `{ids}` or `{condition: {search, params}, except, as_of}`; ids are whole numbers, at most 10,000; `as_of` is held to now.
- `pg_list_table_selected_ids()` reads the ids with the list's own WHERE (rights) and `pg_list_table_selection_where()`: ids `IN`, or the search less `NOT IN` exceptions and `created <= as_of`.

## Which lists are on it?
- Pages, users, contacts, products, commissions and the mail queue: `ui/list-table-driver-lists.md`.
- Quick edit of a list: `ui/list-table-quick-edit.md`.
- A small list printed whole (browser mode): `ui/list-table-printed.md`.

## How does a screen hand its bulk actions over?
- `pg_list_table_html()`'s fourth argument is the screen's bulk buttons, printed in the bar's `.pg-list-table-actions` (shown only while rows are selected).
- The screen keeps a hidden `selection` field filled from `pg-list-table:selection` (`JSON.stringify(detail.payload)`) and posts it with the list's parameters (`list_params`), since an `{ids}` payload carries none; the bulk endpoint builds the source again from those parameters (the user's rights included) and calls `pg_list_table_selected_ids()`. More than 10,000 rows (`more`) is refused, not cut.

## What turns a list screen into HTTP 500?
- `pg_list_table_html()` and `pg_list_table_page()` take the source and page as `array`: a string or null in their place is a fatal TypeError, and the whole screen answers 500. Keep the variables that hold them out of any `foreach` between their assignment and the call. The audit trail's Source filter loop reused `$audit_view_source` from TD6 (#6386) on (issue #8993, PR #9040). `test_list_table_screens_keep_their_source_out_of_loops` (`tests/list_table_test.php`) checks every list screen.

Related: docs/memory/ui/list-table-driver-browser.md, docs/memory/ui/list-table-driver-lists.md, docs/memory/ui/server-paged-lists.md
