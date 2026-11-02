---
name: waf-secure-mode-query-param
description: The ?secure_mode=false query parameter in init.php and get_file.php: ignored on a Secure Mode site, a 302 to http only on a site without it (#5146).
---

## What does `?secure_mode=false` do?
- On a Secure Mode site (`URL_SCHEME === 'https://'`) nothing: the http to https 302, the 403 on an insecure POST and the proxy-loop guard run as for any request, and no `Location: http://` is ever sent.
- On a site without Secure Mode, a request that arrived over https is sent to the same address over http with a **302** (never 301: a 301 stays cached in the browser).
- The block lives in `init.php` and, as a copy kept in step, in `get_file.php`; change both together. `tests/secure_mode_param_test.php` pins both.

## Why was the escape hatch removed?
- No product link builds the parameter. Before #5146 anyone could append it to a link and drop a visitor on a Secure Mode site to http with a cached 301, the session cookie then sent without `Secure`.
- The documented escape for a site locked out of https is `define('REQUIRE_SECURE_MODE', false)` in `config.php`, or turning Secure Mode off (`waf-secure-mode-enable-guard.md`).
- `pg_sw_url_param_reserved()` still reserves the name.

Related: docs/memory/waf/waf-secure-mode-enable-guard.md, docs/memory/waf/waf-security-headers-hsts.md
