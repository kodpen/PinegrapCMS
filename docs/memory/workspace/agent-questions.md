---
name: agent-questions
description: How an agent asks people: the agent question (a poll an application asks), who answers, how it settles, where it shows, the tool, in whose name an outcome is written
---

## What is an agent question?
- A poll an application asks (an agent's key, Claude's routine, an OAuth connection, an assistant). The server sets `ws_polls.agent_question` (`ws_agent_question_flag()`), never the request. Owner (2026-11-14): one thing, the poll, widened; an agent's poll only looks and settles differently (`workflow/decisions-made-workspace-29.md`, column `decisions-made-schema-19.md`).
- A person's poll stays a poll, and so does an agent's poll made before step 11.0.214.
- The approval request does not change. A question one agent asks another, or a list where several lines are ticked, stays a checklist (`agent-checklist-questions.md`).

## Who answers, and how is it settled?
- The people the question mentions, else the channel's owners; the others read; a guest never. No anonymous vote, closing time, counts or bars (`poll-agent-question.md`).
- One answer: the click is the answer; several or "Other": one Send. The first answer settles it, writes no outcome line and wakes the asking agent with the question and the answer in its brief. Closing by hand is refused; its asker takes it back or asks again (`poll-take-back.md`).
- Owner (2026-11-15): an agent question looks like the approved drawing (`agent-questions/drawing.html`), not like a poll (`workflow/decisions-made-workspace-34.md`). The box: `poll-agent-question-box.md`.
- An answered one is a channel decision: the memory index and the Decisions tab list it (architect, message 3460).

## Where does it reach the person?
- Rung whatever their notify level, in Today's "Waiting for you", on the dashboard's Assistant card, in the channel export (`poll-agent-question-reach.md`).

## What does every poll carry?
- An option's description, picture (a file posted in the channel, never inline markup), links, Recommended mark and an "Other" row (`poll-option-parts.md`); change, take back and ask again by its asker (`poll-take-back.md`); three box shapes and the New poll form (`poll-box.md`).
- A poll made before draws, counts and closes as before: every new column defaults to empty, no backfill.

## How does an agent ask with the tool?
- `php tools/ws_poll.php ask [--idempotency-key=K] "<question>" "<recommended>" "<other>"` prints `<message id> <poll id>`; the recommended option goes first. It also takes descriptions, "Other", several answers, `again` and `withdraw` (`poll-agent-question-reach.md`).
- `php tools/ws_wait.php --changed=<message id>` wakes on the answer; `poll.options[].voters` names who chose what.
- `close <poll id>` closes only a poll made before step 11.0.214: a clear winner is written as a decision under it, a tie or no vote as a system line.
- Votes and approval decisions stay a person's (`ws_api_person_only()`, off MCP, `workspace-api-callers.md`). Owner (2026-11-10): a one-answer question is a one-choice poll, not changed afterwards (`workflow/decisions-made-workspace-12.md`).

## In whose name is an outcome written?
- The application's, when the question's message was the application's (`ws_message_app_id()`): `ws_poll_close()` and `ws_approval_settle()` pass it as `app_id` to `ws_message_send()`, so the decision has `sender_kind = app` and `marked_by_kind = app`.
- **Why:** written as the asker (the application's owner) it would read as an owner decision (`workflow/architect.md`).
