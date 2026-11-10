---
name: agent-questions
description: How an agent asks the people with a poll or an approval; a one-answer question as a one-choice poll closed once the vote is read; why the outcome is written in the agent's name
---

## How does an agent ask the people?
- With a poll (`workspace.polls.create`, an MCP tool since step A5b) or an approval request (`workspace.approvals.create`, open to applications before). An application key or an OAuth connection asks as the application (`ws_api_writer_app_id()` → `ws_poll_create(..., $app_id)`); a team devices session asks as the person, as before. The route texts tell an agent to put the option it recommends first.
- Only people answer: votes and approval decisions stay `ws_api_person_only()` and off MCP (`workspace-api-callers.md`).
- An application key closes only a poll it asked itself (`ws_api_poll_is_callers()`, the poll message's `sender_kind = app` and `sender_id` the caller's application); every other close stays the person's. Changing a poll stays own-words-only. `workspace.polls.close` stays off MCP: an agent over MCP sets `closes_at`.

## How is a question with one answer asked, and when is the answer locked?
- Owner (2026-11-10): one choice, no change afterwards, a poll rather than a checklist; the shape is the architect's (#8554, `workflow/decisions-made-workspace-12.md`).
- Ask: `php tools/ws_poll.php ask [--idempotency-key=K] "<question>" "<recommended>" "<other>"` prints `<message id> <poll id>` (#8564). It posts `POST /workspace/channels/{id}/polls` with `question` and `options` (trimmed, blank and repeated ones left out, two to ten), `multiple` and `anonymous` left out.
- Wait: `php tools/ws_wait.php --changed=<message id>` wakes on a vote and prints the message; `poll.options[].voters` names who chose what (`user_id`, `name`).
- Lock: as soon as it reads the vote it waits for (the owner account's, for an owner question) the asking agent closes the poll, `php tools/ws_poll.php close <poll id>` (`POST /workspace/polls/{poll id}/close`, no channel needed; prints `poll.result_message_id`; another's poll is 403, exit 1). A clear winner is written as a decision under the poll, and a closed poll takes no vote (`ws_poll_vote_state()`). Until then a vote can be changed or taken back (`ws_poll_vote()`).
- No vote or a tie gives a line of the system's, not a decision: the agent then writes the answer itself, `php tools/ws_post.php --kind=decision --parent=<message id> "<option> — <who answered, where>"`. The same when the answer came in the architect's window.
- A list where several lines are ticked, and a question one agent asks another, stay a checklist (`agent-checklist-questions.md`).

## In whose name is the outcome written?
- The application's, when the question's message was the application's (`ws_message_app_id()`): `ws_poll_close()` and `ws_approval_settle()` pass it as `app_id` to `ws_message_send()`, so the decision has `sender_kind = app` and `marked_by_kind = app`.
- **Why:** both used to write the outcome as the asker (`created_by`, the application's owner). From A5 on, a decision of the owner's account is an owner decision (`workflow/architect.md`), so an agent's poll result would have read as the owner's decision.
- `ws_message_send()` marks a decision or note posted with an `app_id` by the application (`marked_by` = application id, `marked_by_kind = app`) once the column exists; before the upgrade it keeps the old person mark.
- The outcome is a decision line, so it reaches every agent brief of the channel through the memory index (`channel-memory.md`).
