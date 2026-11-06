---
name: theme-csv-import
description: Create Theme system theme CSV import (add_theme_file.php): heading and rows checked before the file record is written, quoted rule columns, rollback of a refused rule insert
---

## What does the import check before it writes?
- The heading must be the eight columns the export writes (`area,row,col,module,region_type,region_name,property,value`), each once, any order and letter case, spaces around a name ignored: `pg_theme_csv_import_columns()` in `includes/forms/theme_file_form.php` returns the INSERT column list built from the known names, or null (refused as "not a valid System Theme CSV file").
- The data rows are read next; blank lines are skipped and a file with no rule rows is refused as empty. Only then is the `files` row inserted, then the rules.

## Why?
- The import used to insert the theme's `files` row first and build the rule column list from the raw heading cells. `row` unquoted is a syntax error on MySQL 8 (reserved word), and a heading-only file gave an INSERT with no values: the rule insert failed with "Query failed.", the `files` row stayed (no rules, no file on disk), and its name then refused every retry as "already exists".
- A rule insert that still fails now deletes the rules and the `files` row before the error.

## Which test covers it?
- `tests/theme_csv_import_test.php` (the helper, and source order in the screen).
