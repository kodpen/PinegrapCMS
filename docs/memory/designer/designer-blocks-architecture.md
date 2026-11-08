---
name: designer-blocks-architecture
description: The Architecture block family (architecture- prefix, own palette group): plate hero, project sheet, material board; general blocks placed instead; rules, test
---

## Where does it live?
- `assets/js/style_designer_blocks_architecture.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `architecture-`, palette group "Architecture" (`bi-rulers`). Built for the Architecture template's polishing round ([[design-template-architecture]], `plans/design-template-own-looks-order.md`, `architecture-1`); usable by any architecture, interior design or landscape studio.

## Which blocks does it hold?
- `architecture-hero`: a row (eyebrow and the page's `h1` in eight columns, a line and two square buttons in four, aligned to the foot), then a `figure.pg-arch-plate`: one `ratio-21x9` photograph and a `figcaption` like a monograph plate (number `pg-arch-no` in tabular figures, project name, a line, "See the Project" pushed to the end from sm up). Words rise and the plate fades, on load.
- `architecture-sheet`: a `figure` (`ratio-4x3` picture, caption) in seven columns beside the facts in five: eyebrow (the type), `h2` project name, a line, a `dl.pg-arch-facts` of six facts (Location, Type, Floor Area, Stage, Client, Materials; each pair a `div` on a hairline, label beside value from sm up), then `h3` Credits and a `ul` of "Role:" + name. The picture fades and the facts rise (stagger 80) on view.
- `architecture-materials`: a header (eyebrow, `h2`, line) and a `ul` row (2 / 3 / 6 per row) of materials, each a `pg-arch-swatch` mount: square sample picture, name `h3`, finish, where it was used. Rises on view (stagger 80).

## Why are these not reused blocks?
- The hero is not `hero-image-bg` (words over the picture) or `hero-split`: the picture is a wide plate under the words, captioned with the project it shows and a way to it. The sheet is not `features-list` / `launch-specs`: one project's facts as a description list beside its photograph, with its credits. The board is not `image-grid` / `services-image-cards`: each sample is mounted on a board margin with its finish and its use.

## Which general blocks does a studio site place instead?
- Services: `services-icon-cards` / `services-image-cards`. Stages of work: `steps-numbered` or `timeline-horizontal`. Principles: `features-grid`. Team: `team-profiles` / `team-minimal`. Figures: `stats-counter`. Clients' words: `testimonial-grid`. Press: `logos-grid`; awards: `features-list`. Projects gallery: `bento-gallery` / `image-masonry`. Enquiry call: `cta-inline`. Questions: `faq-categorized`.

## What do its rules and texts follow?
- Rules `pg-arch-plate`, `pg-arch-facts` (grid columns inside one `@media (min-width: 576px)`), `pg-arch-swatch`; each colours with a theme variable. Square corners (`rounded-0` buttons, no rounded frames) to match a studio's drawn look. Sample texts are the template's (the hero line and picture keys reused); no year or month, the size in words; no person is named, credits name firms by kind. "Floor Area" is the shared key ([[designer-blocks-general]]).

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_architecture_test.php` runs the kit and the file under Node: a section per block, one `h1` in the hero and one `h2` in the others with no skipped level, no fixed colour or `pai-` class, `{var}` filled, no year or month, entrances kept by `_sdFxNormalize()`; the plate's caption and number, six facts with values and four credits, six mounted samples; the rules through `_pg_designer_block_css_parse()`.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
