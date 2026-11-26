---
name: settings-accounting-open-rows
description: Site Settings › Accounting, the Documents, Sales and Web orders rows drawn open (SA2): pg-switch rows, the three selects turned radio rows with one line each, what each row holds, the radio ids
module: accounting_settings_fold
---

## How are the opened rows drawn?
- One column, no popover, no `div.switch`, no `collapse-switcher`; a field depending on another is drawn under it whatever its state.
- Pure helpers in `includes/fn/accounting_settings_fold.php`: `pg_accounting_settings_switch($name, $on, $label, $help)` (a `pg-switch`, one `pg-field-help` line tied by `aria-describedby`).
- `pg_accounting_settings_choice($name, $legend, $options, $help)`: radio rows as `settings-card-markup.md`: `fieldset` > `legend.form-label.pg-field-label`, each option a `.form-field` with `input.radio`, its line a `div.form-text.mb-3` after it; the choice's own line under the fieldset. Test: `tests/settings_accounting_open_test.php`.
- Source tests that list a card's field names (`settings_accounting_test.php`, `settings_accounting_fold_test.php`) read `name="…"` and the first argument of these two helpers.

## What does each row hold?
- Documents (`pgset-erp`): series, document numbers, tax name, second tax, the sale's address (Turkey only, `d-none` elsewhere as before), payment term; each help line tied to its field.
- Sales (`pgset-erp-sales`): walk-in sales account (select), Prices on the till as radio rows (with tax / without tax, a line each), Invoices change stock (`pg-switch`).
- Web orders (`pgset-erp-orders`): The invoice of a web order as three radio rows (order screen, once paid, once shipped) with the counter-sale and marketplace line under them; Smallest order invoiced on its own, Only for buyers with a tax number, Also invoice marketplace orders, E-mail each issued invoice with its message under it; Tax on shipping and surcharges as three radio rows (by the store's country, included, not taxed).
- Eight fields at most a row, so no group label.

## Which ids changed?
- A radio's id is its name and value, `default` for the empty value: `local_sale_prices_gross`/`_net`, `erp_auto_invoice_on_off`/`_order_paid`/`_order_shipped`, `erp_shipping_tax_default`/`_included`/`_none`. Names and values posted are those the selects posted; `accounting.save.php` is unchanged. Old links to the field names open the row through `pg_settings_legacy_anchors()`.
- Built in SA2 (#11887).

Related: docs/memory/ui/settings-accounting.md, docs/memory/ui/settings-fold-rows.md, docs/memory/ui/settings-card-markup.md
