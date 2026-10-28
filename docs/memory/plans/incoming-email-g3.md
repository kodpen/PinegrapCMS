---
name: incoming-email-g3
description: G3 of incoming e-mail (reply by e-mail): G3a sending core, G3b box SMTP fields and the reply composer, G3c copy to Sent and bounces
---

## Which steps, and where do they stand?
- G3a: PR #2846. Sending core, no screen (`workspace/mail-reply.md`). `email()` takes `headers` (Message-ID, In-Reply-To, References only) and `smtp` (host, port, user, sealed secret; before the site's SMTP and connected account, 15 s timeout); every site mail carries `X-Pinegrap-Mail` with the site's mark. `includes/workspace/mail_reply.php`: pure builders (Message-ID `pg.c<conversation>.<random>@<host>`, subject `Re:` + `[#T<id>]`, In-Reply-To/References from the newest mail of the conversation, body with the writer's name and the quoted mail), `ws_mail_reply_check()`, `ws_mail_reply_send()` (sends at once, not queued: the writer sees a failure; then the `out` item, the thread message, `first_reply_at` / `last_out_at`).
- G3b: PR in progress (branch `feature/incoming-email-g3b-k1`): the box form's sending half (SMTP server and port, the service presets, `reply_via` box | site), "Reply by e-mail" on the card and its composer, the panel action, an outgoing mail drawn with its writer's name.
- G3c: an IMAP box `APPEND`s the reply to its Sent folder; a bounce (`Auto-Submitted`, DSN) adds a "not delivered" line to the conversation instead of being dropped by the loop gate.

## Which rules are fixed already?
- From the design (`incoming-email-design.md`) and answer 6: from the box address, the writer's name in the signature; the site SMTP only when the box address's domain is the site's mail domain (else DMARC fails); `connection` waits for G4. Assistants never send mail.
- Status (`waiting` after a reply) is G5's; G3 only writes `first_reply_at` and `last_out_at`.

## Where to start?
- G3b: `ws_mail_box_server_fields()` in `includes/workspace/mail_settings.php` for the form, `ws_mail_card_html()` in `mail.php` for the button.
