---
name: place-map-census
description: Census MP1 of place-map, part 1: the three address providers' terms on keeping a looked-up coordinate and on showing it on another map (Google, Mapbox, Photon), read 2026-11-16
---

## Which address providers are there?
- Three drivers in `includes/geo/autocomplete/`: `google_places.php` (Places API, `places.googleapis.com`), `mapbox.php` (Geocoding v6, `api.mapbox.com/search/geocode/v6/forward`), `photon.php` (Photon over OpenStreetMap data, public `photon.komoot.io` or the site's own host). The account sits in `connect_apps`. None asks for coordinates today.
- Census and the plan's map: Leaflet with raster tiles, OpenStreetMap's by default (X13), so every provider below is read for "kept in a record" and "shown on a map that is not theirs".

## May Google's result be kept and shown on our map?
- No on both. Google Maps Platform Service Specific Terms (https://cloud.google.com/maps-platform/terms/maps-service-terms, last modified October 28, 2026, read 2026-11-16), Places API: "Customer may temporarily cache latitude and longitude values from the Places API for up to 30 consecutive calendar days, after which Customer must delete the cached latitude and longitude values."
- Same page, Places API and Geocoding API alike: "Customer must not use Google Maps Content from the Places API in conjunction with a non-Google map." Only Places UI Kit may sit beside any map, and it is a widget, not a lookup.
- So a coordinate from Google may neither stay in a record's number fields past 30 days nor be drawn on an OpenStreetMap map. "Find from the address" cannot use the Google account as the plan stands.

## May Mapbox's result be kept and shown on our map?
- Kept: only a "permanent" lookup. Geocoding API docs (https://docs.mapbox.com/api/search/geocoding/, read 2026-11-16): "Temporary results are not allowed to be cached, while Permanent results are allowed to be cached and stored indefinitely." Permanent needs `permanent=true` and "a valid credit card on file or an active enterprise contract"; it is billed apart from the temporary lookups the autocomplete makes.
- Shown: same page, "You may only use responses from the Geocoding API in conjunction with a Mapbox map." Mapbox's product terms page (https://www.mapbox.com/legal/product-terms, last updated Aug 22, 2023) says nothing more on geocoding.
- So a Mapbox coordinate may be kept with `permanent=true`, but drawn only on Mapbox tiles: with OpenStreetMap's tiles it may not be used.

## May Photon's result be kept and shown on our map?
- Yes. Photon's answers are OpenStreetMap data. The OSMF geocoding guideline (https://osmfoundation.org/wiki/Licence/Community_Guidelines/Geocoding_-_Guideline, board-endorsed 2017-08-24, read 2026-11-16): single results are "insubstantial database extracts", may be stored and used on a non-OSM map, and "do not trigger share-alike obligations"; collecting all or nearly all features of a city-sized area is not covered.
- A public geocoder or an app using one "must provide attribution to OpenStreetMap".
- The public instance (https://photon.komoot.io and the README at https://github.com/komoot/photon, read 2026-11-16): use is welcome "as long as the number of requests stay in a reasonable limit. Extensive usage will be throttled or completely banned"; no availability promise. The bulk action (X6) with its bound per call fits that; a site with many places should set its own host.
- The credit line "© OpenStreetMap contributors" printed on the map (X13) covers both the tiles and Photon's coordinates.

Next: `plans/place-map-census-2.md` (tiles and headers), `plans/place-map-census-3.md` (the cookie notice and the questions).
