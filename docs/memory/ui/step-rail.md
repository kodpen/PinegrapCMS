---
name: step-rail
description: Numbered step rail for top-to-bottom screens (edit_offer.php): .pg-step classes, Bootstrap-only rule for everything else, d-none to toggle visibility.
---

## What is the step rail and where does it live?
- Screens whose sections are read top to bottom use a numbered rail: a numbered node on the left with a line joining the nodes, an ordinary Bootstrap card on the right. Bootstrap has no stepper.
- Classes are in `assets/css/backend.src.css`, end of file, block *Admin screen step rail*: `.pg-step`, `.pg-step-rail`, `.pg-step-node` (+ `.pg-step-done` / `.pg-step-todo` state colours). The block is shared; new screens use these classes and never write their own copy.

## What must stay plain Bootstrap?
- Everything outside the rail is Bootstrap 6. Do not invent width classes: field width comes from the grid with breakpoint prefixes (`col-7 sm:col-5 md:col-3 xl:col-2`), rows are `list-group list-group-flush`, labels `badge`, error text `small fg-emphasis-danger`, alignment with `d-flex` / `ms-auto` / `gap-*`.
- No `max-width` on the screen: admin screens are `container-fluid` width.

## How is visibility toggled?
- With `d-none`, the panel's convention; the `show(el, on)` helper in `offer_editor.js` does it in one place. Under Bootstrap 6 `hidden` would also hide a `d-flex` row (`[hidden]` keeps `!important`), but an inline display beats `d-none` (`panel-layout-pitfalls.md`).
