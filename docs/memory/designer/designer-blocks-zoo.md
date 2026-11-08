---
name: designer-blocks-zoo
description: The Zoo block family (zoo- prefix, its own palette group): the four blocks, what each draws, the blocks reused instead, its pg-zoo-* rules, motion and test
---

## Where does it live?
- `assets/js/style_designer_blocks_zoo.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `zoo-`, palette group "Zoo" (`bi-tree`). Built for the Zoo template ([[design-template-zoo-design]]), usable by any zoo, aquarium, safari or wildlife park.

## Which blocks does it hold?
- `zoo-today`: today's opening line, a kind legend, an `ol` of slots (a `<time>` per slot, the title `h3`, a Feeding or Keeper talk pill, "Habitat {var:1} · {var:2}"); the next slot has `aria-current="time"`, a "Next up" badge and a raised surface; a "Good to know" card with a map link.
- `zoo-animal`: the photo in a `figure` (scale on view), the name `h2`, common and Latin name (`lang="la"`), a story, a four-fact `dl`, the seven red-list levels as an `ol` of code pills (each with its name `visually-hidden`, the animal's own `aria-current`), the level said in words, Adopt and map links.
- `zoo-map`: the map picture and a PDF link in a `figure`, zones with an `h3` and numbered habitats (`pg-zoo-pin` with a theme `text-bg-*` per zone, the zone named in words), amenities as icon plus word, a feeding-times link.
- `zoo-conservation`: three project `article` cards on `bg-success-subtle`: a status pill ("CR · Critically Endangered"), species, place and partner, what is done, one impact figure; Adopt and news links.

## Which blocks are reused instead?
- Adoption levels: `pricing-tiers` (one price per level, what it includes). Ticket prices by visitor: `pricing-tiers` or a table on the page. Opening hours: `hours-card` (`hours-seasons` once shipped). Events: `events-list` / `events-cards`. Keepers: `team-grid`. The habitat zones as cards: `features-grid`.
- `zoo-today` is not `timeline-vertical`: that one is milestones (a date and a story per row); a day schedule needs `<time>` values, a kind pill, a place and the next-slot marker.
- `zoo-map` is not `skiresort-slope-map`: that legend is difficulty grades with counts and area buttons; this one is numbered habitats keyed to the picture, grouped by zone.

## What do its rules and texts follow?
- Rules: `pg-zoo-time` (tabular figures and a fixed width for slot times and impact figures), `pg-zoo-pin` (round number marker). Colours only from theme classes.
- A kind or status pill is always an icon plus a word. Several numbers use `{var:1}`, `{var:2}`. The red-list codes (LC … EX) are international and not translated; their names are keys.
- Motion: the schedule list, the zones and the project row rise on view (`props._fx` slide-up, stagger 80); the spotlight photo scales in on view; nothing moves by itself. No date or year in the sample.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_zoo_test.php` runs it under Node: one `h2` and no skipped level per block, no fixed colour or `pzo-`/`zoo-` class, `{var}` filled, no year, interactions kept by `_sdFxNormalize()`, the time slots and the current one, the status levels, the pins, the project pills, the rules reaching the block stylesheet.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/plans/design-template-zoo.md
