---
name: form-adorn
description: Bootstrap 6 form-adorn in the panel (step B9f): which input-groups become a label wrapper with the add-on inside the border, the markup, the panel focus rules, the pilot places
---

## Which input-groups become form-adorn?
- One input with a text or icon add-on and nothing else. Groups with a button or a select, several inputs, or a hint (`title`) on the add-on stay `.input-group` (`plans/bootstrap-6-components.md`).
- Never in site output (5.3.8): the panel only.

## What is the markup?
- `<label class="form-control form-adorn form-adorn-end">` holding `<span class="form-adorn-text" aria-hidden="true">₺</span>` and then the input with `form-ghost` in place of `form-control` (name, id, masks and inline `text-align` stay on the input). Small: add `form-control-sm` to the wrapper (the dist has no `.form-adorn-sm`).
- The add-on comes first in the DOM; `form-adorn-end` draws it after the input. Without `-end` it sits before the input.
- The field's own `<label for>` stays outside the wrapper. No button or link inside the wrapper: it is a label.
- `aria-hidden` on the add-on: otherwise the symbol joins the input's accessible name.

## Why do the focus rules in backend.src.css name it?
- The panel's global `input:focus-visible` rule (custom layer, beats Bootstrap's forms layer) drew a ring around the borderless input inside the wrapper. `.form-adorn > .form-ghost:focus-visible` drops it; `.form-adorn:has(> .form-ghost:focus-visible)` joins the global ring list and `.form-control.form-adorn:focus-within` the focus border rule, so the wrapper looks focused like a plain field.
- Result: the ring surrounds the whole field, add-on included; on an input-group it stopped at the add-on.

## Where is it used?
- Pilot #2219: `product_builder.php` Unit Price, `includes/templates/edit_offer.php` upsell subtotal, `assets/js/offer_editor.js` subtotal condition. Heights unchanged: 38 px and 31 px (small).
- #2280: shipping method and zone rates, container weight and cost, the rest of `product_builder.php`, `includes/forms/page_form.php` (40 places).
- Part 3 (#2355): ERP amounts (expense, receipt, transfer, account opening amount, till opening balance), the stock count scan field (`form-control-lg` on the wrapper, icon add-on), the order refund amount (`view_order.php`), the barcode increase/decrease fields. Heights unchanged: 38 px, 48 px (large).
- An add-on whose text a script changes keeps its id on the span (`#erp_expense_currency_label`).
- The add-on is `aria-hidden`, so every converted input needs its own name: a `<label for>` outside the wrapper, or `aria-label` through `lang()` (barcode fields, whose only text was the add-on).
- A wrapper toggled by script keeps its id (container `#weight_pound` / `#weight_kg`); `d-none` on the label works. `is-invalid` stays on the input: Bootstrap marks the wrapper through `:has()`.

## What stays an input-group on purpose?
- Several inputs or add-ons (shipping days, container dimensions), ERP number +/- controls, `edit_erp_expense.php` file inputs, the invoice form scan field (button), the account tax-number lookup (button), the product dimension units (`label.unit`, rewritten by script), add-ons with a `title` hint (product group address, product start days).
- Settings cards: the `.pg-set-card` row rules and the `pg-f-*` caps in backend.src.css name `.input-group`; a settings part changes them together with the markup.
- Site templates (`includes/templates/` order screens, `data/layouts`): 5.3.8.
