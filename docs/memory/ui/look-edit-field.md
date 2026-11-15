---
name: look-edit-field
description: Add and edit screens in look A: .form-label and .form-control in the screen's cards take the field label (13 / 550) and input (36 px) through one shared rule; what keeps its own
---

## What does the rule draw?
- Block "Look A: the field of add and edit screens" in `assets/css/backend.src.css`, after the add and edit block. Selector root: `body.pg-measure-edit main .card`, so a full-width card under the columns (Edit User's Store Credit) takes it too.
- `.form-label`: 13 px (`--pg-text-2`), weight 550 (`--pg-weight-strong`), 6 px over its field: the values of `.pg-field-label`.
- `.form-control` (input, `select.form-control`, textarea, a `.form-adorn` wrapper): 36 px high, 12 px across, 14 px text, corners 10, `--pg-field-bg`, `--pg-field-line`, `--pg-field-shadow`: the values of `.pg-input`. A textarea gets 9 px top and bottom and line height 1.5.
- `.form-control-sm` (a field in a row): 32 px, corners 9, 13 px, as `.pg-input-sm`.
- Focus: the accent border and a 4 px ring (`--pg-accent` 24 %), as `.pg-input:focus`. A field with `.is-invalid` or `.is-valid` keeps the panel's rose or green ring.

## How does it reach the screens?
- No screen's markup changes: the rule sets Bootstrap's `--bs-control-*` and `--bs-label-margin-bottom` variables, so the panel's `.is-invalid` / `.is-valid` border (set as a property) still wins.
- Every screen with `pg-measure-edit` takes it in its cards, the owner's kept screens (`product_builder`, `edit_user`, `edit_offer`) too: a shared rule of the family reaches them (`plans/panel-old-screens-rules.md`).
- Bootstrap utilities on a label (`small`, `fg-secondary`, `mb-0`) still win: their layer comes after `custom`.

## What keeps its own field?
- Settings: `.pg-settings-body` and `.pg-set-card` keep the 32 px row field and their 13 px label (`look-settings-screens.md`); the rule excludes them with `:not(.pg-settings-body *, .pg-set-card *)`; the settings pages' computed fields were compared before and after (unchanged).
- A drawer or dialog opened from the screen (`dialog *`, `.drawer *`: Edit User's permission drawers) keeps its field.
- A field inside an `.input-group` keeps the group's joined corners: the 10 px corners skip `.input-group > *`.
- The bare input inside an adorned wrapper (`.form-control.form-ghost` in `.form-adorn`, a money field with its unit) is left out: the wrapper draws the one frame and focus ring. Without it the money field of Add Gift Card drew two frames and two rings in focus (#9727).
- The visual designer and the site side load other stylesheets; a toolbar outside a card keeps Bootstrap's field.
- Pickers drawn by their own widget (the page picker `.pg-pfield`, 48 px; select2) are not `.form-control` and keep their box.
- A checkbox's or switch's own label (`.form-field > label` beside a `.check`) is not a `.form-label` and keeps its size.

Related: docs/memory/ui/look-type-and-recipes.md, docs/memory/ui/look-edit-screens.md
