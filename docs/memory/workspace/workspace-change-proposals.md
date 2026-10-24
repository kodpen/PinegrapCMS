---
name: workspace-change-proposals
description: Record changes an application proposes as cards (proposals.php, api_proposals.php): the channel route and its task twin, agents oauth:/app:, checks, limits, who applies, names
---

## What does an AI app call to change a record?
- `workspace.changes.propose` (POST /workspace/channels/{id}/changes) or `workspace.tasks.changes.propose` (POST /workspace/tasks/{id}/changes): workspace:write, MCP tools, dry_run, 201 `WorkspaceProposal`. Body: `text` and `changes` as requests.answer takes them (`ws_api_changes_param()`).
- It only proposes. One route for every plan: a new kind in `ws_change_types()` (erp_quote, variant_set, a bulk create) is proposed here, never through a route of its own.

## Who may call, and what is checked, in which order?
- An application key (agent `app:<api_apps.id>`) or an OAuth connection (`oauth:<oauth_clients.id>`): `ws_proposal_caller_key()`. A team devices session and the Claude and Pinegrap AI keys get 403 (`ws_api_proposal_agent()`).
- Kinds before any record is read: type `user` is refused (403, `ws_proposal_closed_change()`: no API scope reads user accounts, its e-mail would come back as `from`), then each kind's read scope (`ws_proposal_scopes()`, `ws_ai_type_scope()`). Then `ws_message_send_check()`, or tasks:read and `ws_task_note_add_check()`; `ws_changes_input(…, true)` for the owner (refusals worded for a proposal); on a task 409 `assistant_busy` while a run is open; the hourly limit; `api_dry_run_stop()`; `ws_proposal_store()`.
- Limits: `WS_CHANGES_PER_ANSWER` (10) a call; `WS_PROPOSALS_PER_HOUR` (30) per agent and person, 429 `rate_limited`, counted again after the insert so a burst cannot pass it.

## Who applies, and why not the application?
- The person, on the card or from their team devices session: apply and dismiss are no tools, and over REST `ws_api_proposal_decision_gate()` refuses every OAuth connection and key on a change an application proposed. Before it a connection could apply its own card with workspace:write and a read scope only.

## What is written, and why does no queue take it?
- A `ws_ai_requests` row: agent the key, status `answered`, message_id 0, requested_by the owner; in a channel a message as the application mentioning the owner (reply_message_id), on a task note_reply and the bell. Then `ws_changes_store()`.
- The assistants' queues read their own agent and waiting statuses only. GET /workspace/changes lists them under their agent (`ws_change_agent_key()`). Left out of GET /workspace/assistant-requests and of a task run's earlier work (`ws_task_delegation_earlier()`: nobody asked). The drawer carries on the open run, not the newest row (`ws_task_delegation_open_row()`).

## Which name is shown?
- `ws_proposal_name()`: the client's or application's name marked "(application)" (`ws_proposal_name_shown()`), so a client called Claude is never read as Claude: cards, waiting and stale lines, task runs, bell, timeline, Site Log, decisions, bulk decision, refusals, the proposal's message (`ws_proposal_sender()`; other OAuth messages keep the shared OAuth application, plans/workspace-agent-handles.md question 5). A proposed task note is written as the application (`ws_proposal_app_id()`). A record-side sentence (an order's cancellation reason) names the person who applied it (`ws_change_sentence()`).
