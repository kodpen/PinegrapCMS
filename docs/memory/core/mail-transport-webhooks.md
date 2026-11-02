---
name: mail-transport-webhooks
description: mail_webhook.php — bounce, complaint and delivery reports of the API mail transports into the bounce record; URL key, Mailgun/Resend signatures, replays, SES subscription
module: mail_webhook
---

## Where do the reports arrive?
- `POST mail_webhook.php?t=<transport>&key=<secret>` (#5413), handled by `pg_mail_webhook_receive()` (`includes/fn/mail_webhook.php`). Each driver reads its provider's body with the pure `pg_mail_<key>_webhook($raw, $headers)`: a list of id, kind (hard, soft, complaint, delivered, confirm, ''), address, message_id.
- Answers: 405 not POST, 404 unknown transport, 429 over `PG_MAIL_WEBHOOK_PER_MINUTE` (own WAF rate bucket `mail_webhook`, counted even in monitor mode), 503 `mail_bounces` missing, 403 no key stored, 401 wrong key or signature (firewall offence), 413 over 256 KB, 400 unreadable, else 200.
- Listed in `waf_is_api_request()` and `waf_country_api_scripts()` like `carrier_webhook.php`.

## What is the credential?
- The secret key in the address for every transport, made on the first save of the transport on SMTP Settings and kept in its sealed secrets (`connect_apps` `mail_<key>`, `webhook_key`). It stays when another transport is chosen: a message sent earlier may still bounce. The card shows the address in the transport's pane.
- Mailgun and Resend (`webhook_signed` capability) also check the provider's signature when a "Webhook signing key" is stored (`webhook_signing`, same sealed array): Mailgun HMAC of timestamp+token, Resend Svix `v1` HMAC of `id.timestamp.body`. Without a stored signing key the URL key alone admits.
- SES via SNS: the SNS signature is not verified (it would need a certificate fetch); the URL key is the credential. A SubscriptionConfirmation is confirmed by a GET of `SubscribeURL` only when it is https on an `sns.<region>.amazonaws.com` host.

## What does a report change?
- hard, soft, complaint with a keyable address → `pg_mail_bounce_record($address, $kind, <transport key>, message_id)` (so `mail.bounced` / `mail.complained` are announced there). delivered is counted and records nothing; other kinds (open, auto-responder, Brevo `blocked`) are ignored.
- Replay: the event id among the last 500 (`data/temp/mail_webhook.json`, locked) or a signed report older than `PG_MAIL_WEBHOOK_MAX_AGE` (300 s) answers 200 and records nothing. The provider's message id is not stored by the queue (would need a schema change), so a report is placed by its address, not by the message.

## Mapping per provider?
- Postmark: Bounce `HardBounce`/`BadEmailAddress`/`ManuallyDeactivated` hard, `SoftBounce`/`Transient`/`DnsError`/`InboxFull` soft; SpamComplaint; Delivery.
- Mailgun: `failed` permanent hard, temporary soft; `complained`; `delivered`.
- Brevo: `hard_bounce`/`invalid_email` hard, `soft_bounce` soft, `spam` complaint, `delivered`.
- Resend: `email.bounced` Permanent hard, else soft; `email.complained`; `email.delivered`; one event per `to`.
- SES: Bounce Permanent hard, Transient/Undetermined soft; Complaint; Delivery (`notificationType` or `eventType`).

## How was it verified?
- `tests/mail_webhook_test.php`: each provider's documented payload, both signatures (good, wrong, stale), the decisions, the replay list, the card's save decision. No provider posted to a running site; the card was not opened in a browser.

Related: mail-bounces.md, mail-transports.md, docs/memory/plans/mail-deliverability.md
