---
name: site-path-detection
description: How a request finds the site path (PATH) from SCRIPT_NAME and the software folder, in any case; when config.path is written and when it is not (cron reads it)
---

## How is PATH found?
- A web request takes `SCRIPT_NAME` up to the last `/` + `SOFTWARE_DIRECTORY` (init.php; the same in get_file.php `initialize_url_constants()`), searched with `mb_strripos()`: any case, because the folder name comes from disk and the address keeps the spelling it was typed with (Windows and macOS reach `/shop/Pinegrap/` and `/shop/pinegrap/` alike). The site root's index.php takes `dirname(SCRIPT_NAME)`.
- `router.php` keeps the case-sensitive search in its own copies; its else branch only serves a script other than the root index.php.

## When is config.path written?
- A cron run (no `HTTP_HOST`) takes `PATH` from `config.path`, so mail links built by jobs use it. A web request whose PATH differs writes it there.
- Not found at all (should never happen): that request is drawn from `/`, but the guess is not stored (`$pg_path_guessed` in init.php).
- Symptom (#865): a subfolder site on Windows opened as `/shop/Pinegrap/…` was not found case-sensitively; PATH became `/`, was written to `config.path`, and campaign and job mail went out with root links until the next correctly spelled request. Fixed by both rules above.
