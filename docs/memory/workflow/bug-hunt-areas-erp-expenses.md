---
name: bug-hunt-areas-erp-expenses
description: The bug hunt of ERP expenses and repeating expenses (2026-11-26, PR #11843): three races fixed, what was read clean and what was left
---

## Which area was hunted?
- ERP expenses and repeating expenses, named by the architect on 2026-11-26 (request 3141): `includes/erp/expenses.php`, `includes/erp/expense_recurring.php`, the expense handlers of `includes/erp/api_resources.php`, `add_erp_expense.php`, `edit_erp_expense.php`.
- Looked for: a repeat writing a period twice, a paid expense cancelled without the till right, amounts cut by truncation, a receipt whose bytes are not its type, the API's cash gate.

## What did the hunt's PR fix?
- A change of an unpaid expense did not lock the row: sent with "Paid now" after it was paid elsewhere, it paid twice; new figures were written over a paid one (`erp/erp-expense-change-lock.md`).
- A paid expense's cancel judged the till right on the status the caller read before the cancel's check (`erp/erp-expense-form-checks.md`).
- A restart from two expenses of one stopped repeat took two locks and could leave two active repeats (`erp/erp-expense-recurring-restart.md`).

## What was read clean?
- The run (`erp_expense_recurring_run()`): each date is claimed by moving `next_date` in a conditional UPDATE, a failed write gives it back only while it is still the moved date; a restart goes on from the family's latest `next_date`.
- Amounts: `erp_kurus()`, `erp_split_gross()`, `erp_apply_rate()`, `erp_to_base()`, the typed-tax slack; no `(int)` cut of a fraction.
- Pay: row locked, status asked again, till and lock checks repeated inside. Cancel: row locked, reversal dated today.
- Receipts: type read from the bytes, the extension and served type follow it; replacing refused for a cancelled or locked expense.
- API: create with a till, pay (route scope `erp_cash:write` plus the owner's cash right) and a paid cancel all pass the cash gate; a paid expense refuses figure fields.

## What was left?
- With foreign currency switched off, `erp_expense_save_check()` writes the base currency over an unpaid foreign expense on any change, and the run writes a foreign repeat in the base currency; switching it off with foreign expenses is rare, not fixed.
- A repeat written as paid keeps paying from the till after its creator loses the till right: a product choice, named under "Asked" in the PR.
- The first receipt of a cancelled expense is taken through the API (only a replace is refused).

Related: docs/memory/workflow/bug-hunt.md, docs/memory/erp/erp-expenses.md
