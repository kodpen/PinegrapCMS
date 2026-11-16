---
name: place-map-features
description: Plan place-map, features X1 to X16: the map block and its list, coordinates as two fields of the record, the places endpoint, consent before tiles, near me and a typed address, the library, headers
---

## What is the block?
- (X1) A block "Map of places" (mark `data-pg-map`) on a page that has a form list of a collection: it shows that list's records as markers on a map and as a list beside it. The block is markup only; `assets/js/pg_map.js` comes from the mark, on the page and in the Preview.
- (X2) The block names the collection's fields: the address (a text field), latitude and longitude (two number fields) and, optionally, opening hours and a phone number shown as text. No new field kind.
- (X3) A marker opens a small card: name, address, hours, phone, "Directions" (a link to the visitor's maps app) and "Details" (the record's page). The list and the map follow each other, everything is reachable from the keyboard, and the list is the map's text alternative.

## Where do coordinates come from?
- (X4) They are the record's own two number fields: typed, pasted, imported or written over the API as any field is. A record without them stands in the list without a marker. No table keeps coordinates.
- (X5) "Find from the address" on the record's form in the panel asks the address provider the site already set, once, on the server, when it is pressed, and fills the two fields before the save. The call is one of its own under `includes/geo/geocode/`, used only where the provider's terms let a result be kept (the census of MP1 says which). The key stays a secret setting and never reaches a page.
- (X6) The same as a bulk action on the collection's record list: a bounded number per call, and it says how many are left and which addresses were not found. No job works behind the screen.
- (X7) An edited address keeps its coordinates until the action is used again; the form says so when the address changed.

## What does the page ask the server?
- (X8) `places.php` gives JSON: the records the list page shows to this visitor (its filters, complete records, a folder open to everyone) with name, address, the chosen fields, coordinates and the record's address. Same origin, rate limited. It carries 500 places at most: a list with more is cut to its first 500 in its own order, the block tells the designer so, and narrowing is the list's own filter.

## When does a map appear?
- (X9) No tile and no provider script loads before the visitor allowed it: with the cookie notice on, the map is listed in the notice as a service, and until its class is allowed the block draws the list and a "Show the map" button that opens the notice. With the notice off the map draws at once.
- (X10) The list always works, with or without the map: it is searched by typing (a town, a postcode, a name).
- (X11) "Near me" asks the browser's own location question; the position never leaves the browser; the places X8 gave are ordered by distance, which is shown.
- (X12) A typed address can be looked up through the endpoint, one lookup per search and rate limited, and the list ordered by distance from it. Nothing typed is kept.

## What is it built from?
- (X13) Leaflet, vendored unchanged under `assets/lib/leaflet/` with its licence and a checksum test; raster tiles only. The tile address and its credit line are a setting, OpenStreetMap's by default, the credit always printed.
- (X14) A switch "Maps" in Site Settings, Features, off: with it off the block draws only its list, and the security headers stay as they are. They widen for the tile host only on a page that draws a map with the switch on.
- (X15) On the canvas the block is a still picture of a map with three markers.
- (X16) Nothing about a visitor is kept.
