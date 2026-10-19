---
name: erp-taxpayer-lookup
description: Asking GİB about a tax number from the account form (Query button, erp_taxpayer_lookup.php): e-Invoice user, mailboxes, registered name, what is kept on the card
---

## How does the Query button work?
- `erp_account_form_cards()` (includes/erp/account_form.php) draws Query beside the tax number on the add and edit screens when `erp_edoc_installed()`, the active provider supports `check_taxpayer`, the account is Turkish (`data-erp-tr-only`) and the user is not ERP read-only. It replaced the "Ask GİB about the tax number" toolbar button of `edit_erp_account.php` (#625), which submitted the form and answered with a notice.
- The form's inline script POSTs `token`, `tax_number`, `account_id` to `erp_taxpayer_lookup.php` (JSON in every answer, the gates of `erp_account_quick.php`) and prints the answer under the field (`data-erp-taxpayer-answer`, `aria-live`).
- The registered name fills an empty Name field; a different name already there gets a "Use the registered name" button. Nothing is saved until the form is.

## What is kept on the card?
- With an account id: `erp_edoc_account_check_taxpayer()` (service.php) writes `einvoice_user / einvoice_alias / einvoice_aliases (JSON) / einvoice_checked_at` only when the number asked about is the stored one (`stored`); the answer then carries the redrawn status line (`erp_account_form_einvoice_state()`, `data-erp-einvoice-state`).
- Without one (a new account): `erp_edoc_taxpayer_lookup()`, nothing kept.
- The name is never written by the lookup itself.
- The sentence is `erp_edoc_taxpayer_message()` (registry.php, pure): e-Invoice user with up to three mailboxes, e-Delivery note user, the registered name in quotes.

## Where does the name come from?
- Paraşüt: `GET e_invoice_inboxes?filter[vkn]=` → `data[].attributes.e_invoice_address` and `.name` (swagger `EInvoiceInboxAttributes`; also `inbox_type`, `registered_at`), read by `erp_edoc_parasut_inboxes_read()`. İşbaşı: `user/gibUser` `pkList[].title`, else `gbList`.
- GİB lists e-Invoice users only: a number that is not one gets no name, and the answer says its documents go as e-Archive.

## Tests
- `tests/erp_edoc_parasut_test.php`: `test_erp_edoc_parasut_inboxes_read`, `test_erp_edoc_taxpayer_message`.
