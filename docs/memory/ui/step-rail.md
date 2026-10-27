---
name: step-rail
description: Numbered steps on panel screens: Bootstrap 6 .stepper (edit_offer cards, installer, ERP import), why every edit_offer item is .active, theme-reset on the cards, the workspace flow's own .pg-step rail
---

## Which screens show numbered steps, and with what?
- Bootstrap 6's `.stepper` (`<ol class="stepper">`, `<li class="stepper-item">`; the number is a CSS counter, never written in the markup) since step B9g (#2206):
  - `edit_offer.php` (`includes/templates/edit_offer.php`): `.stepper.pg-stepper-cards`, one card per step.
  - The installer (`install/index.php`, `#pg_rail`): each step's text is a `button.pg-install-step` with `data-step` inside the item.
  - ERP account import (`erp_import_steps()` in `erp_accounts_import.php`): `.stepper-horizontal`.
- The Workspace automation flow (`workspace.js`, `.ws-sa-flow`) keeps its own `.pg-step`, `.pg-step-rail`, `.pg-step-node` rail (`backend.src.css`, block *Step rail of the workspace automation flow*); new screens use `.stepper`.

## How are the states coloured?
- Bootstrap colours only `.active` items (node and the line to the next `.active` item), in the item's `theme-*` or primary.
- edit_offer marks every item `.active`; `offer_editor.js` `step()` swaps `theme-success` (answered), `theme-danger` (the offer cannot do anything yet) or `theme-secondary` (neither; Options always).
- Installer and ERP import: finished steps `.active.theme-success`, the current one `.active` with `aria-current="step"`, later ones plain. The installer's finished node shows a check (`.done::before`).

## What does `.pg-stepper-cards` change, and why?
- The stepper sizes rows evenly (`grid-auto-rows: 1fr`) and its text column to the content: one tall card would stretch every step and a wide one overflow. The block sets `grid-auto-rows: auto`, a `minmax(0, 1fr)` column, `--bs-stepper-align-items: start`, and moves the node and the line to sit level with the card header.
- A theme class on the item would reach every button, switch and badge in the card: each card carries `.theme-reset`.

## What must stay plain Bootstrap?
- Everything inside a step is Bootstrap 6: field width from the grid (`col-7 sm:col-5 md:col-3 xl:col-2`), rows `list-group list-group-flush`, labels `badge`, error text `small fg-emphasis-danger`, alignment with `d-flex` / `ms-auto` / `gap-*`. No `max-width`: admin screens are `container-fluid` width.
- Visibility with `d-none`; the `show(el, on)` helper in `offer_editor.js` does it in one place (an inline display beats `d-none`, `panel-layout-pitfalls.md`).
