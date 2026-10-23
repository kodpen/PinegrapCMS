---
name: checkout-contact-card-update
description: What a checkout writes on the contact card: the "Update my contact info" box, tax fields only when on the form, a typed tax number filling an empty card
---

## When does a checkout update the contact card?
- Three handlers do it the same way: `express_order.php`, `billing_information.php` and `submit_order.php` (order preview with billing).
- The whole billing block goes on the card when the visitor is not signed in, is ghosting, or is signed in and left "Update my contact info with this billing info" (`update_contact`) ticked. Unticked is remembered in `$_SESSION['software']['update_contact'] = false`.
- A layout without the box posts nothing for it, so a signed-in customer's card is not updated there either (the express order `/odeme` layout on dev has no box).
- Tax number and tax office are written only when the form had them (`field_in_session()`): a layout without them never clears the card's.

## What happens to the tax number when the card is not updated?
- `orders` has no tax number column, and the order's ERP account is opened from the card (`erp_account_for_contact()`), so a number typed at checkout was lost: the account opened without a VKN and GİB was not asked (#1173 on dev, contact 140).
- Since #943 the handlers' "not updated" branch calls `pg_checkout_fill_contact_tax()` (`includes/fn/contacts.php`): a card with an empty `tax_number` takes the typed number and, when one was typed, the tax office; a card that already names a number keeps it. The decision is the pure `pg_checkout_contact_tax_fill()` (separators removed as on the express order screen, values cut to the column widths 20 / 100); the UPDATE repeats `tax_number = ''`.
- Owner decision (2026-10-20): fill an empty card rather than add tax columns to `orders` (a schema step).

## Does a new number on the card reach the order's invoice?
- No: the card never changes an ERP account that already exists, and the invoice carries the account's number. When the card names another number, the automatic invoice is held and the order screen shows both (owner decision 2026-10-23, erp/erp-order-tax-number-difference.md).

## Which test covers it?
- `tests/checkout_contact_tax_test.php`.
