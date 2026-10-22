---
name: waf-security-headers-hsts
description: Security headers are sent outside waf_run(), independent of the firewall switch; HSTS default off and its https gating; Permissions-Policy; nosniff and Referrer-Policy never doubled by server rules.
---

## Do security headers depend on the firewall switch?
- No. `waf_send_security_headers()` (both bootstraps) and `waf_send_csp_header()` (only init.php, since the router serves files) are called OUTSIDE `waf_run()`, so the "kill switch is absolute" rule stays intact: an operator who turns the firewall off must not lose response headers. They have their own switch `security_headers`.

## When is HSTS sent?
- HSTS is separate and default OFF: it is a one-year commitment and turning it off does not undo it.
- Sent only when Secure Mode is on (`url_scheme === 'https://'`; the box is passive on screen until then and forced to 0 on save) AND `check_if_request_is_secure()` is true. That function is the ONLY answer to "is this https?" (`TRUST_PROXY_SSL_HEADERS`, `test_secure_mode.php`); never write a second definition.
- For that reason HSTS goes with CSP from the init phase.
- `Permissions-Policy` is written with `(self)`, not `()`; `payment` is deliberately absent.

## Who sends X-Content-Type-Options and Referrer-Policy?
- Never write `X-Content-Type-Options` with `header()`: call `waf_send_nosniff()` (`pg_send_nosniff()` outside waf.php). When the site's IIS `web.config` adds it in `<customHeaders>` (the rule `includes/server_config.php` writes, kept for the static files PHP never serves), PHP sends no copy (`waf_server_adds_header()`): IIS appends its value to PHP's and its `<remove>` reaches only headers IIS added, so every page had `nosniff, nosniff`. Apache's `Header always set` replaces PHP's copy; nginx is not detected.
- `Referrer-Policy` comes from PHP only (`strict-origin-when-cross-origin`); pages that must not pass their address on (short links in `router.php`, OAuth authorize, API responses, Workspace guest and approval pages, product feeds) replace it with `header('Referrer-Policy: no-referrer')`. Never add it to a server rule (web.config, .htaccess, the nginx sample in `server_config.php`): the server copy is sent after PHP's, the browser takes the last valid policy, and `no-referrer` is lost. The IIS block carries `<remove name="Referrer-Policy" />`; an older web.config that still adds it makes PHP leave the header to IIS.
- Tests: `tests/waf_headers_test.php`.

Related: docs/memory/waf/waf-secure-mode-enable-guard.md
