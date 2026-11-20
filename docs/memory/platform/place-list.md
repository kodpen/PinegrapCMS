---
name: place-list
description: The "Map of places" block's list (place-map MP3, MP3b): places.php and its gate, the records an answer carries, the cut at 500, the designer's note, pg_map.js, the locations-map block
module: place_list
---

## What does places.php answer?
- `GET places.php?page=<id>`: the places of that page's first form list (`pg_sw_page_widget($page_id, 'form_list_view')`), as `{ok, places: [{name, address, hours, phone, lat, lng, url}], cut, note}`. The request carries the page id only (architect, 2026-11-16, agent channel message 7423); `tests/place_list_test.php` checks no other request value is read.
- Gate (`pg_place_list_page_reason()`): the page exists, its folder is open to everyone (`get_access_control_type()` = `public`) and not in the bin, and the list is public (`pg_sw_form_widget_public()`). Otherwise no places.
- Records: complete only, the list's and its detail view's filters (`pg_sw_list_record_filter_clause()`, no URL filters), in the list's order (`pg_place_list_order()`: id, date or a form field; a statistic order falls back to the date), at most `PG_PLACE_LIST_MAX` (500).
- Same origin only (`pg_request_cross_site()`, 403), then 30 answers a minute per address (`pg_place_list_throttled()`, rule `rate-place-list`, 429), `Cache-Control: private, no-store`.
- `POST places.php` with `find` is the typed-address lookup (X12): `place-list-near.md`.

## Which fields does an answer carry?
- From the saved page, never from the request: the block's own choice, else the name rule (`place-list-fields.md`).

## What does a designer see that a visitor does not?
- `note`: why the list is empty (no form list on the page, the collection lacks the two number fields and an address, the page not open to everyone) or that it was cut at 500 and "Near me" is not offered. Given only to a signed-in user with role below 3; `pg_map.js` prints it above the list. A visitor gets an empty list or the places.

## How does the page get the script?
- The block carries `data-pg-map` (`pg_place_list_pattern()`, also the Preview's row in `_SD_PAGE_SCRIPTS`). `get_page_content.php` loads the module and `pg_place_list_inject($content, $page_id)` adds `window.pgMap = {page, endpoint, labels}` and `assets/js/pg_map.js`, once, before the last `</body>`.
- `window.pgMap.map` is the map's part (`pg_place_tiles_page()`, `designer/designer-blocks-map.md`). The JSON is written with `JSON_HEX_TAG`.
- `pg_map.js` asks `places.php`, drops the sample cards (`data-pg-map-sample`) and, while "Maps" is off, the canvas picture's column (`data-pg-map-picture`) and widens the list's column; then it draws a card per place (name, address, hours, phone link, Directions to OpenStreetMap, Details when the list has a detail page, "Show on the map" while a map is drawn) and narrows by every typed word, accents and Turkish dotted i ignored. A cut list marks the block `data-pg-map-cut`.
- Without `window.pgMap` (the editor's Preview) the block keeps its picture and samples. The module `includes/fn/places.php` is another one (country place lists).

## What is the block?
- `locations-map` in `style_designer_blocks_locations.js` ("Locations: Map of Places", Hours & Locations group): an intro, a search box (`data-pg-map-search`), a still SVG map with three markers in the text colour (X15), a status line and two sample cards; rules `pg-map-list` (cards fill the row) and `pg-map-picture`.

Tests: `tests/place_list_test.php`, `tests/designer_blocks_general_test.php`, `tests/designer_page_scripts_test.php`.
