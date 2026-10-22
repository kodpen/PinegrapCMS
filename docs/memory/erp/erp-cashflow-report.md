---
name: erp-cashflow-report
description: ERP cash flow report: transfers excluded in all-till view, opening balance rule, period slice keys, payment-method labels, toolbar and tr.json traps.
---

## What are the cash flow report's rules? (phase 5, no schema change)
Files `includes/erp/cashflow.php`, `erp_cashflow.php` (gate `'cash'`); entry from the ERP menu (`USER_MANAGE_ERP_CASH` block) and the `erp_cash.php` toolbar.
- Transfers are not flow: in the all-tills view `doc_type = 'transfer'` is excluded (`erp_cashflow_scope_sql()`); in a single till and in the by-till table it is included. The sum of till closings = period closing; if that equality breaks, the rule was violated somewhere.
- Opening = `opening_balance` of the home-currency tills + the sum of `amount_base` before the period; a foreign-currency till's opening column is NOT added (the `erp_aging_cash_total()` rule). The `balance` cache is not used.
- Slice keys must be identical on both sides: day `doc_date` ↔ `Y-m-d`; week `YEARWEEK(doc_date, 3)` ↔ `date('oW')` (ISO, Monday); month `DATE_FORMAT '%Y-%m'` ↔ `date('Y-m')`. PHP walks the period day by day so empty slices exist too. Week start via `strtotime('YYYYWwwD')`.
- Payment method labels are the same five members as in `erp_receipt.php` (cheque included, 4.54); if the `erp_cash_payment_methods()` list changes, change both.

## What UI and translation traps exist on this screen?
- Several `form-control`s inside `.pg-toolbar`: do NOT give the form `flex-wrap`, and put `w-auto` on the fields; otherwise the 100% width of `form-control` throws every field onto its own line.
- Existing `tr.json` entries do not fit as screen labels: `From` → "Şundan", `To` → "Şuna", `Net` → "KDV Hariç", `Method` → "Metod". Use `Start` / `End` / `Net change` / a separate key. Check existing translations before writing a new key.
- Other ERP traps: docs/memory/erp/erp-translation-traps.md.

Related: docs/memory/erp/erp-aging-and-due-date.md
