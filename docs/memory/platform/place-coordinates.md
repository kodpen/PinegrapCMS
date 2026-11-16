---
name: place-coordinates
description: Coordinates of a collection's places: fields found by name, "Find from the address" on a record, "Find missing coordinates" on the record list, place_coordinates.php, always Photon
module: place_map
---

## Where are a place's coordinates kept?
- In the record's own two number fields: no table and no column. A coordinate field has kind `number` and the name `latitude` / `lat` / `enlem` or `longitude` / `lng` / `lon` / `long` / `boylam` (`pg_place_map_names()`, compared after `pg_place_map_name()`: lower-case ASCII, words joined by `_`).
- The address is every plain text box or text area named `address`, `street`, `city`, `postcode`, `country` and the like (Turkish `adres`, `ilce`, `il`, `sehir`…), joined with ", " in form order, each part once, at most 300 characters.
- A form with both coordinates and an address part has place fields (`pg_place_map_fields()`). Typed, imported or API-written values count like any other.
- Architect (2026-11-16, message 6927), for MP3: once the map block names the fields (X2), the button looks first at the block's fields, else finds them by name; the block's pickers default to the same rule.

## What does "Find from the address" do?
- Drawn right under the later of the two coordinate fields on a record's add and edit screens: `pg_place_map_form_tools()` prints at the end of `pg_submitted_form_fields_markup()` and its script moves the box after that field's column.
- A press posts the address as it stands in the form to `place_coordinates.php` (`action=lookup`) and fills the two fields; the user saves. The lookup writes nothing. A help line names where the address is sent.
- One answer line at a time, under the button: an address edited while coordinates are there replaces "found" with the warning that they are still those of the address before.
- Answers: found, not found, address empty, the service asks to wait (HTTP 429), the service did not answer.

## What does "Find missing coordinates" do?
- A button beside Create on the record list while one form with place fields is chosen. Each press looks up the next `PG_PLACE_MAP_BULK` (10) records that have an address and lack a coordinate, and writes what is found (`last_modified_*` set, no revision row).
- The report is the list's notice above the list, never in the toolbar: the endpoint adds it to the `view_submitted_forms` liveform and the page is drawn again (`pg_place_map_bulk_notice()`): found, left, then each address not found on its own line.
- A record found nothing for is not asked again in that session (`software.place_map_skip`). A "wait" or a failure stops the call; the records not asked count as left.

## Who may use it, and which service answers?
- The record screens' rule (`pg_place_map_may()`): Manager and above, or the forms right with edit access to the form's folder. Token checked; a refusal is JSON 403.
- Always Photon (`pg_place_map_source()`): the site's Photon server while its Photon account is on, else the public instance, also while Google Places or Mapbox gives the suggestions. Nothing is asked of those two (`plans/place-map-census.md`).
- Architect (2026-11-16, message 6927, replacing 6794's hidden button): a site using Google suggestions at checkout should not have to change them to find its branches.
- Nothing is looked up when a page is viewed: only `place_coordinates.php` calls the lookup (`tests/place_map_test.php` guards it).
