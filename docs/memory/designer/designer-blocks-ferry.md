---
name: designer-blocks-ferry
description: The Ferry block family (ferry- prefix, its own palette group): departure board, route timetable, crossing strip, fares by vehicle; the general blocks a ferry site places instead; rules and test
---

## Where does it live?
- `assets/js/style_designer_blocks_ferry.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `ferry-`, palette group "Ferry" (`bi-water`). Built for the Ferry template's polishing round ([[design-template-ferry]], `plans/design-template-own-looks-order.md`, `ferry-1`); usable by any boat, bus or shuttle line.

## Which blocks does it hold?
- `ferry-departures`: dark band (`data-bs-theme="dark"` + `bg-body text-body`), a table of the next sailings: the time as `<time>` in a `th scope="row"`, route, vessel, a status pill (On time, Boarding, Delayed with "Now leaving at …", Cancelled with its reason); links to travel notices and timetables. The rows rise on view (stagger 60).
- `ferry-timetable`: one route: season badge, route `h2`, facts (crossing, distance, vessel), "Book this route"; a card per direction ("Departures from {var}", `h3`) with its days in words and a grid of `<time datetime="07:00">` chips that rise on view (stagger 40); check-in notes.
- `ferry-crossings`: an `ol` of route cards, each drawn port to port: the two ports at the ends, between them the wake line (`pg-ferry-wake`) with the crossing time above and the distance below; vessel and sailings a day; the card is one link (`stretched-link`). The cards rise on view (stagger 120).
- `ferry-fares`: a table with a column per crossing length (`scope="col"`) and three `tbody` row groups (foot passengers, vehicles with their driver, extras; `th scope="rowgroup" colspan="3"`), a row head per kind (icon, name, what the fare covers); a kind a crossing does not offer says "Not offered". Beside it the intro, what every fare includes (rises on view) and "Request a booking".

## Why are these not reused blocks?
- The fare table is not `pricing-comparison` / `compare-table` (plans in columns, feature rows with checks and buy buttons) nor `services-price-list` (one price per item): it prices traveller and vehicle kinds in row groups against crossing lengths. Nor `skiresort-passes` (a card per pass).
- The timetable is not `hours-seasons` (a row per day, a column per season): it lists departure times per direction. The crossing strip is not `timeline-horizontal` (numbered steps).

## Which general blocks does a ferry site place instead?
- Ports: `locations-cards` (address, hours, phone). Ticket office hours by season: `hours-seasons`. On board and travel tips: `features-grid` / `services-icon-cards`. A sailing notice: `notice-bar` / `alert-warning-actions`. Booking call: `booking-cta` / `booking-split`. Route photo split: `features-alt`.

## What do its rules and texts follow?
- Rules: `pg-ferry-figures` (tabular numbers on the board, timetable and fares) and `pg-ferry-wake` (dashed line in `--bs-primary-border-subtle`). Each `pg-*` class the file writes is a rule key.
- Sample content is the template's own (Pine Bay, Gull Island, Cedar Island, North Harbour; Seabreeze, Island Star, Kestrel; its fares as `_sdT('$6')` keys with the Turkish lira figure). The shared key "Ship" reads "Gönder" (the verb) in Turkish, so the column says "Vessel" ("Gemi").
- Motion only as entrances on view; nothing moves by itself. No year, no date: times of day only.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_ferry_test.php` runs the kit and the file under Node: a section with one `h2` and no skipped level per block, no fixed colour or `fy-`/`pfy-` class, every `{var}` filled, no year, an entrance kept by `_sdFxNormalize()` in each, every head cell with a scope, every `<time>` with its value, the dark band, the status pills, the row groups, the wake per route and both rules in the block stylesheet.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
