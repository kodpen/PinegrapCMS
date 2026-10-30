---
name: csv-import-feedback
description: Contacts and Users CSV import: separator from the header line, a file with no known column refused, blank rows passed over, rows named 'Line n: reason'
---

## Which separator do the contacts and users imports read?
- `pg_csv_read_separator($handle)` (`includes/fn/core.php`) reads the header line of a `pg_csv_open()` handle and seeks back to where it started: comma, semicolon or tab, whichever the line holds most (`pg_csv_separator()`, the rule of `pg_key_code_import_separator()`). Excel in Turkish writes `;`. Before, both read `,` only, so a `;` file's header was one unknown cell.

## What does Import Contacts refuse or name?
- A header with no recognised column is refused (`output_error()`, Go back, the headings found). Before, every non-blank row ran `INSERT INTO contacts (user, timestamp)`: one empty contact per row, added to the checked groups, "N contacts were imported".
- Unknown headings are named in a warning on View Contacts.
- Named per row ('Line n: reason', the line the row starts on, quoted line breaks counted): an invalid e-mail address blanked (the contact is still imported), a unique-mode row whose address is already on file (not imported). Headings are still the English aliases of `convert_column_name()`.

## What does Import Users refuse or name?
- A file without an e-mail column is refused with that reason and the headings found.
- Blank rows (empty, or separators only) are passed over. A row with values but no e-mail address still refuses the file, now with its line in the error list.
- A second row of the file with the same e-mail address or user name passes the database pre-check and is skipped at insert time; it is named in a warning on View Users.

## How is the list capped?
- `pg_csv_problem_list($problems)` turns `array(line, reason)` pairs into texts: the first 20, then 'and n more line(s) that could not be used.'. Not escaped: callers `h()` each line. Also `pg_csv_row_lines()`, `pg_csv_row_blank()`, `pg_csv_headings_text()`.
- Tests: `tests/csv_import_feedback_test.php`. Encoding (Windows-1254 files) is not handled.
