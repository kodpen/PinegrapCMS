---
name: gift-card-screens-store-credit
description: Gift card screens: store credit shown with its owner and kind, no Delete for store credit, credit balance edits audited, code search without dashes, amount and quantity box sizes
---

## How do the gift card screens show store credit?
- A card with `gift_cards.user_id` > 0 is store credit (`store-credit-model.md`). `edit_gift_card.php` reads `user_id`, `kind`, `reason`, `issued_by`, `expires_at` and the owner's username and role (aliases `credit_*`, only when `pg_credit_ready()`; before the upgrade `0 AS credit_user_id`).
- The status column then draws a Store Credit card (`pg_credit_staff_gift_card_info_html()`, `includes/fn/store_credit_staff.php`): Customer (linked to `edit_user.php#store_credit` when `pg_can_open_user()`, `#<id>` when the account is gone), Source (kind label), Reason, Last day. A credit card with no order shows it instead of "This gift card was created manually"; one with an order shows both.
- `view_gift_cards.php` prints the word "Store credit" (`pg_list_status_word()`) under the code of such a card.
- Symptom fixed: a customer's credit looked like an anonymous hand-made gift card.

## What does a balance edit of store credit write?
- After the balance is written (both the guarded and the old-form path), `pg_audit('update', 'store_credit', id, before, after, pg_credit_audit_label(code))` with `pg_credit_audit_fields()`; plain gift cards write no audit line. Every other credit change was already audited.

## Is Delete on a store credit card allowed?
- No (owner, #6688, `decisions-made-product-27.md`). `pg_credit_staff_gift_card_deletable()` is false for `user_id` > 0: the screen drops the Delete button and prints `pg_credit_staff_gift_card_delete_note_html()` under the button bar (void it on the customer's screen, a link to `edit_user.php?id=<user_id>#store_credit` when `pg_can_open_user()` and the account exists). A posted `submit_delete` for such a card gets a form error and nothing is deleted. Ordinary cards (`user_id` = 0) are deleted as before with a Site Log line.
- Why: the delete dropped the row with no `pg_audit()` line and broke `applied_gift_cards`, the customer's list and the audit link; `pg_credit_void()` (unchanged) keeps the row and audits.
- The external API's `DELETE /gift-cards/{id}` (`includes/api/resources/gift_cards.php`) is not covered by this rule.

## How does the list search find a code typed without dashes?
- The driver searches `row.textContent`; the code cell prints the dashed code, so the stored code (`4059436642850475`) sits beside it in `<span hidden>`. Hidden text stays out of quick edit field names: `rowNameText()` in `pg_list_table_edit.js` skips `hidden` and `aria-hidden` nodes and puts a space around block children (the notes field used to be named "Notes: 6579-…-5163Expired").

## How large are the amount, balance and quantity boxes?
- Amount (create) and Balance (edit) have `maxlength="13"`: the money mask counts grouping and decimals, so `42.949.672,95` (`PG_GIFT_CARD_AMOUNT_MAX`) fits. At 12 the box stopped at 9.999.999,99 and `40000000` made a card of 4.000.000,00 without a word (same fault as `product-price-box-top.md`).
- Quantity: `maxlength` 4 with the 1,000 limit, 9 (what `pg_gift_card_form_quantity()` reads) in the `limit=false` mode, where 12000 used to be cut to 1200.

## Which tests cover it?
- `tests/gift_card_form_test.php` (box lengths, save bar icons), `tests/store_credit_staff_test.php` (`test_store_credit_staff_gift_card_info`, `test_store_credit_staff_gift_card_delete_note`), `tests/list_table_quick_edit_js_test.php` (`test_list_table_quick_edit_row_name`). The edit screen's queries were not run on a database.
