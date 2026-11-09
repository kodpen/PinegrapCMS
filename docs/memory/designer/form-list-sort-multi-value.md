---
name: form-list-sort-multi-value
description: The form_list widget sorted by a check box or a multiple pick list sorts by the values joined in a subquery, so a record is listed once, not once per value (#7689)
module: widgets
---

## How does a form_list sort by a form field?
- `pg_sw_form_list_field_order($alias, $field_id, $type, $multiple, $collection_kind, $direction)` (`includes/fn/widgets.php`, pure), called by `$_resolve_order` of `_render_system_widget_form_list()` for both sort fields (`sort_data`, `sort_data2`).
- A check box, or a pick list with `form_fields.multiple = 1`: `(SELECT GROUP_CONCAT(data ORDER BY id SEPARATOR ', ') …)` and no join, as the classic list view sorts them (`get_form_list_view.php`).
- A collection's number field: `pg_coll_number_order_sql()` over a LEFT JOIN. Any other field: the joined value.

## Why?
- Those fields keep one `form_data` row per chosen value, and the listing query has no GROUP BY: a LEFT JOIN on the field listed a record with three ticked boxes three times, while the count query (no join) gave the right total, so pages held duplicates and pushed records off the list.

## Tests?
- `tests/form_list_sort_multi_value_test.php`.
