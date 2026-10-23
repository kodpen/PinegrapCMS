---
name: erp-order-tax-number-difference
description: Contact card's tax number not on the order's ERP account: the automatic invoice is held, the documents card shows both numbers, the bell and log neither (owner decision 2026-10-23)
---

## Where does the difference come from?
- The order keeps no tax number. Its invoice goes to `orders.erp_account_id`, else the contact's account (`erp_order_invoice_account_id()`, the writer's rule), which `erp_account_for_contact()` returns untouched; the invoice copies that account's `tax_number` and the e-document provider gets that copy.
- The card changes after the account exists: a checkout that updates the card (box ticked, guest, ghost), an empty card filled at checkout (`pg_checkout_fill_contact_tax()`), the contact screen (commerce/checkout-contact-card-update.md).
- A cancelled invoice clears `orders.erp_invoice_id`, not `erp_account_id`, so the next invoice goes to the cancelled one's account. On dev, orders 1172-1174 bill accounts 7/8 (3130557669, no contact) while contact 140 says 1234567808.

## When do the two numbers differ?
- `erp_tax_numbers_differ()`: compared as `erp_edoc_account_norm('tax_number')` keys (letters and digits only, upper case, 11111111111 as none). A card with no number names none: no difference. An account with none under a numbered card differs. A prefix on one side (TR…) or a lost leading zero differs.
- An order with no account yet has nothing to differ from: its first invoice opens the account from the card.
- The card is read as it is now: a later checkout that rewrites it also holds an older uninvoiced order of the same contact.

## What does the store do? (owner decision 2026-10-23: warn and hold, #1307)
- The automatic invoice is not issued: `erp_auto_invoice_decision()` answers `tax_number_differs`, after `unpaid`; `erp_auto_invoice_for_order()` logs it and rings the bell with `erp_auto_invoice_tax_number_reason()`, which names neither number (the log is never deleted, the bell is pushed to phones, a TCKN is a person's ID). With "Only for buyers with a tax number" on, such an order is held, not filtered as `no_tax_number`. Nothing retries: the next trigger or the card's button issues it.
- The documents card's invoice step (`erp_order_flow_state()`) names both numbers and the account (`erp_order_tax_number_difference_text()`), warns, and still offers Issue the Invoice: the operator corrects the account or the card, or issues to the account as it is. Links: Edit the account; Edit Contact only when the account is the contact's own. When the step is blocked for another reason the difference is a line of its own.
- No tax column on `orders`: the 2026-10-20 decision stands. Manual paths (the card, `add_erp_invoice.php`, the API, waybills, the till) are not held.

## Which tests cover it?
- `tests/erp_auto_invoice_test.php`: the comparison, the decision's new reason and its place, the texts (no number in the bell's), the card's warning.
