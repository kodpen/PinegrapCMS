---
name: gift-card-shared-form
description: add_gift_card.php and edit_gift_card.php draw the Gift Card Options card and check its amount and date through includes/forms/gift_card_form.php; what each screen keeps
---

## What is shared?
- `includes/forms/gift_card_form.php` (gate `PG_FUNCTIONS_DIR`, loaded with `require_once` after the liveform include):
  - `pg_gift_card_form_card($liveform, $mode, $options)`: the Gift Card Options card alone (no row or column; the screens place it). `'add'` draws the amount (with focus) and the quantity (`quantity_max`); `'edit'` draws the balance with "Original Amount" under it (`original_amount`, already formatted). Both: the `form-adorn` money field (`form-ghost text-end` + `pg_money_mask_attributes()`), the expiration date with the scripts the screen passes (`scripts`: `get_date_picker_format() . pg_fields_script()`), the notes. `symbol` is printed as HTML, as `BASE_CURRENCY_SYMBOL` was before.
  - `pg_gift_card_form_default_expiration($validity_days, $date_format, $now)`: the validity-days prefill of a new card (`n/j/Y` or `j/n/Y`, `''` without validity days).
  - `pg_gift_card_form_check($liveform, $mode)`: required amount above 0 (add) or balance 0 or above (edit), a typed expiration date valid; returns the normalized amount.
  - `pg_gift_card_form_expiration($value)`: the stored expiration date, `0000-00-00` for blank, the ISO date when the whole value is a date (`pg_date_input_iso()`: site order with `/ - , .`, or ISO), else `''` (the check's error). Both screens write it. `validate_date()` matched a date inside `5/5/20271`, stored as `0000-00-00` (never expires); `15.12.2027` was refused (#5530).
  - Edit mode prints the card's code (`code`, `status_class`, `status_word`: the list's colour and Expired / No balance word) at the top of the card; the breadcrumb's last item and the title are the code too (#5529).
  - The amount or balance must fit `gift_cards.amount`/`balance`, both INT(10) UNSIGNED minor units: `PG_GIFT_CARD_AMOUNT_MAX` (4294967295) and `pg_gift_card_form_amount_fits()`; above it the check says "The highest amount a gift card holds is …". MySQL used to keep the ceiling (or refuse in strict mode) while the screen said saved (PR #4586).
  - `pg_gift_card_form_quantity($value)`: the create screen's quantity as a whole number above 0 (spaces around dropped, no separator), else false. A fraction used to make only the whole part, 0.5 no card (PR #4576). Commas were dropped as thousands until #5532, so a Turkish `1,5` made 15 cards; the box carries the `integer` mask with no minus.
  - The notes textarea opens 5 rows high; `liveform` prints no `rows`/`cols` attribute when they are empty (#5534).
- The card takes its constants as arguments, so `tests/gift_card_form_test.php` renders both modes.

## What stays in the screens?
- Create: the hidden `limit`, the quantity checks (1,000 limit, `limit=false` bypass), the INSERT loop, log and notice.
- Edit: the not-found guard, the row load, the hidden `id` and `balance_was` fields, the order info and redemption history cards, delete. Its save is the shared `pg_gift_card_form_write()`, the one UPDATE of balance, date and notes, also the list's quick edit (`list-table-quick-edit-gift-cards.md`, `commerce/gift-card-balance-edit.md`).
- The list draws the notes in a `text-wrap text-break` box of `max-width:20rem` (`pg_gift_card_list_cells()`); a long note widened the table (#5531).

## How are the screens laid out?
- The edit screen layout (`edit-screen-wide-layout.md`, B11): the card in the form column (`lg:col-8 2xl:col-9`), the save bar in its own sticky `order-3` column. Edit adds Redemption History under the card and Order Info in the sticky status column. Create has nothing to read, so no status column; the form column keeps its width.

## Which tests read the markup?
- `tests/bs6_form_adorn_test.php` reads the adorn field and its attribute line from the form file; `tests/gift_card_form_test.php` also checks that `edit_gift_card.php` keeps the `balance_was` guard. Done in #2895.
