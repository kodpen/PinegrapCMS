---
name: channel-open-items
description: The open items line under a channel's pin (OI1, #9783): open polls, agent questions, the running check-in, waiting approvals; ws_channel_open_items(), drawOpenItems(), phone row
---

## What does the line show?
- Under the pinned line in `.ws-strip` (`drawCenter()`), `drawOpenItems()` (workspace.js) draws one line when the channel has open items: the first one's kind (Poll, Agent question, Check-in, Approval request) and its short text, Go (`jumpTo(message_id, true)`). A click on the text goes there too.
- More than one: a button with the count (`.ws-open-count`) opens the list in the panel's context menu (`ctxMenu()`), one row per item, each going to its own message.
- An item waiting for the reader's own answer carries a "Waiting for you" pill and a warning left border; such items come first. No item, or an earlier version of the conversation open: no line.
- Phone: beside the catch-up line or the pin it shares their row and keeps only the count button, which opens the list (one item too). Alone it keeps its text; Go keeps its icon.

## Where does the list come from?
- `ws_channel_open_items($viewer, $channel)` (`includes/workspace/open_items.php`), in `ws_channel_open`'s answer as `open_items` and in `ws_sync` when a message came or changed (a vote, an answer, a decision, a close touch the message) and on the screen's periodic `counts` look. The screen redraws only when the list changed.
- Polls (`ws_polls`, `agent_question` = 1 is an agent question) not closed, not taken back, not past `closes_at`; approvals (`ws_approvals`) not closed nor past `closes_at`; the channel's newest check-in while it runs. Only the current conversation (above `ws_channel_floor()`), no deleted message, none the reader hid for themselves (`ws_message_hidden_ids()`). Guests get nothing.
- Each: `kind` (poll, question, checkin, approval), `message_id`, `text` (one line, 120 characters), `mine`. Order and bound (10, `WS_OPEN_ITEMS_MAX`): `ws_open_items_order()`; `tests/workspace_open_items_test.php`.

## When is an item the reader's own?
- Poll: the reader may write in the channel and has not voted. Agent question: `ws_agent_question_waits_for()` (they are one of the people it asks). Approval: their `ws_approval_people` row is pending. Check-in: they are a member and have not answered.

## When is a check-in running?
- A check-in has no close. It counts while it is the channel's newest, under 7 days old (`WS_OPEN_ITEMS_CHECKIN_DAYS`; the scheduled one asks weekly), and some member has not answered (`ws_open_items_checkin_running()`).

## Why?
- Owner (2026-11-15, channel 25 message 5143): polls, agent questions, check-ins and approval requests scrolled out of sight were missed; the top of the channel names them with a button that goes to them. Cut as plan channel-open-items OI1 (5157). OI2 brings the same list to the API's channel route.
