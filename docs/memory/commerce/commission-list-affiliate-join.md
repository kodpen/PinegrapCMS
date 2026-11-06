---
name: commission-list-affiliate-join
description: Commission and recurring profile lists join one contact per affiliate code (the oldest); the status totals read commissions alone
module: commission_list
---

## How do the commission lists find the affiliate's name?
- `pg_commission_affiliate_join($code_column)` (`includes/fn/commission_list.php`): a derived table of `MIN(id)` per non-empty `contacts.affiliate_code`, then `contacts` by that id. Used by `pg_commission_list_from()` and `pg_commission_profile_list_from()`. The oldest contact wins, as in the API (`api_commission_affiliates()`).
- Symptom fixed: two contacts sharing a code (imported before the importer refused a taken code, #7042) gave a list row per contact, so each commission or profile was listed twice.

## Where do the totals above the Commissions list come from?
- `view_commissions.php`: one `SUM(amount) … GROUP BY status` over `commissions` alone in the screen's date range; `pg_commission_list_totals()` turns the rows into the four status figures (minor units, 0 when missing).
- Before, four queries joined `contacts` and `orders` on the code without using them: a duplicated code counted the commission twice in Pending/Payable/Paid, so a payout read from the screen overpaid.
- Tests: `tests/commission_list_test.php`.
