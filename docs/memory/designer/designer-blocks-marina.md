---
name: designer-blocks-marina
description: The Marina block family (marina- prefix, its own palette group): the pontoon plan and the arrival by sea, the blocks a marina places instead of adding, its pg-marina-* rules, its test
---

## Where does it live?
- `assets/js/style_designer_blocks_marina.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `marina-`, palette group "Marina" (`bi-life-preserver`). Built for the Marina template's pages ([[design-template-marina]]), usable by any harbour or boat club.

## Which blocks does it hold?
- `marina-pontoons`: a card per pontoon (A to D): the letter in a ring (`pg-marina-pontoon`, `--bs-primary`), name `h3` and its use (visiting boats, seasonal and annual, large yachts, day boats), a `dl` of berths, berth length, largest boat and depth alongside, then what each berth has as pills (icon plus word: shore power, water, Wi-Fi); "Ask for a berth". The row rises on view.
- `marina-arrival`: a dark band (`data-bs-theme="dark"` + `bg-body text-body`): the radio call, phone, approach position and entrance depth as tiles that rise on view (stagger 70), beside a card of four `h4` steps from the breakwater to the office and a night note.
- `pg-marina-figures` keeps the figures tabular on both.

## What does a marina place instead of adding?
- Searched first (core, the general hours and locations files, launch, skiresort, drivingschool, ferry, zoo, coworking). Rates by season are `hours-seasons` (a season per column, a row per kind of berth, the price in the cell); boatyard services `services-icon-cards` / `services-price-list`; facilities ashore `features-grid`; the charter fleet and events are the template's collection widgets; the call to book `booking-cta`.
- The pontoon card is not `locations-spaces` (no picture, guests or price: berth sizes and depth) and the arrival not `ferry-departures` or `ferry-timetable` (no sailings: one boat's own approach).

## What do its texts follow?
- Shared keys with a wrong Turkish value for a marina are not reused: "Power" (Güç), "Visitors" (Ziyaretçiler), "Radio" (Radyo); the blocks say "Shore power", "Visiting boats", "VHF radio" and "Channel {var}".
- No year, no live availability, tide or weather: every value is text the staff edit.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_marina_test.php` runs the kit and the file under Node: a section with one `h2` and no skipped level, no fixed colour or `pmn-`/`mn-` class, `{var}` filled, no year, one entrance per block kept by `_sdFxNormalize()`, four letter marks and facts, amenity pills as icon plus word, the dark band, four approach steps, and the rules reaching the block stylesheet.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
