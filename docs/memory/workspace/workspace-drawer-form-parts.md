---
name: workspace-drawer-form-parts
description: The Workspace kit's drawer form parts in look A (formParts, h.form, PGWsKit.form; #9887, #9909): what each part builds, the two plan drawers built from them, what stays on formRow()
---

## Where are the parts?
- `formParts` in `assets/js/workspace.js` (next to `formRow()` and `select()`), handed over as `form` by `featureHelpers()` and by `window.PGWsKit`.
- My calendar's plan drawer (`planDrawer()` in `workspace_my_calendar.js`) and its roadmap (`workspace_my_calendar_roadmap.js`) take them as `var part = h.form;` and build every field from them; they define no `field()` or `input()` of their own.
- "Add to the plan" (`eventForm()` in `workspace.js`) takes them as `var part = formParts;` since #9909. Every other form stays on `formRow()` (Bootstrap's small look).
- Neither drawer holds a Bootstrap form class (`form-control`, `form-label`, `form-text`, `input-group`, `row`/`col-*`, `.switch`, `btn btn-*`); the pickers they share (`peoplePicker`, the customer pickers) are not rebuilt.

## What does each part build?
- `field(label, control, help)`: `div.ws-plan-field`, a `label.pg-field-label` bound to the control (an id `ws-plan-N` when it has none), the control, an optional `pg-text-meta` help line.
- `group(label, control)`: the same with a `span.pg-field-label`, for a control that names itself (a `pg-seg`, a picker).
- `help(text)` `div.pg-field-help`; `error(text)` `div.pg-field-help.pg-field-error.d-none`.
- `input(type, value, small)`: `pg-input` (`pg-input-sm` when small); date and time get the `data-pg-*` hook (`dateField`). `textarea(value, rows)`: `pg-input`, 4 rows by default.
- `select(options, value, small)`: look A's recipe, `pg-input` on the select itself (`pg-input-sm` when small), since #9909.
- `pair(...)` `div.ws-plan-pair`; `switchRow(label, checked)` returns `{row, input}` (`label.ws-plan-switch` holding `input.pg-switch`); `seg(label, options, onPick, extra)` returns `{node, items}` (`div.pg-seg[role=group]` named by `label`, `button.pg-seg-item` per option; the caller sets `aria-pressed`).
- `save(text)` `pg-button pg-button-fill pg-button-sm`; `remove(text)` `pg-button pg-button-danger pg-button-sm me-auto`.

## How do the two drawers line up? (#9909)
- The shared look and its rule: `workspace-plan-drawers-look.md`. One order: Title; First day and Last day (a pair); All day; Start time and End time (a pair); the drawer's own fields; Note; Customer. "Add to the plan" puts Kind above Title, and Who (a `pg-seg` `ws-plan-who`: People, Department, Company as each is offered today, then the people picker or the Department select), then after Customer the meeting link and the Meeting note button.
- One wording: "Add to the plan" says "First day" (`first_day`), "Start time" and "End time" (`start_time`, `end_time`, used by no other form); the task form keeps "Starts" (`start_date`).
- The line under the header (`ws-plan-private`): "Add to the plan" shows `bi-people` and where the item goes, following Who (`event_where_people`, `_dept`, `_company`).
- The footer: Delete (`remove()`) at the left on an existing item, Save (`save()`) at the right; no Cancel, the cross or Esc closes it.
- The meeting link row `.ws-plan-meeting` (box, Jitsi, Join) wraps on a phone; "Repeat every" is `.ws-plan-every`, the number and its unit (`pg-text-meta`) on one line. A button in a field's place keeps its width (`.ws-plan-field > .pg-button`).
- What is posted to `ws_event_save` / `ws_event_delete` is unchanged; no caller of `eventForm()` changed.

## What is not a part?
- A roadmap step's done mark (`input.check`) and the repeat question's radios (in the `ask()` dialog, not the drawer).

Tests: `tests/workspace_my_calendar_test.php` (`test_workspace_my_calendar_drawer_builds_from_the_form_parts`), `tests/workspace_plan_drawers_look_test.php`.
