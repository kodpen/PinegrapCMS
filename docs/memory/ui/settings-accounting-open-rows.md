---
name: settings-accounting-open-rows
description: Site Settings › Accounting, the ERP rows and the E-Invoice row drawn open (SA2, SA3): pg-switch rows, selects turned radio rows, ticked channels, what each row holds, the radio ids
module: accounting_settings_fold
---

## How are the opened rows drawn?
- One column, no popover, no `div.switch`, no `collapse-switcher` on a switch; a field depending on a switch is drawn under it whatever its state.
- Pure helpers in `includes/fn/accounting_settings_fold.php`: `pg_accounting_settings_switch($name, $on, $label, $help)` (a `pg-switch`, one `pg-field-help` line tied by `aria-describedby`).
- `pg_accounting_settings_choice($name, $legend, $options, $help)`: radio rows as `settings-card-markup.md`: `fieldset` > `legend.form-label.pg-field-label`, each option a `.form-field` with `input.radio`, its line a `div.form-text.mb-3` after it; the choice's own line under the fieldset.
- `pg_accounting_settings_tick($name, $on, $label)`: an inline `input.check` posting 1, as the currency list draws its ticks; `pg_accounting_settings_ticks($group, $legend, $ticks, $help)` puts them under one legend. No chip recipe exists in look A, so "ticked chips" are these inline ticks.
- Test: `tests/settings_accounting_open_test.php`. Source tests that list a card's field names (`settings_accounting_test.php`, `settings_accounting_fold_test.php`) read `name="…"` and the first argument of the switch, choice and tick helpers.

## What does each row hold?
- Documents (`pgset-erp`): series, document numbers, tax name, second tax, the sale's address (Turkey only, `d-none` elsewhere as before), payment term.
- Sales (`pgset-erp-sales`): walk-in sales account (select), Prices on the till as radio rows, Invoices change stock.
- Web orders (`pgset-erp-orders`): the invoice of a web order as three radio rows with the counter-sale line; its two filters, marketplace orders, the invoice e-mail with its message under it; Tax on shipping and surcharges as three radio rows.
- Customer accounts (`pgset-erp-accounts`): A sale past a customer's credit limit as two radio rows (warn, block) with the limit's line; Account prices in the store and Put it on my account at checkout as switches.
- Foreign currency (`pgset-erp-currency`): the switch, then the currencies allowed (a group) and the exchange difference switch under it, shown whatever its state.
- Reminders and notices (`pgset-erp-notices`): more than eight fields, so two group labels. Overdue receivable reminders: threshold days, Frequency and Hour side by side (`col-6` each, also on a phone), Send through: Panel notification, E-mail, Device notification as ticks, the customer reminder switch, Recipients. Notices as they happen: collection switch with its amount under it, low stock, quotes about to expire with their days, the Workspace follow-up.
- E-Invoice (`pgset-invoice`): the provider radios and Test the connection as before, Hand the document to GİB a switch, Enable Parasut Configuration a switch with the Paraşüt connection and its three fields under it (the popover `#ecommerce_parasut_configration_row` is gone).
- Accounting rules: its legend is `pg-field-label`, the Expense categories link a `pg-button-ghost pg-button-sm` (also on the ERP Settings screen, `includes/erp/settings_cards.php`). Late payment fee, e-Fatura threshold, Stock planning, Document templates draw what their functions drew.

## Which ids changed?
- A radio's id is its name and value, `default` for the empty value: `local_sale_prices_gross`/`_net`, `erp_auto_invoice_on_off`/`_order_paid`/`_order_shipped`, `erp_shipping_tax_default`/`_included`/`_none`, `erp_credit_limit_mode_warn`/`_block`. Names and values posted are those the selects and switches posted; `accounting.save.php` is unchanged. Old links to the field names open the row through `pg_settings_legacy_anchors()`.
- Built in SA2 (#11887) and SA3 (#11908).

Related: docs/memory/ui/settings-accounting.md, docs/memory/ui/settings-fold-rows.md, docs/memory/ui/settings-card-markup.md
