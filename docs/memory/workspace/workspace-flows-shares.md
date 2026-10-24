---
name: workspace-flows-shares
description: Flows shared with the team (F7, ws_flow_shares, 11.0.83): view/run/edit, who manages and publishes, access, runs, Claude's reach, lists, bell row, panel actions, API routes, the Share dialog
---

## What is stored?
- `ws_flow_shares` (2026.11.0, `upgrade_2026_11_0_workspace_flow_shares()`, label 11.0.83): flow_id, target_type user|channel, target_id, level view|run|edit (VARCHAR), created_by, created_at; UNIQUE (flow_id, target_type, target_id), idx_target (target_type, target_id, flow_id). Probe `ws_flow_shares_ready()`. Code `flow_shares.php`; tests `workspace_flow_shares_test.php`.

## What does a person reach?
- `ws_flow_access()` gathers, pure `ws_flow_access_level()` decides, the highest wins: owner on the staff edit; reader of a live flow's home run; person share as `ws_flow_share_gives()` says (edit only while staff, `ws_can_schedule()`, else run); channel share in a public or private channel they read view. Binned flows, site members, administrators by rank: nothing.
- View/run readers see the live graph (never published: the draft, read-only, `ws_flow_draft_readable()`); problems to editors only.
- A view share starts no run (403). A run acts with the owner's rights. Its starter reads it whatever its channel while a person share lets them run the flow, or when it has no channel (`ws_flow_run_own_reach()`); through the home alone only while they read the run's channel.

## Who manages and publishes?
- The owner while their access is edit (`ws_flow_share_manager()`): shares, publishes (`ws_flow_publish_check()`), deletes (`ws_flow_bin_check()`), moves the home (403 in `ws_flow_save_check()`). A person shared at edit saves the draft and pauses; the canvas shows them a note instead of Publish.
- Why: a published flow runs in the owner's name and rights (assign rules, the home they post in); the publish check covered fixed assignees only.
- A person: a team member but the owner; a channel: public or private, read by the owner, view only; 200 shares a flow. Sharing again changes the level; the same level writes nothing.

## What is written besides the row?
- A Site Log line on share, level change, removal (API inside `ws_api_as_owner()`). A person newly shared with gets bell row `flow_shared` (`separate`, sentence in `ws_scheduled_notices`). Removal leaves it: ws_inbox has no flow id.

## Lists, panel actions, API?
- `ws_flows_list()` adds shared flows and reads their shares in one query; the canvas lists them under "Shared with me" with the level. `ws_flows_nav_visible()` counts shares.
- Panel actions `ws_flow_shares`, `ws_flow_share_set`, `ws_flow_share_remove` answer the API's shape, `manage`, `owner_id`.
- GET `/workspace/flows/{id}/shares` (workspace:read, a tool); POST `/shares` (201 new, 200 changed), DELETE `/shares/{share_id}` and POST `/delete`: dry_run, `'mcp' => false`, `ws_api_own_words_only()` (key, OAuth refused as for a home move), refuse `ws_api_is_assistant()`. Object WorkspaceFlowShare (level, effective_level, name). Who reaches the flow reads its shares; a channel they do not read is left out (nameless to the owner).

## What does Claude's application reach?
- `ws_api_flow_reachable($viewer, $flow)`: the key owner's own flow; else a person share, a share channel they read where Claude may be asked, or a live flow's such home (pure `ws_api_flow_claude_reaches()`). Ownership decides, not edit access. Others' runs only in such a channel (`ws_api_flow_run_reachable()`). Share lists hide channels Claude may not be asked in (`ws_api_flow_shares_seen()`).

## How does the Share dialog work?
- "⋯" menu: the owner gets Share and Delete, others "Who it is shared with", read-only. Rows with a level select and remove, then `K.peoplePicker` (lent by workspace.js) with a level (default run) and a channel select. Each change sent at once, the list drawn from its answer; focus back to "⋯".
- An editor's home select is disabled; one they do not read reads "A channel you do not read".
- Step not run on MySQL/MariaDB; headless harness (stub API) not in the repo.
