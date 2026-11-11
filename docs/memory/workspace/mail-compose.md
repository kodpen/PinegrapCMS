---
name: mail-compose
description: The Workspace's New e-mail form (WM5): where it opens, its fields, actions ws_mail_compose and ws_mail_new, files as form parts, the unsent form in the browser, the mailbox Signature
---

## Where does New e-mail open?
- The E-mails screen's toolbar (`ws_mail_list_filters_html(..., $can_send)`, primary slot, `data-ws-mail-new`) when `ws_mail_can_send()`: the person has a box to send from (`ws_mail_send_boxes()`). After sending, the new conversation opens in the pane.
- A channel's menu, `channel.mail_new` from `ws_channel_detail()` (`ws_mail_channel_sends()`): a team member who may post there, and an open box with a working route writes into it (its own channel or a route's). The form opens with that channel chosen; after sending the channel syncs.
- Architect (2026-11-10, `plans/workspace-mail-screen.md`): a button on the screen and in the menu of a channel a mailbox writes into. It is the menu's one creating item beside the customer channel's New sales quote: the mail is kept in that channel (README key rule).

## What does the form hold?
- `PGWsMail.write(h, options)` in `assets/js/workspace_mail.js`, a drawer: From (the boxes), Kept in (the chosen box's channels), To, Cc, Bcc (commas), Subject (200), the text (`WS_MAIL_REPLY_MAX`, the `;` saved replies), Under the text (the signature preview), Files, Discard (asks first) and Send e-mail.
- Action `ws_mail_compose` (`ws_mail_compose_offer()`): boxes with id, name, address, signature and channels; the chosen box and channel (the channel asked for, else the first); the writer's name; limits (addresses 20, files 10, bytes = 20 MB or less where the server's request limit is lower, one file's limit, text).
- The browser checks the file count and sizes against those limits; the server checks everything again.

## How is it sent?
- Action `ws_mail_new` as multipart: fields box_id, channel_id, to, cc, bcc, subject, text and `files[]` parts. `ws_mail_compose_request_files()` reads them (`ws_mail_compose_file_parts()`; an upload error refuses the mail by the file's name), then `ws_mail_send()` (`mail-send.md`).
- A refusal names its field (`ws_mail_compose_error_field()`): the form focuses it. Answer: conversation_id, message_id, channel_id, state (queued, sent, failed) and the refused files (the item's `refused_files`), each told as a toast.
- Why form parts: base64 in JSON adds a third and meets the JSON body's memory limit; `post()` reads the answer as `api()` does (413 or 404 = the web server refused the body).

## What is kept in the browser?
- One localStorage slot per site and person, `ws-mail-new:<api_url>|<me>`: box, channel, to, cc, bcc, subject, text and `at`, saved 400 ms after typing and when the drawer closes. `draftRead()` drops it after 14 days, when broken or empty, and keeps only those fields.
- Brought back the next time the form opens, with a note that files are not kept; a channel menu's channel wins over the kept one. Send and Discard clear it. Storage errors are caught.
- The composer's own draft: `workspace-unsent-draft.md`.

## What is the Signature?
- A mailbox's plain text in its `settings` JSON (`signature`), entered in the mailbox form (Signature, 1,000 characters, 10 lines, `ws_mail_signature_clean()`); no schema.
- Under every mail the box sends, new mail and answers alike (`ws_mail_signature_line()` in `ws_mail_send_properties()` and `ws_mail_reply_send()`): `-- `, the writer's name, then the signature, or the box's name without one. The auto-reply keeps its own text.

## Tests and what was not run?
- `tests/workspace_mail_compose_test.php` (signature, offer, file parts, error fields, wiring, the draft helpers under Node), `tests/workspace_channel_menu_test.php`. Not run against a database, a mail server or in a browser.
