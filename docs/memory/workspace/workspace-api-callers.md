---
name: workspace-api-callers
description: Which Workspace API writes the person must make themselves: ws_api_person_only() vs ws_api_own_words_only(), whose name a write carries, refusals, which are no MCP tools
---

## Whose name does a write carry?
- A team devices session (`POST /auth/login`) writes as the person (`ws_api_device_writes_as_person()`; `ws_api_writer_app_id()` is 0), so their rate limit, auto-join and read mark apply as in the panel. A key writes as the application; an OAuth connection's message is its owner's (`agent-sessions.md`). Callers: `api-device-sessions.md`.

## Which answers must be the person's own? (`ws_api_person_only()`)
- The person's answer or preference: read receipt, read mark, approval, applying or setting aside a proposed change, vote, notify level, pinning a channel and its order, joining or leaving a discussion, closing a poll. A key gets 403; an OAuth connection passes, acting for its approver. Refusals say "signed in on a device".
- Over REST only for the decisions on a card: approvals decide, changes apply and dismiss carry `'mcp' => false` (owner decision 2026-10-24), so an assistant never decides in the person's name (`test_ws_api_card_decisions_are_no_tools`).
- What an application asked is answered from the team devices session only (`ws_api_own_words_only()`), whichever application asked, as a connection would answer for its approver:
  - A change it proposed, applied or set aside: `ws_api_proposal_decision_gate()` (`test_ws_api_proposals_are_decided_by_the_person`).
  - A vote on an agent question, and an approval request whose message is an application's (`ws_message_app_id()`): #9818, after #9807 (`test_ws_api_application_questions_are_answered_by_the_person`). A person's poll or request keeps `ws_api_person_only()`.
- Starting and cancelling a flow run: `'mcp' => false`, refusing `ws_api_is_assistant()` (Claude's routine uses REST with its key); an integration's key passes (`test_ws_api_flow_runs_writes_are_no_tools`).

## Which writes need the team devices session? (`ws_api_own_words_only()`)
- Marking an agent's answer right or wrong (#10340).
- Changing or deleting the person's own words (edit, delete, delete for me), and anything opened in their name that later runs as their word or that only they may change: a scheduled message, changing, taking back or asking again a person's poll (an application only its own, `agent-questions.md`), a pin (`ws_api_pin_target()`), publishing a flow (drafting is open to both: `workspace-flows-store.md`), sharing one or taking a share back (`test_ws_api_flow_shares_routes`).
- Setting one's own quiet hours (`api/api-workspace-quiet-hours.md`).
- Key and OAuth are refused there (#371, #375): a connection writes as the application, so what it set up would later read as the person's word. Both may still list and cancel the owner's scheduled messages.
- A published flow moved to another home channel is refused to both by `ws_api_flow_home_move_gate()`, inside the draft change, which stays a tool: the gate checks `ws_api_device_writes_as_person()` itself, not through `ws_api_own_words_only()`.
- Such a route, whether it calls the gate itself or through a helper, carries `'mcp' => false`: an MCP client always comes through an OAuth connection and would get 403 on every call (`test_ws_api_own_words_routes_are_no_tools`).
- Its refusal names "team devices session (POST /auth/login)" and "OAuth connection", never "signed in on a device", as a connection is a device row too (`test_ws_api_own_words_refusals_name_the_team_devices_session`).

## Which write is an application's only?
- Proposing record changes (`workspace.changes.propose`, `workspace.tasks.changes.propose`): an application key or an OAuth connection, under its own agent; a team devices session gets 403, since the person changes the record themselves, and so do the Claude and Pinegrap AI keys, which propose in their answers (`workspace-change-proposals.md`).

## What is not a personal preference?
- A layout that is the same for everybody, and a write that changes access: `workspace-api-layout-writes.md`.
