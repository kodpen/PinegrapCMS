---
name: workflow-table-starters
description: Starters on the New table card (T6c, #3044): contact pipeline and checklist live in code (ws_wtable_starters()), made through the store's functions with optional sample rows; no store, no schema
---

## Where are starters kept, and why not in ws_templates?
- In code: `includes/workspace/wtable_starters.php`, `ws_wtable_starters()` (key => name, description, columns, rows). `ws_templates` holds channel templates (tasks, notes, summary) with their own body format, so it was not reused; the built-in channel templates live in code the same way (`ws_templates_builtin()`). No store, no schema.
- `contacts` (Contact pipeline): Name, Company, E-mail, Phone (text), Stage (status Lead/Contacted/Proposal/Won/Lost, keys `s1…`), Value (money, 2 decimals, site currency), Owner (person), Next step (date). `checklist`: Item, Done (checkbox), Who (person), Due (date), Notes.
- Names and labels go through `lang()`, so a table is made in the language of whoever makes it.

## How is a table made from one?
- New table card: "Start from" (An empty table / each starter) and "Add sample rows to a starter" (checked). The name and description, left empty, are the starter's.
- `ws_wtable_starter_create()`: `ws_wtable_create()`, `ws_wtable_column_create()` per column (a refusal deletes the table), then sample rows through `ws_wtable_import_rows()` (each `ws_wtable_row_create()`, not announced). A sample value: text as a file holds it, `array('days' => N)` a date N days from today, `'me'` the maker's user id (`ws_wtable_starter_value()`).
- Tests: `tests/workspace_wtable_starters_test.php` (options pass the store's check, rows fit and become valid cells).
