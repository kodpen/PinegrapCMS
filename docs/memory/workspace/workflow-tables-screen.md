---
name: workflow-tables-screen
description: The workflow tables screen workspace_wtables.php (T1e, #2966): server-drawn forms over the store, list scopes, rows paged, the row form, column options from the form, shares, nav entry
---

## Where is the screen, and how is it drawn?
- `workspace_wtables.php` (root, URL-called): no `?id=` lists the tables reached (`ws_wtables_reached()`, `?scope=mine|shared`) with a New table card; `?id=N` opens one table through `ws_wtable_for()` (not reached → "That table could not be found."). Menu: `ws_nav_groups()` key `wtables` ("Tables", `bi-table`) while `ws_wtables_ready()`, every member; `ws_side_screen()` and `pg_active_menu` slot 23 know the file.
- Server-drawn, no bundle: every change is a form posted with the token (`wt_action`), handled by the store's write function, then `go()` back (PRG) with a liveform notice or the store code worded by `ws_wtable_screen_error_text()`. The store decides who may do what; the screen only hides buttons a person may not use (`ws_wtable_may()`).
- Deletes (table, column, row, share) post through one hidden form outside the cards via `.ws-wt-action` buttons with `data-confirm`, since no form may sit in a table row (ui/ key rule).

## How are rows shown and written?
- 100 rows a page (`ws_wtable_rows_page()`, `?after=` the last position), formulas as read (`#CODE` on an error), status as a badge in its colour, person by name (`ws_people()`).
- The row form (`#ws-wt-row-form`): a new row, or `?row=M` one row with its `revision`; formula columns have no field. A refusal brings the typed values back once through `$_SESSION['software']['ws_wtable_row']` with each refused cell named; `changed_meanwhile` shows the row as it is now instead.
- `includes/workspace/wtable_screen.php` turns fields into cells: money typed `12.50`/`12,5` → minor units with `round()`, a datetime's `T` → space, a checkbox `1`/hidden `0`, a record link `kind:id` or `kind #id`. What it cannot read goes to the store as typed so the store's code answers. Dates use `data-pg-date`/`data-pg-datetime` + `pg_fields_script()`. Tests `tests/workspace_wtable_screen_test.php`.

## How are columns and shares edited?
- Column options from the form (`ws_wtable_screen_options_from_form()`): decimals, currency, date time, link kinds (comma list), formula expression; status choices one label a line, a label kept keeps its key and colour (`ws_wtable_screen_status_choices()`), so cells keep their choice. The type is chosen once (`type_fixed`).
- Shares card: everybody reaching the table sees the list; the owner shares with a team member (view/edit) or a public/private channel they read (view), and takes a share back. The `wtable_shared` bell line opens `workspace_wtables.php?scope=shared` (the inbox row names no table).

## Where is the board view?
- `?view=board`: the rows as cards by a status column (`workflow-table-board.md`).
- A table from a file, or rows from a file (`workflow-table-import.md`).
- Run from table: the rows as a draft flow (`workspace-flows-from-table.md`).

## What is left out?
- Editing in place, column reorder, row move (the store has `ws_wtable_row_move()`), a record-link picker (typed `kind:id`, shown `kind #id`, not resolved to a name), a table bin.
