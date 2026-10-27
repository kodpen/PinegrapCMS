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

## Where was it piloted?
- `product_builder.php` Unit Price (default size), `includes/templates/edit_offer.php` upsell subtotal and `assets/js/offer_editor.js` subtotal condition (small, the last one rendered from JS), #2219. Heights unchanged: 38 px and 31 px.
- The other single-input groups wait for the owner's look at the pilot screenshots.
