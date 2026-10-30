---
name: message-carried-values
description: Values a liveform message carries (records, panel input, service and database texts) go in through h(); the connect flash, upload values, logs and PHP-escaped warnings; what is still left
---

## Which values does a message escape?
- Every value a liveform message carries that the software did not write: stored titles and names, panel input, a remote service's answer, `erp_db_error()` / `mysqli_error()`. `h()` goes around the value where it enters the message call, never around a variable that also reaches a log, JSON, SQL or an e-mail. Rule and printers: page-messages-defuse.md.
- Done for the visitor-facing calls in `71d3f02d` and for the panel screens in #1288 (ERP), #1289 (products, orders, imports, content), #1290 (settings and system) and #1291 (Workspace); the custom form submit (`custom_form.php`), the membership entrance (`pg_member_activate()`), the page and marketplace settings in #1368. Each area has `tests/message_values_<area>_test.php` (`forms` for the first two): one line per escaped call with its exact count, so taking an escape back out fails the test.

## Which cases need care?
- The connect flash (`pg_connect_flash()`) is plain text, as `connect.php` writes it; the screen that shows it escapes it (`h($flash['text'])`). A writer that escapes would make a flash escaped twice when another screen takes it.
- A value read from an uploaded file (import lists, zip entry names) uses `htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`: plain `h()` returns '' for invalid UTF-8 and empties the whole list.
- A message and a log line built from the same sentence are built twice: `edit_product.php` logs the plain sentence and notices the escaped one.
- An exception that is a PHP warning (`RecursiveDirectoryIterator`) is already HTML-escaped by PHP when `html_errors` is on; `backups.php` escapes it only when it is off.
- A form field's label and the validation message written for it are text in every form's messages (`custom_form.php`, designer forms, cart and express order fields, the submitted-form screens), although a classic form prints the label as it is: a label typed with tags shows them in the message.

## Are Site Log lines HTML?
- No. `log_activity()` and `ws_change_log()` store plain text and every reader escapes it once: Site Log (`convert_text_to_html()`), the dashboard Activity card, Edit User's activity list; `GET /site-log` returns it raw. Pass names raw in a log sentence's `vars`. An explorer JSON `message` is plain too (the File/Catalog Manager toast sets it with `textContent`); the backup screen's error `message` stays escaped because it is appended as HTML.
- Symptom fixed: about twenty bin, restore, upload, Image Editor and catalog lines wrapped the name in `h()`, so "Çay & Kahve" showed as `Çay &amp; Kahve` there and in the group-created and quick-edit toasts (`tests/log_lines_plain_text_test.php`).

## What is still left?
- `submit_order.php` (claimed by #928), `edit_currency.php` (open PR #1341) and `view_contacts.php` (claimed by #723): their message calls get the same treatment once that work merges.
