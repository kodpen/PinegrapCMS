---
name: agent-progress-note
description: An app agent's progress note on a request it holds: the progress route, its refusals and 30 s brake, kept in ai_state, shown with "since HH:MM", cleared on close (AF2)
---

## What is it for?
- While an agent works on a request for minutes, it says in one short line where it is ("reading the plans", "waiting for CI, back by 22:30"). Before it, nothing changed on the screen between the claim and the answer and no time showed (proposal in channel 25, message 5086; step cut in 5131).

## Who may write it, and when?
- `POST /workspace/agent-requests/{id}/progress` (`workspace.agent_requests.progress`, `workspace:write`, `'mcp' => false`, dry run), body `{"text": "..."}`, in `api_agent_requests.php`. Only an active app agent's own key (`ws_api_agent_caller()`, else 403) on one of its own requests (else 404).
- `ws_agent_progress_check()` (pure, `agent_shown.php`): 409 `not_claimed` while queued or sent, 409 `closed` once answered, failed or cancelled, 422 for an empty note or over `WS_AGENT_PROGRESS_MAX` (200) characters after spaces fold, 429 `rate_limited` with `Retry-After` inside `WS_AGENT_PROGRESS_GAP` (30) seconds of the last note.
- The write is conditional on the ai_state it checked, so two notes sent at once cannot both pass the brake. No message, no wake, no inbox line, no Site Log line.

## Where is it kept?
- In the request's `ai_state` JSON as `progress` and `progress_at` (`ws_agent_progress_state()`), beside a wake, question, approval or item state, which stay. No column.
- Cleared when the request closes: the answer and fail routes call `ws_agent_progress_clear()`; the site's own closes (cancel, clear, edit hold's requeue, timeouts) already set `ai_state = NULL`.

## Where does it show?
- `ws_agent_request_state()` adds `note` and `since` (`ws_agent_request_progress_line()`, only while `running` with a claim time): "since HH:MM" is the claim's time in the site's time zone, shown also without a note. `claudeStateLine()` (`workspace.js`) draws them after "@handle is working on it" as `· note` (italic, wraps) and `· since HH:MM`. Claude's, Pinegrap AI's and MCP lines carry neither.
- The request's read (`WorkspaceClaudeRequest`) carries `progress: {text, at}` while running, empty otherwise.
- The agent's instructions (`ws_agent_instructions_request_lines()`) name the route, so `instructions_version` changed.

## Tests?
- `tests/workspace_agent_progress_test.php` (PR #PRNUM): the refusals, the brake, the state kept and cleared, the line, the route row, the wiring.
