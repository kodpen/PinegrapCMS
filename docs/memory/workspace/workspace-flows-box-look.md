---
name: workspace-flows-box-look
description: How a flow box looks (V1, V2 of plans/workspace-flows-look.md, PRs #3118, #3158): its group and colour per theme, its line icon, the 1.5 px border, the selection ring, control arrows
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

## Which icons? (V2, PR #3158)
- `FLOW_ICONS` in workspace.js: per kind the paths of a 16 × 16 line icon (the visual spec's where it names one; row, delay, end, notify, task_open, task_digest, checkin, start_action, start_flow, lead, mcp in the same style); an unknown kind a plain square. `flowIcon(kind, extra)` makes the SVG (`.ws-flow-icon`: 16 px, stroke `currentColor` 1.5 px, `aria-hidden`), lent as `K.flowIcon`; `flowIconPaths()` feeds the mini window, which draws it before the kind's name (`.ws-fd-icon`).
- The canvas draws boxes, palette, outline, template cards and the run's box list with it; `KINDS` and `PALETTE` carry no icon name. A new kind needs its icon (`test_workspace_flows_canvas_kinds_have_line_icons`).
- The scheduled-action drawing still takes the server's Bootstrap icons (`node.icon`) until V5 removes it.

## Arrows?
- Control arrows `.wsf-edge-line`: 2 px #7d8696 (3.67:1 on white), filled head `#wsf-arrow` in the same grey; selected, bad and the run view's taken (green) and not-taken (faded) arrows unchanged.

## Other drawings?
- A scheduled action drawn read-only (`showAction()`): `ACTION_KINDS` gives its boxes groups: when start, condition logic, step people, follow-up system (it starts another action). Their own `wsf-node-sa-*` colours went.
- The mini window (`flowWindowDraw()` in workspace.js) draws its own SVG (`.ws-fd-*`), without the group classes.
