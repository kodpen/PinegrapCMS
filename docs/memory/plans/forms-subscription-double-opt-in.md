---
name: forms-subscription-double-opt-in
description: Plan (approved 2026-10-23; a, b, d done: #1386, #1385, #1401; c open): forms that add to a contact group get throttling, signed link variables, per-form double opt-in and a Turnstile/hCaptcha option
---

## Where does this come from?
- kodpen.com since 2026-10-13: most subscription form entries are strangers' real addresses (subscription bombing); the maths CAPTCHA does not stop it.

## What does the code do now?
- `custom_form.php` adds the contact to `contact_group_id` on submission (~1113-1150) and mails the typed address every time (~1294-1412).
- CAPTCHA (`includes/fn/auth.php`): plain "a + b", honeypot `body`, skipped only for staff; StopForumSpam over https. The WAF sensitive limit for `custom_form.php` (`waf.php`) is skipped only for staff (step a, `waf/form-submission-checks.md`).
- Signed links exist only in campaigns (`pg_email_preferences_signature()`, `includes/fn/mail.php` ~243). `consent_log` (11.0.16) has no form writer; statuses and sources are code lists (`privacy.php` ~117, ~127).

## What was decided?
- Route: a global open-source product with no dependence on Cloudflare; every protection works on any server.
- Captcha: a provider layer; the built-in maths question stays the default; Turnstile and hCaptcha optional, keys in settings; server-side verification. Provider unreachable or wrong keys: the submission is refused with a "try again" message and a rate-limited log line (fail closed).
- Only staff (panel access) skip the captcha and the WAF sensitive rate limit; signed-in members do not.
- Double opt-in: per-form switch `custom_form_pages.double_opt_in` (schema, default 0). When on, submission does not add to the group; a signed stateless link is mailed; GET shows a confirm button, POST confirms and adds to the group. `consent_log`: `pending` on submission, `granted` on confirmation, with IP and user agent, `purpose` = group id.
- Throttling: per recipient address 3/day, per IP 10/hour; per sender domain off by default (0), as one site-wide counter per domain can lock every Gmail user out for an hour (owner, 2026-10-24); config columns with these defaults (one schema step), editable on the Security settings screen. Minimum fill time via a signed timestamp field. StopForumSpam over https, switch default off.
- Variables: `^^email_preferences_url^^` (signed; only in the submitter's own mail, never in admin/watcher copies or on the confirmation page) and `^^confirm_subscription_url^^` (empty when double opt-in is off).
- kodpen.com unconfirmed members since 2026-10-13: site operation for the owner, no re-confirmation mail.
- Schema labels: a = 11.0.28 (config `form_limit_ip_hour` 10, `form_limit_recipient_day` 3, `form_limit_domain_hour` 0, `form_spam_blacklist` 0); c = 11.0.27 (`custom_form_pages.double_opt_in`).

## Steps?
- a — throttling per address/IP/domain (settings, 11.0.28), signed minimum fill time, StopForumSpam https, staff-only captcha and WAF exemption: done, PR #1386 (`waf/form-submission-limits.md`, `waf/form-submission-checks.md`)
- b — `^^email_preferences_url^^` and `^^confirm_subscription_url^^` in form mails: done, PR #1385 (`ui/form-mail-link-variables.md`); the confirmation link stays empty until step c fills it
- c — double opt-in switch (11.0.27), signed confirmation link and page, `consent_log` pending/granted (claim #1377, open)
- d — captcha provider layer: built-in default, Turnstile and hCaptcha, fail closed, CSP: done, PR #1401 (`waf/form-captcha-providers.md`)

## Where to start?
- Read `waf/waf-login-captcha.md`, `designer/custom-form-options-and-rules.md`, `sms-and-iys.md` step 4.
