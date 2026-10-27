---
name: pg-combobox
description: data-pg-combobox on Bootstrap 6 Combobox (B9b, #2243): toggle and menu after the hidden select, silent fill, native change, empty item instead of clear, the select2 field left
---

## How is a combobox field drawn?
- `drawCombobox()` in `assets/js/pg_fields.js` keeps the `<select>` as the posted store (`name[]`, liveform, `required`) and hides it with `.pg-combobox-native` (visually hidden, still validated). After it: a `<button class="form-control combobox-toggle pg-combobox">` and a `.menu.pg-combobox-menu` with a `.menu-item[data-bs-value]` per option and a `.menu-header` per `<optgroup>`. Then `pgUI.combobox.get(toggle, {multiple, search, placeholder})`.
- The toggle takes the select's classes except `form-control`, `select2` (`form-control-sm`, `is-invalid` carry over) and is named by the field's `<label for>` (`aria-labelledby`). From 8 options on the menu has a search box; its words ("Search", "No results found") come from `pg_fields_script()` (`data-pg-search-label`, `data-pg-no-results-label`). The menu is at least as wide as the toggle (set on `show`).

## Why are the chosen items marked in the markup?
- Combobox posts only through its own hidden input (not used: no `name`) and fires `change.bs.combobox` only on a pick. Items carry `.selected` before it is created, so drawing fires nothing. A pick writes the options' `selected` and fires the select's native bubbling `change`: the ERP account fields re-rate taxes on it (`erp_invoice_editor.js`).
- The toggle text is ours: the chosen texts joined with ", " (Bootstrap writes an English "N selected" for several), the placeholder when nothing or the empty value is chosen.
- `pgCombobox.set()` and `pgCombobox.refresh()` (after a script added options: `erp_account_quick.js`) dispose and draw again.

## What replaced select2's clear button and new entries?
- No clear: an `<option value="">` is an item reading as `data-placeholder` (or a dash); picking it empties the choice. The two `data-allow-clear` selects (parent group on edit product group, the product group rows in `backend.src.js`) already have one.
- No new entries and no server search in Combobox: `data-pg-source` (google_product_category on product_builder) stays select2 (`pgFields.engine('combobox', el)` answers `v5`).

## When is it drawn?
- As chips (`pg-chips.md`): DOMContentLoaded, after `pgUI.ready()` when Bootstrap's module has not run, `pgInitInjected()` for injected markup. Never put `data-bs-toggle="combobox"` on the toggle (Bootstrap's DOMContentLoaded pass would create an instance with default options): the toggle's own click listener calls `toggle()`; without it a click does nothing. Several choosable: each item has a `.menu-item-check`.
- Tests: `tests/pg_fields_test.php` (`test_pg_fields_combobox_on_bootstrap_6`), `tests/pg_ui_test.php`.
