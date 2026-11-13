---
name: automation-flows-menu-census
description: Census of the automation-flows-menu plan (AF0): where flows asked the Workspace switch, what the Flows screen takes from its shell, the API, the entries, who holds the right
---

## Where did a flow's path ask the Workspace switch?
- Server, gated now by `pg_flows_enabled()` (AF1, `workspace/automation-flows-gate.md`): `job.php`'s scheduled block, `workspace_recurring_job.php` (the run, the 30-day sweep), `pg_event_record()`. `flow_start.php` never asked it; `ws_event()`, the runner and the step code do not.
- Still on the switch: `ws_screen_gate()` (`workspace_flows.php`), `ws_handle_action()` (every panel action), `api_modules()` (the whole `includes/workspace/api.php`), the dashboard gate `flows` (role < 3 and the `workspace` gate: the Flows card, the "Flow runs (today)" figure, the Start flow shortcut), the menu rows in `includes/fn/output.php`, `pg_user_permission_ws_available()` on the user screens.

## What does the Flows screen take from the Workspace shell? (AF2)
- `workspace_flows.php`: `ws_screen_gate()`, the `ws-shell` markup, `ws_screen_assets($viewer, 'flows')`: the `ws-config` JSON (`ws_screen_config()`: team, rail, addresses), `pg_fields`, the AI rewrite when `ws_screen_ai_rewrite()`, `pg_voice_text`, `workspace.min.js`, `workspace_palette.min.js`, then `pg_graph_canvas.min.js` and `workspace_flow.min.js`, which `workspace.js` starts once the team is known.
- From the shell object `K`: `api`, `select`, `button`, `toast`, `ask`, `fail`, `icon`, `flowIcon`, `avatar`, `peoplePicker`, `openTask`, `dateField`, `scheduledList`, `viewBack`, `onContext`, `debounce`, `cfg`.
- Panel actions (`includes/panel/workspace.php` → `ws_handle_action()`): the `ws_flow_*` family (31 cases: list, get, create, save, publish, pause, resume, bin, templates, AI sketch, run start, Run now, runs, run, cancel, mend, box try, web address, shares), `ws_tick` (the screen's run of what is due) and `ws_ref_search` (contacts for a run's customer).
- Workspace-only parts of the screen: the home channel field and channel shares (shares to people stay), links to channels and to task cards (`K.openTask`), the run channel. The owner hears of a run by a Workspace inbox line (`flow_finished`); `workspace.flow.run.failed` also reaches the notification targets (`includes/fn/notify_channels.php`), linking `workspace_flows.php`.

## What stays inside the Workspace?
- Owner: the view of a flow inside a channel stays there. So the flow layer over a channel (`flowLayer()`), the draft card's read-only embed (`PGWsFlow.embed`, `ws_flow_embed_config`, `ws_flow_draft_view`), Add › Start a flow in the composer and Claude's flow drafts keep the Workspace's gate.

## What is the API's part?
- 26 flow routes (`/workspace/flows…`, `/workspace/flow-runs…`, `flow-kinds`, `flow-templates`) under `workspace:read` (11) and `workspace:write` (15), their schemas, and the run events `workspace.flow.run.finished/failed/cancelled/waiting`, all merged only while `WORKSPACE_ENABLED`. No step of the plan names them yet (`automation-flows-menu-proposals.md`).

## Who holds the right on a site without the Workspace?
- `ws_rights()` never asks the switch: roles 0-2 are members unless their Workspace profile excludes them, and build, share and publish (`ws_can_schedule()`); a role-3 user with `user.manage_workspace` builds their own flows (`ws_flow_member()`). Unchanged by this plan.
- With the Workspace off the user screens draw no Workspace rights row, so a role-3 right can be neither given nor taken there; one given earlier stays. Left as it is (the plan keeps who may make flows).

## Which boxes and starts need the Workspace?
- On the list now: `workspace/automation-flows-workspace-boxes.md`; more found by the census: `automation-flows-menu-proposals.md`.
