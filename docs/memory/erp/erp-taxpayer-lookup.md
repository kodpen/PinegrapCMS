---
name: erp-taxpayer-lookup
description: Asking GİB about a tax number from the account form and the New account dialog (Query button, erp_taxpayer_lookup.php): e-Invoice user, mailboxes, registered name, what is kept on the card
---

## How does the Query button work?
- `erp_account_form_cards()` (includes/erp/account_form.php) draws Query beside the tax number on the add and edit screens when `erp_edoc_installed()`, the active provider supports `check_taxpayer`, the account is Turkish (`data-erp-tr-only`) and the user is not ERP read-only. It replaced the "Ask GİB about the tax number" toolbar button of `edit_erp_account.php` (#625), which submitted the form and answered with a notice.
- The form's inline script POSTs `token`, `tax_number`, `account_id` to `erp_taxpayer_lookup.php` (JSON in every answer, the gates of `erp_account_quick.php`) and prints the answer across the Tax Details card, below its fields (`data-erp-taxpayer-answer`, `aria-live`; a mailbox list is too long for the narrow tax number column).
- The registered name fills an empty Name field; a different name already there gets a "Use the registered name" button. Nothing is saved until the form is.
- The New account dialog of the document screens (`erp_account_quick_modal()`, `assets/js/erp_account_quick.js`) has the same Query beside its tax number, for a Turkish store with such a provider; it asks with `account_id` 0 and also ticks Company when a ten-digit number's name came from GİB (#676).

## What is kept on the card?
- With an account id: `erp_edoc_account_check_taxpayer()` (service.php) writes `einvoice_user / einvoice_alias / einvoice_aliases (JSON) / einvoice_checked_at` only when the number asked about is the stored one (`stored`); the answer then carries the redrawn status line (`erp_account_form_einvoice_state()`, `data-erp-einvoice-state`).
- Without one (a new account): `erp_edoc_taxpayer_lookup()`, nothing kept.
- The name is never written by the lookup itself.
- The sentence is `erp_edoc_taxpayer_message()` (registry.php, pure): e-Invoice user with up to three mailboxes, e-Delivery note user, the registered name in quotes.

## Where does the name come from?
- Paraşüt: `GET e_invoice_inboxes?filter[vkn]=` → `data[].attributes.e_invoice_address` and `.name` (swagger `EInvoiceInboxAttributes`; also `inbox_type`, `registered_at`), read by `erp_edoc_parasut_inboxes_read()`. İşbaşı: `user/gibUser` `pkList[].title`, else `gbList`.
- GİB lists e-Invoice users only: a number that is not one gets no name, and the answer says its documents go as e-Archive.

## What name does an account opened from an order get?
- GİB's, when its VKN is registered (#806): the register is taken as right over what the customer typed (owner, 2026-10-20). `erp_account_for_contact()` with an order (invoice or delivery note from an order, the automatic invoice too) asks the active provider about a Turkish VKN that passes its check digit before the account is written (`erp_account_registered_name_wanted()`, `erp_account_registered_name_lookup()`); a registered name replaces the one the order or the card gave and the account becomes a company's (`erp_account_data_with_registered_name()`), so the creation event and the invoice's copy carry it. The answer's e-Invoice fields are kept (`erp_account_keep_taxpayer_answer()`); a changed name leaves a log line.
- Not asked: a TCKN (a person's invoice carries the first and the last name; a sole trader's registered name can be a trade name), the bulk "open accounts for contacts with orders" (no order), an account that already exists. A failed or unanswered lookup opens the account as before. It is one more provider call per new account; at checkout it runs after the response (`pg_erp_auto_invoice()`).

## Tests
- `tests/erp_edoc_parasut_test.php`: `test_erp_edoc_parasut_inboxes_read`, `test_erp_edoc_taxpayer_message`.
- `tests/erp_account_registered_name_test.php`: which numbers are asked about, when the name is replaced.
