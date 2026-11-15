---
name: poll-agent-question-reach
description: Where an agent question reaches the person it asks: the ring, Today, the Assistant card, the channel export, and tools/ws_poll.php ask, again, withdraw
---

## Who is rung, and how?
- `ws_question_ring()` rings the people `ws_question_asked_ids()` names (the question's mentions, else the channel's owners), inbox kind `question_asked`, whatever their channel's notify level; one holding an unread mention of the message is not rung twice (`agent-checklist-questions.md`). The bell's "Asked of me" chip counts it (`pg_notification_asks()`).

## Where does it wait for the answer?
- `ws_agent_questions_for($viewer, $limit)` (agent_question.php): open agent questions (not answered, not taken back) asking the reader, in channels they read, newest first; each with the question cut to 160 characters, the asking agent as `asker`, the channel, the time and the message address. A guest gets none. Pure test: `ws_agent_question_waits_for()`.
- Today: `ws_home()` gives `questions` (five) and the count line a part "N agent question(s) for you" after the read receipts (`ws_home_lead()`). The "Waiting for you" card draws them after the approvals in `homeRows()` of `workspace_approvals.js` (the agent's face, "asks you", the question; a click opens the message to answer it). The card's count badge counts them (`drawHome()`); the channel and time keep together (`.ws-today-row-place`).
- Dashboard Assistant card: `questions` above the proposals, "Agent questions waiting for your answer · N", chips "Question: <agent> · <question>" (`pg_dashboard_v2_ai_question_chips()`), two lines at most then an ellipsis, drawn only when there are any and only while the card is connected.

## How does the export write it?
- `ws_channel_export_poll_line()`: `Agent question: <question> — <answer> (<who>)` once answered, `Agent question: <question> (waiting for <names>)` while open, `Agent question (taken back): <question>`; a person's poll `Poll (taken back): …`, and "Other" with its words in brackets unless the poll is anonymous.

## What does tools/ws_poll.php do?
- `ask`: an option written `label :: description` carries its line; `--recommend` marks the first option, `--other[=LABEL]` adds the "Other" row, `--multiple` takes several answers (`ws_poll_ask_body()`, plain texts stay texts; "Other" counts among the ten).
- `again POLL_ID "question" "option" …` (`POST /workspace/polls/{id}/again`, prints the new `<message id> <poll id>`), `withdraw POLL_ID` (prints the message id). `close` is refused for an agent question (exit 1).
- Tests: `tests/workspace_agent_question_reach_test.php`.
