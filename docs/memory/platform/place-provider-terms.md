---
name: place-provider-terms
description: Place map providers: why coordinates come only from Photon (Google, Mapbox terms), what OpenStreetMap tiles require, what was left out; read 2026-11-16
---

## Why does "Find from the address" never ask Google or Mapbox?
- Google Maps Platform Service Specific Terms (read 2026-11-16), Places and Geocoding API: a latitude and longitude may be cached for 30 days at most, and may not be used with a non-Google map. A record's coordinate fields keep it for good and the block draws OpenStreetMap tiles: both refused.
- Mapbox Geocoding API docs (read 2026-11-16): only a `permanent=true` lookup (paid, a card on file) may be stored, and any answer may only be shown on a Mapbox map.
- Photon answers OpenStreetMap data. The OSMF geocoding guideline (board-endorsed 2017-08-24): single results may be stored and shown on any map, no share-alike, but OpenStreetMap must be credited. "© OpenStreetMap contributors" on the map covers both the tiles and the coordinates.
- So the lookup, the bulk action and the typed address are Photon only (`place-coordinates.md`, `place-list-near.md`), also on a site whose address suggestions come from Google or Mapbox.

## What does the public Photon server allow?
- photon.komoot.io: welcome while requests stay reasonable; heavy use is throttled or banned, with no availability promise. Hence the bulk action's 10 per press and its pause on the public instance (`place-coordinates.md`); a site with many places should set its own Photon server.

## What do OpenStreetMap's own tiles require?
- OSMF Tile Usage Policy (read 2026-11-16), `https://tile.openstreetmap.org/{z}/{x}/{y}.png`, https only: the credit always visible, never behind a toggle; a real Referer from the browser; the cache headers obeyed.
- Forbidden: bulk download or prefetch of tiles nobody looks at, offline use, a stripped Referer, a faked User-Agent. Hence tiles load only in the visitor's browser as the map is panned, and the canvas shows a still picture.
- Commercial use is allowed but heavy use may be blocked without notice and there is no SLA: a site that outgrows it enters a provider's tile address and key (`designer/designer-blocks-map.md`). That provider's own terms are the site's to read.

## What was left out of the place map?
- Coordinates found by themselves when an address changes; opening hours as data ("open now"); stock locations as places; more places than the 500 a list carries, grouped on the map; routes. Each is a later yes of the owner's.
