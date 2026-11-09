---
name: designer-blocks-bikeshop
description: The Bike Shop block family (bikeshop- prefix, its own palette group): the four blocks, what each draws, the blocks reused instead, its pg-bike-* rules, motion and test
---

## Where does it live?
- `assets/js/style_designer_blocks_bikeshop.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `bikeshop-`, palette group "Bike Shop" (`bi-bicycle`). Built for the Bike Shop template ([[design-template-bikeshop-design]]), usable by any bicycle shop, repair workshop or cycling club.

## Which blocks does it hold?
- `bikeshop-workshop`: the workshop's load this week as an `ol` of four words (Quiet, Steady, Busy, Full; this week's `aria-current="true"`), the load said in a sentence, a next-free-drop-off card with what to bring and a Request a service link, and the jobs as a list (`h3`, what is done, a turnaround pill of icon plus words: Same day, 1 day, 3 days…).
- `bikeshop-sizes`: a lettered geometry drawing in a `figure`, the measures A and B explained, and a size chart `table` in `table-responsive` (caption, `scope` on heads, size, rider height range, stack, reach, seat tube, top tube); heads A–D carry the same `pg-bike-key` letters as the drawing; a Book a bike fit link.
- `bikeshop-rides`: the week's group rides as `article` cards: day and a `<time>` start, the name `h3`, a pace pill (Social, Steady, Fast) and a drop-rule pill (No-drop, Drop ride), a `dl` of distance, climbing and average speed, the meeting point; the next ride has `aria-current="true"`, a "Next ride" badge and a primary border; a pace legend in the header.
- `bikeshop-fit`: the fit photo in a `figure` (fades in on view), five numbered contact points (`pg-bike-key`, `h3` and a line each), a fit sheet card with four readings in a `dl`, a Book a bike fit link.

## Which blocks are reused instead?
- Service prices: `services-price-list`; fit packages and service plans: `pricing-tiers`. Opening hours: `hours-card` / `hours-seasons`. Mechanics and fitters: `team-grid`. Brands stocked: `logos-grid`. Bike models side by side: `launch-specs` is a phone launch's table and stays its own; a model comparison on the pages uses `compare-table`.
- `bikeshop-rides` is not `events-list` / `events-cards`: those are dated one-off events; a ride is a weekly slot with pace, drop rule, distance and climbing.
- `bikeshop-sizes` is not `compare-table`: that one compares features with ticks; a size chart is numbers per size keyed to a drawing.
- `bikeshop-workshop` is not `stats-progress`: the load is named steps, not percentages.

## What do its rules and texts follow?
- Rules: `pg-bike-num` (tabular figures), `pg-bike-key` (square letter or number marker tying a list row to a picture). Colours only from theme classes.
- Every pill is an icon plus a word. Size codes (S, M, L, XL) are plain text, not keys. Numbers use `{var}`, `{var:1}`, `{var:2}`. No date, year or month in the sample ("once the evenings draw in", not month names).
- Motion: the job list, the ride row and the contact points rise on view (`props._fx` slide-up, stagger 80); the fit photo fades in; nothing moves by itself.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_bikeshop_test.php` runs it under Node: one `h2` and no skipped level per block, no fixed colour or `pbs-`/`bikeshop-` class, `{var}` filled, no year or month, interactions kept by `_sdFxNormalize()`, the current load and next ride, the size table and its keys, the ride times and facts, the fit points and readings, the rules reaching the block stylesheet.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/plans/design-template-bikeshop.md
