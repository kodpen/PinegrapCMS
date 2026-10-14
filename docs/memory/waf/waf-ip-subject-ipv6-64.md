---
name: waf-ip-subject-ipv6-64
description: waf_ip_subject(): counters key on the address for IPv4 and on the /64 for IPv6; why IPv4 /24 is not aggregated; no /64 ban over an allowed address.
---

## What does a per-address counter count?
- The SUBJECT, not the raw address: `waf_ip_subject()` returns the address itself for IPv4 and the `/64` for IPv6, in CIDR form (the same string is a valid ban-list pattern, so what is counted can be banned).
- Why: an IPv6 subscriber gets at least a /64 and every address in it is free; an address-keyed bucket never exceeds 1, and a ban on the last-used address costs nothing.
- Users: the three rate limits, `waf_register_offence()` (count and ban target), the login lock `'a'` subject, `pwreset-ip`, and the concurrent request cap. `waf_log.ip_address` still stores the real address.

## Why is IPv4 /24 not aggregated?
- An operator block behind CGNAT counted as one bucket would give real customers 429 on a campaign day; rotation is not free in IPv4 anyway.

## When is a /64 ban NOT placed?
- `waf_subject_contains_allowed()`: if the block contains an address from the allow list, only the offending address is banned. The shield carries only `B` lines, so a block ban would turn the allowed address away without ever consulting the allow list.

Related: docs/memory/waf/waf-ban-ladder.md
