---
name: print-order-id
description: print_order.php reads every order query with the one id it checked ($id from $_REQUEST, whole number), never $_GET['id'] again
---

## Which id does the print order screen read?
- `print_order.php` takes `$id` once: a scalar `$_REQUEST['id']` as a whole number, refused with "Record not found." (404) when no order has it.
- Every later query (order, items, ship tos, custom billing form, shipping tracking) uses that `$id`.

## What went wrong before?
- The check read `$_REQUEST['id']` and the queries `$_GET['id']`: an id sent in the form body passed the check, then the queries read nothing (undefined key warnings, an empty print). Fixed in the order bug hunt; test `tests/print_order_id_test.php`.
