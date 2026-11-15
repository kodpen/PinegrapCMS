---
name: php85-log-warnings-already-fixed
description: Four files of the owner's PHP 8.5 error log (duplicate_products, get_file, view_order, designer_ai) whose warnings were already fixed on development between 4 and 13 November 2026, with the PR of each
---

## Which warnings from the owner's PHP 8.5 log were already gone when they were looked at?
- The log covers 15 October to 13 November 2026, and the owner's checkout was not always up to date. Four of the files it named most often were already fixed on `development` (checked 2026-11-15). Their code was not changed again.
- `duplicate_products.php`, about 5,000 "Trying to access array offset on value of type null": the screen's own CSV export read `$product_submit_form_fields[$product['id']]['create'][$field]` for every product, and a product with no submit-form fields has no entry there. That gave one warning per product per field. #6526 (4 November) deleted that export: `?submit_data=Export Products` now redirects to the Products screen's export, which does not have that read.
- `get_file.php`, "Constant USER_… already defined, this will be an error in PHP 9": a private channel's file reached `initialize_user()` twice. Fixed by #7724 (7 November, `platform/get-file-user-once.md`).
- `view_order.php`, undefined variable: `$output_shipment_labels` and `$output_affiliate_name` were never set for an incomplete order with an address, or for an order whose affiliate is gone. #6512 (4 November) sets both. The log did not say which variable it was, so a different one may still show up in the census.
- `includes/designer_ai.php`, "Array to string conversion": an Apply operation whose `add_classes` / `remove_classes` came as a JSON list was cast to the word "Array". Since #9093 (13 November), `pg_design_ai_op_fields()` joins a list into words and refuses any other field that is not a string.

## How do I check a log warning against development when the log's line numbers are old?
- Read the file's history from the log's date onwards: `git log --since=<date> origin/development -- <file>`. A cloud clone is shallow, so run `git fetch --deepen=3000 origin development` first. Then read the code as it was before the fix (`git show <commit>^:<path>`).
- PHPStan at level 1 (`variable.undefined`, "might not be defined") also flags variables that are set and read under the same condition. In `view_order.php` all its hits were of that kind. Do not treat them as the log's warning without a path that actually reaches the read.

## What is left for the census?
- Any line from these four files dated after the fix above. If one shows up, that path is still live and belongs in the census (EL1).
