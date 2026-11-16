---
name: place-coordinates
description: Coordinates of a collection's places: fields found by name, "Find from the address" on a record, "Find missing coordinates" on the record list, place_coordinates.php, Photon only
module: place_map
---

## Where are a place's coordinates kept?
- In the record's own two number fields: no table and no column. A field holds a coordinate when its kind is `number` and its name is `latitude` / `lat` / `enlem` or `longitude` / `lng` / `lon` / `long` / `boylam` (`pg_place_map_names()`, compared after `pg_place_map_name()`: lower-case ASCII, words joined by `_`).
- The address is every plain text box or text area named `address`, `street`, `city`, `district`, `state`, `postcode`, `zip_code`, `country` and the like (Turkish `adres`, `ilce`, `il`, `sehir`…), joined with ", " in form order, each part once, at most 300 characters (`pg_place_map_address_text()`).
- A form with both coordinates and an address part has place fields (`pg_place_map_fields()`, null before the collections step has run). Typed, imported or API-written values are coordinates like any other.

## What does "Find from the address" do?
- Drawn under the fields of a record's add and edit screens (`pg_place_map_form_tools()` from `pg_submitted_form_fields_markup()`). On a press it posts the address as it stands in the form to `place_coordinates.php` (`action=lookup`), fills the two fields and leaves the save to the user: the lookup writes nothing.
- While coordinates are there and an address part is edited, a line says they are still those of the address before; it clears after a new lookup.
- Answers: found ("Save the record to keep them"), not found, address empty, the service asks to wait (HTTP 429), the service did not answer.

## What does "Find missing coordinates" do?
- A button beside Create on the record list while one form with place fields is chosen (`pg_place_map_list_tools()`). Each press looks up the next `PG_PLACE_MAP_BULK` (10) records of the form that have an address and lack a coordinate, in id order, and writes what is found (`pg_place_map_bulk()`; `last_modified_*` set, no revision row, as other bulk list actions).
- It says how many were found, which were not found (reference code and address) and how many are left. A record found nothing for is not asked again in that session (`software.place_map_skip`). A "wait" or a failure stops the call; the records not asked count as left. No job works behind the screen.

## Who may use it, and which provider answers?
- The record screens' rule (`pg_place_map_may()`): Manager and above, or the forms right with edit access to the form's folder. The token is checked; a refusal is JSON 403.
- Photon only (`includes/geo/geocode/photon.php`): the Photon account of the address suggestions (its host), or the public instance while no provider is on. With Google Places or Mapbox on, the button is not drawn and a line says why: their terms forbid keeping a result or showing it on another map (`plans/place-map-census.md`; architect, agent channel message 6794).
- Nothing is looked up when a page is viewed: only `place_coordinates.php` calls `pg_place_map_lookup()` / `pg_place_map_bulk()` (`tests/place_map_test.php` guards it).

Tests: `tests/place_map_test.php`.
