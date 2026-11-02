---
name: submitted-form-import-export
description: How Import Submitted Forms reads an exported file (fixed record columns, status, one column per field), converts date cells and checks the chosen custom form
module: submitted_form_list
---

## Which columns of an imported file fill fields?
- The export (`view_submitted_forms.php`) writes the record's own columns first, `pg_submitted_form_export_columns()` (form_name, reference_code, status, … form_editor, then quiz_score for a quiz), then one column per field by `form_fields.name`.
- Many template forms have a field named `status`, so an exported file had two `status` headings and the import put both into the field: 'Incomplete' next to the real value (#5590).
- `pg_submitted_form_import_fixed_columns($columns)` counts the leading headings when the file starts with all fixed columns in the export's order (case aside); those columns fill no field. A hand-made file without them maps every heading as before.
- The fixed status cell sets `forms.complete`: `pg_submitted_form_import_complete()` is '0' for "Incomplete" in English or the panel language, else '1'. Before, every imported record was complete.
- One column fills a field: a later heading of an already mapped field is left out and named in a warning ("These columns name a field an earlier column already fills…").

## How are date, date and time, and time cells stored?
- `pg_submitted_form_import_value($value, $type)` (`includes/fn/submitted_form_list.php`) returns the stored form the add screen writes: `Y-m-d`, `Y-m-d H:i`, `H:i`. It reads the export's ISO form, the site's day/month order and the dotted Turkish date (`pg_date_input_iso()`), a 12- or 24-hour time (`validate_time()`).
- A value that is no real date or time ('31.02.2026', text) is null: the field is left empty and the line is named in the import's problem list (`$file_problems`, `pg_csv_problem_list()`).
- Before, the cell was written as it came with `type='date'`; `prepare_form_data_for_output()` split '15/12/1990' by '-' and the edit screen showed `//15/12/1990` with two PHP warnings (#5589).

## How is the chosen custom form checked?
- The page id must be a custom form page (`pg_form_page_sql('page')`), as on Add Submitted Form. A missing one marks "The selected custom form could not be found…" on the picker; before, a null row passed `check_edit_access()` for a manager and the screen said no valid field name was found (#5594).
- The file box takes `accept=".csv,text/csv,text/plain"`.
- Tested in `tests/submitted_form_list_test.php`.
