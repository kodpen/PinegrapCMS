---
name: waf-ban-shield-mirror
description: The ban shield mirror data/temp/ban_shield.txt: line types B/P/A/L, the ban's end on B lines, who writes it, staleness asymmetry, allow-list lines, the shield's page, shield_pending.log and drain.
---

## What is the ban shield mirror?
- File `data/temp/ban_shield.txt`, the single writer is `waf_ban_shield_refresh()` in waf.php. It lets one decision run BEFORE the DB: "is this address already banned?" The shield only BLOCKS, it never counts (no rate limit, no signature scan, no counter).
- Line types: `B` block, `P` proxy, `A` allow, `L` fail2ban log key.
- Proxy (`P`) lines matter as much as block lines: behind a CDN `REMOTE_ADDR` is the edge, a mirror that cannot resolve matches nothing. Do NOT copy the `waf_client_ip()` logic; write the list it resolved into the file (two copies drift apart). A `P` line carries the `$_SERVER` keys the shield may read for that proxy (`waf-trusted-proxy-headers.md`).
- Atomic write. Refreshed instantly on auto-ban with `waf_ip_lists(true)`, at most every 5 minutes by a request with a DB, and on every tick of the general job (`job.php`, `waf_ban_shield_refresh(true)`). It only recognises an ALREADY banned address: the first wave still reaches the DB.
- A block line is `B <entry> <expires>` (`waf_ban_shield_block_line()`, the end from `banned_ip_addresses.expires_at` via `waf_ip_block_expiries()`, 0 for no end). `pg_ban_shield_block_entry()` skips a line whose end has passed and reads an old line without an end as no end (#2157).
- Why the end: on a quiet site only a request that is not banned refreshed the file and cron never did, so the banned operator, alone on the site, waited up to 6 hours past a 60-minute ban, and deleting the row changed nothing (#1989).

## Which lines go stale?
- Block lines go stale (6 hours) so a removed ban is not enforced forever from a file with no screen. Proxy (`P`) and allow (`A`) lines do NOT go stale. Deliberate asymmetry:
  - A stale ban is dangerous (applies what the operator removed, from a file with no screen).
  - A stale proxy range is not (CDN blocks change rarely; it only decides WHICH address the request is written under).
  - A stale allow admits someone one layer early to be checked later; a stale ban gives an unexplainable rejection. Only one of these takes a site down.
- Ageing proxy lines together with blocks would leave the shield silently useless behind a CDN: with no proxy line it falls to `REMOTE_ADDR` (the edge) and bans are tried against an address in no ban list. A long outage is exactly when the mirror cannot refresh (refresh needs the DB).

## Does the shield obey the allow list and is it logged?
- Yes. The shield checks `A` lines BEFORE the block check. Otherwise an operator who bans a range and allows their own address inside it would be turned away by the one layer with no screen.
- The shield answers 403 with `pg_ban_shield_page()`, an English-only twin of `waf_deny()`'s page (no `lang()` before the DB): it names the firewall, shows the ban's end and a reference (`pg_ban_shield_reference()`, the shape of `waf_reference()`), plus `Retry-After` for a ban with an end. Before #2157 it answered plain `Forbidden`.
- The shield has no DB: a rejection appends one line to `data/temp/shield_pending.log` (`ts \t ip \t path \t reference`, 256 KB cap; `waf_shield_pending_line()` still reads the old three-field line). The first request that has a DB runs `waf_shield_drain()` and writes it to `waf_log` in bulk (`ip-list-shield`, `action=block`, same 5-minute bucket key, the group's latest reference in `reference`, at most 200 groups per drain).
- A rule applied unconditionally must be visible unconditionally. The drain also runs BEFORE the mode check: what happened is a fact, not a decision.

Related: docs/memory/waf/waf-no-counters-before-db.md
