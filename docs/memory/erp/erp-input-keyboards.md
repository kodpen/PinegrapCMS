---
name: erp-input-keyboards
description: Phone keyboards of ERP boxes: liveform's opt-in autocapitalize, the till IBAN, the expense VKN/TCKN and the send-by-e-mail recipient list
---

## Does liveform pass autocapitalize?
- Yes, opt-in beside `spellcheck`: `'autocapitalize' => '…'` prints it, escaped, on text, textarea and password fields; without one nothing is printed, so site forms keep the browser default. Pinned by `test_liveform_output_field_autocapitalize` (`tests/liveform_test.php`).

## Why does the till IBAN say autocapitalize="characters"?
- The IBAN (`includes/erp/till_form.php`) is stored as typed (`add_erp_till.php`, `edit_erp_till.php` only trim it); iOS capitalises only the first letter, so `TR33…` was saved as `Tr33…`. It also has `spellcheck="false"`. It is not numeric: an IBAN starts with letters.

## When is the expense supplier VKN/TCKN numeric?
- For a Turkish store (`pg_erp_store_country() === 'TR'`), Add Expense's `supplier_tax_number` is `inputmode="numeric"`: `erp_expense_save_check()` checks it with `erp_tax_number_check()`, digits only. Same rule as Create Order and the seller VKN in settings.

## Why is the send-by-e-mail recipient not type="email"?
- The invoice, quote and purchase-order `mail_to` box takes a list: `erp_mail_addresses()` (`includes/erp/mail.php`) splits on spaces, commas and semicolons, which `type="email"` would refuse. It stays `type="text"` with `inputmode="email"`, `spellcheck="false"`, `autocapitalize="off"`.
- Pinned by `tests/erp_input_keyboards_test.php` (also the IBAN and VKN above).

Related: docs/memory/ui/liveform-field-names.md
