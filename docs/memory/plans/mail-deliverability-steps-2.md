---
name: mail-deliverability-steps-2
description: Steps MD5-MD7 of plans/mail-deliverability.md: campaign hygiene, the sending-domain health card and its alert, close-out; claim as [plan:mail-deliverability] MD<n>
---

## Which steps are there?
- Sizes, tests and memory rules as in `mail-deliverability-steps.md`; MD5 and MD6 run beside MD2 and MD4 once MD1 and MD3 are merged.

- MD5 (S) Campaign hygiene. After MD1.
  - The campaign screens (`view_email_campaigns.php`, the campaign report) show suppressed, bounced and complained counts per send beside the delivered count; the contact list gets the filter "Suppressed" (`pg_contact_list_filters()` in `includes/fn/contact_list.php`) and a badge on the contact card with "Allow again"; a complaint sets `contacts.opt_in` to 0 and writes the consent log line (question 2, `core/consent-log-writers.md`); the contacts export carries the status; personal data export and erasure (`core/personal-data-export.md`, `-erasure.md`) cover the bounce rows. Tests on the count queries' SQL builders. Memory: `core/mail-bounces.md` grows a "Which screens show it?" section.

- MD6 (M) Domain health card. After MD1.
  - Settings › E-mail card "Sending domain" (`includes/settings/`): `dns_get_record()` for the SPF record (does it name the sending host or the transport's include?), the DKIM selector TXT (does it match `pg_dkim_txt_record()`?), DMARC (`_dmarc` present, its policy); each line green, yellow or red with the exact record text to copy; the bounce and complaint rates of the last 30 days from MD1's table against the sends counted by the queue; ops alert `mail_reputation` in `includes/fn/ops_alerts.php` when a rate passes 5 % bounces or 0.3 % complaints (once a day, the alert rules of `platform/ops-alerts.md`). The DNS answers are parsed by pure functions tested on record samples; a DNS failure shows "could not check", never red. Memory: `platform/mail-domain-health.md`.

- MD7 (S) Close-out. After MD1-MD6.
  - PR body names `[NEW] Added bounce and complaint handling, API mail transports and a sending-domain health card`; lasting decisions to `core/mail-bounces.md`, `core/mail-transports.md` and `platform/mail-domain-health.md`; the three plan files and the line in `feature-plans-2026-11.md` deleted; `php tools/memory_index.php`.
