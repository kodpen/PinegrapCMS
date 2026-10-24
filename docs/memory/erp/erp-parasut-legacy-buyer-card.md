---
name: erp-parasut-legacy-buyer-card
description: The Paraşüt card the order screen's buttons put an order on: the one Paraşüt holds for the buyer's VKN/TCKN (#1543), else a new one with the order's identity (#1408) and address (#1518)
---

## Which card does an order go on?
- `parasut_sync_contact()`, called by both buttons: the id the order holds (`orders.parasut_contact_id`); else `parasut_order_contact_for()`: the card Paraşüt holds for the buyer's VKN/TCKN, else a new card. Either id is stored on the order.
- Before #1543 every order opened a card of its own, so a repeat buyer collected one card per order.

## When is a card looked up, and how? (#1543)
- Only for a number that passes GİB's check digits (`parasut_order_contact_search_number()`, `erp_edoc_tax_number_valid()`), as digits. Not for an empty field, the final consumer's `11111111111`, a mistyped number, or a placeholder that fails the check (`00000000000`, `2222222222`, `12345678901`).
- A real VKN/TCKN always passes. A placeholder that passes too (`1234567890`, `4444444444`) is looked up and shared like a real number: buyers who typed it go on one card.
- As the ERP's driver looks (erp-parasut-final-consumer-card.md): `GET contacts?filter[tax_number]=N&sort=-id`, 25 a page, until a card is taken, at most `ERP_EDOC_PARASUT_FIND_PAGES` (20). The first card not archived and carrying exactly the number (digits compared) is taken as it is: nothing is PUT, even when the order's name, address or e-mail differ.
- 404 or no such card: a card is opened. Any other answer refuses ("Paraşüt would not search its contacts: …"); nothing is opened, the order keeps no id, the press can be repeated.
- The driver's `erp_edoc_parasut_contact_for()` is not called: it opens a missing card with the ERP account's details and logs each call to `erp_edoc_log`.

## What does a new card carry? (#1408, #1518)
- `parasut_order_contact_attributes()`: the VKN/TCKN (`_parasut_order_identity()`) in `tax_number`; a person without a billing company or with 11 digits, as the driver. A person's TCKN used to go as an unlisted `tckn`; a card opened so may lack the number, and is then not found.
- A TR address (no billing country: the store's) as the driver maps it (`erp_address_from_checkout()`): street lines in `address`, state field in `city`, city field in `district`, `postal_code`. Abroad: one folded `address`, kept; nothing refused. Cards opened before stay folded (city, district null).

## Which buyers still get a card per order?
- No number, the final consumer, a number that fails the check. The ERP's driver takes a final-consumer card only when name, address and e-mail match (#1405); the legacy buttons do not look.

## How was it checked live? (demo company, 2026-10-24)
- Branch code, dev orders 438 and 439, `parasut_tc_in_field` custom_field_1 = 10000000146. The number held only an archived card, which the search does not list (200, `total_pages` 0). Order 438's sync opened card A; E-Shipment on order 439 stored card A without opening one (unarchived contacts 14 → 14: the 13 before the run plus card A), then stopped at "no stock-tracked items". Order 438 without a number, then with 11111111111, each opened a card of its own. Cards deleted, dev values restored, demo equal to its baseline.

## Tests
- `tests/parasut_contact_reuse_test.php`: the number rule, reuse, archived and other numbers, later pages, page cap, 404, a failed search, no number, a refused create, the steps of `parasut_sync_contact()`.
- `tests/parasut_order_invoice_test.php`: a new card's attributes.
