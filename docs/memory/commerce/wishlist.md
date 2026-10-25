---
name: wishlist
description: Favourites core (includes/fn/wishlist.php, wishlist.php): owner model, the pg_wishlist cookie, lazy merge at sign-in, the toggle and state endpoint, rate limit, trim, privacy export
---

## Where is it kept?
- `wishlist_items` (11.0.14): id, user_id, cookie_token VARCHAR(64), product_id, added_at; UNIQUE (user_id, cookie_token, product_id), KEY product_id.
- Owner: an account is `(user_id, '')`; a guest is `(0, sha256(cookie))`, so a leaked row is no usable cookie. Pure parts: `pg_wishlist_owner_for()`, `pg_wishlist_owner_sql()`.
- API for the heart and the Favourites widget: `pg_wishlist_ready()`, `pg_wishlist_ids()` (newest first, only `products.enabled = 1`, so not binned), `pg_wishlist_has()`, `pg_wishlist_set($id, $on)` (state, null when refused), `pg_wishlist_owner($create)`.

## What is the cookie?
- `pg_wishlist` (`PG_WISHLIST_COOKIE`): 64 random hex, path PATH, HttpOnly, Lax, Secure on https, like `pg_language`. Started only by saving (`pg_wishlist_owner(true)`, only in `pg_wishlist_set()`), never by a read. Each write re-sends it (`pg_wishlist_cookie_follow()`) to expire a year after the list's newest `added_at`, when the trim removes the list; an emptied list clears it.
- The session keeps a cookie it started (`software.wishlist_cookie`, allowed by the page cache) until the browser sends it; `pg_wishlist_visitor_token()` reads cookie, then session. Two first saves sent at once carry no cookie: the file session lock runs them in turn, the second joins the first list instead of starting one whose cookie would replace it (a product lost).
- Necessary: in `pg_consent_catalogue()` while the table exists, not in `pg_consent_revoke_names()`.

## How does a visitor list reach the account?
- Lazily: `pg_wishlist_owner()` runs `pg_wishlist_merge()` for a signed-in visitor, so the first wishlist call after any sign-in path (Apple's cross-site return too) moves it: `INSERT IGNORE ... SELECT` into `(user, '')`, `DELETE` the guest rows, clear the cookie; a plain UPDATE would stop at the unique key.
- Skipped while `$_SESSION['software']['logged_in_as_different_user']`: staff acting as a customer keep their own list.

## What does wishlist.php answer?
- `GET ?action=state`: `{"ids", "token", "signed_in"}`, `Cache-Control: private, no-store`. A catalog page may come from the page cache with `software_token = ""` and empty hearts, so the heart script reads both here (as chat's `site_chat_bootstrap`).
- `POST action=add|remove|toggle, product_id, token`: token checked with `hash_equals()` (403 JSON), then 60 writes / 10 min per `waf_ip_subject()` (`waf_rate` scope `wishlist`, logged as `rate-wishlist`, no offence), then merge and write. It also counts on the firewall's `waf_rate_limit_sensitive` (30/min, shared with sign-in, cart, checkout; blocking mode: HTML 429, an offence). XHR gets `{"product_id", "on"}`; a form is sent to `send_to` via `pg_safe_redirect_path()`.
- Refusals `{"error", "message"}`: 400 action/request, 403 session, 404 product, 405, 413, 429, 503 no table, 500 (`pg_error_throws(true)`, no SQL).
- Not in `pg_page_cache_request_dirties()`: a heart click changes no stored page.

## What cleans it?
- `pg_wishlist_trim_daily()` from `job.php` (`data/temp/wishlist_trimmed.txt`): guest lists last saved over a year ago, rows of deleted products (not binned) and accounts; batches of 1000, 20 per kind a day.
- Deleting an account (`delete_users.php`, `edit_user.php`: `pg_wishlist_forget_user()`, never user_id 0) or a product for good (`pg_pb_delete_product()`, bin purge too: `pg_wishlist_forget_product()`) removes its rows at once: MySQL 5.7 reuses the highest id after a restart, and the trim does not see rows of a reused id as orphaned. A binned product keeps them.

## Personal data?
- Privacy export section `wishlist` (after `watchers`): id, user_id, product_id, SKU, name, added_at, by account. A guest list names nobody until merged.

## What is next?
- Heart in catalog widgets, Favourites page widget: `plans/storefront-conversion-pack.md` 3b, 3c. Tests: `tests/wishlist_test.php`.
