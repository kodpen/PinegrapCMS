---
name: look-workspace-fields
description: WL8: formRow() is the form parts' field(), the form-control and form-label lines of assets/js/workspace.js on pg-input and pg-field-label, input groups, the CSS that followed, what is left
---

## How is a Workspace form row built now? (WL8, #11391)
- `formRow(label, control, help)` in `assets/js/workspace.js` returns `formParts.field()`: `div.ws-plan-field`, `label.pg-field-label`, the control, a `pg-text-meta` help line. Every caller, in `workspace.js` and through `featureHelpers()` (`h.formRow`) in the other scripts, moved with it.
- `field()` took formRow's naming: the caption binds the control or a wrapper's first unnamed field; a picker box with a role is named through `aria-labelledby` (`tests/workspace_field_names_test.php`).
- The row's gap is `.ws-plan-field`'s 16px margin. A row that must sit flush (quiet hours' From and To, Add people) gets `ws-field-flush`; `mb-*` utilities on the row no longer place it.

## Which classes did workspace.js's fields take?
- `form-control form-control-sm` became `pg-input pg-input-sm`, `form-control` `pg-input`, also on the shared `select()` helper (`h.select`, `K.select`), so its callers in the other scripts moved too.
- `form-label` (with `small mb-1`) became `pg-field-label`; spacing utilities that place a caption (`mt-3`, `mt-5`) stay.

## How does a field joined to buttons look?
- An `.input-group` keeps its markup: the field `pg-input`, the buttons `pg-button pg-button-quiet` (`pg-button-sm` in an `input-group-sm`). The joined corners come from the restated `.input-group.input-group` rules; `.input-group > .pg-input` takes the room left and a button in a group is as tall as the field.

## Which CSS followed?
- Rules that named `.form-control` for these fields name `.pg-input`: `.ws-look-row > .pg-input`, the poll window's 16px touch size (`#ws-poll-form .pg-input`, `tests/workspace_phone_touch_test.php`), `.wsf-share-level select.pg-input`.
- `.pg-input.ws-text-area` and `.pg-input.ws-copy-area` carry two classes: the recipe's textarea height and size come later in `backend.src.css`.

## What is left in workspace.js?
- 15 `form-text` lines (help lines outside formRow) and 4 old buttons (context menu rows, pressed toggles; `look-workspace-main-script.md`). The other scripts' `form-control` and `form-label` lines: `look-workspace-fields-scripts.md` (WL9).

Related: docs/memory/ui/look-workspace-fields-scripts.md, docs/memory/ui/look-check.md, docs/memory/workspace/workspace-drawer-form-parts.md
