---
name: redirect-after-hostname
description: Never append raw input after HOSTNAME in a redirect; use pg_safe_redirect_path() everywhere, why it checks only the first two characters, and why it percent-encodes non-ASCII bytes.
---

## Why can raw input not follow HOSTNAME?
Pattern used everywhere: `header('Location: ' . URL_SCHEME . HOSTNAME . $send_to);`
- The attacker controls what comes right after the domain, not only after the slash. `@evil.com/x` -> `https://site.com@evil.com/x` (before `@` is userinfo, the browser goes to evil.com). `.evil.com/x` -> `https://site.com.evil.com/x`.

## What is the rule?
- Every user-sourced value placed after `HOSTNAME` goes through `pg_safe_redirect_path()`.
- Not only `header('Location:')`: also `window.parent.location = ...`, `<a href>`, the argument of `go()`.
- `build_url()` with a `hostname` property is a place too: it puts a slash in front of a path that has none (`@evil.com/x` became `https://site.com@evil.com/x` in a form's message confirmation); a caller still passes a posted address through `pg_safe_redirect_path()` first (`pg_form_payment_confirmation_url()`).
- JavaScript never navigates to a `send_to` it reads from the address bar: it takes the value the server checked and printed (the designer's Ctrl+G exit uses `_design.exitUrl`, from `pg_safe_back_url()`). A browser drops spaces and tabs from an address, so ` javascript:` or `java<TAB>script:` passed the old prefix check in JS; `tests/designer_exit_target_test.php`.
- `escape_javascript()` and `h()` are NOT URL validators: they escape quotes and tags, they do not check where the address goes.

## Why does pg_safe_redirect_path() look only at the first two characters?
- A design decision. A string that starts with `/` and whose second character is not `/` or `\` cannot change the authority when appended after `https://host`; the rest is path. Besides those two characters it refuses only control characters (header splitting), an empty value and a non-scalar (an array sent as `x[]`); anything refused becomes the fallback (`PATH` by default).
- So there is no `str_replace`, `parse_url` + reassembly or `urlencode()` of the whole value in it. Any "fix" that removes or rewrites characters of the body breaks:
  - `[` `]`: file version suffix, `name="x[]"` array params in 54 places (`/dosya [1].pdf`, `?filter[]=jpg`)
  - Turkish / UTF-8: product and page URLs (`/urun/çay_bardağı_seti`)
  - percent-encoding: searches, `send_to` (`?q=%C3%A7ay`)
  - `? # & =`: every query string
- No normalization is needed: the danger is in the prefix, not the body.

## Why does its answer come back percent-encoded?
- A `Location` value is a URI reference, ASCII only (RFC 9110 10.2.2). The browser read raw UTF-8 there as Latin-1: `/pg-ğ` led to `/pg-%C3%84%C2%9F`, which IIS answers with its own 400 (#8983, after the request address became UTF-8).
- So the answer, the fallback too, goes through `pg_uri_ascii()` (`includes/fn/request_scheme.php`): only bytes outside printable ASCII (0x21-0x7E), UTF-8 and spaces, become `%XX`. Every character above keeps its meaning, `[ ] ? # & = %` stay as they are, and a value encoded once comes back unchanged. The Secure Mode redirects (`init.php`, `get_file.php`) write the request address through it too.
- Tests: `tests/safe_redirect_path_test.php`.

Related: docs/memory/platform/redirect-validator-context.md
