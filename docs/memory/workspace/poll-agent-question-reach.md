---
name: poll-agent-question-reach
description: Where an agent question reaches the person it asks: the ring, Today, the Assistant card, the channel export, and tools/ws_poll.php ask (option pictures by message number), again, withdraw
---

## Who is rung, and how?
- `ws_question_ring()` rings the people `ws_question_asked_ids()` names (the question's mentions, else the channel's owners), inbox kind `question_asked`, whatever their channel's notify level; one holding an unread mention of the message is not rung twice (`agent-checklist-questions.md`). The bell's "Asked of me" chip counts it (`pg_notification_asks()`).

## Where does it wait for the answer?
- `ws_agent_questions_for($viewer, $limit)` (agent_question.php): open agent questions (not answered, not taken back) asking the reader, in channels they read, newest first; each with the question cut to 160 characters, the asking agent as `asker`, the channel, the time and the message address. A guest gets none. Pure tests: `ws_agent_question_waits_for()`, `ws_agent_questions_collect()`.
- The site's open questions are read 100 at a time below the last id (`ws_agent_questions_collect()`) until `$limit` wait for the reader or the rows run out (#9810): before, only the site's newest 100 were read, so a person's older question vanished from Today and the Assistant card once 100 newer open ones existed elsewhere, as an agent question never closes on its own.
- Today: `ws_home()` gives `questions` (five) and the count line a part "N agent question(s) for you" after the read receipts (`ws_home_lead()`). The "Waiting for you" card draws them after the approvals in `homeRows()` of `workspace_approvals.js` (the agent's face, "asks you", the question; a click opens the message to answer it). The card's count badge counts them (`drawHome()`); the channel and time keep together (`.ws-today-row-place`).
- Dashboard Assistant card: `questions` above the proposals, "Agent questions waiting for your answer · N", chips "Question: <agent> · <question>" (`pg_dashboard_v2_ai_question_chips()`), two lines at most then an ellipsis, drawn only when there are any and only while the card is connected.

## How does the export write it?
- `ws_channel_export_poll_line()`: `Agent question: <question> — <answer> (<who>)` once answered, `Agent question: <question> (waiting for <names>)` while open, `Agent question (taken back): <question>`; a person's poll `Poll (taken back): …`, and "Other" with its words in brackets unless the poll is anonymous.

## What does tools/ws_poll.php do?
- `ask`: an option written `label :: description` carries its line; `--recommend` marks the first option, `--other[=LABEL]` adds the "Other" row, `--multiple` takes several answers (`ws_poll_ask_body()`, plain texts stay texts; "Other" counts among the ten).
- A picture in an option's own box: `label :: #N` or `label :: description :: #N`, N the message number of a picture posted in the poll's channel.
- The picture's file id: the tool reads message N with the same key (`GET /workspace/channels/{id}/messages?since_id=N-1&limit=1`), takes `file.id` of an `image` message as the option's `file_id` (`ws_poll_picture_file_id()`), and stops with exit 1 when the message is not there or is no picture. `again` with a picture needs the channel (`PG_WS_CHANNEL` or `--channel`). Why: the owner could not tell which picture a question meant when it sat outside the box.
- `--help` (and a command error) prints `ws_poll_usage()`.
- `again POLL_ID "question" "option" …` (`POST /workspace/polls/{id}/again`, prints the new `<message id> <poll id>`), `withdraw POLL_ID` (prints the message id). `close` is refused for an agent question (exit 1).
- Tests: `tests/workspace_agent_question_reach_test.php`, `tests/ws_poll_pictures_test.php`.
