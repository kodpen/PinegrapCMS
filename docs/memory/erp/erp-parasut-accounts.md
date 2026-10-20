---
name: erp-parasut-accounts
description: Paraşüt's customer and supplier cards in ERP › Account sync (the accounts capability): list paging, the card shape, how a change is written, the linked card on invoices
---

## What does the Paraşüt driver hand to the account sync?
- The driver declares `accounts` (#711): ERP › Account sync (`erp_account_sync.php`, `includes/erp/edoc/account_sync.php`) works on Paraşüt's contacts through the contract's three operations:
  - `erp_edoc_parasut_accounts($page)`: `GET contacts?sort=id&page[number]=…&page[size]=25`, counts from `meta.total_count` / `meta.total_pages` (`erp_edoc_parasut_contacts_read()`). Sorted by id: the list's own order is `-balance`, which moves while a read pages through it.
  - `erp_edoc_parasut_account($id)`: `GET contacts/{id}`; 404 answers "Paraşüt no longer has this card".
  - `erp_edoc_parasut_account_save($card, $id)`: `POST contacts` ('' id) or `PUT contacts/{id}`.

## What does a Paraşüt card look like here?
- `erp_edoc_parasut_card()`: one `name` for a person and a company; `contact_type` person|company; `tax_number` is the VKN or the TCKN (letters kept, so a mistyped number stays mistyped); `postal_code`; `account_type` customer|supplier (no "both"); `archived` is the opposite of active.
- Paraşüt has no card code: the contact id is the code, as in poll()'s `party`. `erp_edoc_account_link_code()` therefore hands the contact id to `send_invoice` as `provider_code`.
- The country is free text beside `is_abroad`: the spellings of Türkiye, and no country on a domestic card, read as TR; another name is looked up in `countries`. It is written as "Türkiye" or `erp_country_name()`.

## How is a change written?
- The card is read first and the whole writable attribute set (swagger ContactAttributes without the read-only balances and dates) is PUT back with only the given fields changed (`erp_edoc_parasut_card_attributes()`). The result is the same whether Paraşüt's PUT replaces or merges. Nulls and an empty `invoicing_preferences` are not sent back.
- A short name that mirrored the name follows it; an account that is both goes as a customer; a Turkish number goes as digits. A new card is written with the fields that hold something; the ERP's `PG-<id>` code and e-Invoice answer are not Paraşüt fields.

## What happens to an invoice of a linked account?
- `erp_edoc_parasut_contact_for()` reads the linked contact (one GET, as the search was) and uses it only while it is not archived and still carries the buyer's number (`erp_edoc_parasut_linked_contact_usable()`); otherwise it searches by tax number as before.
- A poll links an account with no card yet to the contact its invoice is on (`erp_edoc_account_link_from_invoice()`, one more GET to fill the card).
- A card the invoice path opens (`erp_edoc_parasut_contact_attributes()`) carries the buyer's postal code (#742); older cards differ from their account on the postal code alone.

## What did the live test show? (demo company, 2026-10-20)
- Read: one page held all 6 cards; the VKN card linked itself to its account by tax number and matched it on every field but the postal code; the final-consumer card (11111111111) stayed unlinked.
- PUT: the postal code went to that card, which read back equal. POST: an account (person, TCKN, customer and supplier) opened a card that read back equal, as a customer.

## Why does each operation move the time limit?
- 25 cards a page and 10 requests per 10 s: 50 pages, or 25 cards sent (POST + read back), wait about 40 s, and on Windows the PHP time limit counts the wait. `erp_edoc_parasut_allow_time()` moves it to at least a minute from now, never earlier; no limit (the command line) stays none.

## What is not settled?
- UNVERIFIED: whether the list carries archived and unlisted (`untrackable`) contacts. A card a complete read did not see is shown as gone at the provider.

## Tests
- `tests/erp_edoc_parasut_test.php`: `test_erp_edoc_parasut_card`, `_contacts_read`, `_card_attributes_*`, `_linked_contact_usable`, `_country_code`, `_offers_its_cards`.
