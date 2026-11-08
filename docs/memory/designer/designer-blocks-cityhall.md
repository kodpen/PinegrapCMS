---
name: designer-blocks-cityhall
description: The City Hall block family (cityhall- prefix, own palette group): task hero, service guide, council agenda; general blocks placed instead; rules, test
---

## Where does it live?
- `assets/js/style_designer_blocks_cityhall.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `cityhall-`, palette group "City Hall" (`bi-building`). Built for the City Hall template's polishing round ([[design-template-cityhall]], `plans/design-template-own-looks-order.md`, `cityhall-1`); usable by any municipality, district office or public agency.

## Which blocks does it hold?
- `cityhall-hero`: eyebrow, the page's `h1` and a line in eight columns; a `nav` named "Common tasks" (`aria-label`) holding a `ul` of six task tiles (`pg-cityhall-task`: icon, name as a `stretched-link`, a line, an arrow `pg-cityhall-go`); under a hairline the citizen line with a `tel:` link and its hours. Words rise, then the tiles (stagger 80), on load.
- `cityhall-service`: one service as a resident reads it before going: eyebrow (its service area), `h2` name, a line, a `dl.pg-cityhall-guide` of Who applies, Where to apply, How long it takes, Fee; `h3` What to bring and a `ul` of documents; Make a Request / All Services. Beside it an `aside` card: the responsible department (`h3`), what it does, floor, `tel:` phone, hours. Facts rise (stagger 80) and the card fades on view.
- `cityhall-council`: the next session (eyebrow, `h2`, the template's meeting sentence, when / where / open to the public, Council Decisions and Meet the Council) beside `h3` "On the agenda" and an `ol.pg-cityhall-agenda` of four items: number (`pg-cityhall-no`, tabular figures on a joining rule), `h3` subject, committee, a status badge that is an icon and a word (Decided, Sent back to committee, To be discussed), never colour alone. The session fades and the items rise (stagger 100) on view.

## Why are these not reused blocks?
- The hero is not `hero-centered` / `hero-split`: its way in is the tasks residents come for, each a whole-tile link, with the always-open line. The guide is not `services-price-list` / `features-list`: one service's conditions as a description list, its documents and its department. The agenda is not `timeline-vertical` / `events-list`: numbered items of one session with the committee and where each stands.

## Which general blocks does a city hall site place instead?
- Service areas: `services-icon-cards`. Departments: `services-icon-cards` / `contact-info`. Service points: `locations-cards` / `hours-location`. Useful numbers: `contact-info` or `features-grid`. Mayor and council: `team-leadership` / `team-minimal`. Figures: `stats-icons`. Request call: `cta-inline`. Questions: `faq-categorized`. Notices and events are collections placed by their list widgets.

## What do its rules and texts follow?
- Rules `pg-cityhall-task` (border and arrow follow hover and focus; the arrow keeps still under reduced motion), `pg-cityhall-guide` (grid columns inside one `@media (min-width: 576px)`), `pg-cityhall-agenda`; each colours with a theme variable. Sample texts reuse the template's keys (tagline, citizen line, building permit, the planning department, the meeting sentence, committee names); no year or month.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_cityhall_test.php` runs it through `tests/helpers/designer_family_run.php`: own group, a section per block with one top heading and no skipped level, no fixed colour or `pcy-`/`cy-` class, `{var}` filled, no year or month, entrances kept by `_sdFxNormalize()`; six tiles each one link, the guide's facts and documents, four numbered items with icon-and-word badges; the named nav and two `tel:` links read from the file; the rules through `_pg_designer_block_css_parse()`.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
