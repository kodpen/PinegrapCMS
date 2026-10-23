---
name: waf-architecture-and-phases
description: waf.php structure: standalone module (no functions.php, no HTTP), what runs in the router vs init phase, signature scanning only in init, fail-open, waf_client_ip(), upgrade compatibility.
---

## What is `waf.php` and why is it standalone?
- An independent module with NO dependency on `functions.php`, because it is called from two different bootstraps: `router.php` (before anything loads `functions.php`; `get_file.php` never reaches `init.php`) and `init.php` (every directly requested software script). A call into `functions.php` there is an undefined function: outside `waf_run()`'s try/catch a fatal error on every routed address, files included, inside it a check that silently stops working. For text use `waf_text()` (docs/memory/platform/router-no-functions-php.md).
- No HTTP call in waf.php or in `includes/geo.php`, which it loads under the same rule: fetches live in `includes/fn/` (`pg_waf_refresh_ai_ranges()`, the location database in `includes/fn/geo.php`).

## Which phases exist?
Two phases; every check has its own latch, so the phase that reaches it first decides and the second call repeats nothing:
- `waf_run('router')` in router.php after `REQUEST_URL` is defined: IP lists, bot classification (attack-tool UA first), country restriction, GLOBAL rate limit. The session has not started yet.
- `waf_run('init')` in init.php after `initialize_user()`: adds signature scanning, the SENSITIVE and `api.php` rate limits and the in-flight cap. Why there: only here can a designer be told apart from an attacker.

## Which rules must never change?
- Signature scanning MUST stay in the `init` phase. Page regions are edited from the page's own frontend URL; there is no session in the router phase, so scanning there would make saving a region containing `<script>` indistinguishable from an attack.
- Fail-open is mandatory. The `waf_run()` body is in try/catch; if the schema is missing, a regex errors or a table is unreachable, the request PASSES. A firewall that breaks a live store is worse than no firewall.

## How is the real client IP found?
- `waf_client_ip()`. `$_SERVER['REMOTE_ADDR']` behind Cloudflare is the edge server, not the visitor. Proxy headers are accepted only if the peer is a known proxy (Cloudflare ranges embedded, the rest from `waf_trusted_proxies`); otherwise an attacker forges the header and bypasses the IP ban.
- Every place that needs the visitor's address asks it (sign-in throttle, `check_banned_ip_addresses()`, the external API, OAuth, Workspace guests, consent and audit records, signatures, `includes/geo.php`); never copy its proxy logic.

## How does it stay compatible with an un-upgraded database?
- `waf_schema_ready()` is SEPARATE from `waf_config()`. On an un-upgraded DB the config row is still read, because `block_unknown_bots` lives there and predates this file. Merging them would silently switch off the existing bot filter for an operator who took the update but did not upgrade the DB.
- The firewall settings save (`includes/settings/firewall.save.php`) rewrites only `source='manual'` rows. The old code `TRUNCATE`d, which would now wipe auto bans and the allow list on every settings save.
