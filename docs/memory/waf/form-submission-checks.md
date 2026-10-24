---
name: form-submission-checks
description: custom_form.php protections other than the limits - order of the checks, signed fill time and its retry rule, StopForumSpam over https, the staff-only exemption and the gaps still open.
---

## Why do they exist?
- Subscription bombing (kodpen.com, Oct 2026): scripts typed strangers' real addresses into a subscription form; the maths CAPTCHA did not stop them. PR #1386. The limits: `waf/form-submission-limits.md`.
- The addresses that went into kodpen.com's contact group since 2026-10-13 without a confirmation are cleaned up by the owner as a site operation; Pinegrap sends them no re-confirmation mail.
- Every check works on any server, with no Cloudflare or other CDN in front: Pinegrap is an open-source product, so Turnstile and hCaptcha stay optional providers (`waf/form-captcha-providers.md`), never a requirement.

## What does custom_form.php check, in order?
- Only for `action == 'submit'` by a visitor who is not staff (`pg_user_has_panel_access()`), and only while the form has no error yet: fill time, StopForumSpam, the limits; then the hook code.
- The CAPTCHA (`validate_captcha_answer()`) runs between fill time and StopForumSpam whenever `CAPTCHA` is on, errors or not; a provider (Turnstile/hCaptcha) is asked only while the form has no error yet.
- Every refusal is a liveform error, so the form comes back with what was typed.

## How does the fill time work?
- `pg_form_issued` = `<time>.<32 hex>`, HMAC-SHA256 over `pg-form-issued|<time>` with `ENCRYPTION_KEY` (`pg_cf_issued_token()`). Under 3 s: "Please take a moment..."; missing, bad or over 24 h: "This form has expired...". No key: no field, no check. Checked whether or not the CAPTCHA is on.
- Printed by `pg_cf_issued_field()` in the three renderers: `get_custom_form_screen_content.php` (standard and `$system` of custom layouts) and the designer `$form_open` in `includes/fn/widgets.php`.
- A protected submission sent back with any error sets `$_SESSION['software']['pg_form_retry']`; the next form drawn (used once, ignored after 10 min) is signed 3 s in the past (`pg_cf_issued_time()`), so an immediate second submit is not refused as too fast.
- Stateless by design: the token is not tied to a session, form or page and can be reused for 24 h. One harvested token plus one 3 s wait passes everywhere; it stops only the simplest scripts.

## How does StopForumSpam work now?
- `pg_form_spam_ip_listed()`: `https://api.stopforumspam.org/api?ip=...&json`, `pg_curl_tls()`, `pinegrap_user_agent()`, 4 s; fails open with the old activity log line. On with `config.form_spam_blacklist` (off by default, 11.0.28: it sends the visitor's address to a third party) or a legacy `BLACKLIST` constant. `pg_form_spam_check()` asks once per request; comments, e-mail a friend and registration reach it through `validate_captcha_answer()`.

## Who is staff?
- `waf_panel_access_from_row()` in `waf.php`: role below 3, a `user_manage_*` flag, `manage_ecommerce_reports`/`manage_erp`/`manage_workspace`, or an `aclfolder` row with rights 2. `waf_user_is_staff()` caches per account only after a real answer (no connection or a failed query is "no" for now, not kept) and fails closed; `_pg_login_region_panel_access()` wraps it.
- Staff skip the CAPTCHA, the form limits, the sensitive rate limit and the concurrency ceiling. Signed-in members do not: auto-registration signs in anyone who types an address.

## What is still open?
- The global limit, the api.php limit and the signature scan still exempt any session (`waf_user_is_authenticated()`), so an auto-registered member skips the signature scan. Moving them to staff-only is not planned yet.

Related: docs/memory/waf/waf-login-captcha.md, docs/memory/designer/custom-form-options-and-rules.md
