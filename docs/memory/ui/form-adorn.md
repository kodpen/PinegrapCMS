---
name: form-adorn
description: Bootstrap 6 form-adorn in the panel (step B9f): which input-groups become a label wrapper with the add-on inside the border, the markup, the panel focus rules, the pilot places
---

## Which input-groups become form-adorn?
- One input with a text or icon add-on and nothing else. Groups with a button or a select, several inputs, or a hint (`title`) on the add-on stay `.input-group` .
- Panel only, never site output (5.3.8).

## What is the markup?
- `<label class="form-control form-adorn form-adorn-end">` holding `<span class="form-adorn-text" aria-hidden="true">₺</span>` and then the input with `form-ghost` in place of `form-control` (name, id, masks, `text-align` stay on it). Small: `form-control-sm` on the wrapper (no `.form-adorn-sm`).
- The add-on comes first in the DOM; `form-adorn-end` draws it after the input, without `-end` before it.
- The field's own `<label for>` stays outside the wrapper. No button or link inside the wrapper: it is a label.
- `aria-hidden` on the add-on: otherwise the symbol joins the input's accessible name.

## Why do the focus rules in backend.src.css name it?
- The panel's global `input:focus-visible` rule (custom layer) drew a ring around the borderless input inside the wrapper. `.form-adorn > .form-ghost:focus-visible` drops it; `.form-adorn:has(> .form-ghost:focus-visible)` joins the global ring list and `.form-control.form-adorn:focus-within` the focus border rule, so the wrapper looks focused like a plain field.
- The ring surrounds the whole field, add-on included.

## Where is it used?
- #2219 pilot (product Unit Price, offer subtotals), #2280 (shipping, container, `product_builder.php`, `page_form.php`), #2355 (ERP amounts, stock count scan with `form-control-lg`, order refund, barcodes), #2404 (gift cards, workspace template icon, MCP alias). Heights: 38, 31 (sm), 48 (lg) px. `tests/bs6_form_adorn_test.php` pins parts 4 to 9.
- #2478: toolbar searches (`view_folders.php`, `view_audit_log.php`, `view_not_found_log.php`; small, `rounded-pill` wrapper, `aria-label` on the input) and the ad region timing. `.pg-toolbar-search` (width, narrow-screen order) sits on the wrapper or its `<form role="search">`.
- #2498: the other toolbar searches (`translations.php`, four `erp_*` lists, workspace scripts) and the contact commission rate. The `<form>` drops `input-group`, so `#button_bar > form:not(.input-group)` lays it out.
- Part 8 (#2537): Paraşüt tax rate, till "Received" (`form-control-lg`), designer home `#sdh-filter` (`.sdh-head-tools` width rules name `.form-adorn`), offer tier fields. #2501 and part 9: settings cards, settings search, commerce settings (`form-adorn-settings-cards.md`).
- An add-on whose text a script changes keeps its id on the span (`#erp_expense_currency_label`) or on the element inside it (`#ws_tpl_icon_preview`).
- A disabled input leaves the wrapper white: a read-only MCP alias wrapper takes `bg-2`.
- The add-on is `aria-hidden`: every converted input needs a `<label for>` with its real id outside the wrapper, or an `aria-label` through `lang()`. #2583: labels naming another id fixed; container `#weightkg` and offer editor row fields named (`tests/panel_label_targets_test.php`).
- A wrapper toggled by script keeps its id (container `#weight_pound` / `#weight_kg`); `d-none` on the label works. `is-invalid` stays on the input (`:has()` marks the wrapper); its message goes after the wrapper (`inline-feedback-placement.md`).

## What stays an input-group on purpose?
- Several inputs or add-ons (shipping days, container dimensions), ERP +/-, `edit_erp_expense.php` files, a button (invoice scan, tax-number lookup, contact e-mail), a select (`view_contacts.php` filters, workspace "repeat every"), `label.unit` (rewritten by script), a `title` hint (product group address, start days, contact Parasut id).
- Settings cards: `form-adorn-settings-cards.md`. Order screens: quantity +/-, day/year and status selects, ship date adjustment. Site output (`includes/templates/`, `data/layouts`, returns, express order): 5.3.8.
