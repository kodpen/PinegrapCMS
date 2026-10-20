---
name: security-findings-2026-10-waf
description: Security findings of the 2026-10-20 firewall and helper scan: IP matching, proxy headers, path exclusions, bot rate exemption, country rule, db error text, back URLs; one PR each
---

## Where does this come from?
- Bug scan of `development` @ `60b0d27` (2026-10-20, #706), sandbox-checked (PHP 8.3, MariaDB 10.11, firewall in block mode, trusted proxy 127.0.0.1). Kept out of public issues (SECURITY.md). The API customers item is item 3 of `security-findings-2026-10-api.md`. Check `pinegrap-verilmis-kararlar` first; none is on it.

## What is still open? (one PR each)
1. **IP lists compared as text** (medium). `waf_ip_matches()` (`waf.php` ~565) is `===` outside CIDR, so `2001:DB8::1`, `2001:0db8::1` never match a stored `2001:db8::1`; `waf_ip_in_cidr()` never compares 4- and 16-byte forms, so `::ffff:1.2.3.4` (dual-stack socket) misses `1.2.3.4`, `1.2.3.0/24`, the Cloudflare ranges and `127.0.0.0/8` (tunnel: everyone becomes one counted address). Same in `waf_ip_matches_simple()` (`includes/db_guard.php`) and the maintenance allow list. Fix: compare `inet_pton()` forms, map `::ffff:a.b.c.d` to IPv4.
2. **Any trusted proxy may name the visitor with a vendor header** (high, owner question). `waf_client_ip()` (~284) reads CF-Connecting-IP, True-Client-IP, X-Real-IP… from every trusted proxy before X-Forwarded-For; most proxies only add XFF and pass the client's own headers through. Sandbox: banned address with `True-Client-IP: 203.0.113.9` → 200; same for the allow list, rate limits, maintenance list and `pg_ban_shield_client_ip()`. Which header does each proxy set: a per-proxy choice, or vendor headers only from that vendor's ranges?
3. Done (PR #953): `waf_is_excluded()` matches the path cut at the script it runs (`waf_path_to_script()`); PATH_INFO cannot claim an exclusion (`tests/waf_exclusion_test.php`).
4. **Requests classed as bots skip every rate limit** (high, owner question). With `block_unknown_bots` off (default) `waf_handle_bot()` (~3743) defines `IS_BOT` for curl, an empty UA or any `bot` token, and for good-bot names it cannot verify (PTR-less "Googlebot", WhatsApp, Stripe…); the global, sensitive, api.php and in-flight limits return on `IS_BOT` (~4073, 4102, 4184, 4235). Sandbox: 36 POSTs/min to `index.php` → Firefox UA 6×429, curl/empty/fake Googlebot 0×429. Exempt only `waf_bot_verified()`?
5. **db() failures print the MySQL error with DEBUG off** (low). `pg_db_run()` (`includes/fn/core.php` ~275) puts `h($err)` into the message; `output_error()`'s DEBUG gate only covers its own line. Fix: log it, let `output_error()` decide.
6. **Country rule skips the visitor scripts of the software directory** (low–medium, owner question). `waf_country_is_panel_request()` (~3857) counts `add_comment.php`, `custom_form.php`, `registration_entrance.php`, `express_order.php`… as panel; a refused country posts to them (sandbox 200, `/` 403). Staff-only, as for api.php, minus the panel sign-in screens?
7. **`pg_safe_back_url()` passes `/\evil.com` and `https://evil.com\@site/`** (low). `includes/fn/output.php` ~1409: the root-relative branch rejects only `//`, the absolute one trusts `parse_url()`'s host; Cancel/exit buttons (`edit_theme_css.php`, `designer_screen.php` …) go off-site. Fix: the second-character rule of `pg_safe_redirect_path()`, no `\` in an absolute URL.

## Where to start?
- 1, 3, 5, 7 are fixes; 2, 4, 6 change behaviour and wait for the owner's answer.
