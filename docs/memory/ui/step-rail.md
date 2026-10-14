---
name: step-rail
description: Numbered step rail for top-to-bottom screens (edit_offer.php): .pg-step classes, Bootstrap-only rule for everything else, d-none instead of hidden.
---

## What is the step rail and where does it live?
- Screens whose sections are read top to bottom use a numbered rail: a numbered node on the left with a line joining the nodes, an ordinary Bootstrap card on the right. Bootstrap has no stepper.
- Classes are in `assets/css/backend.src.css`, end of file, block *Admin screen step rail*: `.pg-step`, `.pg-step-rail`, `.pg-step-node` (+ `.pg-step-done` / `.pg-step-todo` state colours). The block is shared; new screens use these classes and never write their own copy.

## What must stay plain Bootstrap?
- Everything outside the rail is Bootstrap. Do not invent width classes: field width comes from the `col-*` grid (`col-7 col-sm-5 col-md-3 col-xl-2`), rows are `list-group list-group-flush`, labels `badge`, error text `small text-danger-emphasis`, alignment with `d-flex` / `ms-auto` / `gap-*`.
- No `max-width` on the screen: admin screens are `container-fluid` width.

## How is visibility toggled?
- With `d-none`, NOT `hidden`. Bootstrap display utilities (`d-flex` / `d-block`) carry `!important` and beat the `[hidden]` rule; an element with a grid or flex class cannot be hidden with `el.hidden = true`. The `show(el, on)` helper in `offer_editor.js` does this in one place.
