---
name: send-to-url-safety
description: What pg_send_to_url() refuses (control characters, '//' and '/\' starts, non-paths) and why it matches pg_safe_redirect_path() (PR #3563)
module: core
---

## What does pg_send_to_url() refuse?
- It reads `$_REQUEST['send_to']`, trims it and returns the caller's fallback for: an empty value, any control character (`/[\x00-\x1F\x7F]/`), a value not starting with `/`, and a second character of `/` or `\`. Anything else comes back unchanged.
- About 30 panel screens use it for the back arrow, breadcrumb, Save/Cancel target and `add_file.php`'s upload redirect, so the rule lives only there.

## Why the same rule as pg_safe_redirect_path()?
- Browsers turn `/\host` into `//host` (a protocol-relative address to another site) and drop a tab or newline inside a URL. `pg_safe_redirect_path()` already refused both; `pg_send_to_url()` refused only CR/LF and `//`.

## What was the symptom?
- A link with `send_to=/\evil.example/` (or `/<TAB>/evil.example`) sent the panel user to another site when they pressed the back arrow or Save.

## Which test pins it?
- `tests/send_to_url_safety_test.php`: the off-site forms give the fallback; `/pinegrap/view_files.php?filter=x` and `/` come back unchanged.
