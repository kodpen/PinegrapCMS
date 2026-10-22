---
name: system-widget-functional-classes
description: Which pg-eo-* / pg-qty-stepper classes and data-* attributes in system widget trees are functional (keep) versus decorative (deletable).
---

## Which classes and attributes are functional (must not be deleted)?
JS/CSS behaviour hangs on them:
- `pg-eo-form` (form identification via `[data-pg-eo-form]`), `pg-eo-cc-fields` (CC payment toggle JS hook).
- `pg-eo-total-formatted` (install fee JS live total update), `pg-eo-installment-fee-row` / `pg-eo-installment-fee-value` (install fee JS toggle), `pg-eo-surcharge-row` (surcharge JS toggle).
- `pg-eo-terms-modal` (Bootstrap modal target), `pg-qty-stepper` (qty stepper JS limit wrapper).
- `pg-eo-applied-offers-anchor`, `pg-eo-saved-cart-anchor`, `pg-eo-upsell-anchor` (auto-injection detection); `pg-eo-applied-offers` (auto-inject self-detection).
- `data-*` attributes: `data-pg-qty-action`, `data-pg-remove-href`, `data-pg-eo-form`, `data-pg-cc-required` (JS hooks).

## Which classes are decorative (deletable, CSS hook only)?
- `pg-eo-item-cell`, `pg-eo-item-image`, `pg-eo-item-name`, `pg-eo-item-short-desc`, `pg-eo-item-full-desc`: no longer in the default tree (since 2026.1.26).
- `pg-eo-item-extras-anchor`: replaced; a text binding is used.

## What is the designer rule?
- A designer may delete an element, add a new one, make the same binding from the data binding menu, and the system keeps working. Classes are NOT the function; data binding is.
- Never hang new behaviour on a decorative class or add a new "magic" class: give the element a binding (system-widget-bindings.md).

Related: docs/memory/designer/system-widget-bindings.md
