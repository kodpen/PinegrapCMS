---
name: mail-email-fields
description: What email() gained for Workspace mail (G3a #2846, #8646): cc and bcc, the threading headers it lets through, a box's own SMTP server, and the site's mark every mail carries for the loop gate
---

## Which fields did email() gain?
- `cc`: an address or a list, as `bcc` (#8646), in the failure log too.
- `headers`: only Message-ID, In-Reply-To, References, each `<id@host>` tokens (`pg_mail_thread_headers()`; anything else dropped).
- `smtp`: host, port, user, `secret` sealed with `pg_connect_seal()` (a queued row never holds the password); before the connected account and the site SMTP; 15 s timeout; an unreadable secret is a permanent failure.
- Port 465 is implicit TLS (SMTPS), any other port STARTTLS when offered. "certificate verify failed" on 587 while the box reads mail fine: the provider's submission port shows another certificate (Natro's mail.kurumsaleposta.com: an expired `*.natrohost.com`, 2026-11-12); 465 has the right one. Changing only the port in the box form keeps the password.

## What is the site's mark?
- Every mail carries `X-Pinegrap-Mail: <pg_mail_site_mark()>`, 16 hex of an HMAC with the site's key. The loop gate (`ws_mail_loop_reason()`) refuses only this site's mark, so another Pinegrap site's mail (a shop to its supplier's support box) comes in.

## Who uses them?
- Answers and new mails of the Workspace (`mail-reply.md`, `mail-send.md`); `keep_mime` for the sent-folder copy is in `mail-sent-and-bounces.md`.

## Tests?
- `tests/workspace_mail_reply_test.php` (the header filter), `tests/workspace_mail_send_test.php` (cc), `tests/workspace_mail_ingest_test.php` (the mark).
