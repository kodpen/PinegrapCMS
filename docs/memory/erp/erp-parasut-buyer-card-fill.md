---
name: erp-parasut-buyer-card-fill
description: A reused Paraşüt buyer card gets the order's details only in the fields it holds empty, in one PUT of the whole card; a refused user stops, any other refusal keeps the card (#12013, decision #2441)
module: parasut
---

## Which cards are filled?
- A card an invoice reuses: the ERP driver's linked or found card (`erp_edoc_parasut_contact_for()` through `erp_edoc_parasut_contact_taken()`) and the order screen's card found by the buyer's number (`parasut_order_contact_for()`, erp-parasut-legacy-buyer-card.md). A card just opened carries the details already.
- Decision #2441: only the empty fields are filled; a value the card has is never overwritten. Before #12013 a reused card was taken as it was.

## Which fields?
- `erp_edoc_parasut_contact_fill($card, $wanted)` (pure): `email`, `phone`, `tax_office`, `address`, `district`, `city`, `postal_code`, each only when the card holds it empty (null or spaces) and the order has a value.
- Never `name`, `tax_number`, `contact_type`, `account_type` or `is_abroad`: they decide whose card it is, and the match already holds them.

## How is it sent?
- `erp_edoc_parasut_contact_fill_send()`: nothing when there is nothing to fill; else one `PUT contacts/{id}` with every writable attribute the card holds (`erp_edoc_parasut_card_attributes()`, as the account sync writes a card back whole, erp-parasut-accounts.md) plus the filled ones. Read-only ones (balance, created_at) are not sent.

## What if Paraşüt refuses the PUT?
- A login error, 401 or 403 (`erp_edoc_parasut_contact_fill_stops()`) stops the invoice before `POST sales_invoices`: "Paraşüt would not fill the empty fields of the buyer card: …". Many 401/403 answers can get a client switched off, so nothing more is sent.
- Any other refusal (a 422, a 5xx, a timeout) keeps the card as it is and the invoice goes on; the card is filled at its next use.

## How was it checked?
- Unit tests only: `tests/parasut_contact_fill_test.php` (the fields, the whole card, the stops, both paths), `tests/parasut_contact_reuse_test.php`, `tests/erp_edoc_parasut_test.php`.
- Not tried live: whether Paraşüt's PUT replaces or merges the attributes it is not sent.
