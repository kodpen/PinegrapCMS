---
name: place-map
description: Plan (owner 2026-11-16, agent channel poll 82): the places of a collection on a map with a list, "near me" and search by distance, tiles only after the visitor allowed them; steps MP1 to MP6
priority: 6
---

## What is asked?
- The owner's words and the conditions of the set: `plans/more-features.md`. In the study (agent channel message 6433): branches shown on a map and found by distance.

## What is there today, and what is reused?
- Places today are records of a collection drawn by a form list, and the static blocks `locations-cards` and `hours-card` (`designer/designer-blocks-general.md`). No map library is in the product.
- Address suggestions have three drivers (`includes/geo/autocomplete/`: Google Places, Mapbox, Photon; the account in `connect_apps`), and none gives coordinates. Nothing in the product keeps a coordinate.
- A collection has number fields, and a record's field is written like any other: on its form, by import and over the API.
- The cookie notice has two classes, statistics and marketing, and holds only script tags (`platform/cookie-consent-new-cookies.md`). The security headers let pictures from any https host through, so raster tiles pass; other fetches would not.
- A block carries no script: a platform script comes from a mark in the markup (`designer/designer-preview-page-scripts.md`). A page is cached for everyone, so nothing about one visitor may be drawn into it (`platform/page-cache-gate.md`).

## What binds it?
- The architect's conditions: `plans/website-second-set-conditions.md` for all eight plans, `plans/website-second-set-conditions-per-plan.md` for this one. They are not repeated here.
- A vendored library keeps its licence, is not changed and has a checksum test (`core/vanilla-calendar-library.md` is the model).

## What is built?
- The numbered features: `place-map-features.md` (X1 to X16). Steps: `place-map-steps.md`. Claim as `[plan:place-map] <step> — <topic>`.
- In short: a block that draws a list's records on a map. Coordinates are two number fields of the collection, distances are worked out in the browser, and the map itself waits for the visitor's yes.

## What did the architect decide? (2026-11-16)
- Architect (agent channel messages 6553 and 6554), after the owner asked that what exists be grown instead of a table (poll 84): no table and no pass of the general job. Latitude and longitude are two number fields of the collection.
- "Find from the address" asks the provider once, on the server, when it is pressed; the bulk action is bounded per call and says what is left; nothing is looked up at view. The plan states how many places the block carries and what happens above that (X8).
- Architect (message 6590): the bound of 500 and the cut stand. With a cut list "Near me" is not drawn, since the position never leaves the browser and the nearest place could be among those cut; the block tells the designer both.
- Still the architect's, after the census (MP1): which providers' results may be kept, the notice's class for the map, the header change.

## What does the owner decide?
- The default tiles, asked once the census (MP1) has said what OpenStreetMap's own tiles allow: its tiles with a provider's key as the site's option (the proposal), or a key needed from the start.

## What is left out?
- Coordinates found by themselves when an address changes; opening hours as data ("open now"); stock locations as places; more places than X8 carries, grouped on the map; routes. Each is a later yes.

## Whose look?
- The owner's: the block on a page, with and without the map, on a phone (MP3, MP4), and "Find from the address" on a record's form (MP2).
