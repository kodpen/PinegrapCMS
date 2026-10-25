---
name: workspace-change-proposals
description: Record changes an application proposes as cards (proposals.php, api_proposals.php): routes, agents oauth:/app:, checks, role-3 contacts, the hourly limit and its lock, who applies, what is written
---

## What does an AI app call to change a record?
- `workspace.changes.propose` (POST /workspace/channels/{id}/changes) or `workspace.tasks.changes.propose` (POST /workspace/tasks/{id}/changes): workspace:write, MCP tools, dry_run, 201 `WorkspaceProposal`. Body: `text` and `changes` as requests.answer takes them (`ws_api_changes_param()`).
- It only proposes. One route for every plan: a new kind in `ws_change_types()` (erp_quote, variant_set, a bulk create) is proposed here, never through a route of its own.
- Proposing is offered, not enforced: a key or a connection still writes records directly where its scopes allow (`workspace.tasks.update`, notes, products, orders, customers). The owner keeps them (2026-10-24): no OAuth ceiling or column against them; consent presets, when built, leave record write scopes unticked by default.

## Who may call, and what is checked, in which order?
- An application key (agent `app:<api_apps.id>`) or an OAuth connection (`oauth:<oauth_clients.id>`): `ws_proposal_caller_key()`. A team devices session and the Claude and Pinegrap AI keys get 403 (`ws_api_proposal_agent()`).
- Kinds before any record is read: type `user` is refused (403, `ws_proposal_closed_change()`: no API scope reads user accounts, its e-mail would come back as `from`), then each kind's read scope (`ws_proposal_scopes()`, `ws_ai_type_scope()`). Then `ws_message_send_check()`, or tasks:read and `ws_task_note_add_check()`; `ws_changes_input(…, true)` for the owner (refusals worded for a proposal); on a task 409 `assistant_busy` while a run is open; the hourly limit; `api_dry_run_stop()`; `ws_proposal_store()`.
- A contact, for an owner with the user role (3): only one filed in their contact groups, the API's rule (`pg_contact_owner_sql()`), in `ws_change_allowed()` and for bulk in `ws_bulk_match()`; out of reach and missing are refused alike. Before #1564 the drawer and the cards reached any contact.

## How is the hourly limit kept?
- `WS_CHANGES_PER_ANSWER` (10) a call; `WS_PROPOSALS_PER_HOUR` (30) per agent and person, 429 `rate_limited`.
- `ws_proposal_store()` checks the count and inserts under one `GET_LOCK` per agent and person (`ws_proposal_lock_name()`, with the database in it), so a burst gets no further than the limit and loses no free place (a recount after the insert would refuse every call of a burst that met it).
- Retry-After and the message give the seconds until the oldest counted proposal leaves the hour (`ws_proposal_retry_after()`, `ws_proposal_wait()`): over MCP only the message reaches the model. A lock not had in 5 s answers 429 with 5 s.

## Who applies, and why not the application?
- The person, on the card or from their team devices session: apply and dismiss are no tools, and over REST `ws_api_proposal_decision_gate()` refuses every OAuth connection and key on a change an application proposed. Before it a connection could apply its own card with workspace:write and a read scope only.

## What is written, and why does no queue take it?
- A `ws_ai_requests` row: agent the key, status `answered`, message_id 0, requested_by the owner; in a channel a message as the application mentioning the owner (reply_message_id), on a task note_reply and the bell. Then `ws_changes_store()`.
- The assistants' queues read their own agent and waiting statuses only. GET /workspace/changes lists them under their agent (`ws_change_agent_key()`). Left out of GET /workspace/assistant-requests and of a task run's earlier work (`ws_task_delegation_earlier()`: nobody asked). The drawer carries on the open run, not the newest row (`ws_task_delegation_open_row()`).
- Names, the drawer's line and the decisions API: `workspace-change-proposal-display.md`.
