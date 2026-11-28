---
name: import-collection-csv
description: Webflow and Wix collection CSV import (2026.11.0, kinds webflow and wix): one shared reader, a mapping row per column guessed by name, drafts, dates, Wix media, Webflow's old address typed
module: importers
---

## Where is it?
- `includes/import/collection.php` (PR #12134), shared by `webflow.php` (whose products and redirects files are `import-webflow.md`) and `wix.php`. Cells go to `write_record.php` (`import-records.md`).
- Tests: `tests/import_collection_test.php` on `tests/fixtures/import/webflow_collection.csv` and `wix_collection.csv`, built from the columns the help pages name (`import-sources.md`), not from a real export.

## How is the file told?
- `pg_import_detect_kind()`: a header with `Item ID` and `Slug` is webflow; `ID` with `Owner`, or with `Created Date` and `Updated Date`, is wix. A Wix file exported without its system fields is not recognised.

## What does the mapping ask?
- `form` (required), `file_folder`, for Webflow `address_base` (a `text` row), then `col_<n>` per header column (`n` its place), a `form_field` row with `guess` = the heading. Left out: Webflow `Collection ID`, `Locale ID`, `Item ID`; Wix `ID`, `Owner`.
- Field types offered: text box, text area, e-mail address, date, date and time, file upload, check box, pick list, radio button (`pg_import_collection_field_types()`).

## What does an item hold?
- A record; key `webflow:item:<Item ID>` / `wix:item:<ID>`, else `<service>:line:<n>`. Title `Name` (Webflow) or `Title` (Wix), else the slug, else "Line n" (only the dry run shows it; the record's title comes from the paired column).
- `data.columns` the non-empty cells; `status` draft when Webflow's `Draft` or `Archived` is true (an incomplete record); `time` from `Published On`, else `Created On` (Webflow, JavaScript date text with its "(zone name)" dropped) or `Created Date` (Wix, ISO 8601).
- Wix `wix:image://v1/<id>/…` becomes `https://static.wixstatic.com/media/<id>` in every cell (`pg_import_collection_wix_media()`).

## Which old address does an item get?
- Webflow: `/<address_base>/<Slug>` (`pg_import_redirect_collection_address()`; a whole address typed is cut to its path). The file does not hold the collection's address, so the person types it; left empty, no redirect. Wix's file has none.

## How does a cell go into its field?
- `pg_import_record_column_rows()` (pure, `write_record.php`). Rich text area: HTML through `pg_import_media_localize()` and the staff cleaner, a plain cell as an escaped paragraph; text area/box: plain text; e-mail and date: the value or a note; file upload: the cell's first web address fetched (`pg_import_media_fetch()`), a failure a note.
- A date field keeps the day the cell names, read in the cell's own zone (`pg_import_collection_date()`): Webflow and Wix write a date as midnight UTC, the day before west of UTC (#12247). A date and time is shown in the site zone.
- Check box, pick list, radio: names from a JSON list, else cut at `;`, else `,` (a one-choice field reads the whole cell), matched like categories; a lone check box takes `true`/`yes`. A reference column stays text: pair it with a text field.
