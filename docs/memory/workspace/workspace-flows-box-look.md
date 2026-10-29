---
name: workspace-flows-box-look
description: How a flow box looks (V1 of plans/workspace-flows-look.md, PR #3118): its group and colour per theme, the 1.5 px border, the selection ring, control arrows, scheduled-action boxes
---

## Which group is a box in?
- `KINDS[kind].group` in workspace_flow.js: start (manual, schedule, event, join, row), values (constants, value), logic (condition, delay, end), people (task, task_open, checkin, approval, task_digest, lead), message (post, email, notify, report), system (web_check, webhook, change, start_flow, start_action, mcp). The owner's plan lists them so; the add column (V4) lists them in that order.
- `kindClasses(kind)` gives a box and its outline entry `wsf-node-<kind> wsf-group-<group>`; an unknown kind `wsf-node-unknown`, no group (grey).
- A new kind needs its group, or `test_workspace_flows_canvas_every_kind_has_one_group` fails.

## Which colours?
- `.wsf-group-<group>` sets `--wsf-kind` once per theme in backend.src.css. Dark (`html[data-bs-theme="dark"]`): the visual spec's start #6fdca6, system #45c4d6, logic #e9b44c, message #6ea8ff, people #b49cff, values #e58fd0. Light: #1a7f4e, #0e7383, #8a5c00, #2f62c4, #6d4bd0, #a8468f, each at least 4.5:1 on white (`test_workspace_flows_canvas_group_colours` computes it).
- Before (until this step) only manual, task, end and the value boxes had a colour, as a 4 px left stripe.

## What takes the colour?
- The box's 1.5 px border all round, its kind label and icon (`.wsf-node-head`), its ports; a value box's border dashed. The outline entry keeps its left stripe in the group colour.
- Selected: a ring outside the border, `--wsf-ring` (#a78bfa dark, #7c3aed light), a 1.5 px gap in the ground colour between.
- A run view box's border takes its run state's line (`--wsf-run-line`); its label keeps the group colour. A problem box's border is red.

## Arrows?
- Control arrows `.wsf-edge-line`: 2 px #7d8696 (3.67:1 on white), filled head `#wsf-arrow` in the same grey; selected, bad and the run view's taken (green) and not-taken (faded) arrows unchanged.

## Other drawings?
- A scheduled action drawn read-only (`showAction()`): `ACTION_KINDS` gives its boxes groups: when start, condition logic, step people, follow-up system (it starts another action). Their own `wsf-node-sa-*` colours went.
- The mini window (`flowWindowDraw()` in workspace.js) draws its own SVG (`.ws-fd-*`), untouched by these classes.
