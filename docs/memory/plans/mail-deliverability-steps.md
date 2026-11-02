---
name: mail-deliverability-steps
description: Steps MD1-MD4 of plans/mail-deliverability.md: bounce record and send gate, VERP and mailbox parsing, transport drivers, webhooks; claim as [plan:mail-deliverability] MD<n>
---

## Which run side by side?
- Wave 1: MD1 (the record and the gate) and MD3 (the transport layer) at once, different files. Wave 2, after both: MD2, MD4, MD5, MD6 at once, up to four workers. Wave 3: MD7. Questions 1-2 of the plan before MD1. MD5-MD7: `mail-deliverability-steps-2.md`.
- S under 400 product lines, M about 1,000. Each step: tests in `tests/mail_<topic>_test.php` (pure functions, red first), its memory file under `core/` (`platform/` for the card), texts through `tr_add.php`.

## Which steps are there?
- MD1 (M) Bounce record and the send gate. `free` after questions 1-2.
  - Schema sub-step of the open version (`install_create_table()`, in `get_tables()`), rerun twice on a dev DB. New `includes/fn/mail_bounces.php`: `pg_mail_bounce_record($address, $kind, $source, $ref)`, `pg_mail_address_blocked($address)` (hard or complaint, or N soft in 30 days), `pg_mail_bounce_clear($address)` on an address change or resubscribe; `mail_queue.php` asks the gate before a send and marks the row `suppressed` (a queue outcome, not a failure); `email_campaign_send.php` skips suppressed addresses and counts them. Memory: `core/mail-bounces.md`.

- MD2 (M) VERP return path and the mailbox reader. After MD1.
  - `pg_mail_envelope_sender()` gains a signed per-message token (`bounce+<token>@<mail host>`, HMAC of queue id and address, no table); a Workspace mailbox marked "reads bounces" (mailbox settings, `includes/workspace/mail_settings.php`) hands DSNs (`multipart/report`, `message/delivery-status`, the common plain-text shapes) to `pg_mail_parse_dsn()` in `mail_parse.php`, which returns address, kind and the token; `ws_mail_fetch_box()` records the bounce and files the DSN out of the channel. Tests with real DSN samples from Gmail, Outlook, Yahoo and Postfix in `tests/fixtures/mail_dsn/`. Memory: `core/mail-bounce-verp.md`.

- MD3 (M) Transport drivers. `free`.
  - `includes/mail/driver.php` (the contract comment, as `includes/payments/driver.php`), `pg_mail_transport()` picks the driver from `config.mail_transport` (default `smtp`), `pg_mail_connection_deliver()` calls `pg_mail_<key>_send($message)` for a driver and keeps SMTP as it is; drivers `postmark.php`, `mailgun.php`, `brevo.php`, `resend.php` (JSON APIs through `pg_curl_run()`), `ses.php` (SigV4 from `includes/fn/aws_sign.php`, shared with `object-storage.md` OS1: whichever lands first writes it, the other reuses it). Settings › E-mail: transport pick, its fields, Send a test. Tests: each driver's request body from fixed inputs. Memory: `core/mail-transports.md`.

- MD4 (S) Transport webhooks. After MD1 and MD3.
  - `mail_webhook.php` (root, URL-called): `?t=<key>` verifies the provider's signature or token (sealed in the driver settings), maps bounce, complaint and delivered events to `pg_mail_bounce_record()`; a replay or an unknown message id answers 200 and does nothing; WAF rate bucket as other webhooks. Tests with each provider's documented payload. Memory: `core/mail-transport-webhooks.md`.
