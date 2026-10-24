---
name: wishlist
description: Favourites core (includes/fn/wishlist.php, wishlist.php): owner model, the pg_wishlist cookie, lazy merge at sign-in, the toggle and state endpoint, rate limit, trim, privacy export
---

## Where is it kept?
- `wishlist_items` (11.0.14): id, user_id, cookie_token VARCHAR(64), product_id, added_at; UNIQUE (user_id, cookie_token, product_id), KEY product_id.
- Owner: an account is `(user_id, '')`; a visitor who is not signed in is `(0, sha256(cookie))`. Only the hash is stored, so a leaked row cannot be replayed as a cookie. `pg_wishlist_owner_for()` / `pg_wishlist_owner_sql()` are the pure parts.
- Module API for the heart and the Favourites widget: `pg_wishlist_ready()`, `pg_wishlist_ids()` (newest first, only `products.enabled = 1`, which also leaves the Recycle Bin out), `pg_wishlist_has()`, `pg_wishlist_set($id, $on)` (state or null when refused), `pg_wishlist_owner($create)`.

## What is the cookie?
- `pg_wishlist` (`PG_WISHLIST_COOKIE`): 64 random hex, path PATH, HttpOnly, SameSite=Lax, Secure on https; written like `pg_language`. Started only by saving a product (`pg_wishlist_owner(true)`, only in `pg_wishlist_set()`), never by a read. After each write `pg_wishlist_cookie_follow()` sends it to expire a year after the list's newest `added_at`, when the trim removes the list; an emptied list clears it.
- Strictly necessary (it keeps what the visitor asked for): listed under necessary in `pg_consent_catalogue()` while the table exists, not in `pg_consent_revoke_names()`.

## How does a visitor list reach the account?
- Lazily: `pg_wishlist_owner()` runs `pg_wishlist_merge()` for a signed-in visitor, so the first wishlist read or write after any sign-in path (also Apple's cross-site return, no Lax cookie) moves the list. `INSERT IGNORE ... SELECT` into `(user, '')`, then `DELETE` the visitor rows, then the cookie is cleared; a plain UPDATE would stop at the unique key.
- Skipped while `$_SESSION['software']['logged_in_as_different_user']`: staff signed in as a customer keep their browser list.

## What does wishlist.php answer?
- `GET ?action=state`: `{"ids", "token", "signed_in"}`, `Cache-Control: private, no-store`. Catalog pages may come from the page cache with `software_token = ""` and empty hearts, so the heart script reads the list and the token here (as chat's `site_chat_bootstrap`).
- `POST action=add|remove|toggle, product_id, token`: token checked with `hash_equals()` (403 JSON), then 60 writes per 10 minutes per `waf_ip_subject()` (`waf_rate` scope `wishlist`, `waf_log_event` + firewall.log rule `rate-wishlist`, no offence), then merge and write. Like any POST outside api.php it also counts on the firewall's `waf_rate_limit_sensitive` (30/min per address, shared with sign-in, cart, checkout; blocking mode: `waf_deny()`'s HTML 429 and an offence). XHR gets `{"product_id", "on"}`; a form is sent to `send_to` via `pg_safe_redirect_path()`.
- Refusals `{"error", "message"}`: 400 action/request, 403 session, 404 product not shown, 405, 413, 429, 503 no table, 500 failed (`pg_error_throws(true)`, no SQL text).
- Not in `pg_page_cache_request_dirties()`: a member's heart click changes no stored page.

## What cleans it?
- `pg_wishlist_trim_daily()` from `job.php` (state file `data/temp/wishlist_trimmed.txt`): visitor lists whose last save is older than a year (the cookie is gone), rows of deleted products (not binned ones) and of deleted accounts; batches of 1000, 20 per kind a day.
- Deleting an account (`delete_users.php`, `edit_user.php`) removes its rows at once, `pg_wishlist_forget_user()` (never user_id 0): MySQL 5.7 reuses the highest id after a restart. A deleted product's rows wait for the trim.

## Personal data?
- Privacy export section `wishlist` (after `watchers`): id, user_id, product_id, SKU, name, added_at, by account. A visitor list names nobody until it is merged.

## What is next?
- Heart in catalog widgets and the Favourites page widget: `plans/storefront-conversion-pack.md` steps 3b and 3c. Tests: `tests/wishlist_test.php`.
