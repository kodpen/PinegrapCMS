---
name: list-table-quick-edit-gift-cards
description: All Gift Cards list quick edit (gift_card_list_edit.php): balance (over the one its cell opened with), expiration date and notes through the screen's own check and save; store credit stays text
module: gift_card_list_edit
---

## Which cells change?
- `pg_gift_card_edit_columns()`: Balance (`money`, typed as on the screen), Expiration Date (typed in the site's date order, blank for never) and Notes (`textarea`: lines kept, also the line a cancelled order adds). Text: the code, the original amount, From, Recipient, Delivery Date, Created, Last Modified (an order sets them, or they are computed). `tests/gift_card_list_edit_test.php` fails for a new column that is neither.

## How is the balance kept from overwriting a redemption?
- The driver posts `was`, the value the cell opened with (`data-pg-edit-value`; for an undo the value the save left), read by `pg_list_edit_was()` into `pg_list_edit_request()`'s `was` (`list-table-quick-edit.md`).
- `pg_gift_card_quick_balance_was()` turns it into the screen's `balance_was` (minor units); the write is then `... AND balance = <was>` (`commerce/gift-card-balance-edit.md`). A card spent after the list was drawn answers `changed` ("The gift card changed in the meantime…"); a balance edit without a readable `was` is refused the same way, never written blind. Another field passes the stored balance, so the balance is left alone.
- Before (#6968 to #7010) the balance was text: the list posted only `field`, `id`, `value`, and the endpoint could only compare with the balance it read itself, putting a spent amount back.

## Which rows keep their cells as text?
- Store credit (`gift_cards.user_id` > 0, `credit_user_id`): every cell, the balance too. The screen records a credit balance change in the audit trail (`pg_audit('update', 'store_credit', …)`); the list does not, so credit changes there only.
- `pg_gift_card_quick_cell_editable()` decides both for the cell and the write.

## How is a change checked and written?
- `pg_gift_card_quick_update()` fills a throwaway liveform (`gift_card_quick_edit`) with the stored card as the screen opens it plus the one change (`pg_gift_card_quick_form_values()`) and runs the screen's `pg_gift_card_form_check($form, 'edit')`: a balance of 0 or above under the column's ceiling, a real date or blank. The first field error is the refusal's message.
- The write is the screen's own `pg_gift_card_form_write()` (`includes/forms/gift_card_form.php`), the only UPDATE of these columns: the balance only over `balance_was`, the notes only over the ones read (`notes_was`). A guard that fails writes nothing. Unchanged values write nothing; a change logs "gift card (…) was changed on the gift card list".
- The answer is the row read again (`pg_gift_card_list_sql($id)`, `pg_gift_card_list_row_cells()`), every cell with its field's value, so the code's colour, No balance and Expired follow the change.

## Who may change them?
- The gift card screen's rights (`validate_ecommerce_access()`): role below User, or a User with `manage_ecommerce`. `view_gift_cards.php` answers `pg_list_edit_request()` before its own access check, so a refusal is JSON.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/gift-card-shared-form.md
