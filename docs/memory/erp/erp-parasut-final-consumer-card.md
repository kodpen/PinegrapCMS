---
name: erp-parasut-final-consumer-card
description: Which Paraşüt card the invoice of a buyer without a TCKN (final consumer, 11111111111) goes on, why a name match is not enough, and what is still open
---

## Which card does a buyer without a number go on?
- `erp_edoc_parasut_send_invoice()` gives the walk-in account and a person without a TCKN the final consumer's number (`erp_edoc_final_consumer_tckn()`, `11111111111`). Such an account has no linked card (`erp_edoc_account_link_code()` answers ''), so `erp_edoc_parasut_contact_for()` always searches `GET contacts?filter[tax_number]=11111111111&page[size]=25`.
- A listed card is taken only when it is not archived, carries exactly that number, and `erp_edoc_parasut_final_consumer_card_matches()` finds its name, address, district, city and e-mail equal to what `erp_edoc_parasut_contact_attributes()` would write for the buyer (trimmed, case ignored). An e-mail on the card that the buyer did not give is a difference. Otherwise `POST contacts` opens a card with the buyer's own details.
- Phone, postal code and tax office are not compared.

## Why is a name match not enough?
- The `sales_invoices` body sends no address, phone or e-mail: the e-Arşiv shows the card's, and Paraşüt mails it to the card's e-mail (the `sent` and `bounced` states the poll reads).
- Until #PR the name alone decided: a second "Ayşe Demir" with another address and e-mail was invoiced on the first one's card, so her e-Arşiv showed the first buyer's address and phone and went to the first buyer's mailbox, and a returning consumer who had moved got the old address. Nothing corrected it later: the account sync never links the final-consumer card (`erp_edoc_account_link_from_invoice()` leaves it alone).

## What is still open?
- One page of 25 cards is read: once a store has more cards under the number, the buyer's own card may not be on it and an equal second card is opened (right details, one card more). Paraşüt accepts `filter[name]` on contacts (its error text lists it); it has not been tried in this search.
- A buyer who gives an e-mail one time and none the next gets two cards.

## Tests
- `tests/erp_edoc_parasut_test.php`: `test_erp_edoc_parasut_final_consumer_card_matches`, `test_erp_edoc_parasut_contact_for_checks_final_consumer_card` (source check of the search).
