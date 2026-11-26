---
name: erp-expense-change-lock
description: A change of an expense locks its row and is refused when it was paid or cancelled since the form read it, so it is never paid twice or rewritten behind its till movement
---

## What does a change do when the expense was paid or cancelled meanwhile?
- `erp_expense_save()` for an existing expense reads the row with `FOR UPDATE` inside its transaction and refuses when its status is no longer the one `erp_expense_save_check()` read: "The expense was paid or cancelled meanwhile; open it again to see it as it is now." Nothing is written.

## What was wrong before? (PR #11843)
- The status was read only before the transaction. A change sent with "Paid now" from one tab after the expense was paid from another (or through the API) wrote a second movement out of the till and moved `cash_id` to it; cancelling later put back only one. A change without paying wrote new figures over a paid expense, so it no longer matched the till movement, and a cancelled one could be changed and paid.
- Test: `tests/erp_expense_save_lock_test.php` (fake db layer in its own PHP process).

Related: docs/memory/erp/erp-expenses.md
