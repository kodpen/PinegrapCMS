---
name: settings-accounting-tidy
description: Plan (step TR5 of settings-tidy-round): Site Settings › Accounting as folded rows, the flat ERP card cut into six by topic, steps SA1 to SA4
priority: 6
---

## What is asked?
- Owner (2026-11-21, 2026-11-26): old settings screens made modern (`settings-tidy-round.md`). Accounting's ERP card holds about forty fields in one flat column (`includes/settings/accounting.php:29-288`); the pane's other cards are short.

## What is there today? (read 2026-11-26)
- Cards: ERP (`pgset-erp`), E-Invoice (`pgset-invoice`), Accounting rules (`pgset-erp-rules`), Late payment fee (`pgset-late-fee`), e-Fatura threshold (`pgset-efatura-threshold`), Stock Planning (`pgset-stock-plan`), Document Templates (`pgset-erp-templates`).
- The ERP card, top to bottom: the module switch; series, numbers, tax names, the sale's address, payment term; walk-in account; the web order's invoice with its filters and the marketplaces; till prices; stock; the invoice e-mail; the credit limit; account prices and "Put it on my account"; tax on shipping; foreign currency; overdue reminders; notices as they happen.

## What must it never do?
- Change a field's name, id or value, a settings key, `accounting.save.php` or what a save writes; one Save a pane. "Enable the ERP module" stays at the pane's head, in the same form as the invoice series: the save checks one with the other (`ui/settings-features.md`).
- Lose an anchor: `pgset-erp` stays the first new card's id; every field that leaves it gets its line in `pg_settings_legacy_anchors()`, and the registry's labels and keywords follow.
- Hide the card of a feature that is off, or change what a country gate shows (`erp/erp-account-country-rules.md`): a row a site does not have is not drawn, as today.
- Change behaviour. A contradiction goes to the architect.

## Which rows, and what does each say closed?
- Documents (`pgset-erp`): "Series ABC · numbers by year · due in 30 days".
- Sales (`pgset-erp-sales`): "Till prices with tax · invoices change stock" and the walk-in account's name.
- Web orders (`pgset-erp-orders`): "Invoiced from the order screen", "Invoiced once paid" or "once shipped", then "e-mailed to the customer" when on.
- Customer accounts (`pgset-erp-accounts`): "Over the limit: warned" or "refused", then "account prices in the store" and "on account at checkout" when on.
- Foreign currency (`pgset-erp-currency`): "Off: everything in {base}" or the currencies allowed.
- Reminders and notices (`pgset-erp-notices`): "Overdue after 7 days, daily at 09:00" or "No reminder", and how many notices are on.
- E-Invoice, Accounting rules, Late payment fee, e-Fatura threshold, Stock planning, Document templates: each its own row with one line of its state; inside, what its function draws today.
- Pills: On or Off where a row is a feature; E-Invoice asks for attention when a provider is picked and not connected.

## How is a row drawn open?
- As `ui/settings-fold-rows.md`: one column, a group label where a row passes eight fields, every switch `pg-switch`, the three-way choices (the web order's invoice, till prices, the limit, tax on shipping) as radio rows with their one line each, a field that depends on a switch shown under it, never in a popover. Reminder channels as ticked chips; Frequency and Hour side by side.

## Which steps? (one at a time: one file; tests red first)
- SA1 (M) The fold and the cut: the ERP card's fields into the six cards, the rows' order, names, icons, lines and pills as pure functions in `includes/fn/accounting_settings_fold.php` with `tests/settings_accounting_fold_test.php`; anchors and registry.
- SA2 (M) after SA1: Documents, Sales and Web orders opened.
- SA3 (M) after SA2: Customer accounts, Foreign currency, Reminders and notices opened; the six short rows' buttons and labels.
- SA4 (S) Close-out: `ui/settings-accounting.md`, the survey's lines corrected, this file deleted.

Related: docs/memory/plans/settings-tidy-round.md, docs/memory/ui/settings-fold-rows.md, docs/memory/erp/erp-edoc-settings-card.md
