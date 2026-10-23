---
name: form-captcha-providers
description: Turnstile and hCaptcha beside the built-in maths question - where the keys live, render and server check, fail closed, staff-only exemption, CSP and the cookie notice.
---

## What is it?
- PR #1401: `includes/fn/captcha.php`. The built-in maths question stays the default and needs no account; Cloudflare Turnstile or hCaptcha can replace it (Security settings, Spam Protection card). Not tied to Cloudflare: any provider is optional.

## Where are the keys?
- `connect_apps` rows `captcha_turnstile` / `captcha_hcaptcha` (`pg_captcha_app_key()`), like `sms_<key>`: site key in `client_id`, secret sealed with `ENCRYPTION_KEY` and never drawn back, `enabled` = the chosen provider. No schema.
- `pg_captcha_active()` (memoized, one query): the first enabled row whose site key and secret are both readable. Incomplete or unreadable keys fall back to the maths question, never to no CAPTCHA.

## How is it drawn?
- `get_captcha_fields()` prints the honeypot `body`, a label and `pg_captcha_widget()`; `get_captcha_info()` returns `question` '' (layouts drop their answer box) and the widget in `system`. The designer `captcha` section binding and layout `$system` need no change.
- The script tag goes out with the first widget of the request only (a static flag): both providers refuse a second load. A widget whose markup is dropped after being built (a register widget without a `captcha` section) spends the flag.
- The fill-time field (`pg_cf_issued_field()`) is printed by the renderers, CAPTCHA or not.

## How is it checked?
- `validate_captcha_answer()`: honeypot first, then `pg_captcha_check()`: siteverify POST (`pg_curl_tls()`, `pinegrap_user_agent()`, 5 s connect, 10 s total) with secret, token, `waf_client_ip()`, and `sitekey` for hCaptcha. StopForumSpam runs only after `pass`.
- `pg_captcha_verify_outcome()` (pure): success -> pass; token codes (`invalid-input-response`, `timeout-or-duplicate`, `not-using-dummy-passcode`...) or a bare failure -> retry; key codes (`invalid-input-secret`, `sitekey-secret-mismatch`...) -> misconfigured; non-200, no JSON, `internal-error`, cURL error -> unavailable.
- Fails closed: misconfigured and unavailable refuse with "The spam check could not be completed..." and one `log_activity` line per provider and outcome per 10 min (`waf_rate_record`, scope `cap_<outcome>`). A missing token never calls the provider.

## Who is exempt?
- Only staff (`pg_user_has_panel_access()`, step a); signed-in members see the widget.

## CSP and consent?
- `waf_csp_policy()` adds `pg_captcha_csp_sources()` to the built-in policy through `waf_csp_add_sources()` while `config.captcha` is on (the `CAPTCHA` constant is defined after the header goes out). `waf_csp_add_payment_sources()` is a wrapper of it. An operator's own policy is never changed; the card lists the sources to add.
- The provider is a necessary row in `pg_consent_catalogue()` and not held behind consent: the form cannot be sent without it.

## Settings?
- `pg_captcha_settings_decide()` (pure) -> `pg_captcha_settings_check()` before the config row is written (asks siteverify with a made-up token: a key error marks the secret field, no answer becomes a "saved without being checked" notice) -> `pg_captcha_settings_save()` after. Choosing one provider switches the others off; the built-in question with an emptied site key deletes that provider's row.
- Tried against both siteverify endpoints with their test keys: Turnstile names a wrong secret (`invalid-input-secret`) even for a made-up token; hCaptcha judges the token first (`invalid-input-response`), so a wrong hCaptcha secret shows up only on the first real submission (refused and logged). The widget itself was not tried on a running site.

Related: docs/memory/waf/form-submission-checks.md, docs/memory/designer/custom-form-options-and-rules.md
