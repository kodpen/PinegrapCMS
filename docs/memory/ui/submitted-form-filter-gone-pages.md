---
name: submitted-form-filter-gone-pages
description: Forms screen Custom Form filter: an entry for submitted forms whose page is deleted or no longer a form page, [All] counted from the entries, one grouped count query
module: submitted_form_list
---

## Why did "[All] (58)" sit over 407 rows?
- The filter's options came from `pg_submitted_form_list_custom_forms()` (form pages only: `pg_form_page_sql()`), each counted with its own query, and [All] was their sum. The list (`pg_submitted_form_list_source()`) shows every row of `forms` in the user's reach, so rows of other pages were listed but could not be picked, exported or deleted by form (#9027).
- Such rows come mostly from designed forms: their page is of the standard type, and emptying the Recycle Bin deletes it (`pg_delete_page_record()` refuses only a `custom form` page with submissions) while its submissions and its `custom_form_pages` row stay. A page whose design no longer draws a form also leaves `pg_form_page_sql()`.

## What does the filter offer now?
- `pg_submitted_form_list_filter_forms($user)` answers the custom forms, then one entry per other page id found in `forms` (`pg_submitted_form_list_gone_forms()`): the form name (else the page name, else "Custom Form") with ` [PAGE DELETED]` or ` [NOT A FORM PAGE]`, ` (#id)` when the name is taken (`pg_submitted_form_list_gone_labels()`, pure). A manager or above sees them all; anybody else only those on a page in a folder they may edit, as the list's folder rule has it.
- The numbers come from one `SELECT page_id, COUNT(*) FROM forms GROUP BY page_id` (`pg_submitted_form_list_counts()`); [All] is `pg_submitted_form_list_total()` of the entries, so it equals the rows of [All] with no date limit. The advanced filters' boxes use the same entries and counts.
- The screen and the rows action both settle the remembered form with `pg_submitted_form_list_defaults()` on this list, so a picked gone entry stays picked while more rows load. Create does not deep link to a gone entry.
- Add Submitted Form, Import Submitted Forms and `select_custom_form()` keep the form pages only: a gone page takes no new submissions.
- Tested in `tests/submitted_form_list_test.php`. Related: `form-names-distinct.md`.
