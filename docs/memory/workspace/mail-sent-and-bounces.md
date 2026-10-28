---
name: mail-sent-and-bounces
description: After a Workspace e-mail answer leaves (G3c): the copy APPENDed to the box's sent folder, email()'s keep_mime, and a delivery failure (DSN) drawn as a "Not delivered" line under the conversation
---

## When is an answer copied into the box's sent folder?
- `ws_mail_reply_send()` asks `email()` for `keep_mime` and, after the item and thread message are written, calls `ws_mail_reply_copy()` when `ws_mail_reply_copy_plan()` says so: a box read over IMAP (`transport = imap`, host, user, sealed secret), `settings.sent_copy` not 0 (a box saved before G3c has no key and copies), and the sending server not on a domain of `ws_mail_reply_keeps_sent()` (gmail.com, googlemail.com, office365.com, outlook.com keep what their own SMTP sends; a copy would show twice). Through the site's mail settings it copies too.
- The folder is `settings.sent_folder` as typed, else `pg_imap_sent_folder()`: `LIST (SPECIAL-USE)` when offered, the `\Sent` mark first, then usual names (Sent, Sent Items, Sent Messages, Sent Mail, INBOX.Sent …), `\Noselect` skipped. The found name is APPENDed as the server spells it (modified UTF-7, `$encoded`).
- Its own IMAP session with a 10 s budget while the writer waits; flags `(\Seen)`. No mail kept (a demonstration site sends none) copies nothing. A failure never fails the answer: the action returns `copy_note` and the drawer shows it as a warning toast instead of "The e-mail was sent."
- Box form, "Sending answers": the "Keep a copy…" box (ticked on a new box) and "Sent folder" (empty = found by itself).

## What did email() and the IMAP client gain?
- `email()`: `'keep_mime' => true` keeps PHPMailer's `getSentMIMEMessage()` after a successful `send()` for `pg_mail_last_sent_mime()`; reset at the start of every call (a demo site's or a failed send's is ''). Not kept through a connected account or the queue.
- `pg_imap_append($session, $folder, $mime, $flags, $encoded)`: line ends made CRLF, the mail as a literal; a refusal is the `folder` error, over `PG_IMAP_MAX_LITERAL` is `too_large`. `pg_imap_parse_untagged()` tokenizes LIST/LSUB; `pg_imap_list_result()` gives name and upper-cased attributes.

## How is a bounce told?
- In `ws_mail_ingest()`, after the duplicate check and before the loop gate (a DSN carries `Auto-Submitted` and was dropped as `auto` before G3c): `ws_mail_dsn()` reads a `multipart/report; report-type=delivery-status` (or `global-delivery-status`): the first recipient block with `Action: failed` (`ws_mail_dsn_failed()`: Final-Recipient, Status `x.y.z`, Diagnostic-Code, folded lines joined) and the Message-ID of the returned mail (`message/rfc822`, `message/global`, `text/rfc822-headers`, `message/global-headers`). Delays, success reports and read receipts are not bounces.
- Only a failure of one of the box's own answers (an `out` item found by `ws_mail_hash()` of that id) is kept, by `ws_mail_bounce_store()`; anything else, or a conversation whose channel is gone or archived, meets the loop gate as before.
- Kept as an `in` item with `conversation_id = 0` and `user_id` = the answer's writer (`ws_mail_item_is_bounce()`; an incoming mail never has a writer): it is never quoted, counted for G5 or taken for the customer's mail, and its hash keeps the box from reading it twice. `to_list` the recipient, `body_text` status and diagnostic, `in_reply_to` the answer's id, the `.eml` kept 30 days.
- A thread message under the card (top level when the card is gone); `ws_mail_card_html()` draws badge "Not delivered", "To …" and the server's words. Status `bounce`: the `.eml` upload says so, the reader takes it (`ws_mail_fetch_outcome()`). The conversation's status and times do not change.

## Tests?
- `tests/workspace_mail_sent_test.php` (DSN fixtures, copy plan, form fields, the bounce card, wiring) and `tests/imap_client_test.php` (LIST, sent folder, APPEND against a scripted server). Neither the copy nor a real bounce was run against a server in the cloud sessions (PR #?).
