---
name: mail-status
description: State of a Workspace e-mail conversation and the box's automatic answer (G5a, mail_status.php): open/waiting/closed, the holder, the card's controls, the auto-reply's rules
---

## Which states, and who changes them?
- `ws_mail_conversations.status`: `open` (new mail, or the customer wrote again: `ws_mail_ingest()`), `waiting` (set by an e-mail answer in `ws_mail_reply_send()`, which also clears `closed_at`), `closed` (by hand; `closed_at` set, cleared when opened). Words `ws_mail_statuses()`.
- Holder `assignee_id`: "Take it" (assign `me`) or "Let it go" (`none`); taking a held one moves it to the viewer. Assigning someone else waits for G5b.
- Action `ws_mail_conversation` (`actions.php`) → `ws_mail_conversation_set()`: whoever may post in the conversation's channel (`ws_can_post_channel()`); `ws_mail_conversation_changes()` is the pure part (columns to write; `nothing` when nothing changes). The card is touched so every reader redraws it.

## What does the card show?
- `ws_mail_card_html()` on the conversation's card: the status badge and the holder's name (`ws_mail_status_html()`), for every reader.
- Payload `mail_state` (`ws_mail_state_offer()`: status, mine, taken) only where `mail_reply` is offered; `workspace_mail.js` draws a status select and the take/let-go button beside "Reply by e-mail". Texts `ws_mail_status_js_strings()` (in `screen.php`'s config).

## When does a box answer by itself?
- Columns `auto_reply` (0 by default) and `auto_reply_text` (owner answer 8: per box, off, editable). Box form "Automatic answer": switch and text; on needs a text, at most `WS_MAIL_AUTO_REPLY_MAX` (5,000) characters (`ws_mail_auto_reply_input()`).
- `ws_mail_auto_reply($box, $item_id)` from `ws_mail_ingest()` only when the mail opened a new conversation. `ws_mail_auto_reply_due()`: on with a text, the mail's `auth` is not `fail` (an answer to a forged address reaches a stranger), and no automatic answer to that address from this box in `WS_MAIL_AUTO_REPLY_EVERY` (a day).
- Sent through the box's route (`ws_mail_reply_route()`; none: nothing sent), `'queue' => true` so a mailbox reader never waits on a server, threading headers and the `[#T<id>]` subject like an answer, `'auto_submitted' => true`: `email()` adds `Auto-Submitted: auto-replied` (RFC 3834), and our own loop gate refuses such mail too.
- Kept as an `out` item with `user_id = 0` (`ws_mail_item_is_auto_reply()`; a person's answer always has a writer), drawn under the card with badge "Automatic answer" and the box's name. It is not the first answer (`first_reply_at` untouched) and leaves the status open.

## Tests?
- `tests/workspace_mail_status_test.php`. The writes, the queued answer and the controls in a browser were not run in the cloud session (PR #2923).
