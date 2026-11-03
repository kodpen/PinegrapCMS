---
name: panel-bfcache
description: Panel HTML in the browser's back/forward cache (#6081): private, no-cache from output_header(), the no-store screen list, Clear-Site-Data on sign-out, the session check of a restored page
---

## Why is panel HTML not no-store?
- Owner decision (#6081, `workflow/decisions-made-product-21.md`): list, record, Back is the panel's most frequent move, and a `no-store` page never enters the back/forward cache, so every Back reloaded the screen (~1 s). The `no-store` came from PHP's session default (`session.cache_limiter = nocache`), not from a choice in the code.

## Where is the header set?
- One place: `pg_panel_cache_headers()` (`includes/fn/output.php`), called by `output_header()`, so only panel screens change; site pages and `output_header_secure()` screens keep the session default.
- It replaces the session's `Cache-Control` with `private, no-cache` (every ordinary visit still revalidates) and removes `Pragma` and `Expires`. A screen that sent its own `Cache-Control` before `output_header()` keeps it (`pg_panel_cache_is_session_default()`; `device_pairing.php` sends `no-store`).

## Which screens keep no-store?
- `pg_panel_no_store_screens()`, the one list: `api_settings.php` (secret shown once), `connections.php`, `connected_apps.php` (connected-account tokens), `edit_user.php` (sign-in factors, recovery), the key code screens. Sign-in, password reset and set, two-factor setup, passkeys and account security do not use `output_header()` and keep the session's `no-store`. A new screen that shows a secret joins the list.

## What protects a shared computer?
- `logout()` (`includes/fn/forms.php`) sends `Clear-Site-Data: "cache"` (HTTPS only), which also drops back/forward cache entries.
- A page restored on `pageshow` (`persisted`) asks `session_state.php` (`{"signed_in": bool}`, `pg_session_signed_in()`) and reloads when the session is gone, so the server sends it to sign-in (`pg_preloader_markup()`).

## In-page JSON replies?
- The decision drops `no-store` from them too (`private, no-cache`); `pg_list_edit_reply()` does. The endpoints that send their own `no-store` are changed one by one; `api.php` belongs to its own work.
