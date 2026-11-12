---
name: mail-reply
description: Answering a Workspace e-mail by e-mail (WM7): the queued answer, Cc, Bcc, files, Reply to all, the drawer, route, headers and subject, the API's answer sent at once
---

## How does the panel's answer leave?
- WM7 (#8779): action `ws_mail_reply` (form parts, as New e-mail) → `ws_mail_send()` with `conversation_id`, cc, bcc, text, files. `ws_mail_send_check()` takes box, channel and route from `ws_mail_reply_target()` (may post in the channel, box on, customer address, a route), To the customer, the subject from `ws_mail_reply_subject()`, then the new mail's limits (`mail-send.md`).
- `ws_mail_send_answer()`: the out item `queued` with `in_reply_to` and `reference_ids` of the newest incoming mail (`ws_mail_reply_answered()`), its text and files under the card, the conversation `waiting` (`closed_at` 0, `first_reply_at` once) when written, not when it leaves; then `ws_mail_send_dispatch()`. Waiting to be sent, Not sent, Send again and the sent-folder copy as for a new mail.
- The mail: `ws_mail_send_headers()` from the item; text `ws_mail_answer_body()`: the writer, the box's Signature (its name without one), the answered mail quoted (60 lines, read when it leaves).

## And the API's answer?
- `POST …/reply` (`mail-api.md`) keeps `ws_mail_reply_send()` and its contract: `email()` at once, `not_sent` with the server's words, only the customer, the same text. Its item reads sent.

## Whom does Reply to all add?
- `ws_mail_answer_others()`: the answered mail's sender when not the customer, its To and Cc, without the customer and the box's own addresses (`ws_mail_answer_own()`: the address, any +tag of it, a route's whole address), each once, at most 19. They go into Cc; To stays the customer.

## Where does a person answer?
- The card's "Reply by e-mail" (`ws_mail_reply_offer()`: the card, for a reader who may post); the E-mails pane's Reply, and Reply to all when `ws_mail_pane_tools()` counts others; "Open the reply draft" under Pinegrap AI's draft.
- The drawer (`replyForm()` asks `ws_mail_reply_form`, then `answerForm()`): "To …", the "Reply to all" box with "Also to …", Cc and Bcc, the answer with saved replies, Files, Send. Texts `ws_mail_js_strings()`.
- Under a failed answer only Send again: workspace.js draws `node()` where `mail_reply` or `mail_resend` is set; the resend offer stands on the item's own message, not its files.

## Which route?
- `ws_mail_reply_route()`: `reply_via = box` needs `settings.smtp_host` (port `smtp_port`, default 587) and the box's IMAP `user` and sealed `secret`; `site` only when the box address's domain is `ws_mail_reply_site_domain()` (`DKIM_DOMAIN`, else the domain of `EMAIL_ADDRESS`), else DMARC fails; `connection` through the box's connected account (`mail-microsoft.md`).
- Box form "Sending answers": a new sending server asks for the password again, so a kept one never reaches a server it was not typed for.

## Which headers and subject?
- Message-ID `pg.c<conversation>.<16 hex>@<pg_mail_hostname()>` (the box's domain without one). In-Reply-To the newest incoming mail, References its references plus it, the last 20.
- Subject `Re: ` unless it starts with an answer prefix (Re, AW, SV, VS, Ynt, Antw, R), plus ` [#T<id>]` unless the tag is there (`ws_mail_find_conversation()`).
- `email()`'s fields and the site's mark: `mail-email-fields.md`.

## What is kept?
- The out item: `auth = pass`, `from_email` the box, `from_name` and `user_id` the writer, `body_text` without quote or signature, the hashed Message-ID the customer's answer is found by; drawn under its writer (`ws_mail_sender()`) with its send badge and "To …".

## Which PRs, which rules?
- G3a #2846, G3b #2872, G3c #2885 (`mail-sent-and-bounces.md`). Assistants never send mail, only drafts (owner answer 7, #2444).

## Tests?
- `tests/workspace_mail_reply_test.php` (pure parts, Reply to all, wiring), `workspace_mail_send_test.php` (the answer's headers and path). Never run against an SMTP server.
