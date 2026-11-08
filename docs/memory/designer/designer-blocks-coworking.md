---
name: designer-blocks-coworking
description: The coworking- block family (Coworking group): the workspace hero, what it reuses instead (spaces, plans, amenities, floors), its pg-cw-* rules, its test
---

## What does the family hold?
- `pinegrap/assets/js/style_designer_blocks_coworking.js` ([[designer-block-family-files]]), prefix `coworking-`, palette group "Coworking" (`bi-laptop`), step `coworking-1` of `plans/design-template-own-looks-order.md`.
- `coworking-hero`: the page's `h1`, a line and a question ("How do you work?") answered by a list of ways in (`ul` labelled "Ways to work"): hot desk, fixed desk, private office, meeting room, each a link row with its icon, name, line, "Starting at" + price + unit as separate nodes (`/day`, `/month`, `/hour`) and an arrow; beside it a mosaic of two pictures. Text and ways slide up on load (stagger), the mosaic fades in.

## Why only one block?
- The reuse search ([[design-template-own-look]]) found the rest in the palette: meeting rooms and event halls are `locations-spaces`, the membership plans `pricing-tiers` / `pricing-comparison`, the amenities `features-grid`, the figures `stats-counter`, the events `events-cards`, opening hours `hours-card`, a day in the house `timeline-vertical`. The building's floors are a general block several sectors use, so it went into the Locations file as `locations-floors` ([[designer-blocks-general]]).
- The hero is not `hero-split`: its answer is a list of priced ways in, not two buttons.

## What do its rules do?
- `pg-cw-way`: the row's own border (Bootstrap's `border` utility is `!important`, a rule could not recolour it), primary on hover and focus with a 4 px shift, still under reduced motion. `pg-cw-mosaic` (`pg-cw-tall` 3:4, `pg-cw-short` 1:1 set lower), `pg-cw-price` (tabular figures).
- Turkish: the shared `From` reads "Kimden", so the price line uses its own `Starting at` ("Başlangıç"); hot desk "Esnek masa", fixed desk "Sabit masa".

## How is it tested?
- `tests/designer_blocks_coworking_test.php` through `tests/helpers/designer_family_run.php` (the Node runner the general families' test uses too): own group, one `h1`, no fixed colour or year, three entrances kept by `_sdFxNormalize()`, four ways with price and unit apart, picture alt texts, the rules in the block stylesheet. `tests/designer_blocks_test.php` checks the file like every family file.

Related: docs/memory/designer/design-template-coworking.md, docs/memory/plans/design-template-own-looks-order.md
