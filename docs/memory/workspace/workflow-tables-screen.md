---
name: workflow-tables-screen
description: The workflow tables screen workspace_wtables.php (T1e #2966, redrawn 2026-11): server-drawn over the store, one table, rows paged, the row drawer, column options, shares
---

## Where is the screen, and how is it drawn?
- `workspace_wtables.php` (root, URL-called): no `?id=` is the Data list (`workflow-tables-data-list.md`); `?id=N` opens one table through `ws_wtable_for()`. Menu: `ws_nav_groups()` key `wtables` ("Data", `bi-table`) while `ws_wtables_ready()`, every member; `ws_side_screen()` and `pg_active_menu` slot 23 know the file.
- Server-drawn: a change is a form posted with the token (`wt_action`), handled by the store's write function, then `go()` back (PRG) with a liveform notice or the store code worded by `ws_wtable_screen_error_text()`. The store decides who may do what; the screen hides what a person may not use (`ws_wtable_may()`).
- Bar and drawers: `workflow-tables-screen-bar.md`; grid: `workflow-tables-grid-drawer.md`; cells in it: `workflow-tables-grid.md`; column menu, rows' order: `workflow-tables-grid-order.md`.
- The redraw (2026-11) changed how the table is drawn and which gestures it takes, never who may do what: every write is still a `ws_wtable_*` store function with its rights, limits, refusals and `changed_meanwhile`. The board view and the Data list kept their look.
- Deletes and moves post via one hidden form outside the drawers (`.ws-wt-action` buttons, `data-confirm`): no form sits in a table row.

## How are rows shown and written?
- 100 rows a page (`ws_wtable_rows_page()`, `?after=`/`after_id=` the last row; with a query bar filter or sort, `?from=` an offset: `workflow-tables-query.md`, which also has the totals line), formulas as read (`#CODE` on an error), status as a badge in its colour, person by name (`ws_people()`).
- The row drawer (`#ws-wt-row-form`, `workflow-tables-grid-drawer.md`): a new row, or `?row=M` with its `revision`; formula columns have no field. A refusal brings the typed values back once through `$_SESSION['software']['ws_wtable_row']`.
- `includes/workspace/wtable_screen.php` turns fields into cells: money and numbers read the site's way (`workflow-tables-numbers.md`), money → minor units with `round()`, a datetime's `T` → space, a checkbox `1`/hidden `0`, a record link `kind:id` or `kind #id`. What it cannot read goes to the store as typed, so the store's code answers.
- A date cell is a native `type="date"`/`"datetime-local"` input (`data-pg-date`/`data-pg-datetime`, the store takes ISO); `ws_wtable_screen_cell_to_form()` gives it `Y-m-d`/`Y-m-d\TH:i`; lists and board cards show dates in the site format (`ws_wtable_screen_date_text()`). Tests: `tests/workspace_wtable_screen_test.php`.

## How are columns and shares edited?
- Column options from the form (`ws_wtable_screen_options_from_form()`): decimals, currency, date time, link kinds (comma list), formula expression.
- Status choices one label a line, a label kept keeps its key and colour (`ws_wtable_screen_status_choices()`), so cells keep their choice; with the line count unchanged a label changed on its line is a rename and keeps the key; a new label never takes a key a current choice or a row holds (`ws_wtable_column_held_values()`). The type is chosen once (`type_fixed`).
- "Belongs to" drawer: the table's channel and the move (`workflow-table-channel.md`).
- Shares drawer: whoever reaches the table sees the list; whoever manages it shares with a team member (view/edit) or a public/private channel they read (view), and takes a share back. The `wtable_shared` bell line opens `workspace_wtables.php?scope=shared` (the inbox row names no table).

## Where are the other views?
- `?view=board`: cards by a status column (`workflow-table-board.md`). Import: `workflow-table-import.md`; Run from table: `workspace-flows-from-table.md`.

## What is left out?
- Pickers (a record link typed `kind:id`, shown `kind #id`; a person), saved views, calendar and gallery views: `workflow-tables-later.md`. The bin: `workflow-tables-bin-screens.md`.
