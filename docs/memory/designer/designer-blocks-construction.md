---
name: designer-blocks-construction
description: The Construction block family (construction- prefix, own palette group): site board hero, project progress, neighbour notice; general blocks placed instead; rules, test
---

## Where does it live?
- `assets/js/style_designer_blocks_construction.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `construction-`, palette group "Construction" (`bi-bricks`). Built for the Construction template's polishing round ([[design-template-construction]], `plans/design-template-own-looks-order.md`, `construction-1`).

## Which blocks does it hold?
- `construction-hero`: a `g-0` row: a `ratio-4x3` site photograph in seven columns, and the firm's board (`pg-cn-board`) in five, laid over the photograph's edge from lg up (negative margin, shadow). The board starts with the barrier stripe (`pg-cn-stripe`, `aria-hidden`), then eyebrow, the page's `h1`, a line, Request a Quote / See Our Projects (square buttons), and a `dl` of four board facts (two columns from sm up). Photograph fades, board rises, on load.
- `construction-progress`: one project in five columns (eyebrow "On site now", `h2`, line, "Work done" with its share, a `progress` bar with `role="progressbar"` and its aria values, the latest site photograph) beside an `ol.pg-cn-stages` in seven: a stage per `li` (`pg-cn-done` / `pg-cn-now` / `pg-cn-next`), a dot (`pg-cn-dot`, `aria-hidden`) on a line down the left, `h3` name, the state in words as a badge, a line. Fades and rises (stagger 100) on view.
- `construction-notice`: the notice a contractor puts up for the street, on a tinted band: a framed sheet (`pg-cn-notice`) under the barrier stripe, cone icon and `h2`, a line, a `dl` of four facts (working hours, deliveries, noisy work, how long; the times in tabular figures, `pg-cn-hours`), the site manager's call and a button. Rises on view.

## Why are these not reused blocks?
- The hero is not `hero-split` or `hero-image-bg`: a board with the facts a site board shows overlaps the photograph. The progress is not `steps-numbered` / `timeline-vertical` (a process in general) or `stats-progress` (several figures): one project's stages, each marked done, on site or to come, with the share done. The notice is not `hours-card` / `alert-*`: a framed street notice with the works' hours, deliveries, noise and length, and who to call.

## Which general blocks does a contractor's site place instead?
- Services: `services-icon-cards` / `services-image-cards`. Sectors: `features-grid`. Process: `steps-numbered` / `timeline-horizontal`. Safety: `features-list`. Projects: `bento-gallery` / `image-masonry`. Team: `team-profiles`. Figures: `stats-counter`. Clients' words: `testimonial-grid`. Quote call: `cta-inline` / `booking-split`. Questions: `faq-categorized`.

## What do its rules and texts follow?
- Rules `pg-cn-stripe` (diagonal stripes in `--bs-primary` on `--bs-emphasis-color`), `pg-cn-board`, `pg-cn-stages` (also defines `pg-cn-dot`, `pg-cn-done`, `pg-cn-now`), `pg-cn-notice` (also `pg-cn-hours`); each colours with a theme variable. Square corners (`rounded-0`). The `pcn-` classes stay the template's.
- No year or month; the length in words. Shared keys that read wrong for a site ("Our crews" is "Mürettebatımız", "Frame" is "Çerçeve") are not reused: "Our builders", "Structural frame".

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_construction_test.php` runs the kit and the file under Node: a section per block, one `h1` in the hero and one `h2` in the others with no skipped level, no fixed colour or `pcn-` class, `{var}` filled, no year or month, entrances kept by `_sdFxNormalize()`; four board facts, five stages (two done, one now, two next) with a dot and a badge each and the progress bar, four notice facts with two times; the rules through `_pg_designer_block_css_parse()`.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
