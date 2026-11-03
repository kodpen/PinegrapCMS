---
name: classic-order-pages-language
description: The classic order view, receipt and preview write every label through lang(): billing Phone/Fax/PO Number, shipping method and arrival date, the order view's headings and totals (PR #5202)
---

## Which labels of the classic order pages go through lang()?
- All of them. `get_order_receipt.php`, `get_order_preview.php` and `get_view_order_screen_content.php` (My Account order view) write `h(lang('Label')) . ':'`, the same as `get_order_receipt_in_plain_text.php`.
- Fixed in PR #5202: the billing block's Phone, Fax and PO Number, the shipping row's "Shipping Method" and "Requested Arrival Date", and in the order view the payment schedule, gift card fields, table headings, totals rows, Recurring Charges, Applied Gift Cards and Back were bare English literals, so a Turkish shop showed them in English.

## How is it kept?
- `tests/order_pages_lang_test.php` scans the three files (comments blanked, `lang('...')` keys folded) for the old literals. A new label goes through `lang()` with its key added by `php tools/tr_add.php`.
