---
name: place-map
description: Plan (owner 2026-11-16, agent channel poll 82): the places of a collection on a map with a list, "near me" and search by distance, tiles only after the visitor allowed them; steps MP1 to MP6
priority: 6
---

## What is asked?
- The owner's words and the conditions of the set: `plans/more-features.md`. In the study (agent channel message 6433): branches shown on a map and found by distance.

## What is there today, and what is reused?
- Places today are records of a collection drawn by a form list, and the static blocks `locations-cards` and `hours-card` (`designer/designer-blocks-general.md`). No map library is in the product.
- Address suggestions have three drivers (`includes/geo/autocomplete/`: Google Places, Mapbox, Photon; the account in `connect_apps`), and none gives coordinates (`pg_addr_fill()` works only for ids handed to that browser).
- The cookie notice has two classes, statistics and marketing, and holds only script tags (`platform/cookie-consent-new-cookies.md`). The security headers let pictures from any https host through, so raster tiles pass; other fetches would not.
- A block carries no script: a platform script comes from a mark in the markup (`designer/designer-preview-page-scripts.md`). A page is cached for everyone, so nothing about one visitor may be drawn into it (`platform/page-cache-gate.md`).
- Stock locations are warehouses with a free-text address and no public flag (`commerce/stock-locations-pickup.md`).

## What binds it?
- The architect's conditions: `plans/website-second-set-conditions.md` for all eight plans, `plans/website-second-set-conditions-per-plan.md` for this one. They are not repeated here.
- A vendored library keeps its licence, is not changed and has a checksum test (`core/vanilla-calendar-library.md` is the model).

## What is built?
- The numbered features: `place-map-features.md` (X1 to X16). Steps: `place-map-steps.md`. Claim as `[plan:place-map] <step> — <topic>`.
- In short: a block that draws a list's records on a map. Coordinates are worked out by the general job, distances in the browser, and the map itself waits for the visitor's yes.
- "Once on the server at the save" is done by the job right after a save, so that none of a record's five save paths changes.

## What does the owner decide?
- The default tiles, asked once the census (MP1) has said what OpenStreetMap's own tiles allow: its tiles with a provider's key as the site's option (the proposal), or a key needed from the start. The table (asked with the set's other tables).

## What does the architect decide?
- After the census (MP1): which providers' results may be kept, the notice's class for the map, the header change. The table's columns, in MP2's draft PR.
- Whether X4 meets "once, on the server at save": the save marks the address and the general job's pass looks it up, so a slow or failing provider never holds a save; nothing is looked up at view.

## What is left out?
- Opening hours as data ("open now"); stock locations as places; thousands of places grouped on the map; a marker corrected by hand; routes. Each is a later yes.

## Whose look?
- The owner's: the block on a page, with and without the map, on a phone (MP3, MP4).
