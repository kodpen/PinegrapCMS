---
name: workspace-flows-versions
description: A paused flow resumed on its live version, and a flow's published versions listed, read and brought back into the draft (flow_versions.php, API routes, no schema)
---

## Where is it?
- Engine `includes/workspace/flow_versions.php` (loaded after flow_shares.php); handlers in `api_flows.php`; tests `tests/workspace_flow_versions_test.php`. Columns of 11.0.80 only. API only for now: the canvas still makes a paused flow live by publishing.

## What does resume do?
- `ws_flow_resume_check()` / `ws_flow_resume()`: the owner only (`ws_flow_share_manager()`, 403), a paused flow with a live version (`ws_flow_resume_refusal()`, pure: `not_paused`, `no_version`), then `ws_flow_publish_check()` handed the version's graph as `draft`, so the owner's rights now are checked as at publish (a channel left, a time gone, an assignee no longer allowed → 422, the owner publishes a changed draft).
- One UPDATE `AND status = 'paused' AND version_id = …`, affected rows counted (a second resume → 409); then `ws_flow_anchor_sync()` from the version, as publish does. The draft is not touched; a Site Log line.

## What does restore do?
- `ws_flow_version_restore_check()` / `_restore($viewer, $flow, $n, $data)`: the version by number (`ws_flow_version_numbered()`, 404), then a draft save (`ws_flow_save_check()` / `ws_flow_save()` with `graph` = the version's JSON and the optional `draft_rev`): edit access, 409 `changed_meanwhile`, size. The flow's status and live version stay; a Site Log line.

## Which routes?
- `POST /workspace/flows/{id}/resume` (`workspace.flows.resume`, `workspace:write`, dry run, `'mcp' => false`, `ws_api_own_words_only()`), as publish: a resumed flow opens tasks in the person's name.
- `GET /workspace/flows/{id}/versions` (list of WorkspaceFlowVersion, newest first, cursor on n), `GET …/versions/{n}` (WorkspaceFlowVersionDetail: the version, `live`, `graph`), `POST …/versions/{n}/restore` (dry run answers boxes and problems; answer WorkspaceFlow). All three need edit (`ws_api_flow_editable()`, 403 for run or view), are MCP tools and take an application key: a draft never runs.

Related: docs/memory/workspace/workspace-flows-store.md, docs/memory/workspace/workspace-flows-anchors.md
