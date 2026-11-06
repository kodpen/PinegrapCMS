---
name: mail-bounce-verp
description: VERP return path with a signed per-message token, pg_mail_parse_dsn() for delivery reports and ARF complaints, and the Workspace mailbox that reads bounces and records them
module: mail_bounces
---

## What does the return path carry?
- A queued message (`pg_mail_queue_run()` sets `bounce_ref` = `q<id base36>`) or a campaign message (`pg_email_campaign_mail_properties()` with the campaign's `id`/`email_campaign_id`: `c<id>`) leaves with `Sender` = `<box local>+<ref>.<16 hex>@<box domain>`. The hex is HMAC-SHA256(`ENCRYPTION_KEY`, ref + "\n" + the lower-cased recipient) (`pg_mail_bounce_token()`); no table.
- Only when `email()` sends through PHPMailer (not a connected account), the message has exactly one `to`, a Workspace mailbox is on with "Reads bounces" (`pg_mail_bounce_mailbox()`), the key exists, the local part stays within 64 characters, and the mailbox's domain equals the envelope sender `pg_mail_envelope_sender()` chose (`pg_mail_bounce_envelope()`: the SMTP account may send as it, SPF checks the same domain). Otherwise the envelope is as before.
- Other mail (system mail sent at once, Workspace answers) carries no token: their reports reach the Workspace as before (`workspace/mail-sent-and-bounces.md`).

## How is a report read?
- `pg_mail_parse_dsn($raw)` (`includes/fn/mail_parse.php`, pure): `multipart/report` delivery-status (first `Action: failed` block; delayed is none), ARF feedback-report (complaint; `Feedback-Type: not-spam` is none) or a plain-text notice from MAILER-DAEMON/postmaster or with a failure subject (a delay warning is none). Kind: status 5.x.x hard, 4.x.x soft; without one a basic SMTP code or the words (full, quota, temporary) decide, else hard.
- It returns the tokens found anywhere in the raw mail (`pg_mail_bounce_tokens_in()`) and the candidate addresses: the reported recipient, the returned message's To, every address in the text, without the return path and MAILER-DAEMON/postmaster/abuse.
- `pg_mail_bounce_dsn_match()` keeps the first candidate a token signs; none means nothing is recorded (anyone can send a mailbox a report). A report that names its failed recipient (`Final-Recipient`/`Original-Recipient`, ARF `Original-Rcpt-To`) is matched against that address only: a bcc copy (campaign bcc, order receipt bcc) leaves with the return path signed for the `to`, and its report's returned headers say `To:` that recipient, so before the fix the bcc's bounce suppressed the `to`. Only a plain-text notice, which names no recipient, still falls back to the candidates. `pg_mail_bounce_record_dsn()` records it, source `dsn`, the returned Message-ID and the campaign id for a `c` ref (`core/mail-bounces.md`).

## Where is it read?
- Mailbox form: "Reads bounces" (`settings.reads_bounces` in `ws_mail_boxes.settings`, no column). The first enabled box with it (lowest id) is the return path.
- `ws_mail_fetch_box()`: in such a box a downloaded mail with a token goes to `ws_mail_fetch_records_bounce()` before `ws_mail_ingest()`; recorded means taken (marked seen or moved as `after_fetch` says), never stored in the channel, counted in `bounces`. Unmatched reports are ingested as before. Uploaded `.eml` files are not read for bounces.

## Tests?
- `tests/mail_bounce_verp_test.php` with `tests/fixtures/mail_dsn/`: Gmail, Outlook (headers only), Yahoo (soft), Postfix, Postfix delay, qmail plain text and ARF. The fixtures are written to each provider's published report layout, not captured mail. No IMAP read or real bounce was run.
