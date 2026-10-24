---
name: erp-parasut-final-consumer-card
description: Which Paraşüt card the invoice of a buyer without a TCKN (final consumer, 11111111111) goes on, why a name match is not enough, how many pages of cards are read, and what is still open
---

## Which card does a buyer without a number go on?
- `erp_edoc_parasut_send_invoice()` gives the walk-in account and a person without a TCKN the final consumer's number (`erp_edoc_final_consumer_tckn()`, `11111111111`). Such an account has no linked card (`erp_edoc_account_link_code()` answers ''), so `erp_edoc_parasut_contact_for()` always searches `GET contacts?filter[tax_number]=11111111111&sort=-id&page[number]=N&page[size]=25`.
- A listed card is taken only when it is not archived, carries exactly that number, and `erp_edoc_parasut_final_consumer_card_matches()` finds its name, address, district, city and e-mail equal to what `erp_edoc_parasut_contact_attributes()` would write for the buyer (trimmed, case ignored). An e-mail on the card that the buyer did not give is a difference. Otherwise `POST contacts` opens a card with the buyer's own details.
- Phone, postal code and tax office are not compared.

## Why is a name match not enough?
- The `sales_invoices` body sends no address, phone or e-mail: the e-Arşiv shows the card's, and Paraşüt mails it to the card's e-mail (the `sent` and `bounced` states the poll reads).
- Until #1405 the name alone decided: a second "Ayşe Demir" with another address and e-mail was invoiced on the first one's card, so her e-Arşiv showed the first buyer's address and phone and went to the first buyer's mailbox, and a returning consumer who had moved got the old address. Nothing corrected it later: the account sync never links the final-consumer card (`erp_edoc_account_link_from_invoice()` leaves it alone).

## How many pages of cards are read?
- Page after page (`meta.total_pages`) until a card is taken, at most `ERP_EDOC_PARASUT_FIND_PAGES` (20 pages, 500 cards); then a card is opened, as for a buyer Paraşüt does not know. Until #PR only the first 25 were read, and a store with more cards opened a second card for a buyer whose own was on a later page.
- `sort=-id`: the list's own order is by balance (id ascending at equal balances, seen live), and an invoice written meanwhile moves it. Newest first keeps recent buyers inside the cap, and a card opened past the cap is the newest the next time.
- A page that answers neither 2xx nor 404 (no card) refuses the send, as the first page always did, so no card is opened while one may be unread. From page 2 on, `erp_edoc_parasut_allow_time()` gives the request more time (10 requests in 10 seconds).
- Live check (#PR, demo company): 51 cards under the number, the buyer's card first on page 2 in both orders; the search read two pages and wrote the sales invoice on that card, the count stayed 51. The old one-page read held no matching card.
- `filter[name]` answered 200 on contacts live and listed only the card of that name; the search does not use it.

## What is still open?
- A buyer who gives an e-mail one time and none the next gets two cards.
- Past 500 cards, a returning buyer whose card is older than the newest 500 gets one more card.

## Tests
- `tests/erp_edoc_parasut_test.php`: `test_erp_edoc_parasut_final_consumer_card_matches`, `test_erp_edoc_parasut_contact_for_checks_final_consumer_card` (source check), and the `test_erp_edoc_parasut_contact_for_*` tests with a stand-in request (later page, every page read, page cap, failed page, 404 and a real number).
