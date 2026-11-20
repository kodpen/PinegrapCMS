---
name: ad-conversions-click-ids
description: Ad click ids of the landing address (fbclid, gclid, ttclid, SC1b): read list, value bound, held in the session only, into a purchase row's ids with consent, cleared on refusal, page cache
module: ad_click_ids
---

## Where is it?
- `includes/fn/ad_click_ids.php`; tests `tests/ad_click_ids_test.php`. Plan step SC1b of `plans/ad-conversions.md`, after SC1 (`ad-conversions-queue.md`). No column of its own: the ids go into `ad_conversion_events.ids`.
- Architect (2026-11-20): a step apart from SC1 and SC2 (agent channel message 10637), the session before consent, one reader call in `ad_conversions.php`.

## Which parameters are read?
- `pg_ad_click_id_parameters()`: `meta_capi` → `fbclid`, `ga4_mp` → `gclid`, `tiktok_events` → `ttclid`. No other parameter is read, case as written.
- Bound (`pg_ad_click_id_value()`): a string of 1 to `PG_AD_CLICK_ID_MAX_LENGTH` (500) letters, digits, `.`, `-`, `_`. Anything else is dropped, never cut.

## When is a click captured?
- `get_page.php`, before `init_tracking()`: `pg_ad_click_ids_capture($_GET)`, not for the panel's preview nor `IS_BOT`. Held as `$_SESSION['ad_click_ids'][service] = id + at` (the click time); a newer click of a service replaces its earlier one.
- Before consent it is written to no table and no cookie. A stored refusal of marketing (notice on) removes it on the next page instead of capturing.
- A page without a click id writes nothing to the session.

## How does it reach a row?
- At the payment step, only on the keep (consent) branch: `pg_ad_conversion_ids_for(...) + pg_ad_click_ids_held_for($service)`. Meta gets `fbc` = `fb.1.<click time ms>.<fbclid>`, GA4 `gclid`, TikTok `ttclid`. A browser's own `_fbc` cookie wins over the built one.
- Senders (SC2 on) read the id from the row's `ids`, never from this module.

## When is it removed?
- `consent_answer.php`: an answer that refuses marketing → `pg_ad_click_ids_consent_changed()` empties the session key (beside SC1's row withdrawal). Without consent the payment step takes the row's ids off, so a held id is never used.

## Does it work with the page cache?
- Yes: an address with any query is never served from the file cache (router check) and is not cacheable (reason `query`, `platform/page-cache-gate.md`), so the landing is always drawn and captures. A CDN set to ignore query strings would skip it.
- The key is outside `pg_page_cache_session_anonymous()`'s list, so a visitor holding a click id gets `pg_nc` and fresh pages for the visit.
