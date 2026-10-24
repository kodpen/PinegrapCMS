---
name: workspace-change-proposals
description: Record changes an application proposes as cards (proposals.php, api_proposals.php): the channel route and its task twin, agents oauth:/app:, checks, limits, why no queue takes them, names
---

## What does an AI app call to change a record?
- `workspace.changes.propose` (POST /workspace/channels/{id}/changes) or `workspace.tasks.changes.propose` (POST /workspace/tasks/{id}/changes): workspace:write, MCP tools, dry_run, 201 `WorkspaceProposal` (request id, message id, the changes as GET /workspace/changes shows them, status pending). Body: `text` and `changes` as requests.answer takes them (`ws_api_changes_param()` builds both).
- It only proposes: the person the key or the connection acts for applies or sets aside on the card (those routes are no tools). One route for every plan: a new kind in `ws_change_types()` (erp_quote, variant_set, a bulk create) is proposed here, never through a route of its own.

## Who may call, and what is checked, in which order?
- An application key (agent `app:<api_apps.id>`) or an OAuth connection (`oauth:<oauth_clients.id>`, from `api_devices.oauth_client_id`): `ws_proposal_caller_key()`. A team devices session and the applications of Claude and Pinegrap AI get 403 (`ws_api_proposal_agent()`).
- Then the read scope of each kind named, before any record is read (`ws_proposal_scopes()`, `ws_ai_type_scope()`, as `workspace.claude.record` does); `ws_message_send_check()` in a channel, or tasks:read and `ws_task_note_add_check()` on a task; `ws_changes_input()` for the owner (`ws_change_allowed()`); the hourly limit; `api_dry_run_stop()`; `ws_proposal_store()`.
- Limits: `WS_CHANGES_PER_ANSWER` (10) a call; `WS_PROPOSALS_PER_HOUR` (30) per agent and person, else 429 `rate_limited`. An application's messages have no per-minute limit of their own (`ws_rate_limited()` counts people), hence this one.

## What is written, and why does no queue take it?
- A `ws_ai_requests` row: agent the key, status `answered`, message_id 0, requested_by the owner. In a channel the text goes out as the application with the owner mentioned (reply_message_id); on a task it is note_reply and the owner is told on the bell (`task_assistant`). Then `ws_changes_store()`.
- The queues of Claude, Pinegrap AI and MCP agents read their own agent and waiting statuses only. GET /workspace/assistant-requests leaves proposals out (`ws_proposal_where('', false)`); GET /workspace/changes lists them under their agent (`ws_change_agent_key()`).

## Where does the application's name show instead of Claude's?
- `ws_proposal_name()`: the OAuth client's name, or the application's. Card titles and stale lines (`proposer` in `ws_changes_map()`, workspace.js, workspace_task_delegate.js), the run on a task (`ws_assistant_name()`, `ws_proposal_request_state()`), the bell, the timeline, decision and log sentences (`ws_ai_voice()`), the bulk decision, apply and set-aside refusals, a proposed task note's writer (`ws_proposal_app_id()`).
- The proposal's own message from an OAuth connection is drawn under the client's name on the screen and in the API (`ws_proposal_sender()`); other OAuth messages still carry the shared OAuth application (plans/workspace-agent-handles.md question 5).
