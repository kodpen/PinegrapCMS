---
name: agent-questions
description: How an agent asks the people with a poll or an approval, and why the outcome is written in the agent's name (never as the owner's decision)
---

## How does an agent ask the people?
- With a poll (`workspace.polls.create`, an MCP tool since step A5b) or an approval request (`workspace.approvals.create`, open to applications before). An application key or an OAuth connection asks as the application (`ws_api_writer_app_id()` → `ws_poll_create(..., $app_id)`); a team devices session asks as the person, as before. The route texts tell an agent to put the option it recommends first.
- Only people answer: votes and approval decisions stay `ws_api_person_only()` and off MCP (`workspace-api-callers.md`).
- An application key closes only a poll it asked itself (`ws_api_poll_is_callers()`, the poll message's `sender_kind = app` and `sender_id` the caller's application); every other close stays the person's. Changing a poll stays own-words-only. `workspace.polls.close` stays off MCP: an agent over MCP sets `closes_at`.

## In whose name is the outcome written?
- The application's, when the question's message was the application's (`ws_message_app_id()`): `ws_poll_close()` and `ws_approval_settle()` pass it as `app_id` to `ws_message_send()`, so the decision has `sender_kind = app` and `marked_by_kind = app`.
- **Why:** both used to write the outcome as the asker (`created_by`, the application's owner). From A5 on, a decision of the owner's account is an owner decision (`workflow/architect.md`), so an agent's poll result would have read as the owner's decision.
- `ws_message_send()` marks a decision or note posted with an `app_id` by the application (`marked_by` = application id, `marked_by_kind = app`) once the column exists; before the upgrade it keeps the old person mark.
- The outcome is a decision line, so it reaches every agent brief of the channel through the memory index (`channel-memory.md`).
