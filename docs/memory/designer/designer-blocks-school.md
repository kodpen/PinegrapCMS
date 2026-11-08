---
name: designer-blocks-school
description: The School block family (school- prefix, own palette group): hero with an age finder, age ladder, school year in terms; general blocks placed instead; rules, test
---

## Where does it live?
- `assets/js/style_designer_blocks_school.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `school-`, palette group "School" (`bi-backpack`). Built for the School template's polishing round ([[design-template-school]], `plans/design-template-own-looks-order.md`, `school-1`); usable by any nursery, kindergarten or primary school.

## Which blocks does it hold?
- `school-hero`: text beside a `ratio-4x3` picture: eyebrow, the page's `h1`, a lead, Apply and Visit buttons, then the age finder (`nav.pg-school-finder` with `aria-label`): a tile per programme (ages, then the name as a `stretched-link`). On the picture a card with the next open morning. Text rises and picture fades, on load.
- `school-ages`: every programme on one age scale (2 to 10). `pg-school-ages` is an eight-column grid from lg up; a programme's `li` spans with `pg-school-from-<age>` / `pg-school-to-<age>` (2-9 / 3-10), so nursery, kindergarten and primary share the first row (tinted `bg-primary-subtle`) and clubs and summer school fall to their own rows. Above it an `aria-hidden` scale of ticks (the grid's `div` children), hidden under lg; under it the class groups (`h3` + pills). The list rises on view.
- `school-year`: an `ol` of three term cards (`pg-school-term`: a primary band along the top): length in words, `h3` with the season's name, a line, highlights, the report home at the foot (`mt-auto`); under the first two the holiday after it (`pg-school-break`: dashed line, quieter words). A foot line links summer school. Rises on view (stagger 120).

## Why are these not reused blocks?
- The hero is not `hero-split`: it holds a navigation into the programmes by age and an event card. The ladder is not `features-grid` / `services-icon-cards`: the cards are placed on a shared age axis that shows the path through the school. The year is not `timeline-horizontal` (one point per step): a card of highlights and a report per term, with the holiday between.

## Which general blocks does a school site place instead?
- A day hour by hour: `timeline-vertical`. Admission steps: `steps-numbered`. Staff: `team-profiles` / `team-grid`. Why families choose it, the approach, the campus: `features-grid`, `bento-features`. Figures: `stats-icons`. Accreditations: `logos-grid` or `features-list`. Events: `events-cards`. Parents' words: `testimonial-grid`. Visit call: `booking-split`. Questions: `faq-categorized`. Fees (words, no figure): `features-list` beside `cta-inline`.

## What do its rules and texts follow?
- Rules `pg-school-finder`, `pg-school-ages` (grid lines inside one `@media (min-width: 992px)`), `pg-school-term`, `pg-school-break`; each colours with a theme variable. Sample content is the template's own (programmes, ages, class groups, open morning keys reused). No year, month or date: terms are named by season and their length is words; no child is named.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_school_test.php` runs the kit and the file under Node: a section per block, one `h1` in the hero and one `h2` in the others with no skipped level, no fixed colour or template class, `{var}` filled, no year or month, entrances kept by `_sdFxNormalize()`; the finder's five tiles, the ladder's span classes and class groups, three terms and two breaks; the rules through `_pg_designer_block_css_parse()`, every grid line present.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
