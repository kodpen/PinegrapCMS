---
name: designer-blocks-map
description: The map of the "Map of places" block (#10581): the Maps switch and its tiles in Site Settings, Features, consent before any tile, the vendored Leaflet, the Firewall copy line, headers unchanged
module: place_tiles
---

## When does the block draw a map?
- While "Show places on a map" is on (Site Settings, Features, card "Maps"; config_text `place_map_on`, off on every site). Off, `pg_map.js` drops the still picture and the list takes the row (`platform/place-list.md`).
- On, the picture's place becomes a Leaflet map: a marker per place with both coordinates, the place's card in the marker's popup, the view fitted to the markers (at most zoom 15), wheel zoom off. "Show on the map" on a card opens its marker; an open marker outlines its card. Markers follow the search as the list does.

## When may a tile load?
- With the cookie notice on (`pg_consent_enabled()`), only after the visitor allows marketing: before that neither `leaflet.js`, `leaflet.css` nor a tile is asked for. The picture stays with a "Show the map" button and a line saying why; the button clicks the notice's corner button (as a `data-pg-cc-open` opener it would hide that button for good once the map replaced it).
- `pg_map.js` reads the `pg_consent` cookie (`m1`) at load and waits for the notice's `pg:consent` event otherwise: the map is drawn without a reload. With the notice off it is drawn at once. Without the notice on the page (a crawler) no button is drawn.
- The notice lists the map as a marketing service on a page with the block while the switch is on: "OpenStreetMap" for the default tiles, else the tile host (`pg_place_tiles_consent_name()` in `pg_consent_catalogue()`). Architect (2026-11-16, message 6794): marketing, no third class.

## Where do the tiles come from?
- `pg_place_tiles_settings()`: the tile address (https, `{z}`, `{x}`, `{y}`; `{s}` and `{key}` allowed), the credit line and a key. Empty address and credit mean OpenStreetMap's (`https://tile.openstreetmap.org/{z}/{x}/{y}.png`, "© OpenStreetMap contributors" linked to its copyright page). Owner (2026-11-16, poll 99): OpenStreetMap first, a site writes its own provider.
- The key is sealed with the site's key (`pg_connect_seal()`), drawn empty ("Saved"), removed by its tick and put into the address in place of `{key}` on the page, so every visitor's browser receives it: the help line asks for a key the provider limits to the site's address.
- The credit is always printed (`pg_place_tiles_credit_html()`, escaped); Leaflet's own prefix is a plain "Leaflet" link.
- What a page carries is the same for every visitor (`pg_place_tiles_page()` in `window.pgMap.map`), so the page cache keeps it.

## What about the security headers?
- Architect (2026-11-16, message 6794): unchanged with the switch on and off: the built-in policy's `img-src https:` lets tiles through, Leaflet is `'self'`. `tests/place_tiles_test.php` checks `waf.php` and `init.php` read nothing of the switch.
- Firewall settings, under Policy: while Maps is on and the site's own policy would refuse the tile host (`img-src`, else `default-src`), a warning gives `img-src https://<host>` to copy (`pg_place_tiles_firewall_note()`), like the CAPTCHA's line. Keep `Referrer-Policy` off `no-referrer` on a map page: OpenStreetMap's tiles ask for a Referer (`platform/place-provider-terms.md`).

## Which Leaflet?
- 1.9.4 (BSD-2-Clause), `assets/lib/leaflet/`: `leaflet.js`, `leaflet.css`, `LICENSE` and the five pictures of `dist/images`, byte for byte from the npm package, no local change. `pg_map.js` adds its two tags once, only when the map may load. An update copies the same files and changes the hashes in `tests/leaflet_library_test.php`.

Tests: `tests/place_tiles_test.php` (Node: nothing mounted before marketing is allowed), `tests/leaflet_library_test.php`.
