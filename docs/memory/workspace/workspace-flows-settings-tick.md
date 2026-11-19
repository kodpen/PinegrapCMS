---
name: workspace-flows-settings-tick
description: How a tick in a flow box's settings is drawn (field() in workspace_flow.js, #10274): the box before its label on one line, help under the label, .pg-checkbox
---

## How does a tick in a flow box's settings look?
- The box stands before its label on one line, the help text under the label (indented with it), as the panel's forms draw a check. Its label is regular weight; the labels of the other fields stay semibold above their controls.
- The box is the look's `.pg-checkbox` (`ui/look-type-and-recipes.md`), not Bootstrap's `.check`.

## Where is it drawn?
- `field(label, control, help)` in `assets/js/workspace_flow.js` takes its own branch when the control is an `input[type=checkbox]`: `div.d-flex.align-items-start.gap-3` holding the box (`mt-1` to sit on the label's first line) and a `div.flex-fill` with the label and the help.
- Callers only build `el('input', 'pg-checkbox')` with `type = 'checkbox'` and hand it to `field()`: `tickField()`, the WhatsApp box's e-mail fallback, the check box's redirect tick, the lead box's private channel, the list-records box's "Only records new since the last run".
- List rows of ticks (`label.wsf-person` with a checkbox inside: people, report counts) are not drawn through `field()` and are unchanged.

## What symptom did it fix?
- Before #10274 `field()` put the label first and the control after it, so a tick stood after its label, raised at the label's end, with the help under both.

Test: `tests/workspace_flow_tick_field_test.php`.
