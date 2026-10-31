---
name: pg-combobox
description: data-pg-combobox on Bootstrap 6 Combobox (B9b, #2243, #2362): toggle and menu after the hidden select, silent fill, native change, empty item instead of clear, server search for data-pg-source
---

## How is a combobox field drawn?
- `drawCombobox()` in `assets/js/pg_fields.js` keeps the `<select>` as the posted store (`name[]`, liveform, `required`) and hides it with `.pg-combobox-native` (visually hidden, still validated, `tabindex=-1`). After it: a `<button class="form-control combobox-toggle pg-combobox">` and a `.menu.pg-combobox-menu` holding a `[role=listbox]` with a `.menu-item[data-bs-value][role=option]` per option and a `[role=group]` with a `.menu-header` per `<optgroup>`. Then `pgUI.combobox.get(toggle, {multiple, search, placeholder, searchNormalize: true})`.
- The toggle takes the select's classes except `form-control`, `select2` (`form-control-sm`, `is-invalid` carry over); its name and value, the listbox and the picked state: `pg-fields-speech.md`. From 8 options on the menu has a search box; its words ("Search", "No results found") come from `pg_fields_script()` (`data-pg-search-label`, `data-pg-no-results-label`). The menu is at least as wide as the toggle (set on `show`).
- Search is Bootstrap's `_filterItems()` (the "No results" line stays in step); `searchNormalize` strips accents and the dot of İ, else "ist" never found İstanbul (`toLowerCase()` gives i + U+0307). ı stays unfolded, accepted. No own filter.

## Why are the chosen items marked in the markup?
- Combobox posts only through its own hidden input (not used: no `name`) and fires `change.bs.combobox` only on a pick. Items carry `.selected` before it is created, so drawing fires nothing. A pick writes the options' `selected` and fires the select's native bubbling `change`: the ERP account fields re-rate taxes on it (`erp_invoice_editor.js`).
- The toggle text is ours: the chosen texts joined with ", " (Bootstrap writes an English "N selected" for several), the placeholder (`data-placeholder`, else the empty option's text) when nothing or the empty value is chosen.
- `pgCombobox.set()` and `pgCombobox.refresh()` (after a script added options: `erp_account_quick.js`) dispose and draw again.

## What replaced select2's clear button and new entries?
- No clear: an `<option value="">` is an item reading as `data-placeholder` (or a dash); picking it empties the choice. The two `data-allow-clear` selects (parent group on edit product group, the product group rows in `backend.src.js`) already have one.
- New entries only through server search with `data-pg-free-text`: a `data-pg-source` field is a Combobox that asks the server as you type (`pg-combobox-server-search.md`).

## Does the panel still load select2?
- No (#2428): `output_control_panel_header_includes()` loads no select2 sheet or script and `backend.src.css` has no `.select2` rule (`tests/panel_select2_test.php`). `assets/lib/select2` stays: the `v5` engine of `pg_fields.js` draws a `data-pg-source` field as a select2 on a site page without Bootstrap 6 that loads select2 itself. No product PHP loads the folder any more.

## When is it drawn?
- As chips (`pg-chips.md`): DOMContentLoaded, after `pgUI.ready()` when Bootstrap's module has not run, `pgInitInjected()` for injected markup. Never put `data-bs-toggle="combobox"` on the toggle (Bootstrap's DOMContentLoaded pass would create an instance with default options): the toggle's own click listener calls `toggle()`; without it a click does nothing. Several choosable: each item has a `.menu-item-check`.
- Inside a `.collapse` (a switch panel, which v6 clips with `overflow-y:clip`) the toggle gets `data-bs-strategy="fixed"`, so the menu is not cut off at the panel's edge: the product builder's "Allowed Zones" in "Shippable" (#2585).
- Tests: `tests/pg_fields_test.php` (`test_pg_fields_combobox_on_bootstrap_6`, `test_pg_fields_combobox_server_search`), `tests/pg_ui_test.php`.

Related: docs/memory/ui/pg-combobox-server-search.md, docs/memory/ui/pg-fields-speech.md
