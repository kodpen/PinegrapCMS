---
name: collection-rows-list
description: A record's rows listed on its record page: the dynamic value "Rows of the shown record" (shown record rows) and the record view's "Rows of this record" button
module: collection_rows
---

## What does "Rows of the shown record" list?
- A form list filter on `reference_code` with "is equal to", value empty, `dynamic_value: 'shown record rows'`, `dynamic_value_attribute`: the name of a rows field of the shown record's form.
- `pg_sw_form_list_filter_sql()` reads the code of the shown record (`pg_coll_shown_record_code()`), then `pg_coll_rows_shown_value()`: the field's value on the complete record with that code, only when that field is a rows text box whose child form is the list's form. `pg_coll_rows_codes()` splits it.
- `pg_coll_rows_list_sql()` (pure) gives `forms.reference_code IN (…)` and `FIELD(forms.reference_code, …) ASC`, returned as the filter's `order`; `_render_system_widget_form_list()` puts it before the list's own sort, so the rows stand in the field's order.

## What when something is missing?
- No shown record, no rows field named, no such record or field, an empty field: the list shows no record (not "left out", unlike `shown record`).
- A code that names no record matches nothing; the others keep their order. A malformed code or a repeat is dropped by `pg_coll_rows_codes()`.
- On another field or operator it is counted in `invalid`, so the list closes. A fixed value wins over it. Without an address (sitemap, record view) it is counted in `address`, so a record view closes. `pg_record_file_filter_sql()` (the twin in `includes/record_files.php`) treats it as the other address values.

## What does the editor offer?
- `_swFilterDynamicOptions(type, withUrl, field)` offers "Rows of the shown record" on a list's Reference Code only; the row shows a text input for the rows field's name (`.sd-sw-filter-rows`, saved as the attribute) and a warning while it is empty.
- The Form Item View panel shows "Rows of this record" with one "Add the list of …" button per rows field of its form: `_swAddRowsList()` creates a Form List View widget (`_swRowsListConfig()`: the child form, the filter above) with the list's starter layout and places it right after the selected record view.

Tests: `tests/collections_rows_list_test.php`; the twin in `tests/collections_lists_test.php`.
