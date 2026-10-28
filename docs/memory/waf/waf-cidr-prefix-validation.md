---
name: waf-cidr-prefix-validation
description: waf_ip_in_cidr() / waf_ip_matches_simple() must reject empty or non-numeric CIDR prefixes (ctype_digit); the (int) cast bug that banned everyone.
---

## How is a CIDR pattern validated?
- `waf_ip_in_cidr()` and its mirror twin `waf_ip_matches_simple()` validate the part after `/` with `ctype_digit()`. An empty or non-numeric prefix returns `false` (pattern rejected).
- The old code used `(int) $parts[1]`. `(int) "abc"` and `(int) ""` are ZERO, and zero is a valid prefix length ("compare no bits"), so the function returned `true` for EVERY address in the world: `1.2.3.4/`, `1.2.3.4/abc` and `1.2.3.4/24x` all banned everyone.
- Why it mattered: the ban list is a free-text field the operator types by hand (a trailing slash is one keystroke), and the shield applies the pattern BEFORE the database from a file with no screen, so there was nowhere to show the error.
- Addresses are compared packed (`waf_ip_packed()`, also in `waf_ip_matches_simple()`), `::ffff:a.b.c.d` as IPv4, and `waf_client_ip()` is normalised (`waf_ip_normalize()`): as text, a ban on `2001:db8::1` missed `2001:0db8::1`, and a dual-stack socket's `::ffff:1.2.3.4` missed `1.2.3.0/24` (#952, `tests/waf_ip_match_test.php`).
- `0.0.0.0/0` deliberately still covers everyone: the pattern the operator wrote is honoured, a pattern they did not write is not invented.

Related: docs/memory/waf/waf-ban-shield-mirror.md
