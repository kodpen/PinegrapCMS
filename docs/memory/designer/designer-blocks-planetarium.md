---
name: designer-blocks-planetarium
description: The Planetarium block family (planetarium- prefix, its own palette group): the four blocks, what each draws, the blocks reused instead, its pg-sky-* rules, motion and test
---

## Where does it live?
- `assets/js/style_designer_blocks_planetarium.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `planetarium-`, palette group "Planetarium" (`bi-stars`). Built for the Planetarium template ([[design-template-planetarium-design]]), usable by any planetarium, public observatory, science centre or astronomy club.

## Which blocks does it hold?
- `planetarium-tonight`: the telescope status pill ("Telescopes open tonight"), the dark hours as a `dl` of `<time>` values (sunset, full darkness, moonrise, dome closes), five cloud cover steps as an `ol` (tonight's `aria-current`, the others' names `visually-hidden`), the planets up tonight as a list group (an `h4`, where to look, a rise and a set `<time>`, a seen-with pill).
- `planetarium-shows`: day links (Today `aria-current="date"`), three show `article` cards (still, `h3`, length, minimum age and language as icon plus word, a summary, showtimes as links holding a `<time>`; a sold-out time is crossed out with "Sold out" beside it, a few-seats time has a pill).
- `planetarium-objects`: four object cards (kind and name, constellation and where to look, a line, the magnitude, a seen-with pill), a legend of the three seen-with pills.
- `planetarium-moon`: eight phases as an `ol` (an icon orb, the phase `h3`, "In {var} days"; three carry a "Telescope night" pill), a nights link and the call-off rule.

## Which blocks are reused instead?
- Showtimes over days are not `events-list` / `events-cards`: those are one date per row; a dome show has several times a day, seats left and a minimum age. Prices: `pricing-tiers`. Hours: `hours-card`. Astronomers: `team-grid`. Telescope specs: `launch-specs` or a table on the page.
- The cloud cover scale is not `stats-progress`: it is a named step, never a percentage.

## What do its rules and texts follow?
- Rules: `pg-sky-time` (tabular figures for every time), `pg-sky-orb` (round icon or letter marker, `aria-hidden`, the word beside it carries the meaning), `pg-sky-wane` (mirrors a moon icon for the waxing phases). Colours only from theme classes; a template puts the night sections under `data-bs-theme="dark"`.
- A status or seen-with pill is always an icon plus a word. Moon days count from today; no date or year in the sample. Planet and object names are keys (Turkish names differ: Ülker, Avcı).
- Motion: the planets list, the show row and the phases slide up on view, the object row fades in (`props._fx`, stagger 90); nothing moves by itself.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_planetarium_test.php` runs it under Node: one `h2` and no skipped level per block, no fixed colour or template class, `{var}` filled, no year, interactions kept by `_sdFxNormalize()`, pills of icon plus word, only the orbs hidden, the cover step and day marked current, the time elements, the telescope nights, the rules reaching the block stylesheet.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/plans/design-template-planetarium.md
