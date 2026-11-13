---
name: workspace-flows-quick-setup
description: Flows quick setup server (flow_quick.php): catalogue of areas, starts, step groups, recipes; the choices the dialog sends; how they become a draft graph; the panel action
---

## What is it?
- A flow built by choices: a start, an optional "Only when", steps one after the other, a summary (owner, 2026-11-12, #8950, `workflow/decisions-made-workspace-20.md`). Code `includes/workspace/flow_quick.php`; tests `tests/workspace_flow_quick_test.php`; the drawing against the boxes: `workspace-flows-quick-setup-map.md`.
- It only creates: `ws_flow_quick_setup()` saves a new draft (`ws_flow_save()`), with publish also `ws_flow_publish()`; a refused publish keeps the draft (`publish_error`).

## What does the catalogue give?
- `ws_flow_quick_catalogue($fill)` (pure; `ws_flow_quick_for($viewer)` fills it): `areas` (key, label, icon, part top/site/other for the rail's lines, starts), `starts` by key, `groups` (key, label, kinds), `recipes`, `limits` (60 boxes, 5 conditions, depth 3).
- A start key is `event:<event>` or a start box kind. A start: kind, event, label (the "when" sentence or box label), name, record, read (Read a record's record and id, or null), fields `trigger.<key>` / `record.<key>` for "Only when" (none for row and web).
- Areas (`ws_flow_quick_areas()`): every catalogue event and start box in one site/other area, Often used repeats six (the test fails for a new event or start without one). Groups (`ws_flow_quick_groups()`): every step kind in one group or in `ws_flow_quick_kinds_left_out()` (End, End for each, value boxes); a new box kind such as WA7's WhatsApp box gets its line there.
- Recipes (`ws_flow_quick_recipes()`): key, title, icon, fits (start keys, or `record` for any start about a record), steps in the dialog's shape; one whose kinds the person cannot add is left out. `ws_flow_quick_recipes_for($catalogue, $key)` filters by start.
- The dialog still drops what the palette drops (`paletteItems()`: MCP without a server, row boxes without a table, AI not set up).

## What do the choices look like?
- `start`, `config` (the start box's settings; an event start keeps its event), `only_when` [{field, op, value}] (op of `ws_flow_condition_value_operators()`), `steps` [{kind, config, id?, steps?}], `name` ('' takes the start's label), `channel_id` (home), `publish`.
- Only a condition or a For each box holds steps; no loop in a loop, none empty.
- A sent id is kept when it is a box id not yet taken and not `start` or `read`, so a step reads an earlier one as `{{node.<id>.…}}`; else `step1`, `step2`… in order.

## How do the choices become a graph?
- `ws_flow_quick_graph()` (pure): `start`; `read` (record_get of the start's record) when a condition or a step reads `{{node.read.…}}`; one condition box (`only_when1`…) per condition, chained on yes; then the steps, each in the next column (256 apart), a held step 112 lower per depth.
- A step leads to the next on its first outcome (task done, approval approved, wait done); a sorting AI box on always (`ws_flow_quick_on()`).
- A condition's steps follow on yes, and what comes after follows its last step and its no (a join, `ws_flow_walk()`); a condition with no steps lets the rest follow on yes only.
- A For each box's steps follow on each into an End for each box it places (`loop_end1`…), which the rest follows on done.
- Refusals name the part (`start`, `steps`, `only_when`); a box's missing settings are draft problems.

## Who may use it?
- Whoever builds flows (`ws_flow_builder()`); a role-3 member only `ws_flow_member_kinds()` (starts manual, schedule, event, no_order); staff also join, row (a table to start on), mention, web; no_order by `ws_flow_no_order_refusal()`.

## Where is it wired?
- Panel action `ws_flow_quick_setup` (`workspace/actions.php`, listed in `panel/actions.php`): answers the flow (`ws_flow_detail()`), `published`, `publish_error`.
- `ws_flow_js_config()` mode flows serves the catalogue as `quick` to builders. The dialog and its ways in: `plans/flows-quick-setup.md` QS2-QS5.
