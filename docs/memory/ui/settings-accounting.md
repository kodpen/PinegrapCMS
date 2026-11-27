---
name: settings-accounting
description: Site Settings › Accounting as folded rows: Enable the ERP module on top, the old ERP card cut into Documents, Sales, Web orders, Customer accounts, Foreign currency, Reminders; lines, pills, anchors
module: accounting_settings_fold
---

## How is the pane drawn?
- "Enable the ERP module" (`erp_enabled`, a `pg-switch` with its help) stands above the rows, in the pane's one form: the save checks it with the invoice series (`ui/settings-features.md`).
- Then folded rows (`pg_settings_fold()`, `ui/settings-fold-rows.md`): Documents, Sales, Web orders, Customer accounts, Foreign currency, Reminders and notices, E-Invoice, Accounting rules, Late payment fee, e-Fatura threshold, Stock planning, Document templates. Order, names, icons, lines: `includes/fn/accounting_settings_fold.php` (pure but for `lang()`); test `tests/settings_accounting_fold_test.php`.
- E-Invoice and e-Fatura threshold, where `pg_edoc_settings_shown()` / `erp_settings_efatura_threshold_shown()` is false, are no rows: their cards are drawn `d-none` after the rows, so a save keeps their values.
- How each row is drawn open: `ui/settings-accounting-open-rows.md`. Built in #11875, #11887, #11908 (the plan closed 2026-11-26).

## Where did the old ERP card's fields go?
- Until #11875 one ERP card held about forty fields in one column. Documents (`pgset-erp`, the old card's id): series, document numbers, tax name, second tax, the sale's address (Turkey only), payment term.
- Sales (`pgset-erp-sales`): walk-in sales account, prices on the till, Invoices change stock.
- Web orders (`pgset-erp-orders`): the invoice of a web order, its two filters, marketplace orders, the invoice e-mail and its message, tax on shipping and surcharges.
- Customer accounts (`pgset-erp-accounts`, drawn once one of its columns exists): credit limit, account prices in the store, Put it on my account.
- Foreign currency (`pgset-erp-currency`): the switch, the currencies, the exchange difference. Reminders and notices (`pgset-erp-notices`): overdue reminders, then notices as they happen.

## What does each row say closed?
- Documents "Series PGF · numbers by year · due in 30 days". Sales "Till prices with tax · invoices change stock · <walk-in account>". Web orders "Invoiced from the order screen" / "once paid" / "once shipped", "· e-mailed to the customer".
- Customer accounts "Over the limit: warned" / "refused", then "account prices in the store", "on account at checkout". Foreign currency "Off: everything in TRY" or the codes, pill On/Off. Reminders "Overdue after 7 days, daily at 09:00" or "No reminder", "· 2 notices on", pill On/Off.
- E-Invoice: "No e-document provider" (Off), "<name> · connected" (On), "<name> is not connected" (pill Not connected, attention: the pane opens on it). Late payment fee: rate or "No fee", e-Fatura threshold: the two amounts or "No warning", both with On/Off. Accounting rules, Stock planning, Document templates: a line, no pill.

## What must a change to the pane keep?
- Field names, values, `accounting.save.php` and one Save a pane; ids too, but the radio rows' (`ui/settings-accounting-open-rows.md`). A row a site lacks by its country gate is not drawn (`erp/erp-account-country-rules.md`); a feature that is off keeps its row.
- Anchors: `pgset-erp` lands on Documents (`erp/erp-module-basics.md` links it); a field moved to another row gets its line in `pg_settings_legacy_anchors()` and the registry's labels and keywords follow. Create Order's "Sales settings" opens Sales.

Related: docs/memory/ui/settings-fold-rows.md, docs/memory/ui/settings-accounting-open-rows.md, docs/memory/erp/erp-edoc-settings-card.md, docs/memory/erp/erp-settings-cards.md
