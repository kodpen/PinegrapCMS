---
name: designer-blocks-museum
description: The Museum block family (museum- prefix, own palette group): exhibition hero, look closer, highlights trail; general blocks placed instead; rules, test
---

## Where does it live?
- `assets/js/style_designer_blocks_museum.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `museum-`, palette group "Museum" (`bi-bank2`). Built for the Museum template's polishing round ([[design-template-museum]], `plans/design-template-own-looks-order.md`, `museum-1`); an art gallery or any exhibiting house places the same blocks.

## Which blocks does it hold?
- `museum-exhibition`: the exhibition on now as a poster. Eyebrow "Now On", the page's `h1`, a line, a `dl.pg-museum-facts` (When, Where, Curator, Admission; two columns from sm up), Book Tickets / About the Exhibition; under them `h2` "Also on" and a `ul` of two other exhibitions (`h3` title, when and where). Beside it a portrait picture in `pg-museum-poster` (4:5, cover) with a caption. On load the words rise, the poster fades (delay 200), the other exhibitions follow (delay 400).
- `museum-object`: one work to look closer at. The picture with its wall label as the `figcaption` (`pg-museum-label`: maker, title in italics, period, material, gallery); beside it eyebrow, `h2` title, a line and an `ol` of three details, each a number (`pg-museum-no`), a 16:9 close-up, an `h3` name and a line; Explore the collection. Picture fades, details rise (stagger 120) on view.
- `museum-trail`: the works not to miss as a route. Header (eyebrow, `h2`, a line; length, meeting point, Highlights Tour) above an `ol.pg-museum-trail` of four stops in a row from lg up: number on a dashed route line (lg up), picture, `h3` title, maker and period, the room as a badge that is an icon and a word. Header fades, stops rise (stagger 120) on view.
- Sample texts are the template's own (the exhibitions, the highlights, the departments' galleries); no year or month.

## Why are these not reused blocks?
- The hero is not `hero-split`: it carries the exhibition's facts and the other exhibitions on, the way a museum's front page opens. Look closer is not `architecture-sheet` / `zoo-animal`: no fact list, but a wall label and numbered close-ups of the one work. The trail is not `steps-numbered` / `timeline-horizontal` / `services-image-cards`: works in walking order with their rooms on a route.

## Which general blocks does a museum site place instead?
- Opening hours: `hours-card` / `hours-seasons`. Floors and galleries: `locations-floors`; spaces to hire: `locations-spaces`. Admission and membership: `pricing-tiers` / `services-price-list`. Departments: `services-icon-cards`. Figures: `stats-counter` / `stats-icons`. People: `team-profiles`. Learning sessions: `features-grid` / `steps-numbered`. Visitors' words: `testimonial-grid`. Ways to help: `features-grid` + `cta-inline`. Questions: `faq-categorized`. Exhibitions, highlights and events stay collection list widgets.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_museum_test.php` runs it through `tests/helpers/designer_family_run.php`: own group, a section per block with one top heading and no skipped level, no fixed colour or `pmz-`/`mz-` class, `{var}` filled, no year or month, entrances kept by `_sdFxNormalize()`; the hero's facts and two other exhibitions, the label and three numbered details, four stops with icon-and-word rooms; the ordered lists read from the file; the rules through `_pg_designer_block_css_parse()`, each with a theme variable.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
