---
name: waf-secure-mode-enable-guard
description: firewall.save.php refuses to enable Secure Mode from a request not seen as HTTPS: predicate, the messages per peer kind, the one-step Trusted Proxies offer, early return, why disabling is unchecked.
---

## When may Secure Mode be switched on?
- Never from a request not seen as HTTPS. `firewall.save.php` asks `check_if_request_is_secure()` only on the OFF -> ON transition (`URL_SCHEME` is `http://` and `secure_mode` arrives checked). If false it leaves an error via `$liveform->add_error()`, resets `$url_scheme` to `URL_SCHEME` and `return`s: the whole save is NOT written (the `contact.save.php` Mailchimp pattern).
- The messages, all linking `test_secure_mode.php`: no header claims https: "HTTPS never reaches the server: install a certificate, save from a session opened over HTTPS" (that is how an admin with working SSL who enters the panel via `http://localhost` gets through); a loopback or private peer claims it: "tick Add this proxy to Trusted Proxies to list <entries> and turn Secure Mode on in one step"; a public peer: "list all its published ranges first (Cloudflare is recognised on its own) or Full mode"; any other peer, or before the `waf_trusted_proxies` column exists: "list it first or Full mode". With the box ticked the save lists the peer and goes on: `platform/secure-mode-proxy-offer.md`.
- The predicate is deliberately NOT `pg_request_is_https()`: it reads the forwarded headers without the peer check (`platform/proxy-https-trust.md`) and would let a Cloudflare Flexible setup behind an unlisted proxy pass.
- Saving while already on, and turning off, are NOT checked: a locked-out site's exit is turning it off.
- `screen.php` now skips `log_activity` and the "saved" notification when `check_form_errors()` is true (the pane path `settings_pane.php` already did); the redirect is unchanged and `output_errors()` shows the error.

Related: docs/memory/waf/waf-security-headers-hsts.md
