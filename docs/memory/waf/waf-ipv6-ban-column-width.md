---
name: waf-ipv6-ban-column-width
description: IP columns must be VARCHAR(45) (IPv6); how a VARCHAR(15) ip_address silently truncated and defeated bans; atomic waf_auto_ban ODKU (2026.2.7).
---

## Why must a new IP column be `VARCHAR(45)`?
- `banned_ip_addresses.ip_address` was `VARCHAR(15)` (exactly `255.255.255.255`), older than IPv6. An IPv6 address was SILENTLY truncated after char 15, giving a compound bug:
  1. `waf_auto_ban()` looked up the existing row by the FULL address and missed (the stored one was truncated).
  2. It INSERTed; MySQL truncated again.
  3. `waf_ip_matches()` compared the full address with the truncated stored value: `false`, so the ban was never enforced.
  4. The same client offended again, back to step 1.
- Symptom: dozens of identical ban rows for one address while it browsed unblocked. Both duplicates and non-enforcement came from this single column. IPv4 (12-15 chars) was never affected, so it went unnoticed for months.
- RULE: any new IP column is `VARCHAR(45)` (longest IPv4-mapped IPv6 form). `waf_log` and `waf_ip_reputation` are sized correctly.

## How is auto-ban written now?
- `waf_auto_ban()` is a single atomic `INSERT ... ON DUPLICATE KEY UPDATE`. The old SELECT-then-INSERT also had a race: two concurrent requests from one attacker both saw "no row" and both inserted. `uniq_entry(ip_address, list_type, source)` makes duplicates structurally impossible.
- The manual list INSERT in `settings.php` also uses ODKU; otherwise saving the screen a second time failed with a UNIQUE violation (`Query failed`).

Related: docs/memory/waf/waf-ban-ladder.md
