---
name: download-assistant-session
description: Who starts the session in the root download_assistant.php: pinegrap/init.php on an installed site, the assistant itself (da_session_start()) only before an install (#10439)
---

## Who starts the session of the download assistant?
- On an installed site (`pinegrap/init.php` and a `DB_DATABASE` in `pinegrap/data/config.php`), `init.php` does, with the panel's cookie settings: HttpOnly, SameSite Lax, strict mode, Secure over https. The assistant then calls `da_session_start()`, which starts one only if none is running.
- Before an install, or with a config that names no database, the assistant starts its own plain session with `da_session_start()` before it reads `$_SESSION`.

## Why not at the top of the file any more?
- The assistant called `session_start()` on its first line and included `init.php` later. PHP refuses session ini settings while a session is active, so the panel's cookie settings were never applied there and every visit logged "Session ini settings cannot be changed when a session is active" (seen in the owner's PHP 8.5 error log, `core/error-reporting.md`).
- Nothing before the "Check if Pinegrap exists" block reads the session, so the start moved into its three branches.

## What keeps it?
- `tests/download_assistant_session_test.php`: no `session_start()` at the top, every start skips a running session, `init.php` comes first on an installed site, and the session starts before the first `$_SESSION` read.
