---
name: place-list-near
description: Near me and the typed-address box of the Map of places block (#10613): position kept in the browser, the POST lookup in places.php, 10 a minute, nothing typed kept
module: place_list
---

## When are "Near me" and the address box drawn?
- Only when places.php gave the whole list (`cut` false) and at least one place has coordinates (`nearOffered()` in `pg_map.js`): in a cut list the nearest place could be among those left out. The block then carries `data-pg-map-near`.
- `pg_map.js` builds both under the search box (the block's markup is unchanged): "Near me" while the browser has `navigator.geolocation`; the box ("Address or postcode", Find) while `window.pgMap.find` is true.
- `find` is `pg_place_list_find_on()`: cURL is there and the Photon geocode file is there.
- Architect (2026-11-16, message 6794): always Photon, as "Find from the address" (`platform/place-coordinates.md`, why: `place-provider-terms.md`): the site's Photon server while its account is on, else the public instance; never Google or Mapbox.

## What does "Near me" send?
- Nothing. The browser's own location question; the position stays in the page, where `arrange()` orders the places nearest first and each card shows its distance (`formatKm()`: one decimal under 10 km). A refusal says "Your browser did not give a position."

## How is a typed address looked up?
- `POST places.php` with `find=<text>&page=<page id>` in the body, never in the address, so no server access log writes it. One request per press of Find, none while typing.
- Order of checks: same origin (403), the box's switch (403), `pg_place_list_find_throttled()`: 10 lookups a minute per client address (`PG_PLACE_LIST_FIND_LIMIT`, scope `place_find`, rule `rate-place-find`, 429), apart from the list's 30 (`place_list`); then the page (403) and the site's bound ("wait"), below. All before any outside call.

## Which page may look up? (#10838)
- Architect (2026-11-20, agent channel message 10967): no site-wide switch, the block's presence is the switch.
- `pg_place_list_find_refusal()`: a browser's same-origin request (`Sec-Fetch-Site: same-origin`, else a same-host `Origin`; no headers is refused), naming a page `pg_place_list_page_open()` allows: the list's rule (open to everyone, so no draft, not in the bin, a public form list) and the block in its tree. A site without the block never calls Photon. No transition: new in the open version (#10613).

## Is there a site-wide bound? (#10838)
- Architect (2026-11-20, agent channel message 10967): only on the public Photon instance, at most 30 lookups a minute for the whole site (`PG_PLACE_LIST_FIND_SITE_LIMIT`, scope `place_find_site`) and at least 1 second apart (`PG_PLACE_LIST_FIND_GAP_MS`, the bulk action's spacing; back-to-back calls get 503). Past it the answer is state `wait` ("The address service asks to wait"), no outside call.
- `pg_place_list_find_site_held()`; the spacing is one `waf_rate` row (seconds in `window_start`, milliseconds in `hits`) claimed by `pg_place_list_find_gap_sql()`. Only counts and times are kept.
- The site's own Photon account: no site-wide bound, the per-visitor 10 a minute stays. The panel's "Find from the address" and "Find missing coordinates" do not count; they keep their own spacing.
- `pg_place_list_find()` cuts the text to one line of 200 characters (`PG_PLACE_LIST_FIND_MAX`) and calls `pg_place_map_lookup()` once. The answer is `{ok, state, lat, lng}` (found, not_found, empty, wait, error): the point alone, never the text.
- Nothing typed is kept: no table, file, session, cookie, browser storage or log line; the firewall's 429 line names the rule and the address only. `tests/place_list_test.php` guards it.
- Found: the list is ordered from that point, as "Near me", with "Nearest first from: <address>" in the status line.

Tests: `tests/place_list_test.php` (the rate, nothing kept, the switch, `arrange()`/`nearOffered()`/`formatKm()` under Node), `tests/place_map_test.php` (the lookup's callers).
