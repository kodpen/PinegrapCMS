---
name: agent-route-words
description: The words an application agent reads on its own routes: the brief's tool line, the answer route's 403, no-channel 422 and unheard-answer 422 and its description, a paused agent's 403 (CA6, #9768)
---

## What does the brief's tool line say?
- `ws_agent_brief_tool_lines()` (`agent_queue.php`): the site's `mcp.php` address and HTTP, both with the agent's own key; the agent's own routes (`/workspace/agent-instructions`, `/workspace/agent-requests`, `/workspace/agent-wake`, `/workspace/agent-report`) are tools there for its own key only, named after the route (`workspace_agent_requests_answer`); `GET /workspace/agent-feedback` is HTTP only.
- Since AA7a (#10867, `api/mcp-agent-own-tools.md`); before it the line said the own routes were HTTP only.
- Before CA6 it said "every API route is a tool there", and an agent looked for an answer tool that does not exist (gap G6 of the agents' census).

## What do the answer route's refusals say?
- `POST /workspace/agent-requests/{id}/answer`, the agent's own key only. Texts in `ws_agent_answer_refusal_text()` (`api_agent_requests.php`), pure, through `lang()`.
- 403 forbidden (the agent is no longer allowed in the channel, or the person who asked can no longer write there): the sentence now ends "Close the request with POST /workspace/agent-requests/{id}/fail (reason)."
- 422 on `text` for a wake made in no channel: write with `POST /workspace/channels/{id}/messages`, then close it with `no_answer` (it named the MCP tool name `workspace.messages.create` before).
- The route's description names where the rest goes: `POST /workspace/tasks`, `POST /workspace/channels/{id}/polls`, `POST /workspace/channels/{id}/approvals` and `POST /workspace/approvals/{id}/decide`, `/workspace/changes` (gap G7).

## When is an answer to another agent refused?
- Owner decision `decisions-made-workspace-52.md` (poll 226): on a request an application agent's message made, the answer's words are asked of `ws_agent_wake_matches()` for the asker's row, as an answer to its message written by an application. No match: 422 on `text`.
- Code: `ws_agent_answer_wake_refusal()` (pure) and `ws_agent_answer_asker_row()` (`agent_queue.php`, the row from `ws_agent_reach_rows()` of the answer's channel), called after the 403 check, so a dry run meets it too.
- Words: "This answer would not wake @handle, which asked: it wakes only when it is named. Begin the answer with @handle, or close the request with no_answer."
- An asker under "When mentioned" needs its @handle or `<@app:N>`; one under "Replies to its messages" is reached without it. Never refused: an answer to a person, `no_answer` (with or without an emoji), a task run's answer, an asker that is paused, no longer allowed there, or the answering agent itself.
- Why: answers without the asker's name reached nobody on 2026-11-20, 25 and 26.

## What does a paused agent's key get?
- Every route of an agent's own answers 403 forbidden for a key that is no active app agent (`ws_api_agent_caller()`).
- When the key's application has a paused app agent (`ws_api_agent_caller_paused_handle()`, no device or OAuth connection), the text is "@handle is paused on the Agents card (Site Settings): its own routes answer again once a person sets it back to Active there." (`ws_agent_caller_refusal_text()`); any other caller keeps "Only an active application agent, with its own key, may use this endpoint."
- No route sets the status: the Agents card has no endpoint on purpose (`api/api-settings-no-endpoint.md`).

## Where are they pinned?
- `tests/workspace_agent_words_test.php` (#9768); the unheard answer in `tests/workspace_agent_answer_wakes_test.php`. No route, scope or column was added.
