---
name: erp-edoc-account-autolink
description: Which provider cards ERP › Account sync links to accounts on its own after a read (erp_edoc_accounts_autolink()): active cards only, the shared number rule, what is left to the operator
---

## When does a card link itself to an account?
- After a read of the list, `erp_edoc_accounts_autolink()` (`includes/erp/edoc/account_sync.php`) links a card (`status = 'new'`, `no_auto_link = 0`) when: the card is active (`is_active = 1`), its number is valid for its country, no other active card of the provider carries the number, exactly one account carries it, and that account (not the walk-in one) has no card yet. Both providers (Paraşüt, İşbaşı) keep a passive or archived card with `is_active = 0`.
- A passive or archived card is kept and shown and may be linked by hand; it is never linked on its own, and an archived duplicate does not make the active card's number shared.

## Why only active cards?
- An invoice never goes to an archived card (Paraşüt `erp_edoc_parasut_linked_contact_usable()` searches by number and opens a new card instead), and a poll links the invoice's card only to an account with no card (`erp_edoc_account_link_from_invoice()`). Since archived cards came into the list (#884) the auto-link took them: an account with only an archived card was linked to it, its invoices opened a new card that never got linked, and every invoice read the archived card once more. An active card with an archived duplicate counted as shared and was no longer linked at all. Fixed in PR #1116 (issue #967).
- An account already linked to an archived card by that rule stays linked until someone unlinks it on the Account sync screen.

## Tests?
- `tests/erp_edoc_account_autolink_test.php` (the two queries); in the sandbox both cases from the issue, `development` against the fix.
