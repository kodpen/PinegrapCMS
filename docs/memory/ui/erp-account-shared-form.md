---
name: erp-account-shared-form
description: add_erp_account.php and edit_erp_account.php share their cards, field list, checks and the erp_account_save() row through includes/erp/account_form.php; what each screen keeps
---

## What is shared?
- `includes/erp/account_form.php` (gate `PG_ERP_ENTRY`): `erp_account_form_cards()`, `erp_account_form_single_fields()`, `erp_account_form_country()`, `erp_account_form_check_formats()`, and since #3043:
  - `erp_account_form_check_days($liveform)`: payment term and reminder threshold trimmed, marked when refused; returns both strings.
  - `erp_account_form_save_fields($liveform, $country_code, $currency, $contact_id, $payment_days, $overdue_days, $fallback_country)`: the row for `erp_account_save()` without `id` and `created_by`; the screen adds those (`array('id' => …) + … + array('created_by' => …)`).
- `erp_account_form_check_tax_number_taken($liveform, $country_code, $account_id = 0, $stored_number = null, $lookup = null)`: run right after the format check (in edit before the contact relink, which writes). Marks `tax_number` with the New account dialog's key when another **active** card (own id excluded, SQL `=` on the normalised number) holds it. Skips an empty or malformed number; edit passes the stored number and checks only a changed one, as the ERP API does (`erp/erp-api-dry-run.md`), so cards that already share a number still save. Before it, the panel saved a duplicate silently, which the CSV import (oldest card wins) and account sync (skips a shared number) then never matched. 11111111111 never reaches it for TR: the format check refuses it.
- `erp_account_days_refused($value)` lives in `includes/erp/accounts.php` (loaded by the bootstrap) because the CSV import (`includes/erp/import.php`) uses it too: whole days 0-3650, `''` not refused, no trimming inside.

## Why does the row carry every key?
- `erp_account_save()` writes `contact_id`, `state`, `country_code`, `invoice_email`, `invoice_mail`, `credit_limit` only when the key is present (`erp/erp-account-save-partial-writes.md`): a key dropped from the row would quietly keep the stored value. `tests/erp_account_form_test.php` pins the exact key list.

## How do add and edit differ in the row?
- Only the fallback: add passes `erp_default_country_code()`, so an empty choice saves the store's country; edit passes `''`, so an empty choice stays empty. The Turkish "no state" rule reads the saved code (empty: the store's country), as both screens did before.

## What stays in the screens?
- Add: base-currency choice, opening balance and its rate, the contact "already linked" refusal, the GET prefill.
- Edit: not-found guard, currency lock once there are movements, the contact relink through `erp_account_link_contact()`.
