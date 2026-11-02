---
name: mail-domain-health
description: The "Sending domain" card of SMTP Settings (SPF, DKIM, DMARC judged from DNS, the record to copy, 30-day bounce and complaint rates) and the mail_reputation operations alert
module: mail_domain_health
---

## Where is it?
- `smtp_settings.php`, card `#mail_domain_card` under the DKIM card; logic in `includes/fn/mail_domain_health.php`, loaded by the screen and by `pg_ops_alerts_check_mail_reputation()` (not by `functions.php`). Settings has no E-mail category in `includes/settings/`: SMTP Settings is the e-mail screen. 2026.11.0, plan mail-deliverability MD6.

## Which domain and records are checked?
- Domain: `DKIM_DOMAIN` unless empty or the default config's `example.com`, else the domain of `EMAIL_ADDRESS` (`pg_mail_sending_domain()`). Selector `DKIM_SELECTOR`, else `pinegrap` (as `email()` signs).
- SPF: the domain's TXT `v=spf1`. A transport (`pg_mail_transport()`) must be named by its include (`pg_mail_spf_includes()`: Postmark `spf.mtasv.net`, Mailgun `mailgun.org`, Brevo `spf.brevo.com`/`spf.sendinblue.com`, Resend and SES `amazonses.com`; a subdomain of it counts); SMTP by the system host (`a:`/`include:`/`mx:`/`exists:`/`redirect=` naming the host or a domain above it). A connected account or `mail()`: any SPF record is green. Two records or `+all` are flagged; the offered text inserts the mechanism before `all`/`redirect=`.
- DKIM: `<selector>._domainkey.<domain>`, its `p=` against the one in the stored zone line (`files.description` of `dkim.key`). With a transport the service signs: "could not check" with a pointer to its portal. No key generated: yellow.
- DMARC: `_dmarc.<domain>`; missing or no valid `p=` red (offers `p=none` with `rua=` the site address), `none` yellow, `quarantine`/`reject` green; two records red.

## When is a line "could not check"?
- `dns_get_record()` missing or returning false, and a resolver that knows neither NS nor SOA of the domain or a domain above it (`pg_mail_dns_answers()`): some hosts answer every query with an empty list, which would read as missing. Never red for a lookup failure.
- The lookups run on every view of the screen (no cache); a dead resolver slows the screen by its timeout.

## How are the rates counted?
- `pg_mail_reputation_counts()`: sends = delivered campaign recipients (`complete = 1, failed = 0`) of campaigns whose `start_time` is in 30 days + `mail_outbox` rows `sent` in 30 days (the queue deletes sent rows after 7 days, so in practice the last week); bounces = `mail_bounces` rows hard/soft with `last_at` in the window (one per address), complaints likewise. Direct `email()` sends are not counted.
- `pg_mail_reputation_rates()`: over when bounces > 5 % or complaints > 0.3 % (constants in the module); below 100 sends nothing is judged.

## When does the alert fire?
- `mail_reputation` (warning, url the card) in `pg_ops_alert_codes()`, checked by `pg_ops_alerts_check_mail_reputation()` in `pg_ops_alert_sources()` after `mail`; once a day by the common rule (`ops-alerts.md`); nothing before the `mail_bounces` table exists. No threshold setting (it would need a column).

## How was it verified?
- `tests/mail_domain_health_test.php`: SPF, DKIM and DMARC samples, offered texts, lookup failure, the limits and the minimum, the alert's registration. The card was rendered from CLI with sample data; no live DNS (the sandbox resolver answers nothing) and no browser run.

Related: mail-transports (core), mail-bounces (core), ops-alerts.md
