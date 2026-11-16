---
name: workspace-drawer-form-parts
description: The Workspace kit's drawer form parts in look A (formParts, h.form, PGWsKit.form; #9887): what each part builds, who is built from them, what stays on formRow()
---

## Where are the parts?
- `formParts` in `assets/js/workspace.js` (next to `formRow()` and `select()`), handed over as `form` by `featureHelpers()` and by `window.PGWsKit`.
- My calendar's plan drawer (`planDrawer()` in `workspace_my_calendar.js`) and its roadmap (`workspace_my_calendar_roadmap.js`) take them as `var part = h.form;` and build every field from them; they define no `field()` or `input()` of their own. "Add to the plan" (`eventForm()`) moves onto them in PD2 of plan-drawers-one-look; every other form stays on `formRow()` (Bootstrap's small look).

## What does each part build?
- `field(label, control, help)`: `div.ws-plan-field`, a `label.pg-field-label` bound to the control (an id `ws-plan-N` when it has none), the control, an optional `pg-text-meta` help line.
- `group(label, control)`: the same with a `span.pg-field-label`, for a control that names itself (a `pg-seg`, a picker).
- `help(text)` `div.pg-field-help`; `error(text)` `div.pg-field-help.pg-field-error.d-none`.
- `input(type, value, small)`: `pg-input` (`pg-input-sm` when small); date and time get the `data-pg-*` hook (`dateField`). `textarea(value, rows)`: `pg-input`, 4 rows by default.
- `select(options, value, small)`: still `form-control` (`form-control-sm`): the drawer's selects as before; PD2 gives it look A's recipe.
- `pair(...)` `div.ws-plan-pair`; `switchRow(label, checked)` returns `{row, input}` (`label.ws-plan-switch` holding `input.pg-switch`); `seg(label, options, onPick, extra)` returns `{node, items}` (`div.pg-seg[role=group]` named by `label`, `button.pg-seg-item` per option; the caller sets `aria-pressed`).
- `save(text)` `pg-button pg-button-fill pg-button-sm`; `remove(text)` `pg-button pg-button-danger pg-button-sm me-auto`.

## What is not a part?
- A roadmap step's done mark (`input.check`) and the repeat question's radios (in the `ask()` dialog, not the drawer).

Test: `tests/workspace_my_calendar_test.php` (`test_workspace_my_calendar_drawer_builds_from_the_form_parts`).
