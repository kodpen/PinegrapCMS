---
name: bug-hunt-place-map
description: Bug hunt of the place map (architect 2026-11-20, agent channel message 10779): three faults fixed in #10770, two issues opened (#10779, #10780), both fixed in #10838, what was read clean
---

## What was the area?
- `places.php`, `place_coordinates.php`, `includes/fn/places.php`, `place_map.php`, `place_list.php`, `place_tiles.php` and their tests. Given by the architect 2026-11-20, agent channel message 10779. `assets/js/pg_map.js` was read only for how it uses the answer.

## What did it fix? (#10770)
- `places.php` answered for any public page with a public form list, block or not: up to 500 records' name, address, hours, phone and coordinates, fields that page's list may not show. It now answers only for a page whose tree, or a shared component it names (four levels deep), holds the block (`pg_place_list_tree_holds_block()`); else reason `block`, the designer's note "this page holds no Map of places block".
- A coordinate written by an import or the API with a decimal comma ("40,98") drew no marker: `pg_place_list_coordinate()` reads it as the record form saves it (`pg_coll_number_value()`).
- "Find missing coordinates" stopped every press at a record whose address was only a tab or line break (MySQL's TRIM keeps them, the lookup got an empty text and answered "empty", which stopped the call). `pg_place_map_bulk_outcome()` counts it as not found, so it is reported and skipped.

## What was opened as an issue?
- #10779 (fixed in #10838): the typed-address lookup answered any client on any site with cURL, with or without the block. Now only a browser's same-origin request for a page with the block (`platform/place-list-near.md`).
- #10780 (fixed in #10838): visitor lookups had no site-wide bound on the public Photon server. Now 30 a minute, a second apart (`platform/place-list-near.md`).

## What was read clean?
- The outgoing call: Photon only, `q` and `limit=1` sent, 5 s timeout and 3 s connect through `pg_curl_run()` (TLS, user agent), nothing logged, the answer's point range-checked.
- `place_coordinates.php`: POST only, session token with `hash_equals()`, the record screens' rule, office-use fields kept out of the block choice.
- Escaping: the bulk notice, the help line, the tile credit (`h()`), `window.pgMap` with `JSON_HEX_TAG`, the tile address refused with quotes, spaces or angle brackets; the CSP host check and its wildcard.
- The tile key sealed with the site's key; the consent row; the Turkish place pack (`includes/fn/places.php`).

Related: docs/memory/workflow/bug-hunt.md, docs/memory/platform/place-list.md, docs/memory/platform/place-coordinates.md
