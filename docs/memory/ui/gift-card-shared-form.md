---
name: gift-card-shared-form
description: add_gift_card.php and edit_gift_card.php draw the Gift Card Options card and check its amount and date through includes/forms/gift_card_form.php; what each screen keeps
---

## What is shared?
- `includes/forms/gift_card_form.php` (gate `PG_FUNCTIONS_DIR`, loaded with `require_once` after the liveform include):
  - `pg_gift_card_form_card($liveform, $mode, $options)`: the Gift Card Options card. `'add'` draws the amount (with focus) and the quantity (`quantity_max`); `'edit'` draws the balance with "Original Amount" under it (`original_amount`, already formatted). Both: the `form-adorn` money field (`form-ghost text-end` + `pg_money_mask_attributes()`), the expiration date with the scripts the screen passes (`scripts`: `get_date_picker_format() . pg_fields_script()`), the notes. `symbol` is printed as HTML, as `BASE_CURRENCY_SYMBOL` was before.
  - `pg_gift_card_form_default_expiration($validity_days, $date_format, $now)`: the validity-days prefill of a new card (`n/j/Y` or `j/n/Y`, `''` without validity days).
  - `pg_gift_card_form_check($liveform, $mode)`: required amount above 0 (add) or balance 0 or above (edit), a typed expiration date valid; returns the normalized amount.
- The card takes its constants as arguments, so `tests/gift_card_form_test.php` renders both modes.

## What stays in the screens?
- Create: the hidden `limit`, the quantity checks (1,000 limit, `limit=false` bypass), the INSERT loop, log and notice.
- Edit: the not-found guard, the row load, the hidden `id` and `balance_was` fields, the stale-save guard (`commerce/gift-card-balance-edit.md`), the order info and redemption history cards, delete.

## Which tests read the markup?
- `tests/bs6_form_adorn_test.php` reads the adorn field and its attribute line from the form file; `tests/gift_card_form_test.php` also checks that `edit_gift_card.php` keeps the `balance_was` guard. Done in #2895.
