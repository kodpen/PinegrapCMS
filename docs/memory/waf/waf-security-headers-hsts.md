---
name: waf-security-headers-hsts
description: Security response headers are sent outside waf_run() and independent of the firewall switch; HSTS default off and its https gating; Permissions-Policy form.
---

## Do security headers depend on the firewall switch?
- No. `waf_send_security_headers()` (both bootstraps) and `waf_send_csp_header()` (only init.php, since the router serves files) are called OUTSIDE `waf_run()`, so the "kill switch is absolute" rule stays intact: an operator who turns the firewall off must not lose response headers. They have their own switch `security_headers`.

## When is HSTS sent?
- HSTS is separate and default OFF: it is a one-year commitment and turning it off does not undo it.
- Sent only when Secure Mode is on (`url_scheme === 'https://'`; the box is passive on screen until then and forced to 0 on save) AND `check_if_request_is_secure()` is true. That function is the ONLY answer to "is this https?" (`TRUST_PROXY_SSL_HEADERS`, `test_secure_mode.php`); never write a second definition.
- For that reason HSTS goes with CSP from the init phase.
- `Permissions-Policy` is written with `(self)`, not `()`; `payment` is deliberately absent.

Related: docs/memory/waf/waf-secure-mode-enable-guard.md
