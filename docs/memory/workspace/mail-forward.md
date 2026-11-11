---
name: mail-forward
description: Forward as e-mail in the Workspace (WM6): the buttons, the filled New e-mail form, what a forward quotes and carries, the line under a forwarded mail, the checks when it is sent
---

## Where is Forward as e-mail?
- Channel screen: "Forward as e-mail" on the bar of messages chosen together (beside Forward) and in a message's menu; a mail's own text offers it on its card beside "Reply by e-mail". E-mails screen: "Forward" under each mail in the reading pane (`data-ws-mail-forward`).
- Shown to a team member with a mailbox to send from (`ws_mail_forward_may()`, config `mail_forward` on the channel screen; the pane marks each mail by `ws_mail_forward_pane()`). Never for a delivery failure, a deleted message or a guest.

## What opens?
- The New e-mail drawer (`newForm()` in `assets/js/workspace_mail.js`, `mail-compose.md`), titled "Forward as e-mail", filled by `ws_mail_forward` (`ws_mail_forward_fill()`): the subject, the text with two empty lines above the quote and the caret there, the files carried (each can be taken out) and an alert naming files left out. Focus is on To.
- Channel messages: subject "A message from #channel" or "Messages from #channel"; the quote is the same heading, then each message as writer · `d.m.Y H:i`, its plain text (`ws_plain_text()`) and "File: name".
- A mail: subject "Fwd: " once (an existing Fwd:/FW: kept) with the `[#T…]` tag dropped; the quote is "---------- Forwarded message ----------", From, Date (sent time, else received), Subject, To, Cc (never Bcc), a blank line and `body_text`.
- The quote is cut at 45,000 characters (`WS_MAIL_FORWARD_QUOTE_MAX`), "[…]" where it was cut, to leave room in the 50,000 of a mail.
- Kept in: the box keeping mail in the source's channel, else the first. The unsent form is kept apart (`ws-mail-new:…|fwd`), brought back only for the same source; carried files taken out are not kept. The Signature goes under the whole text, quote included, when the mail leaves (`ws_mail_send_body()`).

## Which files go with it?
- The source's files in order while they fit a mail: at most 10 and 20 MB together (`ws_mail_forward_take()`); the rest is named "Left out, over the limits of an e-mail: …". Files the person adds count toward the same limits, checked by `ws_mail_send_input()`.

## What does Send check?
- `ws_mail_new` with `forward_kind`, `forward_ids`, `forward_files`: `ws_mail_forward_request()` reads the source again (`ws_mail_forward_source()`), so a message deleted since, a channel no longer readable or a file id the source does not carry is refused or ignored; the kept files' bytes come from `pg_file_local_copy()`. The mail then goes through `ws_mail_send()` like any new mail: a conversation of its own, customer the first To, status Waiting, holder the writer.
- Messages: `ws_forward_pick()` (one channel the person reads, up to 20, written by people, applications or guests). A mail: readable in its own message's channel or in its conversation's channel.

## What is the line under a forwarded mail?
- A `ws_messages` row of the new out item (`sender_kind` mail, empty body, no file) under the source conversation's card, else under the mail's own message; `ws_message_touch()` on that card. Not written into an archived channel or one the person does not read.
- `ws_mail_card_html()` draws it (`ws_mail_forward_is_line()`: an out item's message that is neither its text nor a file) as the send badge, "Forwarded this e-mail to …" and "Open the e-mail" to `workspace.php?channel=…&message=<new card>`.

## Why is the reply drawer `replyForm()`?
- WM5 (#8719) added New e-mail's `compose(h, options)` in the same scope as the reply drawer's `compose(message, view, h, draft)`; the later one replaced both, so "Reply by e-mail" on the card, in the pane and on Pinegrap AI's draft failed. WM6 renamed the drawer; a test keeps one `function compose(`.

## Tests?
- `tests/workspace_mail_forward_test.php` (pure parts, Node, wiring by source).
