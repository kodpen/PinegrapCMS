---
name: workspace-flows-box-look
description: How a flow box looks: a 196 x 72 card, group colour on tile, kind and port, three cut lines, tooltip, 8 px ports, ring, problem and run lines, 1.75 px arrows, List view entry
---

## Which group is a box in?
- `KINDS[kind].group` in workspace_flow.js: start (manual, schedule, event, join, row), values (constants, value), logic (condition, delay, for_each, for_each_end, end), people (task, task_open, checkin, approval, task_digest, lead), message (post, email, notify, report), system (web_check, webhook, change, start_flow, start_action, mcp, row_add, row_set, row_get).
- `kindClasses(kind)` gives a box and its List view entry `wsf-node-<kind> wsf-group-<group>`; an unknown kind `wsf-node-unknown`, no group (grey).
- A new kind needs its group, or `test_workspace_flows_canvas_every_kind_has_one_group` fails.

## Which colours?
- `.wsf-group-<group>` sets `--wsf-kind` once per theme in backend.src.css. Dark (`[data-bs-theme="dark"]`, any dark subtree, so the Design examples' dark pane too): start #6fdca6, system #45c4d6, logic #e9b44c, message #6ea8ff, people #b49cff, values #e58fd0. Light: #1a7f4e, #0e7383, #8a5c00, #2f62c4, #6d4bd0, #a8468f, each at least 4.5:1 on white (`test_workspace_flows_canvas_group_colours` computes it).

## How is a box drawn? (FE1, #11083)
- A card (`.pgc-node.wsf-card`), 196 × 72 px (`BOX_W`, `BOX_H` of workspace_flow.js; the canvas adapter's `size()` hands them to the core, which places, fits and routes arrows by them): the card surface gradient, the hairline (`--pg-border-color-translucent`), `--pg-shadow-card`, radius 13 px. The group colour is never on the border.
- `cardFace()`: a 30 px `.pg-icon-tile.wsf-node-tile` with the kind's line icon, its `--pg-tone` the group colour; beside it `.wsf-node-text` with three lines: the kind in capitals in the group colour (`.wsf-node-head`, which also holds the warning mark and the run or AI badges), the title, the line of what it does (second ink). Each is cut with an ellipsis.
- The tooltip holds the three lines whole (`data-face`), then the box's problems and warnings (`data-told`, `markProblems()`); `boxTip()` joins them. In the run view the run's result follows the lines.
- Ports: 8 px dots in the group colour centred on the edge, a 2 px ring in the canvas ground; the in port shows only while the box is pointed at, in focus, chosen or an arrow's target. The 24 px hit target stays.
- Chosen: the ring outside the border (`--pgc-ring`, #a78bfa dark, #7c3aed light) over the card shadow. A problem box (`wsf-bad`) has the red border and glow; a run view box its run state's line (`--wsf-run-line`) on the border, a box no run reached dashed. A value box's border is dashed.
- The scheduled-action drawing (`showAction()`) draws the same card with its Bootstrap icon in the tile.

## Arrows?
- Flow arrows `.pgc-edge-line` are 1.75 px #7d8696 under `.wsf-app` and `.wsf-embed` (the core's 2 px stays for other canvases), filled head `#wsf-arrow`; chosen, bad, taken (green) and not-taken arrows unchanged. Outcome names on pills: `workspace-flows-arrow-names.md`.

## And the List view?
- Each entry is the same card (hairline, surface, shadow, radius 13 px; no left stripe): its heading the kind's tile, then the kind in capitals over the title (`.wsf-outline-text`). In the run view its border takes the run state's line.

## Which icons?
- `FLOW_ICONS` in workspace.js: per kind the paths of a 16 × 16 line icon; an unknown kind a plain square. `flowIcon(kind, extra)` makes the SVG (`.ws-flow-icon`), lent as `K.flowIcon`. A new kind needs its icon (`test_workspace_flows_canvas_kinds_have_line_icons`).

## Where is the model?
- Design examples › Patterns › "Flow box" (`pg_look_examples_flow_box_pattern()`, `tests/look_examples_test.php`): a chosen Condition with Yes / No pills, an End, an e-mail box with a problem, a value box. The mini window (`.ws-fd-*`) keeps its own drawing.
