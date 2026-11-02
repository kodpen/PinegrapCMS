---
name: mail-deliverability
description: Plan (owner 2026-11-02): bounces and complaints stop sending to an address, API mail transports beside SMTP, a sending-domain health card; steps MD1-MD7
---

## What is wrong now?
- Every message leaves through one SMTP connection (`pg_mail_connection_*()` in `includes/fn/mail.php`, PHPMailer). The queue gives up on permanent refusals (`core/mail-queue-permanent-errors.md`) and campaigns store refused deliveries (`core/email-campaign-send.md`), but nothing reads what comes back later: a bounce sent minutes after the delivery or a spam complaint lands in the sender's inbox, and the address is mailed again on the next campaign.
- Mailbox providers rate a sender by its bounce and complaint rates; a list that keeps mailing dead addresses is throttled or sent to spam for everyone, transactional mail included.
- `List-Unsubscribe` with one-click (RFC 8058) and DKIM signing (`pg_dkim_key_pair()`, `pg_dkim_txt_record()`) exist; there is no screen that says whether the site's DNS carries the DKIM record, an SPF record naming the sending host, or a DMARC policy.
- A host without SMTP, or a provider that only offers an API (Amazon SES, Postmark, Mailgun, Brevo, Resend), cannot send at all.

## What is proposed?
- A bounce record per address: kind (hard, soft, complaint, manual), source, count, last time, the message it came from. A hard bounce or a complaint stops marketing and transactional mail to that address; soft bounces stop it after N in a row; a change of address or an explicit resubscribe clears it. Read by the mail queue (before the send, never after), campaigns, SMS stays untouched.
- Two ways a bounce arrives: a mailbox the site reads (the Workspace mailbox fetcher, `includes/workspace/mail_fetch.php`, `includes/fn/mail_parse.php`) in which delivery status notifications are told apart by a VERP return path (`pg_mail_envelope_sender()` gains a per-message token), and provider webhooks (`mail_webhook.php`, one signed endpoint per transport).
- Transports as drivers, `includes/mail/<key>.php`, the SMS layer's pattern (`platform/sms-provider-layer.md`): `pg_mail_<key>_send()`, `_capabilities()`, `_fields()`, `_webhook()`; SMTP stays the default and the fallback; secrets sealed (`core/security-helpers-and-secret-storage.md`); every call through `pg_curl_run()`.
- A domain health card in Settings › E-mail: the DNS records found (SPF, DKIM selector, DMARC), what is missing with the texts to copy, the bounce and complaint rates of 30 days, a bell warning (`ops_alerts`, code `mail_reputation`) when a rate crosses its threshold.

## Which decisions bind it?
- Legacy behaviour unchanged when nothing is configured: SMTP as today, no suppression until a bounce is recorded. A suppressed address is never deleted from `contacts`; the contact keeps its consent rows (`core/consent-log-writers.md`).
- No outside library: SES SigV4 signing is the same code the object-storage plan writes (`object-storage.md`, OS1); Postmark, Mailgun, Brevo and Resend are plain JSON over HTTPS.
- Texts through `tr_add.php`; every new function in `includes/fn/mail_bounces.php` or the driver file; events `mail.bounced` and `mail.complained` through `pg_announce()` so webhooks and Flows see them.

## Which questions are decided? (#5036)
1. New table `mail_bounces`, one row per address with its current state; shape in the issue.
2. Soft bounces: 3 in 30 days (a module constant); owner: a complaint also sets `contacts.opt_in` to 0 with a consent-log row.

## Where does it start?
- MD1 and MD3 touch different files and start together; read `core/README.md` key rules, `core/mail-queue-permanent-errors.md`, `core/email-campaign-send.md`, `platform/sms-provider-layer.md`, `includes/fn/mail.php` from `pg_mail_hostname()` on. Steps: `mail-deliverability-steps.md`, `-steps-2.md`.
