---
name: workspace-flows-member-canvas
description: The Flows screen for a role-3 member building their own flows (#3345 R2): the entry, the palette from ws_flow_member_kinds(), refused boxes marked, no home or shares, staff-only templates and AI draft
---

## Who sees Flows now?
- `ws_flows_nav_visible()`: staff (`ws_can_schedule()`) and a role-3 member who builds (`ws_flow_member()`), besides home readers and sharees. The menu entry is `ws_nav_groups()` reading it.
- `ws_flows_scheduled()` stays staff's: a member's list has no scheduled-actions groups.
- The list (`ws_flows_list()`) is unchanged: a member's own flows (edit) and the flows a home channel or share lets them run, as before R2. Taking the run ones away would take Run from them.

## What does the canvas get?
- `ws_flow_js_config()` mode `flows`: `build` true for a member; `ws_flow_js_member()` (pure) adds `member` and `member_kinds` (`ws_flow_member_kinds()`, null for staff). The script never names the kinds itself, so R3 adding `change` reaches the palette by itself.
- `ws_scheduled_js_config($viewer, true)` gives a member the conditions, times and events; the scheduled screens call it without `true` and stay staff's.
- No MCP servers, templates, "Save as template" or "Describe it to AI" for a member: a template or an AI draft may hold refused boxes, so they stay staff's (the gallery is skipped; New flow opens an empty canvas). The draft card (`flow_draft.php`) and Make a flow from a table (`wtable_flow.php`) still ask `ws_can_schedule()`.

## What does the script do for a member?
- `memberKind()` filters `paletteItems()` (palette, outline buttons) and refuses `addItem()` (a drop too).
- `memberOwn()` (member and `flow.manage`): the flow settings show "Your own flows have no home channel: only you run them." instead of the picker; "⋯" has no share item (`shareOffered()`); a save sends `channel_id` 0, so a home kept from staff days goes with the first save rather than refusing it.
- A report's e-mail field (`reportToField()`) is hidden; one still holding addresses shows it with the save check's text so it can be cleared.

## How is a refused box shown?
- `ws_flow_detail()` adds `ws_flow_member_problems()` (pure, code `member_kind`) to the draft's problems when the owner reads it: "The box …: <ws_flow_member_node_refusal()>". The box is outlined and listed under Problems; the save check refuses the draft until it is removed.

## Tests
- `tests/workspace_flows_member_canvas_test.php` (helpers, gates, script follows the server list); `test_workspace_flows_canvas_member_palette` runs `paletteItems()` under Node.
