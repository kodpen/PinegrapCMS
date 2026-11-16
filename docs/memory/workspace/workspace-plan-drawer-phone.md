---
name: workspace-plan-drawer-phone
description: My calendar's plan drawer on a phone (#9858): why it scrolled sideways (hidden date inputs at full width), the step's calendar in a row of its own, "Day of the step", Who can see wrapping
---

## Why did the New plan drawer scroll sideways?
- The posted input of a date or time field is hidden by `.pg-field-native` (1 px, clipped) behind its typed box. In the drawer it also carries `.pg-input`, later in the same `@layer custom`, whose `width: 100%` won. Placed from a second column (Last day, End time, a step's day) it ran a column's width past the drawer: body 484 px in 319 at 320, 820 in 543 at 1440. `clip-path` does not remove it from the scroll area.
- Now `.pg-field-native.pg-field-native` in `backend.src.css`, before `.pg-input`: every `.pg-input` date or time field in the panel keeps its 1 px.

## Where does a step's date calendar open?
- In a drawer the calendar is drawn open in a `.pg-date-inline` wrapper after the box (`ui/pg-datepicker-drawer.md`). In the step row (a grid) it took a cell and pushed the row past the drawer. Now `.ws-roadmap-step > .pg-date-inline` spans the row (`grid-column: 1 / -1`) under the step; `.ws-roadmap-tools` is pinned to row 1, column 4 (column 3 on a phone) so Make a task and Remove stay beside the step.

## How is the step's date box named?
- Placeholder and `aria-label` "Day of the step" (`step_day` in `ws_personal_plan_steps_js_strings()`), as the title box shows "Step". The placeholder passes to the typed box (`drawParts()` in `pg_fields.js` copies it).

## How does Who can see fit 320 px?
- `.ws-plan-who` is a full-width flex row; its segments share the width and a long label breaks onto two lines (`white-space: normal`).
- Known, left: at 320 the Repeat ends select shows "Hiçbir zama…" cut inside its half-column box; nothing spills.

Test: `tests/workspace_personal_plan_steps_test.php` (`test_workspace_personal_plan_drawer_fits_a_phone`).
