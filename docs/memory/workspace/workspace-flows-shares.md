---
name: workspace-flows-shares
description: Flows shared with the team (F7, ws_flow_shares, 11.0.83): view/run/edit, who manages, how access is worked out, lists, runs, bell row, panel actions, API routes, the Share dialog
---

## What is stored?
- `ws_flow_shares` (2026.11.0, `upgrade_2026_11_0_workspace_flow_shares()`, label 11.0.83; 11.0.82 left free): flow_id, target_type user|channel, target_id, level view|run|edit (VARCHAR), created_by, created_at; UNIQUE (flow_id, target_type, target_id), idx_target (target_type, target_id, flow_id). Probe `ws_flow_shares_ready()`. Code `includes/workspace/flow_shares.php`; tests `tests/workspace_flow_shares_test.php`.

## What does a person reach?
- `ws_flow_access()` gathers the facts, pure `ws_flow_access_level()` decides, the highest wins: owner on the staff edit; reader of a live flow's home run; person share as `ws_flow_share_gives()` says (edit only while staff, `ws_can_schedule()`, else run); channel share in a public or private channel they read view. Binned flows and site members nothing; administrators nothing by rank.
- View and run readers see the live graph; a flow never published shows them its draft read-only (`ws_flow_draft_readable()`); problems go to editors only.
- A view share starts no run (403). A run acts with the owner's rights; its starter reads it while they may run the flow, whatever its channel (runs of a flow without a home have none).

## Who manages the shares?
- The owner while their access is edit (`ws_flow_share_manager()`). A person shared at edit saves the draft, publishes and pauses (the existing edit rule, kept on purpose: the owner chose a colleague on the staff), but does not share, move the home (403 in `ws_flow_save_check()`) or delete (`ws_flow_bin_check()`).
- A person is a team member other than the owner; a channel is public or private, read by the owner, view only; 200 shares a flow. Sharing again changes the row's level; the same level writes nothing.

## What is written besides the row?
- A Site Log line on share, level change and removal (API inside `ws_api_as_owner()`). A person newly shared with gets bell row `flow_shared` (`separate`, sentence in `ws_scheduled_notices`, url workspace_flows.php). Removal leaves that line: ws_inbox has no flow id to find it by.

## Lists, panel actions, API?
- `ws_flows_list()` adds the shared flows and reads their shares in one statement; the canvas lists them under "Shared with me" with the level. `ws_flows_nav_visible()` counts shares too.
- Panel actions `ws_flow_shares`, `ws_flow_share_set`, `ws_flow_share_remove` answer the shares in the API's shape, `manage` and `owner_id`.
- GET `/workspace/flows/{id}/shares` (workspace:read, a tool); POST `/shares` (201 new, 200 changed), DELETE `/shares/{share_id}` and its POST `/delete` twin: dry_run, `'mcp' => false`, refuse `ws_api_is_assistant()` (an AI app does not widen who sees a flow). Object WorkspaceFlowShare (level, effective_level, name). Everybody who reaches the flow reads its shares; a channel they do not read is left out (shown nameless to the owner).

## How does the Share dialog work?
- "⋯" menu: the owner gets Share (and Delete), the others "Who it is shared with", read-only. Rows with a level select and remove, then `K.peoplePicker` (the note share's picker, lent by workspace.js) with a level (default run) and a channel select. Every change is sent at once and the list drawn from the answer; the focus goes back to "⋯".
- A person shared at edit sees the home select disabled; a home they do not read reads "A channel you do not read".
- The step has not been run on MySQL/MariaDB yet; the headless harness (stub API) is not in the repo.
