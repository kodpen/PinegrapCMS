---
name: support-chat-request
description: The chat window's "Make a support request" (HC5): a site chat taken into a chosen support box as a mail from the customer; a second click finds the same request
module: chat_requests
---

## Where is the button?
- The panel's chat window, a site chat opened from the team's side: an envelope icon "Make a support request" in the header, before Close Conversation and Delete Conversation. Shown when the viewer may manage the chat and the customer left an address (`party_email`).
- It folds out a strip under the header: "Support box" (enabled e-mail support boxes, `pg_support_form_boxes()`, the list a form's "Support Request" offers), one line of help, Cancel and "Make a support request". The result sentence takes the help line's place.
- Strip and buttons are look A parts (`pg-input pg-input-sm`, `pg-button-fill`/`-quiet`, `pg-icon-button-sm`); the rest of the chat window is still the old look.

## Who may make one?
- `pg_chat_request_allowed()` (pure): a `site` chat, the viewer on its `target` side (the operator, or an administrator reading every site chat), role Manager or above, a valid `party_email`. Checked again in `chat_request_boxes` and `chat_request` (`includes/fn/chat_requests.php`, loaded by `chat.php`, not by `functions.php`); both actions are in the panel action table beside the other `chat_*` ones.

## What is made?
- `pg_chat_request_make()` builds a plain-text mail with `pg_support_form_mail_raw()`: From the chat's address and name, To the box, subject the customer's first words (80 characters at most, cut at a word) or "Live chat #<id>", text an opening line (chat number, start time, page) and one block a message, writer and time above the words, an attachment as `[Attachment: name]`; system lines are left out, 500 messages at most.
- Handed to `ws_mail_ingest()` with a `tie` from the member who started the chat (`pg_support_form_tie()`: only when their address is the chat's), so routes, rules, notices and the automatic answer with `[#T<id>]` and the signed link follow (`support-request-page.md`).
- Message-ID `pg-chat-<chat id>-<last message id>@<host>`: clicked again before anything new was written, the intake calls it a duplicate and the window says "This chat is already request #T<id>."; after a new message a new request is made. Another box takes it again.
- A refusal writes an activity-log line "chat conversation (#id) opened no support request: …"; a made one logs "… made into support request #T<id>". Nothing is written into the chat itself; the chat stays as it was (open or closed).

## Tests?
- `tests/chat_requests_test.php`: who may, whose words, subject, text, Message-ID and the mail parsed back, the wiring. No database, mailbox or browser run in the cloud session.

Related: docs/memory/workspace/support-form-request.md, docs/memory/workspace/support-request-page.md, docs/memory/ui/chat-window-send-and-unread.md
