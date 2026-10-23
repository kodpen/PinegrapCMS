---
name: designer-form-uploads-for-readers
description: Designed-form uploads open for visitors when a public designed record view or list shows the record and prints the field (get_file.php, includes/record_files.php); what keeps them closed
---

## What was wrong?
- `get_file.php` opened a form upload (`files.attachment = 1`) only with folder edit access, Manage Forms, or a classic layout printing `^^field^^`; designed widgets were never asked, so covers and staff photos were closed to readers (302, 403, `no-image.svg`, closed og:image / RSS enclosure).

## When does the file open now?
- After the classic checks refuse it, `pg_record_file_designed_access($form_page, $record, $field)` (`includes/record_files.php`) grants it when one `form_item_view` or `form_list_view` widget (older list names too) of the record's form:
  - is public (`pg_sw_form_widget_public()`: public access; a list without viewer filter),
  - prints the field: a text/html/src/alt/href/value binding or `^^token^^` by name, name without hyphens, or label (not a slugified label); a list only inside its first `loop_area` (`pg_record_file_widget_binds()`: its surround is drawn once with no record), a record view or a list without `loop_area` anywhere,
  - sits on a page (its own tree, else its style's) outside the recycle bin and the Drafts folder that the visitor may view (`check_view_access()`, as the classic branch),
  - lets the record through its filters: a record view its own, strictly (incomplete records too, as `?r=` shows them); a list its detail page's first record view of the form first, then with its own, address filters left out, only `forms.complete = 1`.
- Decision: `pg_record_file_view_grants()` over gathered facts. Order: widgets, bindings, then pages: the lists' detail pages and the home page by id, one tree scan (one LIKE per widget) only for widgets not found there on an open page. Filters (memoized) are asked only for a widget on an open page, so refusal time never tells whether a record on a closed page passes. First grant ends it.
- A file opened this way is sent `Cache-Control: private`: it depends on the visitor and on the record's current state, so no shared cache may keep it.

## What keeps it closed?
- Filtered-out records, incomplete ones under a list, a closed filter set (missing field, unknown operator, 51st row, address filter on a record view), a private widget, a viewer filtered list, a binned, draft or unviewable page, a field the widget does not print (a list's surround only).
- Filters only `functions.php` builds: a standard field, the viewer or their e-mail, a date in the site's format.
- A widget inside another shared component is not found (`pg_sw_page_widgets()` does not look there either).
- A private upload folder stays closed (its check runs first).
- Comment attachments are out of scope.

## Why a separate file and not widgets.php?
- `get_file.php` never loads `functions.php` (own `db()`, `output_error()`). So `includes/record_files.php` (gate `PG_FUNCTIONS_DIR` or `PG_GET_FILE`) carries copies of `pg_sw_form_widget_public()`, `prepare_sql_operation()`, the custom-field part of `pg_sw_form_list_filter_sql()`, the page-tree walk and `_split_widget_tree()`'s loop search. Contract: itself, `escape()`, `check_view_access()`, `pg_auth_column_exists()`, PHP; queries via `pg_record_file_rows()` (a failed query keeps it closed).
- `get_file.php` loads it only on the refusal path of a submitted-form attachment.
- A filter the copy cannot build closes, never opens. Changing an original means changing its copy: `tests/record_files_test.php` compares them.

## Is it the same as the page?
- As in the classic rule, the file opens by name while the page needs `?r=`: a public record view of a private form opens its uploads to anyone knowing the name. Keep such uploads in a private folder.

## Where are the tests?
- `tests/record_files_test.php` (decision, binding, list surround, filter SQL, fail-closed rows, copies, contract, call site). Live: published 200, draft 302, list-surround-only field 302.
