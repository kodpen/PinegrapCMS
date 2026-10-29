---
name: mail-status
description: State of a Workspace e-mail conversation (G5a, G5b, mail_status.php): open/waiting/closed, the holder, the first-response target, the card's controls, the automatic answer
---

## Which states, and who changes them?
- `ws_mail_conversations.status`: `open` (new mail, or the customer wrote again: `ws_mail_ingest()`), `waiting` (set by an e-mail answer in `ws_mail_reply_send()`, which also clears `closed_at`), `closed` (by hand; `closed_at` set, cleared when opened). Words `ws_mail_statuses()`.
- Holder `assignee_id`: assign `me`, `none` or a person's id (the viewer's own id counts as `me`). Somebody else must be a member who may post in the channel (`ws_change_viewer_for_id()` + `ws_can_post_channel()`), else error `assignee`. Who is told: `mail-notices-and-event.md`.
- Action `ws_mail_conversation` (`actions.php`) → `ws_mail_conversation_set()`: whoever may post in the conversation's channel (`ws_can_post_channel()`); `ws_mail_conversation_changes()` is the pure part (columns to write; `nothing` when nothing changes). The card is touched so every reader redraws it.

## What is the first-response target?
- Per box, `settings.first_reply_hours` (0 none, at most `WS_MAIL_FIRST_REPLY_MAX` 720), box form field "First answer within (hours)", `ws_mail_first_reply_input()`; read by `ws_mail_first_reply_hours()`.
- Due = `first_in_at` + target while `first_reply_at` = 0 and not closed (`ws_mail_first_reply_due()`); a "waiting" set by hand without an answer still counts; the automatic answer is no answer. The card shows "First answer by …" or a red "First answer overdue" (`ws_mail_first_reply_html()`); `ws_mail_items_map()` reads each box's target once per page.

## What does the card show?
- `ws_mail_card_html()` on the conversation's card: the status badge and the holder's name (`ws_mail_status_html()`), for every reader.
- Payload `mail_state` (`ws_mail_state_offer()`: status, mine, taken, assignee, me) only where `mail_reply` is offered; `workspace_mail.js` draws a status select and a holder select (Nobody, Me, the open channel's `members`; a holder outside them reads "Somebody else") beside "Reply by e-mail". The take/let-go button went with G5b. Texts `ws_mail_status_js_strings()` (in `screen.php`'s config).

## When does a box answer by itself?
- Columns `auto_reply` (0 by default) and `auto_reply_text` (owner answer 8: per box, off, editable). Box form "Automatic answer": switch and text; on needs a text, at most `WS_MAIL_AUTO_REPLY_MAX` (5,000) characters (`ws_mail_auto_reply_input()`).
- `ws_mail_auto_reply($box, $item_id)` from `ws_mail_ingest()` only when the mail opened a new conversation. `ws_mail_auto_reply_due()`: on with a text, the mail's `auth` is not `fail` (an answer to a forged address reaches a stranger), and no automatic answer to that address from this box in `WS_MAIL_AUTO_REPLY_EVERY` (a day).
- Sent through the box's route (`ws_mail_reply_route()`; none: nothing sent), `'queue' => true` so a mailbox reader never waits on a server, threading headers and the `[#T<id>]` subject like an answer, `'auto_submitted' => true`: `email()` adds `Auto-Submitted: auto-replied` (RFC 3834), and our own loop gate refuses such mail too.
- Kept as an `out` item with `user_id = 0` (`ws_mail_item_is_auto_reply()`; a person's answer always has a writer), drawn under the card with badge "Automatic answer" and the box's name. It is not the first answer (`first_reply_at` untouched) and leaves the status open.

## Tests?
- `tests/workspace_mail_status_test.php`. The writes, the queued answer, the notices and the controls in a browser were not run in the cloud sessions (PR #2923, G5b K1).
