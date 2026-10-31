---
name: form-upload-beside-fields
description: Forms that carry a file beside typed fields (ERP expense, bank statement, ERP accounts import, Add/Edit Submitted Form): discarded body, the record in the action, the stated limit, refused uploads.
---

## What does a discarded body do on these screens?
- `pg_post_body_discarded()` is checked before `if ($_POST)`: the screen marks a message naming the request size and post_max_size (`erp_upload_discarded_message()`, `pg_submitted_form_discarded_message()`) and goes back to itself.
- Nothing typed survives (PHP dropped it), so the record must: the id rides in the form's `action` query string as well as the hidden field (`add_erp_expense.php?id=`, `edit_erp_expense.php?id=…&send_to=`, `add_submitted_form.php?page_id=`, `edit_submitted_form.php?id=…&send_to=`). Before this a lost body turned "Change the expense" into a blank "New expense" and Edit Expense into "The expense could not be found.".

## Which limit do the boxes state and enforce?
- `erp_upload_max_bytes($app_max)` (`includes/erp/expenses.php`): the lower of the box's own limit (`ERP_EXPENSE_FILE_MAX_BYTES`, `ERP_IMPORT_MAX_BYTES`, 5 MB for statements) and `pg_upload_limits()['file_max']`. Hints print it with `erp_upload_mb_text()`.
- `erp_upload_refusal($upload, $app_max)` names the file and the limit for an error code or a size over the cap; '' for an empty box, which each screen words itself.
- The accounts import box has `accept=".csv,.txt,text/csv"` (liveform's file field now passes `accept`).

## How is a submitted form's attachment refused?
- `pg_submitted_form_check_field()` marks a file upload field from its `$_FILES` error code (`pg_submitted_form_upload_refusal()`), so no forms row is written for an oversize file.
- `pg_submitted_form_store_upload()` returns 0 without copying or inserting when the file is not a whole upload or `copy()` fails; the screens write a form_data row with file_id 0, the same as an empty box. Before, `copy('')` threw ValueError on PHP 8 after the forms row was in (half-saved record), and on PHP 7 a size-0 files row pointed at nothing.

Related: [[upload-limits-and-empty-post]]
