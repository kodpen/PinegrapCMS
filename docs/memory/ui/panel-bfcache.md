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
- `pg_panel_no_store_screens()`, the one list: `api_settings.php` (secret shown once), `connections.php`, `connected_apps.php` (connected-account tokens), `edit_user.php` (sign-in factors, recovery), the key code screens, and the screens that print a bearer link (#6143 review): `workspace_calendar_feed.php` (iCal feed token, every visit), `erp_accountant.php` (accountant link and QR), `view_short_links.php`, `view_folders.php` (one-time links), `view_cart_quotes.php`, `edit_erp_quote.php` (links shown once), `workspace_mcp.php` (value shown once), `view_not_found_log.php` (a short link made from a 404 row, its full address shown once), and the settings screens that print a stored secret into a field: `settings_security.php` (Google client secret), `settings_commerce.php` (payment gateway keys and passwords, the product feed link). Sign-in, password reset and set, two-factor setup, passkeys and account security do not use `output_header()` and keep the session's `no-store`. A new screen that shows a secret joins the list.

## What protects a shared computer?
- Every path that ends a session calls `pg_session_end_clear_cache()` right after `session_destroy()`, which sends `Clear-Site-Data: "cache"` (HTTPS only) and so also drops back/forward cache entries: `logout()`, `account_security.php` (all devices, this device), the kiosk's anonymous branch, `initialize_user()` and the device-bind kick in `includes/fn/auth.php`, `login_as_user.php`. `tests/preloader_test.php` checks every `session_destroy()` in those files is followed by it.
- A page shown by Back or Forward, from the back/forward cache (`persisted`) or the HTTP cache (navigation type `back_forward`, Safari, plain HTTP, an evicted entry), is hidden (`html.pg-session-check`) until `session_state.php` answers `{"signed_in": true, "view": <key>}` with the key the page carries; otherwise, or when the request fails, it reloads and the server decides (`pg_preloader_markup()`, #6143 second review). The key is `pg_session_view_key()`: a hash of a random value in the session and the user id, so another user signed in in the same browser does not match. A reload is not `back_forward`, so it cannot loop.

## In-page JSON replies?
- They keep `no-store`: JSON fetched by a script never enters the back/forward cache, so dropping it gains nothing (`pg_list_edit_reply()` stays `private, no-store`, #6143 review).
