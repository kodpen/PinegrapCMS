---
name: form-list-view-page-order
description: A classic form list view's page order ends on the record id, so records sharing a sort value are neither shown twice nor skipped between pages
module: forms
---

## How is a classic form list view paged?
- `get_form_list_view.php` reads one page with `LIMIT start, count` over the view's up to three order-by fields. `pg_form_list_view_order_sql()` (`includes/fn/forms.php`) appends `forms.id ASC` to that ORDER BY, and without an order-by field the page is `ORDER BY forms.id ASC` (submission order). Tested in `tests/form_list_view_order_test.php`.

## What did it fix?
- Symptom: records sharing the sort value (one category, one pick-list value, one submit second) had no fixed order, so MySQL could return them in another order for the next page; a visitor paging through saw one record twice and never saw another. A view without an order-by field had no ORDER BY at all.
- `random` still shuffles each page on its own; the id after `RAND()` changes nothing there.

## Which other lists already end on an id?
- The designer's form list widget (`_render_system_widget_form_list()`, `forms.id`), the catalog widget (`products.id`), the panel list driver (`pg_list_table_*`) and the external API cursors (`api_cursor_encode()` value + id).
