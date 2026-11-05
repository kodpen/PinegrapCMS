---
name: list-table-quick-edit-gift-cards
description: All Gift Cards list quick edit (gift_card_list_edit.php): expiration date and notes through the screen's own check and save; balance and store credit stay text
module: gift_card_list_edit
---

## Which cells change?
- `pg_gift_card_edit_columns()`: Expiration Date (typed in the site's date order, blank for never) and Notes. Text: the code, the original amount, From, Recipient, Delivery Date, Created, Last Modified (an order sets them, or they are computed), and Balance (below). `tests/gift_card_list_edit_test.php` fails for a new column that is neither.

## Why is the balance not a field?
- The screen refuses a typed balance when the card's balance changed since the screen opened (`balance_was`, `commerce/gift-card-balance-edit.md`). A list edit posts only `field`, `id`, `value`, so the endpoint could only compare with the balance it reads itself: a redemption made after the list was drawn would be overwritten, the spent amount put back (review on #6968).
- To make it a field, the driver (`pg_list_table_edit.js`, `pg_list_edit_request()`) must post the value the cell opened with (its `data-pg-edit-value`, the undo's `after`) and the endpoint use it as `balance_was`, answering `changed` on a mismatch.
- Before, only Notes was a field (the functions sat in `includes/fn/ecommerce.php`).

## Which rows keep their cells as text?
- Store credit (`gift_cards.user_id` > 0, `credit_user_id`): every cell. The screen records a credit balance change in the audit trail (`pg_audit('update', 'store_credit', …)`); the list does not, so credit changes there only.
- Notes on more lines (typed on the screen, or the line a cancelled order adds): the notes cell only; the list's box is one line and would join them.
- `pg_gift_card_quick_cell_editable()` decides both for the cell and the write.

## How is a change checked and written?
- `pg_gift_card_quick_update()` fills a throwaway liveform (`gift_card_quick_edit`) with the stored card as the screen opens it plus the one change (`pg_gift_card_quick_form_values()`) and runs the screen's `pg_gift_card_form_check($form, 'edit')` (the stored balance passes it): a real date or blank. The first field error is the refusal's message.
- The write is the screen's own `pg_gift_card_form_write()` (`includes/forms/gift_card_form.php`), the only UPDATE of these columns: from the list the balance is unchanged, so not written, and the notes only over the ones read (`notes_was`). A guard that fails writes nothing; the list answers "The gift card changed in the meantime…". Unchanged values write nothing; a change logs "gift card (…) was changed on the gift card list".
- The answer is the row read again (`pg_gift_card_list_sql($id)`, `pg_gift_card_list_row_cells()`), every cell with its field's value, so the code's colour and Expired word follow the date.

## Who may change them?
- The gift card screen's rights (`validate_ecommerce_access()`): role below User, or a User with `manage_ecommerce`. `view_gift_cards.php` answers `pg_list_edit_request()` before its own access check, so a refusal is JSON.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/gift-card-shared-form.md
