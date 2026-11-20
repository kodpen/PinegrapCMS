---
name: place-list-near
description: Near me and the typed-address box of the Map of places block (MP5, X11, X12): position kept in the browser, the POST lookup in places.php, 10 a minute, nothing typed kept
module: place_list
---

## When are "Near me" and the address box drawn?
- Only when places.php gave the whole list (`cut` false) and at least one place has coordinates (`nearOffered()` in `pg_map.js`): in a cut list the nearest place could be among those left out. The block then carries `data-pg-map-near`.
- `pg_map.js` builds both under the search box (the block's markup is unchanged): "Near me" while the browser has `navigator.geolocation`; the box ("Address or postcode", Find) while `window.pgMap.find` is true.
- `find` is `pg_place_list_find_on()`: cURL is there and the Photon geocode file is there. Always Photon, as "Find from the address" (`platform/place-coordinates.md`): the site's Photon server while its account is on, else the public instance; never Google or Mapbox.

## What does "Near me" send?
- Nothing. The browser's own location question; the position stays in the page, where `arrange()` orders the places nearest first and each card shows its distance (`formatKm()`: one decimal under 10 km). A refusal says "Your browser did not give a position."

## How is a typed address looked up?
- `POST places.php` with `find=<text>` in the body, never in the address, so no server access log writes it. One request per press of Find, none while typing.
- Order of checks: same origin (403), the box's switch (403), then `pg_place_list_find_throttled()`: 10 lookups a minute per client address (`PG_PLACE_LIST_FIND_LIMIT`, scope `place_find`, rule `rate-place-find`, 429), apart from the list's 30 (`place_list`).
- `pg_place_list_find()` cuts the text to one line of 200 characters (`PG_PLACE_LIST_FIND_MAX`) and calls `pg_place_map_lookup()` once. The answer is `{ok, state, lat, lng}` (found, not_found, empty, wait, error): the point alone, never the text.
- Nothing typed is kept: no table, file, session, cookie, browser storage or log line; the firewall's 429 line names the rule and the address only. `tests/place_list_test.php` guards it.
- Found: the list is ordered from that point, as "Near me", with "Nearest first from: <address>" in the status line.

Tests: `tests/place_list_test.php` (the rate, nothing kept, the switch, `arrange()`/`nearOffered()`/`formatKm()` under Node), `tests/place_map_test.php` (the lookup's callers).
