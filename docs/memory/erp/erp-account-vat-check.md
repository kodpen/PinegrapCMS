---
name: erp-account-vat-check
description: The ERP account card's tax number checked against VIES on save: when it runs, what each answer does, the open refuse-or-warn choice
---

## When does the card reach VIES?
- `erp_account_form_check_vat()` (`includes/erp/account_form.php`), called by `add_erp_account.php` and `edit_erp_account.php` after the format and duplicate-number checks, through `pg_vat_check()` (`commerce/eu-vat-check.md`, a day's cache).
- Only a number the format check left unmarked, and only when the account's country and the store's are both member states and differ (`pg_vat_checkout_applies()`, as at checkout). A Turkish card, a card in the store's own country or outside the EU makes no call.
- On edit only a changed number is checked (compared without case and separators), so saving an untouched card makes no call.

## What does each answer do?
- `erp_account_vat_outcome($check, $refuse_invalid)` (pure). Valid: a notice on the account list with the name VIES gives. VIES down (`unavailable`): a warning, the card saved unchecked.
- Unknown to VIES or not shaped like a VAT id: a warning, or with `$refuse_invalid` an error on the tax number box. A card may hold a national number that is no EU VAT id; the screens pass false until that product choice is decided (plan step SF6b2b).
- Nothing is stored on the card: no schema change.

## Which tests cover it?
- `tests/erp_account_vat_check_test.php`: every answer under both policies, the cross-border rule, a marked number not sent, an untouched number on edit.
