---
name: waf-ban-shield-mirror
description: The ban shield mirror data/temp/ban_shield.txt: line types B/P/A/L, who writes it, staleness asymmetry, allow-list lines, shield_pending.log and drain.
---

## What is the ban shield mirror?
- File `data/temp/ban_shield.txt`, the single writer is `waf_ban_shield_refresh()` in waf.php. It lets one decision run BEFORE the DB: "is this address already banned?" The shield only BLOCKS, it never counts (no rate limit, no signature scan, no counter).
- Line types: `B` block, `P` proxy, `A` allow, `L` fail2ban log key.
- Proxy (`P`) lines matter as much as block lines: behind a CDN `REMOTE_ADDR` is the edge, a mirror that cannot resolve matches nothing. Do NOT copy the `waf_client_ip()` logic; write the list it resolved into the file (two copies drift apart).
- Atomic write. Refreshed instantly on auto-ban with `waf_ip_lists(true)`. It only recognises an ALREADY banned address: the first wave still reaches the DB.

## Which lines go stale?
- Block lines go stale (6 hours) so a removed ban is not enforced forever from a file with no screen. Proxy (`P`) and allow (`A`) lines do NOT go stale. Deliberate asymmetry:
  - A stale ban is dangerous (applies what the operator removed, from a file with no screen).
  - A stale proxy range is not (CDN blocks change rarely; it only decides WHICH address the request is written under).
  - A stale allow admits someone one layer early to be checked later; a stale ban gives an unexplainable rejection. Only one of these takes a site down.
- Ageing proxy lines together with blocks would leave the shield silently useless behind a CDN: with no proxy line it falls to `REMOTE_ADDR` (the edge) and bans are tried against an address in no ban list. A long outage is exactly when the mirror cannot refresh (refresh needs the DB).

## Does the shield obey the allow list and is it logged?
- Yes. The shield checks `A` lines BEFORE the block check. Otherwise an operator who bans a range and allows their own address inside it would be turned away by the one layer with no screen.
- The shield has no DB: a rejection appends one line to `data/temp/shield_pending.log` (`ts \t ip \t path`, 256 KB cap). The first request that has a DB runs `waf_shield_drain()` and writes it to `waf_log` in bulk (`ip-list-shield`, `action=block`, same 5-minute bucket key, at most 200 groups per drain).
- A rule applied unconditionally must be visible unconditionally. The drain also runs BEFORE the mode check: what happened is a fact, not a decision.

Related: docs/memory/waf/waf-no-counters-before-db.md
