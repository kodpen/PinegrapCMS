---
name: waf-secure-mode-enable-guard
description: firewall.save.php refuses to enable Secure Mode from a request not seen as HTTPS: predicate, messages, early return, why disabling is unchecked.
---

## When may Secure Mode be switched on?
- Never from a request not seen as HTTPS. `firewall.save.php` asks `check_if_request_is_secure()` only on the OFF -> ON transition (`URL_SCHEME` is `http://` and `secure_mode` arrives checked). If false it leaves an error via `$liveform->add_error()`, resets `$url_scheme` to `URL_SCHEME` and `return`s: the whole save is NOT written (the `contact.save.php` Mailchimp pattern).
- Two messages: if `check_proxy_ssl_headers()` is true: "proxy reports HTTPS but the server sees plain HTTP: `TRUST_PROXY_SSL_HEADERS` or Full mode"; if false: "HTTPS never reaches the server: install a certificate". Both link to `test_secure_mode.php` and end with "save this setting from a session opened over HTTPS" (that is how an admin with working SSL who enters the panel via `http://localhost` gets through).
- The predicate is deliberately NOT `pg_request_is_https()`: it trusts `X-Forwarded-Proto` without opt-in and would let a Cloudflare Flexible setup pass.
- Saving while already on, and turning off, are NOT checked: a locked-out site's exit is turning it off.
- `screen.php` now skips `log_activity` and the "saved" notification when `check_form_errors()` is true (the pane path `settings_pane.php` already did); the redirect is unchanged and `output_errors()` shows the error.

Related: docs/memory/waf/waf-security-headers-hsts.md
