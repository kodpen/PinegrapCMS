---
name: mail-compose
description: New e-mail in the Workspace (WM5, #8719): the form and its fields, where it opens, the panel actions, who may send, the unsent form kept in the browser, a mailbox's Signature and where it goes
---

## Where does New e-mail open?
- The E-mails screen's toolbar (primary slot, `data-ws-mail-new`, drawn only when `ws_mail_send_boxes()` gives a box) and the channel menu of a channel a mailbox keeps its mail in (`mail_new` of `ws_channel_detail()`, `ws_mail_compose_in_channel()`). Both open one drawer, `id="ws-mail-compose"` (`PGWsMail.compose()` in `assets/js/workspace_mail.js`); `ws-mail-new` is the settings card's New mailbox collapse.
- From a channel the form starts on the first box keeping mail there and on that channel. After a send the screen opens the new conversation in the pane; the channel syncs and shows the card.

## Which fields, in which order?
- From (a box, "name <address>"), Kept in (that box's channels: its own first, then its routes' channels the person may post in), To, "Cc and Bcc" (a link that opens both; open at once when a kept form has them), Subject, Text (plain, 50,000), the Signature preview under the text, Files (several, removable before sending).
- Addresses are separated by commas; every check is the server's (`ws_mail_send_input()`).

## Which actions, and who may send?
- `ws_mail_compose`: the boxes (`ws_mail_compose_boxes()`: id, name, address, signature, channels id and name; never the route or settings) and the writer's name. `ws_mail_new`: the form as `multipart/form-data` with `files[]` (`ws_mail_compose_files()`, each through `ws_upload_part_error()`), turned into `ws_mail_send()`'s array by `ws_mail_compose_mail()`.
- Both refuse a guest ("You cannot send from this mailbox.") and answer "The database has not been upgraded yet." before 11.0.185. Everything else is `ws_mail_send_check()`: the box from `ws_mail_send_boxes()`, the channel one of its channels (`ws_can_post_channel()`), addresses, subject, text, files, the bounce list, the hourly limit (`mail-send.md`).
- People only: no API route (WM8) and no MCP tool; assistants never send (`mail-decisions.md`).

## Where is the unsent form kept?
- `localStorage` `ws-mail-new:<api url>|<viewer>`: one form per site and person (not per channel), `{ box, channel, to, cc, bcc, subject, text, at }`, saved 400 ms after typing pauses, dropped when the form is emptied, on Send and by Discard on the "Your unsent e-mail was brought back." line. Older than 14 days or without words it is not restored and is removed. Files are never kept. Every storage call is in `try`/`catch`. The composer's own slots: `workspace-unsent-draft.md`.

## What is a mailbox's Signature?
- Box form field "Signature" (Workspace settings, Mailboxes): plain text in `settings.signature`, no schema; `ws_mail_signature_input()` keeps line breaks, drops other control characters, a typed leading "-- " line and empty lines at the ends; at most 1,000 characters (`WS_MAIL_SIGNATURE_MAX`) and 12 lines (`WS_MAIL_SIGNATURE_LINES`), checked even for a box without servers.
- A new mail's text (`ws_mail_send_body()`, from `ws_mail_send_properties()`): the text, "-- ", the writer's name, then the Signature in place of the box's name; without one, the box's name as before. Built when the mail leaves, so Send again carries the Signature of that moment. The form shows the same lines under the text.
- Reply by e-mail keeps its own signature (writer and box name, `ws_mail_reply_body()`) until WM7 moves the answer to `ws_mail_send()`; that step decides whether answers take the Signature.

## Tests?
- `tests/workspace_mail_compose_test.php`: Signature input and body, boxes and menu rule, request and files, the guest and the one send path by source, the toolbar, the unsent form under Node. Not run against a database, a mail server or in a browser beyond the sandbox look in PR #8719.
