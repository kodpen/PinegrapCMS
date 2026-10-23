---
name: form-submission-limits
description: custom_form.php protections - signed fill time, per address/e-mail/domain limits in waf_rate, StopForumSpam over https, staff-only exemption from CAPTCHA and the sensitive WAF limits.
---

## Why do they exist?
- Subscription bombing (kodpen.com, Oct 2026): scripts typed strangers' real addresses into a subscription form; the maths CAPTCHA did not stop them, and each victim got the submitter mail.

## What does custom_form.php check, in order?
- Only for `action == 'submit'` and a visitor who is not staff (`pg_user_has_panel_access()`), and only while the form has no error yet.
- Fill time: `pg_form_issued` = `<time>.<32 hex>` HMAC-SHA256 over `pg-form-issued|<time>` with `ENCRYPTION_KEY` (`pg_cf_issued_token()`). Under 3 s: "Please take a moment..."; missing, bad or over 24 h: "This form has expired...". No key: no field, no check. Printed by `pg_cf_issued_field()` in the three renderers: `get_custom_form_screen_content.php` (standard and `$system` of custom layouts) and the designer `$form_open` in `includes/fn/widgets.php`.
- CAPTCHA as before, then StopForumSpam, then the limits; the hook code runs after them.
- Every refusal is a liveform error, so the form comes back with what was typed.

## How do the limits count?
- `waf_rate` rows (`pg_cf_throttle_subjects()`): `cfi` the visitor's `waf_ip_subject()` per hour, `cfr` the address per day, `cfd` its domain per hour. Address trimmed and lower-cased; `waf_rate_key()` hashes subjects.
- `cfr`/`cfd` only when the form mails the submitter, adds to a contact group or auto-registers (`pg_cf_form_writes_to_submitter()`); a form that only stores has no recipient to protect. The address is `pg_cf_submitter_address_guess()`, the same order as the later code: contact e-mail field, account address, the only e-mail field.
- All rows are read first (`pg_cf_throttle_full_row()`); a full one refuses and counts nothing. Counted after the hook code, only for an accepted submission. Fails open like every `waf_rate` counter.
- Refusal: `waf_log_event('rate', 'form-<scope>', ...)` plus a `DENY rule=form-<scope>` line in `data/firewall.log`. No offence is registered: the domain counter is shared by innocent visitors.
- Works with the firewall off; sweeps itself (1 in 50). `waf_rate_sweep_queries()` keeps `|cfr` keys 24 h (`waf_rate_long_scopes()`).

## Where are the numbers set?
- `config.form_limit_ip_hour` 10, `form_limit_recipient_day` 3, `form_limit_domain_hour` 30, `form_spam_blacklist` 0 (2026.11.0 step 11.0.28); Site Settings, Security, Session & Password card. 0 turns a limit off; clamped 0..10000. Before the step, `waf_setting()` gives the same defaults.

## How does StopForumSpam work now?
- `pg_form_spam_ip_listed()`: `https://api.stopforumspam.org/api?ip=...&json`, `pg_curl_tls()`, `pinegrap_user_agent()`, 4 s; fails open with the old activity log line. On with the setting or a legacy `BLACKLIST` constant. `pg_form_spam_check()` asks once per request; comments, e-mail a friend and registration reach it through `validate_captcha_answer()`.

## Who is staff?
- `waf_panel_access_from_row()` in `waf.php`: role below 3, a `user_manage_*` flag, `manage_ecommerce_reports`/`manage_erp`/`manage_workspace`, or an `aclfolder` row with rights 2. `waf_user_is_staff()` caches per account and fails closed; `_pg_login_region_panel_access()` wraps it.
- Staff skip the CAPTCHA, these limits, the sensitive rate limit and the concurrency ceiling. Signed-in members do not: auto-registration signs in anyone who types an address. The global limit, the api.php limit and the signature scan still exempt any session.

Related: docs/memory/waf/waf-login-captcha.md, docs/memory/designer/custom-form-options-and-rules.md
