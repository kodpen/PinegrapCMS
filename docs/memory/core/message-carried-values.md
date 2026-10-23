---
name: message-carried-values
description: Values a liveform message carries (records, panel input, service and database texts) go in through h(); the connect flash, upload values, logs and PHP-escaped warnings; what is still left
---

## Which values does a message escape?
- Every value a liveform message carries that the software did not write: stored titles and names, panel input, a remote service's answer, `erp_db_error()` / `mysqli_error()`. `h()` goes around the value where it enters the message call, never around a variable that also reaches a log, JSON, SQL or an e-mail. Rule and printers: page-messages-defuse.md.
- Done for the visitor-facing calls in `71d3f02d` and for the panel screens in #1288 (ERP), #1289 (products, orders, imports, content), #1290 (settings and system) and #1291 (Workspace). Each area has `tests/message_values_<area>_test.php`: one line per escaped call with its exact count, so taking an escape back out fails the test.

## Which cases need care?
- The connect flash (`pg_connect_flash()`) is plain text, as `connect.php` writes it; the screen that shows it escapes it (`h($flash['text'])`). A writer that escapes would make a flash escaped twice when another screen takes it.
- A value read from an uploaded file (import lists, zip entry names) uses `htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`: plain `h()` returns '' for invalid UTF-8 and empties the whole list.
- A message and a log line built from the same sentence are built twice: `edit_product.php` logs the plain sentence and notices the escaped one.
- An exception that is a PHP warning (`RecursiveDirectoryIterator`) is already HTML-escaped by PHP when `html_errors` is on; `backups.php` escapes it only when it is off.

## What is still left?
- Files held by open PRs when the panel PRs went up (claim #1285): `custom_form.php`, `submit_order.php`, `marketplace_settings.php`, `edit_page.php`, `edit_currency.php`, `includes/fn/auth.php`, `view_contacts.php`. Their message calls get the same treatment once those PRs merge.
