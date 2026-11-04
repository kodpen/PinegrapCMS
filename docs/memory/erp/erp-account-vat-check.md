---
name: erp-account-vat-check
description: The ERP account card's tax number checked against VIES on save: when it runs, what each answer does, why an unknown id only warns
---

## When does the card reach VIES?
- `erp_account_form_check_vat()` (`includes/erp/account_form.php`), called by `add_erp_account.php` and `edit_erp_account.php` after the format and duplicate-number checks, through `pg_vat_check()` (`commerce/eu-vat-check.md`, a day's cache).
- Only a number the format check left unmarked, and only when the account's country and the store's are both member states and differ (`pg_vat_checkout_applies()`, as at checkout). A Turkish card, a card in the store's own country or outside the EU makes no call.
- On edit only a changed number is checked (compared without case and separators), so saving an untouched card makes no call.

## What does each answer do?
- `erp_account_vat_outcome($check, $refuse_invalid)` (pure). Valid: a notice on the account list with the name VIES gives. VIES down (`unavailable`): a warning, the card saved unchecked.
- Unknown to VIES or not shaped like a VAT id: a warning on the account list, the card saved (with `$refuse_invalid` it would be an error on the tax number box).

## Why does an unknown id only warn?
- Owner decision (#6489): the card is the accountant's record and may hold a national number that is no EU VAT id; the reverse charge is decided at checkout from the order's own check (`commerce/eu-vat-checkout.md`), never from the card. Both screens pass `$refuse_invalid = false`.
- Nothing is stored on the card: no schema change.

## Which tests cover it?
- `tests/erp_account_vat_check_test.php`: every answer under both policies, the cross-border rule, a marked number not sent, an untouched number on edit.
