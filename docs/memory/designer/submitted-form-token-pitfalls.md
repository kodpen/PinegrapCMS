---
name: submitted-form-token-pitfalls
description: Pitfalls of submitted-form tokens in system widgets: single escape, single-pass replace, truncate before escape, submitted_form_info counters per page and who refreshes them on designed pages.
---

## When is a value escaped in token replacement?
- **Once**, inside `pg_sw_apply_tokens()` (`prepare_form_data_for_output()`). Escaping during indexing turns `&` into `&amp;amp;`.
- Truncating the comment preview and turning comment attachments into links happen **before** escape; truncating escaped text cuts `&amp;` in the middle (a bug of the classic screen).

## How must replacement run?
- **One pass per token** (`preg_replace(..., 1)`): the same name may appear with two different formats; a global replace would overwrite the second with the first's output.

## Which page do `submitted_form_info` counters belong to?
- Counters are kept **per form item view page**. In a list the widget's configured detail page is read; in detail view the page itself (`_pg_sw_current_page_id()`).
- If no detail page is selected there is no row: the token prints 0 (`pg_sw_apply_tokens()`), while a filter reads it as '' (`form-list-filters-fail-closed.md`, unanswered records).
- Designed detail pages write the view counters too (`record-view-counts.md`); the page prints the count before the current view.
- On a designed record page only `add_comment.php` and `job.php` refresh the comment fields of the row. `edit_comment.php` (a held reply approved, a comment deleted), the API and `submit_order.php` refresh them only on classic pages until step 3 of the designable-comments plan, so the comment count, newest comment and the newest-activity order lag there; that step's PR deletes this line.

Related: docs/memory/designer/submitted-form-system-fields.md
