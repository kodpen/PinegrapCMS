---
name: waf-trusted-proxy-headers
description: Each Trusted Proxies entry names the header its proxy sets; new or edited entries without one read X-Forwarded-For only, stored ones keep every vendor header (#2750).
---

## Which header does a trusted proxy name the visitor with?
- The one its entry names: `<address or range> <header>`, the header one of `cf-connecting-ip`, `x-sucuri-clientip`, `true-client-ip`, `fastly-client-ip`, `incap-client-ip`, `x-real-ip` (`waf_proxy_headers()`) or `x-forwarded-for`. `waf_proxy_entry()` splits an entry; a name it does not know reads as `x-forwarded-for`.
- `waf_client_ip_resolve()` asks `waf_proxy_client_headers($raw, $remote)`: the first entry covering the peer decides. An entry without a header reads every vendor header (CF-Connecting-IP first, for Cloudflare Tunnel); an `x-forwarded-for` entry reads none. The X-Forwarded-For walk follows in every case, over the ranges.
- Cloudflare's own edge ranges still read CF-Connecting-IP without an entry.

## What does a save do?
- `waf_proxy_list_on_save($posted, $stored)` in `includes/settings/firewall.save.php`: an entry stored before without a header keeps that reading; an entry added or edited without a header (or with an unknown name) is stored as `x-forwarded-for`. Removing a header from an entry is an edit too. The stored value is the comma list the chips field shows, the header inside the chip.
- Why: owner decision 2026-10-28 (security finding 2): most proxies only add X-Forwarded-For and pass the client's own True-Client-IP or X-Real-IP through, so a banned address got 200 by sending `True-Client-IP: 203.0.113.9`; the allow list, rate limits, maintenance list and the shield were fooled the same way. Existing sites keep working until the operator edits an entry.

## Who else reads the list?
- Anything that needs only "is this peer trusted" uses `waf_proxy_ranges()` (header stripped): `waf_ip_is_infrastructure()`, the XFF walk, `pg_geo_trusted_edge()` (`includes/geo.php`). Never pass the raw list to `waf_ip_in_any()`: an entry with a header matches nothing.
- The ban shield mirror: `waf_ban_shield_proxy_line()` writes `P <range> <$_SERVER keys, comma-separated | ->`, Cloudflare ranges with `HTTP_CF_CONNECTING_IP`. `pg_ban_shield_proxy_headers()` (`includes/db_guard.php`) reads exactly those keys (only `HTTP_*`), and a line without keys (a mirror written before) reads the old CF-Connecting-IP/True-Client-IP pair. The shield never walks X-Forwarded-For.
- Tests: `tests/waf_proxy_header_test.php`.

Related: docs/memory/waf/waf-architecture-and-phases.md, docs/memory/waf/waf-ban-shield-mirror.md, docs/memory/waf/waf-geo-country-lookup.md
