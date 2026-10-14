---
name: waf-architecture-and-phases
description: waf.php structure: standalone module, router vs init phase, signature scanning only in init, fail-open, waf_client_ip(), upgrade compatibility.
---

## What is `waf.php` and why is it standalone?
- An independent module with NO dependency on `functions.php`, because it is called from two different bootstraps: `router.php` (get_file.php / robots.txt / sitemap.xml, which never reach `init.php`) and `init.php` (every directly requested software script).

## Which phases exist?
Two phases, separate latches:
- `waf_run('router')` in router.php after `REQUEST_URL` is defined: IP lists, attack-tool UA, GLOBAL rate limit. Why there: the session has not started yet.
- `waf_run('init')` in init.php after `initialize_user()`: adds signature scanning and the SENSITIVE rate limit. Why there: only here can a designer be told apart from an attacker.

## Hard rules
- Signature scanning MUST stay in the `init` phase. Page regions are edited from the page's own frontend URL; there is no session in the router phase, so scanning there would make saving a region containing `<script>` indistinguishable from an attack.
- Fail-open is mandatory. The `waf_run()` body is in try/catch; if the schema is missing, a regex errors or a table is unreachable, the request PASSES. A firewall that breaks a live store is worse than no firewall.

## How is the real client IP found?
- `waf_client_ip()`. `$_SERVER['REMOTE_ADDR']` behind Cloudflare is the edge server, not the visitor. Proxy headers are accepted only if the peer is a known proxy (Cloudflare ranges embedded, the rest from `waf_trusted_proxies`); otherwise an attacker forges the header and bypasses the IP ban.
- Users: `check_banned_ip_addresses()` and the external API (`includes/api/auth.php`, `router.php`).

## Backward compatibility
- `waf_schema_ready()` is SEPARATE from `waf_config()`. On an un-upgraded DB the config row is still read, because `block_unknown_bots` lives there and predates this file. Merging them would silently switch off the existing bot filter for an operator who took the update but did not upgrade the DB.
- `settings.php` rewrites only `source='manual'` rows. The old code `TRUNCATE`d, which would now wipe auto bans and the allow list on every settings save.
