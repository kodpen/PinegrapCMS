---
name: redirect-validator-context
description: Redirect validation corner rules: leave escape_url() alone, validators are context-bound (pg_safe_back_url vs go()), go() hardening, no hand-written checks.
---

## Why must escape_url() not be tightened?
- It deliberately accepts `//`, `http://`, `https://` addresses: it validates for **display**.
- Screens printing `http_referer` (`print_order.php`, `edit_submitted_form.php`) and calendar links carry external addresses; there an external address is data, not an error. Tightening it does not fix redirects and breaks the reports.

## Can a validator be moved to another context?
- `pg_safe_back_url()` allows relative `.php` paths and is correct inside `href=` (the browser resolves against the current directory, same origin).
- The same output given to `go()` gets `HOSTNAME` glued in front: `x.evil.com/a.php` -> `https://site.comx.evil.com/a.php`, a subdomain the attacker can register.
- Rule: when putting a value in a new place, separately prove its validation holds there. Two different places need two different validations.

## How is go() hardened, centrally for every caller?
- An address with a scheme is accepted only if it starts with our own prefix, and the prefix is stripped. Prefix match alone is not enough (`https://site.com.evil.com` shares it): the prefix must be followed immediately by `/`, `?` or `#`.
- A relative path without a leading slash is resolved against the running script's directory (as the browser does for `href`). Pasting it raw onto `HOSTNAME` is the subdomain attack above.
- Comparison is plain string; with `parse_url` + rebuild, square brackets in the query string risk being rewritten.
- Whatever is left then goes through `pg_safe_redirect_path()` before `HOSTNAME` (`includes/fn/core.php`), so every `go()` call is covered.
- The one redirect input exempted as "server-generated" is `$_SERVER['PHP_SELF']` (always starts with `/`). Exempt nothing else on that ground.

## Should I write my own check?
- No. Three hand-written checks existed and all missed `\`: `preg_match('#^/[^/]#', $back)` passes `/\evil.com`. Call the central function.
- Do not trust `parse_url()` output shape either (what `path` gets for `@evil.com/x` must be read from behavior). Validate before parsing so the question disappears.

Related: docs/memory/platform/redirect-after-hostname.md
