---
name: erp-parasut-legacy-buyer-number
description: The buyer's VKN/TCKN the order screen's Paraşüt buttons send: a Turkish buyer's digits, and Create Invoice refuses a number that fails the check digits instead of issuing an e-Archive (#7674)
module: parasut
---

## How is the number read?
- `_parasut_order_identity()`: the field `PARASUT_TC_IN_FIELD` names (`custom_field_1/2` on the order, `tax_number` on the linked contact), through `parasut_order_identity_number()`.
- A buyer whose billing country is TR (none given: the store's, `erp_account_country()`) goes as digits only: spaces, dashes, dots and a `TR` prefix are dropped. A buyer abroad keeps the number as typed (trimmed): a foreign VAT id is not a VKN.
- The digits go to the `e_invoice_inboxes?filter[vkn]=` lookup (`_parasut_resolve_edoc_kind()`) and to the card's `tax_number` (`parasut_order_contact_attributes()`, whose 11-digit person test now counts digits).

## What does Create Invoice refuse?
- `parasut_order_identity_refusal()`, asked before the e-document kind: a Turkish number that is not 10 digits (VKN) or 11 (TCKN) passing `erp_edoc_tax_number_valid()`. Nothing is created; the error names the number. Not refused: an empty number (e-Archive, as before), the final consumer's `11111111111`, a buyer abroad.
- E-Shipment is not refused here; its card gets the digits too.

## Why? (#7674)
- The number used to go with only `trim()`. Paraşüt answers a filter on a malformed number with 200 and an empty list, the "not registered" answer, so a GİB-registered company typed as `123 456 78 90` or `TR1234567890` (free text at checkout, or a contact saved with spaces) got an e-Archive where an e-Invoice was due, and its card the raw string. `parasut_check_einvoice_address()` and the ERP's lookup already read digits and refused other lengths.

## Tests
- `tests/parasut_buyer_identity_test.php`: the digits, a foreign number, the refusal, the card's `tax_number` and type, the refusal before the lookup.
