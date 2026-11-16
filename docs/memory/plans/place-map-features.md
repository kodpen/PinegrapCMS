---
name: place-map-features
description: Plan place-map, features X1 to X16: the map block and its list, where coordinates come from, the places endpoint, consent before tiles, near me and a typed address, the library, headers, the switch
---

## What is the block?
- (X1) A block "Map of places" (mark `data-pg-map`) on a page that has a form list of a collection: it shows that list's records as markers on a map and as a list beside it. The block is markup only; `assets/js/pg_map.js` comes from the mark, on the page and in the Preview.
- (X2) The block names which text field of the collection is the address, and optionally a field for opening hours and one for a phone number, shown as text. No new field kind and no change to a record's form.
- (X3) A marker opens a small card: name, address, hours, phone, "Directions" (a link to the visitor's maps app) and "Details" (the record's page). The list and the map follow each other, everything is reachable from the keyboard, and the list is the map's text alternative.

## Where do coordinates come from?
- (X4) Found on the server, once per address, by a pass of the general job after a record was saved: no save path changes. Kept in a table by record and field with a hash of the address; a changed address is looked up again.
- (X5) The lookup uses the address provider the site already set, through a coordinates call of its own under `includes/geo/geocode/`, and only where the provider's terms let a result be kept (the census of MP1 says which). The key stays a secret setting and never reaches a page.
- (X6) An address that cannot be found is marked: its record stands in the list without a marker, and the collection's record list in the panel shows which they are.
- (X7) The pass takes its turn with one claim, looks up a bounded number an hour and waits when the provider says to.

## What does the page ask the server?
- (X8) `places.php` gives JSON: the records the list page shows to this visitor (its filters, complete records, a folder open to everyone) with name, address, the chosen fields, coordinates and the record's address. Same origin, rate limited, at most 500 places.

## When does a map appear?
- (X9) No tile and no provider script loads before the visitor allowed it: with the cookie notice on, the map is listed in the notice as a service, and until its class is allowed the block draws the list and a "Show the map" button that opens the notice. With the notice off the map draws at once.
- (X10) The list always works, with or without the map: it is searched by typing (a town, a postcode, a name).
- (X11) "Near me" asks the browser's own location question; the position never leaves the browser; the list is ordered by distance and shows it.
- (X12) A typed address can be looked up through the endpoint, one lookup per search and rate limited, and the list ordered by distance from it. Nothing typed is kept.

## What is it built from?
- (X13) Leaflet, vendored unchanged under `assets/lib/leaflet/` with its licence and a checksum test; raster tiles only. The tile address and its credit line are a setting, OpenStreetMap's by default, the credit always printed.
- (X14) A switch "Maps" in Site Settings, Features, off: with it off the block draws only its list, and the security headers stay as they are. They widen for the tile host only on a page that draws a map with the switch on.
- (X15) On the canvas the block is a still picture of a map with three markers.
- (X16) Nothing about a visitor is kept.
