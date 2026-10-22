---
name: erp-edoc-account-sync
description: Account sync (4.69): the provider's customer and supplier cards beside the ERP accounts in erp_edoc_accounts, the one automatic link, field-by-field alignment, no balance in either direction.
---

## How are the provider's cards reached?
- Only through the one door: `erp_edoc_call('accounts' | 'account' | 'account_save')`. A driver with the three and the `accounts` capability gets the screens (`erp_account_sync.php`, `erp_account_sync_item.php`; menu link through `pg_erp_provider_offers('accounts')`). The work is `includes/erp/edoc/account_sync.php`; the card shape is in its header. Paraşüt's side: erp-parasut-accounts.md.
- Cards and links live in `erp_edoc_accounts`. Never open a provider column on `erp_accounts`.
- A read is on request (at most 50 pages; each page is one read of the provider's quota). It refreshes the provider's fields and never overwrites the store's decision (`status`, `account_id`, `no_auto_link`). A card missing from the last complete read is shown as gone (`erp_edoc_account_gone()`).
- Every read is a full read. Paraşüt can list only the cards changed since a time (live, 2026-10-22): `filter[updated_at][gteq]=<date or ISO time>` (operators eq, lt, gt, gteq, lteq, not_eq), also with `filter[archived]=true`; 5 of the demo company's 13 cards for 2026-10-22. A bare `filter[updated_at]=` is `eq` and matched no card, not even at its own time; `gt` compares whole seconds; `updated_at` is no sort key. Such a read cannot see a card that is gone.

## What happens on its own?
- One thing: a card is linked to the single ERP account with the same tax key (`erp_edoc_accounts_autolink()`, `erp_edoc_account_tax_key()`), when the number is usable for its country and on no other card. A link somebody undid (`no_auto_link`) is not made again.
- The walk-in account and the final-consumer number `11111111111` are never linked and send no card code on invoices (`erp_edoc_account_link_code()`).
- A linked card's code goes with each invoice, so the provider puts the invoice on that card; a poll links an account without a card to the card its invoice landed on (`erp_edoc_account_link_from_invoice()`).

## How are differences written?
- Only on the operator's button: a card taken into the ERP (`erp_edoc_account_import()`; an account already carrying the number is linked instead), an account sent as a new card coded `PG-<account id>` (`erp_edoc_account_push()`), or a linked pair aligned field by field with a direction each (`erp_edoc_account_align()`): the provider first (the side that can refuse), then read back; a value it did not keep is named.
- Balance never travels, in either direction: each side's balance belongs to its own ledger.
- A driver that changes a provider card reads it first and writes it back with only the given fields changed (İşbaşı's `PUT firms` requires the balance fields; Paraşüt's PUT the same way).
- An ERP account is updated with the full row (`erp_edoc_account_save_data()`): `erp_account_save()` writes every column it knows on an update (erp-account-save-partial-writes.md).
