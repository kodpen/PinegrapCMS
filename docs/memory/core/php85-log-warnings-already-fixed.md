---
name: php85-log-warnings-already-fixed
description: Four files of the owner's PHP 8.5 error log (duplicate_products, get_file, view_order, designer_ai) whose warnings were already fixed on development between 4 and 13 November 2026, with the PR of each
---

## Which warnings from the owner's PHP 8.5 log were already gone when they were looked at?
- The log covers 15 October to 13 November 2026, and the owner's checkout was not always up to date. Four of the files it named most often were already fixed on `development` (checked 2026-11-15). Their code was not changed again.
- `duplicate_products.php`, about 5,000 "Trying to access array offset on value of type null": the screen's own CSV export read `$product_submit_form_fields[$product['id']]['create'][$field]` for every product, and a product with no submit-form fields has no entry there. That gave one warning per product per field. #6526 (4 November) deleted that export: `?submit_data=Export Products` now redirects to the Products screen's export, which does not have that read.
- `get_file.php`, "Constant USER_… already defined, this will be an error in PHP 9": a private channel's file reached `initialize_user()` twice. Fixed by #7724 (7 November, `platform/get-file-user-once.md`).
- `view_order.php`, undefined variable: `$output_shipment_labels` and `$output_affiliate_name` were never set for an incomplete order with an address, or for an order whose affiliate is gone. #6512 (4 November) sets both. The log did not say which variable it was.
- `includes/designer_ai.php`, "Array to string conversion": an Apply operation whose `add_classes` / `remove_classes` came as a JSON list was cast to the word "Array". Since #9093 (13 November), `pg_design_ai_op_fields()` joins a list into words and refuses any other field that is not a string.

## How is a log warning checked against development when its line numbers are old?
- `error-reporting.md` holds the recipe: the checkout's reflog, `git log --since`, PHPStan's false alarms. In `view_order.php` every PHPStan level 1 hit was a variable set and read under the same condition, not the logged warning.

## Did the full log show these files again?
- No. The census of the log up to 17 November 2026 found no shape of these four files that the fixes above had not removed.
