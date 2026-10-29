---
name: workspace-field-names
description: How Workspace drawer and dialog fields get their screen-reader names: formRow(), saLabelled(), ask(), labelTarget()/hasName(), the pickers
---

## How does a Workspace caption name its field?
- `formRow()` (`assets/js/workspace.js`) points its caption at `labelTarget(control)`: the control itself, or the first input, select or textarea inside a wrapper (an input group). An id-less field gets a `ws-field-` id. A wrapper's field that already has a name (a radio with its own label, a search box with an `aria-label`) keeps it.
- A picker box with a role of its own (`peoplePicker` is `role=group`, `colorPicker` a radiogroup) is named as a group: the caption gets an id and the box `aria-labelledby`, unless it is named already (`colorPicker` carries `aria-label` Colour, as the icon grid carries Icon).
- `saLabelled()` (record drawer quick edit, quick create) ties its label the same way. `saValueInput()` stays directly above it: `tests/workspace_change_limits_test.php` reads the one up to the other.

## Why does hasName() search the tree for label[for]?
- Forms are built off the page; `.labels` is empty for a detached control, so a radio's own label would be missed and the caption added to it. `hasName()` checks `aria-label`, `aria-labelledby`, `.labels`, a wrapping label, and `label[for=id]` in the control's own tree.

## How is the question dialog named?
- `ask()` gives its `<dialog>` `aria-labelledby="ws-ask-text"` (the question paragraph) and names the first unnamed detail control (thread rename, Move to a group, a share select or people box) with the question. Flows reaches `ask()` and `formRow()` through the kit; both only add a name where none exists.

## What carries its own name?
- A control created after `formRow()` (the forward-to-note picker, `fwd_note_pick`), the record search kind select (`ref_kind`, "Kind of record"), the notes share Can read / Can edit select (`notes_share_right`, "Access").
- `peoplePicker().set()` (a task-check suggestion adds a person) updates `aria-pressed` with `.active`.

Test: `tests/workspace_field_names_test.php`.
