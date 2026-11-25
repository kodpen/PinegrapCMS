---
name: submitted-forms-export-columns
description: The Forms screen's CSV and Excel export prints each record's field values against one heading list, sort order then id (#11453)
module: submitted_form_list
---

## How are the field columns of the export built?
- `pg_submitted_form_export_fields($page_id)` reads the form's fields once (no information block, no upload), `ORDER BY sort_order, id`; the headings and every record's cells (`pg_submitted_form_export_row()`, values of a check box group joined with ', ') follow that list (`view_submitted_forms.php`).
- Before #11453 the headings and each record's values were two queries ordered by `sort_order` alone. Fields of equal sort order, such as a signature row a designer save keeps while it renumbers the others, could come back in another order and put a value under another field's heading.

## Which form is exported?
- One: the Custom Form filter, or the single checked form of the advanced filters. `export_forms()` in the browser refuses [All] and more than one checked form.
