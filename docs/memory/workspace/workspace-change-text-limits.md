---
name: workspace-change-text-limits
description: Workspace change catalogue text limits must fit their columns (non-strict cuts in silence): the six once over, the channel summary kept, longer values, line breaks in one-line drawer fields, the test
---

## Why does a text limit in ws_change_types() have to fit its column?
- Proposals, the record drawer, bulk and scheduled changes write a field into the column named in `ws_change_types()` (`includes/workspace/changes.php`). The session runs without strict mode, so MySQL cuts a value longer than a `varchar(N)` column to N characters without an error, while the decision card and the audit line show the whole text.
- The limit is in characters, as `varchar(N)` in utf8mb4 is. A TEXT-type column holds bytes; the test counts four per character.

## Which limits were wider than their columns, and what are they now?
- Group `short_description` and `keywords` took 2000 (columns `varchar(100)`, `varchar(255)`); page `meta_description` 1000 (`varchar(255)`); calendar event `name`, `short_description`, `location` 255, 1000, 255 (`varchar(100)`, `varchar(255)`, `varchar(100)`). No upgrade step ever changed these columns.
- Now each is its column's width: 100, 255, 255, 100, 255, 100. Every other text field fits its column, but one (next question).

## Which limit is still wider, and why is it left?
- Channel `summary`: 20000 characters, as the summary tab keeps (`ws_channel_set_summary()`, `includes/workspace/channels.php`), in `ws_channels.summary` TEXT (65535 bytes, 2026.4.4): 16383 at four bytes a character. 20000 characters pass 65535 bytes only with many four-byte ones (emoji); three-byte text (60000 bytes) still fits.
- The limit predates the fix and is kept. Lowering it means both places together; a wider column is a schema step (owner's call).

## What happens to a longer value?
- The same as before, only at the column's width: `ws_change_value()` cuts a `text`, `long` or `email` value to the limit, and refuses a `keywords` list (page search keywords) that joins longer than it. A `code` must have exactly its length.
- The drawer follows on its own: `ws_quick_edit_field_meta()` hands the limit on as `max_length` (input `maxLength` in `assets/js/workspace.js`). Claude's `change_types` route (`ws_api_claude_change_types()`) and the AI bulk prompt read the same limit.

## How does the drawer draw a long field of 255 or less?
- As one line, as the product's short description already was, unless its value holds a line break (`saValueInput()`): then a textarea. The API and proposals store a `long` value's line breaks; a one-line input dropped them, so saving any other field of a group or an event sent its short description back without them, and the toast and decision named a change nobody made.

## What keeps them in line?
- `tests/workspace_change_limits_test.php` reads the catalogue from source (`pg_test_ws_change_types()`, `tests/helpers/source_reader.php`) and the tables from `data/backups/english_default/sql.sql` (checking `turkish_default` agrees), then asserts every text field with a column has a written-out limit no wider than that column. The six limits above are also checked by value.
- Tables an upgrade step creates (`erp_accounts`, `ws_channels`, `ws_tasks`, `ws_task_notes`, `ws_notes`) are not in the setup SQL: their text columns' widths are written out from the step (`workspace_change_limits_test_upgrade_columns()`), and the channel summary is listed as the one wider on purpose. Columns the setup SQL lacks (`products.vat_exemption_code`, `contacts.tax_number`, `contacts.tax_office`, `orders.notes`, `files.content`) are listed with the reason. A new field, or a stale entry, fails the test.
- It also runs `saValueInput()` under node (skipped without node) for the line-break rule.
- Widening a column instead is a schema step (owner's call); the limit then moves with it in the same change. The external API's limits follow the same rule: `api/api-text-field-limits.md`.
