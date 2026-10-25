---
name: workspace-change-text-limits
description: Text limits of the Workspace change catalogue must fit their columns (non-strict session cuts in silence): the six once over, how a longer value is treated, the test against the setup SQL
---

## Why does a text limit in ws_change_types() have to fit its column?
- Proposals, the record drawer, bulk and scheduled changes write a field into the column named in `ws_change_types()` (`includes/workspace/changes.php`). The session runs without strict mode, so MySQL cuts a value longer than a `varchar(N)` column to N characters without an error, while the decision card and the audit line show the whole text.
- The limit is in characters, as `varchar(N)` in utf8mb4 is. A TEXT-type column holds bytes; the test counts four per character.

## Which limits were wider than their columns, and what are they now?
- Group `short_description` and `keywords` took 2000 (columns `varchar(100)`, `varchar(255)`); page `meta_description` 1000 (`varchar(255)`); calendar event `name`, `short_description`, `location` 255, 1000, 255 (`varchar(100)`, `varchar(255)`, `varchar(100)`). No upgrade step ever changed these columns.
- Now each is its column's width: 100, 255, 255, 100, 255, 100. Every other text field already matched its column.

## What happens to a longer value?
- The same as before, only at the column's width: `ws_change_value()` cuts a `text`, `long` or `email` value to the limit, and refuses a `keywords` list (page search keywords) that joins longer than it. A `code` must have exactly its length.
- The drawer follows on its own: `ws_quick_edit_field_meta()` hands the limit on as `max_length` (input `maxLength` in `assets/js/workspace.js`), and a `long` field of 255 or less is drawn as one line, as the product's short description already was. Claude's `change_types` route (`ws_api_claude_change_types()`) and the AI bulk prompt read the same limit.

## What keeps them in line?
- `tests/workspace_change_limits_test.php` reads the catalogue from source (`pg_test_ws_change_types()`, `tests/helpers/source_reader.php`) and the tables from `data/backups/english_default/sql.sql` (checking `turkish_default` agrees), then asserts every text field with a column has a written-out limit no wider than that column. The six limits above are also checked by value.
- Tables an upgrade step creates (`erp_accounts`, `ws_channels`, `ws_tasks`, `ws_task_notes`, `ws_notes`) are not in the setup SQL and are listed in the test with the reason, as are columns the setup SQL lacks (`products.vat_exemption_code`, `contacts.tax_number`, `contacts.tax_office`, `orders.notes`, `files.content`). A field on one of them is checked by hand against its upgrade step; the test fails when a listed entry turns up in the setup SQL or is no longer written.
- Widening a column instead is a schema step (owner's call); the limit then moves with it in the same change. The external API's limits follow the same rule: `api/api-text-field-limits.md`.
