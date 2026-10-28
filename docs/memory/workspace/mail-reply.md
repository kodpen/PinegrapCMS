---
name: mail-reply
description: Answering a Workspace e-mail conversation by e-mail (mail_reply.php, workspace_mail.js): route, sending fields, the drawer, headers, subject tag, the outgoing item, email() headers/smtp, site mark
---

## How does an answer leave?
- `ws_mail_reply_send($viewer, $conversation_id, $text)`: `ws_mail_reply_check()` (text 1–50,000 characters, the viewer may post in the conversation's channel, box on, customer address valid, a route), then `email()` at once, not queued: the writer waits and sees a failure (`not_sent` with `pg_mail_last_error()`). `notify_sender` is false.
- `ws_mail_reply_route()`: `reply_via = box` needs `settings.smtp_host` (port `smtp_port`, default 587) and the box's IMAP `user` and sealed `secret`; `site` only when the box address's domain is `ws_mail_reply_site_domain()` (`DKIM_DOMAIN`, else the domain of `EMAIL_ADDRESS`), else DMARC fails; `connection` is refused until G4.
- From: the box's name and address; the signature is the writer's name and the box's name; the customer's newest incoming mail is quoted (60 lines).

## Where does a person answer, and where is it set up?
- Payload `mail_reply` = `ws_mail_reply_offer()`: the conversation id on a conversation's card for a reader who may post, else 0. `assets/js/workspace_mail.js` (`PGWsMail`, in the workspace bundle) draws "Reply by e-mail" under it; the drawer posts `ws_mail_reply` (`actions.php`), which answers with the reason and the server's words on failure. Texts `ws_mail_js_strings()`.
- Box form "Sending answers": `reply_via` box | site, `smtp_host` / `smtp_port` in `settings` (a service fills its own, e.g. smtp.gmail.com 465). A new sending server asks for the password again, so a kept password never goes to a server it was not typed for.
- An outgoing item is drawn under its writer (`ws_mail_sender()`), badge "Sent by e-mail" and "To <address>".

## Which headers and subject?
- Message-ID `pg.c<conversation>.<16 hex>@<pg_mail_hostname()>` (the box's domain without one). In-Reply-To the newest incoming mail, References its references plus it, the last 20.
- Subject `Re: ` unless it starts with an answer prefix (Re, AW, SV, VS, Ynt, Antw, R), plus ` [#T<id>]` unless the tag is there (`ws_mail_find_conversation()` uses it when a program drops the headers).

## What is kept?
- A `ws_mail_items` row `direction = out`, `auth = pass`, `from_email` the box, `from_name` and `user_id` the writer, `body_text` what was written (no quote), hashed Message-ID, so the customer's answer finds the conversation by In-Reply-To.
- A thread message under the card (`sender_kind = mail`), `last_out_at` and `first_reply_at` (once). Status changes are G5's.

## What did email() gain?
- `headers`: only Message-ID, In-Reply-To, References, each `<id@host>` tokens (`pg_mail_thread_headers()`; anything else dropped).
- `smtp`: host, port, user, `secret` sealed with `pg_connect_seal()` (a queued row never holds the password); before the connected account and the site SMTP; 15 s timeout; an unreadable secret is a permanent failure.
- Every mail carries `X-Pinegrap-Mail: <pg_mail_site_mark()>`, 16 hex of an HMAC with the site's key. The loop gate (`ws_mail_loop_reason()`) refuses only this site's mark, so another Pinegrap site's mail (a shop to its supplier's support box) comes in.

## What happens after it leaves?
- The copy in the box's sent folder and a bounce's "Not delivered" line: `mail-sent-and-bounces.md`.

## Tests?
- `tests/workspace_mail_reply_test.php` (pure parts, the header filter, offer and wiring); `tests/workspace_mail_card_test.php` (sending fields, a sent answer); `tests/workspace_mail_ingest_test.php` the mark. Sending was not run against an SMTP server in the cloud sessions.
