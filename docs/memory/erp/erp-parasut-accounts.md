---
name: erp-parasut-accounts
description: Paraşüt's customer and supplier cards in ERP › Account sync (accounts capability): the lists a read pages through, archived and unlisted cards, card shape, writing a card, the linked card on invoices
---

## What does the Paraşüt driver hand to the account sync?
- `accounts` (#711): ERP › Account sync (`erp_account_sync.php`, `includes/erp/edoc/account_sync.php`) reaches the contacts through three operations:
  - `erp_edoc_parasut_accounts($page)`: page N of `GET contacts?sort=id&page[number]=N&page[size]=25` and of the same with `filter[archived]=true` (`erp_edoc_parasut_contact_lists()`, `_contacts_merge()`); counts from `meta`. A later page asks only the lists that reach it. By id: the list's own order, `-balance`, moves during a read.
  - `erp_edoc_parasut_account($id)`: `GET contacts/{id}`; 404 answers "Paraşüt no longer has this card".
  - `erp_edoc_parasut_account_save($card, $id)`: `POST contacts` ('' id) or `PUT contacts/{id}`.

## Which cards do the lists leave out? (live, 2026-10-20)
- Archived cards: the list leaves them out, so a complete read showed an archived card as gone until #884. `filter[archived]=true` (documented for other lists only) lists them alone; they come in passive.
- Unlisted cards (`untrackable`, fixed when a card is opened) are in no list and no `filter[tax_number]` search; `filter[untrackable]` is refused ("Acceptable: name, email, tax_number, tax_office, city, updated_at"). Only `GET contacts/{id}` reads one: the invoice path never lands on one; an account linked to one shows it as gone.

## What does a Paraşüt card look like here?
- `erp_edoc_parasut_card()`: one `name`; `contact_type` person|company; `tax_number` VKN or TCKN (letters kept: a mistyped number stays mistyped); `postal_code`; `account_type` customer|supplier (no "both"); `archived` = not active.
- No card code at Paraşüt: the contact id is the code (as in poll()'s `party`), and `erp_edoc_account_link_code()` hands it to `send_invoice` as `provider_code`.
- The country is free text beside `is_abroad`: Türkiye's spellings, or none on a domestic card, read as TR; another name is looked up in `countries`; written as "Türkiye" or `erp_country_name()`.

## How is a change written?
- The card is read first and every writable attribute (swagger ContactAttributes less balances and dates) is PUT back with only the given fields changed (`erp_edoc_parasut_card_attributes()`); nulls and an empty `invoicing_preferences` are left out.
- A short name that mirrored the name follows it; an account that is both goes as a customer; a Turkish number goes as digits. A new card is written with the fields that hold something; the ERP's `PG-<id>` code and e-Invoice answer are not Paraşüt fields.

## What happens to an invoice of a linked account?
- `erp_edoc_parasut_contact_for()` reads the linked contact (one GET) and uses it only while it is not archived and still carries the buyer's number (`erp_edoc_parasut_linked_contact_usable()`); otherwise it searches by tax number.
- A poll links an account with no card yet to its invoice's contact (`erp_edoc_account_link_from_invoice()`, one more GET).
- A card the invoice path opens (`erp_edoc_parasut_contact_attributes()`) carries the buyer's postal code (#742); live, it read back equal to its account.

## What did the live test show? (demo company, 2026-10-20)
- Read: the VKN card linked itself to its account by tax number; the final-consumer card (11111111111) did not. PUT: a postal code, and Status → passive (`archived`), read back equal. POST: a person's account opened an equal card, as a customer.

## Why does each operation move the time limit?
- At 10 requests per 10 s, 50 pages or 25 cards sent (POST + read back) wait ~40 s, counted by PHP's time limit on Windows: `erp_edoc_parasut_allow_time()` moves it a minute ahead at least (never earlier).

## Tests
- `tests/erp_edoc_parasut_test.php`: `test_erp_edoc_parasut_card`, `_contacts_read`, `_contact_lists`, `_contacts_merge`, `_card_attributes_*`, `_linked_contact_usable`.
