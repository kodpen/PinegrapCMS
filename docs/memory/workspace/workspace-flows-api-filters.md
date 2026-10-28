---
name: workspace-flows-api-filters
description: Flow list filters start, status, channel_id, updated_since and the start/start_text of GET /workspace/flows/{id} (A1, PR #2122); the Flows screen's start filter (C4, PR #2391)
---

## Which filters does the list take?
- `start` (time, event, join, manual), `status` (draft, live, paused), `channel_id` (home channel), `updated_since` (router `datetime`: unix time, ISO 8601 or YYYY-MM-DD), all optional and combined. A bad value is refused by the router with 422 `validation_failed`, as every enum/int/datetime param is; the handler never sees it.
- `binned` is no `status` value: deleted flows are never listed (`ws_flow_access()` returns '' for them).
- `updated_since` compares `ws_flows.updated_at`, raised by save, publish, pause and delete; sharing does not raise it, so a flow newly shared with the caller is not in a narrowed list.

## Where does each filter run?
- `status`, `channel_id`, `updated_since` in SQL: `ws_flows_list_where()` (pure, tested) appended to the `ws_flows_list()` query; status reaches the query only as one of the three literals.
- `start` in PHP after the access check: `ws_flows_start_kinds()` reads the live version's graph for a published flow and the draft for one never published (two reads), then `ws_flow_start_kind()`. Drafts are read only when `start` is sent.

## How is a flow's start known?
- By its start box (`ws_flow_trigger_node()`), mapped by `ws_flow_start_names()`: schedule → time, event, join, manual. The graph is `ws_flow_start_graph()`: live version once published, else the draft; a graph without a start box has start '' (matches no filter, null in the API). Not from the anchor row: the anchor's status (paused, a one-off done) would need its own reading.

## What are start and start_text?
- `WorkspaceFlow` fields, null in the listing (like draft and graph), set by `ws_api_flow_answer()` for GET and the write answers.
- `start_text` = `ws_flow_start_text()`: manual says "By hand"; an anchor kind builds its scheduled-action rule (`ws_flow_start_rule()`, pure, tested) and says it with `ws_scheduled_rule_text()`, the same words the scheduled actions use; an unfilled time box says its label ("At a time").
- A channel in the start box the caller does not read is not named: join says "When somebody joins a channel", event drops the channel from its rule. The API's readable check is `ws_api_flow_claude_channel()` for the Claude app, `ws_flow_channel_readable()` otherwise.
- The event catalogue reads tables (`ws_approvals_ready()`), so tests check the event rule, not its words.

## How does the Flows screen filter by start? (C4, PR #2391)
- `ws_flow_list` (panel action) reads every listed flow's start with `ws_flows_start_kinds()` and `ws_flow_brief()` answers it as `start` ('' for no start box); `ws_flow_detail()` works it out with `ws_flow_start_graph()`, so `listEntry()` keeps it after a save.
- `drawList()` puts a select over the list (`startFilter()`, `state.startFilter`, filtered in the browser): every start, time, manual, event, join. A part left empty says "No flow starts this way."

Related: docs/memory/workspace/workspace-flows-store.md, docs/memory/workspace/workspace-flows-anchors.md, docs/memory/plans/workspace-flows-api.md
