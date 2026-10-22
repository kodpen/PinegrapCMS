---
name: redirect-after-hostname
description: Never append raw input after HOSTNAME in a redirect; use pg_safe_redirect_path() everywhere and why it checks only the first two characters (a design decision).
---

## Why can raw input not follow HOSTNAME?
Pattern used everywhere: `header('Location: ' . URL_SCHEME . HOSTNAME . $send_to);`
- The attacker controls what comes right after the domain, not only after the slash. `@evil.com/x` -> `https://site.com@evil.com/x` (before `@` is userinfo, the browser goes to evil.com). `.evil.com/x` -> `https://site.com.evil.com/x`.

## What is the rule?
- Every user-sourced value placed after `HOSTNAME` goes through `pg_safe_redirect_path()`.
- Not only `header('Location:')`: also `window.parent.location = ...`, `<a href>`, the argument of `go()`.
- `escape_javascript()` and `h()` are NOT URL validators: they escape quotes and tags, they do not check where the address goes.

## Why does pg_safe_redirect_path() look only at the first two characters?
- A design decision. A string that starts with `/` and whose second character is not `/` or `\` cannot change the authority when appended after `https://host`; the rest is path. Besides those two characters it refuses only control characters (header splitting), an empty value and a non-scalar (an array sent as `x[]`); anything refused becomes the fallback (`PATH` by default).
- So there is no `str_replace`, `preg_replace`, `urlencode`, or `parse_url` + reassembly in it, and there never will be. Any "fix" touching the body breaks:
  - `[` `]`: file version suffix, `name="x[]"` array params in 54 places (`/dosya [1].pdf`, `?filter[]=jpg`)
  - Turkish / UTF-8: product and page URLs (`/urun/çay_bardağı_seti`)
  - percent-encoding: searches, `send_to` (`?q=%C3%A7ay`)
  - `? # & =`: every query string
- No normalization is needed: the danger is in the prefix, not the body.

Related: docs/memory/platform/redirect-validator-context.md
