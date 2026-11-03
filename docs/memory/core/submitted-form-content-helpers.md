---
name: submitted-form-content-helpers
description: pg_submitted_form_page() fills the page row keys of submitted billing/shipping forms; pg_form_reference_title_row() escapes the reference code title row; faults fixed.
module: content
---

## How do the submitted-form functions read the form's page row?
- `get_submitted_form_content_with_form_fields()` and `get_form_review_info()` (`includes/fn/content.php`) wrap each page `db_item()` in `pg_submitted_form_page()`, which always returns `id`, `type`, `form_name` and `form_label_column_width` ('' when absent or NULL).
- Why: the express order shipping form row selects only `page_id AS id` (that form has no name or label width of its own), yet both functions then read `form_name` (and the first `form_label_column_width`): an undefined-key warning on every order preview, receipt and view-order screen of such an order under PHP 8. A missing row (null) is handled the same way.

## How is the row under a product form's reference code field printed?
- `get_form_info()` prints it with `pg_form_reference_title_row()`: the label of the submitted form's title field (`rss_field = 'title'`) and the title, both through `h()`. No title, no row.
- Before, the label was printed raw while every other label is escaped, so a label with `&` or `<` broke the row's markup.

## Where is it tested?
- `tests/content_submitted_form_test.php`.
