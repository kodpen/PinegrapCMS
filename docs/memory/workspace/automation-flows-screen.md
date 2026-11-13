---
name: automation-flows-screen
description: The Flows screen with the Workspace off (AF2): its gate, the panel actions the switch lets through, what the screen leaves out that lives in a channel, how it was checked
---

## Does the Flows screen open with the Workspace off?
- Yes, once flows run on the site (`pg_flows_enabled()`, `automation-flows-gate.md`): `workspace_flows.php` calls `ws_screen_gate($user, true)`, which refuses only when `!ws_enabled() && !($flows && pg_flows_enabled())`. A site not yet updated and a person outside the team are refused as before; the team and its rights are the same (`ws_viewer()` never asks the switch).
- The same page and scripts: `workspace.min.js` is the kit (`K`) the canvas draws with, its start asks `ws_bootstrap`, starts `PGWsFlow` and sends `ws_tick`. Nothing in the shell polls on this screen; the main menu prints no Workspace list with it off.
- The list, the window (`?flow=`) and the run view (`?run=`) all open; the editor over a channel and a draft's card stay the Workspace's (owner's rule, `plans/automation-flows-menu.md`).

## Which panel actions answer with it off?
- `ws_handle_action()` asks `ws_action_switch_allows($action, $request, ws_enabled(), ws_ready() && pg_flows_enabled())` first (pure). Off, only `ws_flow_actions_without_workspace()`: `ws_bootstrap`, `ws_tick`, `ws_ref_search` for `type` contact only (Run's customer), and the Flows screen's `ws_flow_*` (list, get, create, save, publish, pause, resume, bin, templates, quick setup, AI sketch, runs, Try, web address, shares). A share set in a channel is refused; taking one back is not.
- Refused off: `ws_flow_editor_config`, `ws_flow_embed_config`, `ws_flow_draft_*`, `ws_flow_from_draft`, `ws_flow_scheduled_copy/edit` and every other `ws_` action, with "The workspace is not switched on.".
- Answers that change off: `ws_tick` runs only `ws_scheduled_run()` (flows' anchors, AF1); `ws_bootstrap` gives no channels, groups, discussions (empty lists) and inbox 0; `ws_flow_list` ends with no scheduled actions (`scheduled` null); `ws_flow_run_start` posts no run card into a channel.

## What does the screen leave out with it off?
- `ws_flow_js_config()` gives `workspace` (`ws_enabled()`) and `workspace_kinds` (`ws_flow_kinds_workspace()`); `workspace_flow.js` reads `FL.workspace !== false`, so a config without the key (a card's embed) counts as on.
- No Home channel field (`homeField()`; a home set earlier stays), no Home channel column or search on it, no Share in a channel (shares to people stay, a channel share made earlier is still listed), no link to a box's task (`taskShown()`), no box that needs the Workspace in the palette (`memberKind()`, AF3: `automation-flows-workspace-boxes.md`).
- `ws_screen_assets()` prints the search box (`workspace_palette.min.js`) only with the Workspace on; Ctrl+K then opens the panel's own search.

## How was it checked?
- `tests/automation_flows_screen_test.php`: the switch (pure), every action `workspace_flow.js` names is on the list and is a panel action, the gate and answers (source), the script's parts in Node, the problems, the offers.
- The AF2 and AF3 PR ran the sandbox with the Workspace off: list, a flow published and run (run view), a draft holding Post a message (palette without it, two problems, Publish refused, Try refused, share in a channel refused); with it on, the screen as before.
