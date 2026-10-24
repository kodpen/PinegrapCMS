---
name: page-view-beacon
description: How a visit answered from the page file cache is still counted (PR #1402): the signed beacon in the stored copy, page_view.php, what it counts and what it cannot
---

## Why is there a beacon?
- A page the router answers from the file cache (`platform/page-file-cache.md`) never reaches `get_page.php`, so `init_tracking()`, `update_visitor_page_data()` and the record view counter would not run.

## What does the stored copy carry?
- `pg_page_cache_store_page()` puts one inline script before the last `</body>` of the copy, never of the drawn answer (that one counted itself). Only when visitor tracking is on or the page showed a record.
- Payload (`pg_page_cache_beacon_payload()`): page id and name, `PRETTY_URL_PATH`, the `pg_track_content()` item, the record view's form and page. Signed with HMAC-SHA256 of `'page-view:' . payload` over `ENCRYPTION_KEY`; an empty key writes no beacon. Strings are JSON with `JSON_HEX_*`, so a page name cannot end the script.
- The script posts `p`, `s` and `document.referrer` as FormData with `navigator.sendBeacon`, else `fetch` keepalive; errors are swallowed.

## What does page_view.php do?
- Root endpoint, `init.php` with a session (the visitor row is found again through it). POST only (405 otherwise); always an empty 204 with `Cache-Control: no-store`.
- Drops: a discarded body, a bad signature, a payload without a page, `pg_request_is_automated()`.
- With visitor tracking: `HTTP_REFERER` = the posted referrer when it is an absolute http(s) address (≤ 2048), else unset; `PRETTY_URL_PATH` and `pg_track_content()` from the payload; then `init_tracking()` and `update_visitor_page_data()` as `get_page.php` runs them.
- Record view: `pg_sw_should_count_record_view(..., 'GET', false)` then `pg_sfv_count_view()`, outside the tracking switch as on a drawn page.
- `init_tracking()` lives in `includes/fn/seo.php` since this PR (moved unchanged from `get_page.php`).

## What cannot it count?
- Visitors without JavaScript on a cached page (bots were never counted).
- A replayed payload counts like a reload; the signature only stops counting pages of one's choice.
- `utm_*`, `t` and `a` query codes: an address with a query is never served from the cache, so they are always counted by a drawn page.
- A CDN copy taken from a drawn answer has no beacon; one taken from a file-cache hit has it.

## Is a new visitor counted twice?
- No for IPv4: the drawn first page dropped its new session (`pg_page_cache_drop_new_session()`), so the beacon's request opens another session, and `init_tracking()` finds the visitor row again through its 30-minute IP match (same IP, tracking and affiliate code).
- IPv6: `INET_ATON()` is NULL (0), so that match joins every cookieless IPv6 visitor of the last 30 minutes into one row (`platform/page-cache-gate.md`); undercounts, never doubles.
