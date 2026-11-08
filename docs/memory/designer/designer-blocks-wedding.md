---
name: designer-blocks-wedding
description: The Wedding block family (wedding- prefix, its own palette group): invitation hero, real wedding story, planning checklist; the general blocks a wedding site places instead; rules and test
---

## Where does it live?
- `assets/js/style_designer_blocks_wedding.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `wedding-`, palette group "Wedding" (`bi-balloon-heart`). Built for the Wedding template's polishing round ([[design-template-wedding]], `plans/design-template-own-looks-order.md`, `wedding-1`); usable by any planner or wedding venue.

## Which blocks does it hold?
- `wedding-hero`: a wide photo (`ratio-21x9`) with an invitation card laid over its lower edge from lg up (`pg-wedding-invite`: inner hairline frame, negative top margin): eyebrow, the page's `h1`, the ornament divider (`pg-wedding-rule`, `aria-hidden`), a lead, the planner's three facts as a `dl` (icon, `dt`, `dd`), two buttons. Photo fades in, card rises, both on load.
- `wedding-story`: one real wedding in a band: a three-picture mosaic (`pg-wedding-mosaic`: a tall picture beside two stacked), "Real wedding", the couple as `h2`, facts (space, season, guests; the label kept as `visually-hidden` text), the summary, their words in `figure`/`blockquote`/`figcaption`, "The suppliers" (`h3`) as a `dl` of role and name, "Read their story". Mosaic fades in, text rises, on view.
- `wedding-checklist`: what to do and when: an `ol` of six stage cards (icon, "Twelve months before"…, `h3`, tasks with `bi-check2-square`), rising on view (stagger 100); the ornament under the `h2`; a foot line with "See the planning services".

## Why are these not reused blocks?
- The hero is not `hero-image-bg` / `hero-split` (text on or beside a picture): a card over the photo holding a description list of facts. The story is not `features-alt` or `testimonial-single`: a mosaic, facts, quote and supplier credits of one record. The checklist is not `steps-numbered` (one line per step) nor `timeline-vertical` (one text per date): a task list per period.

## Which general blocks does a wedding site place instead?
- Packages priced per guest: `catering-packages` (icon, price "per person", minimum, items, featured card). Spaces: `locations-spaces`. The order of the day: `timeline-vertical` with times of day. Planning services side by side: `compare-table`. Kind words: `testimonial-grid` / `quote-spotlight`. Pictures: `gallery-polaroid`, `image-masonry`. Venue visit call: `booking-split`. Questions: `faq-categorized`.

## What do its rules and texts follow?
- Rules `pg-wedding-invite`, `pg-wedding-rule` (`content: ""` hairlines), `pg-wedding-mosaic`; each colours with a theme variable. Sample content is the template's own (its keys reused: the Olive Garden, Selin and Emre, the timeline stages), so most texts are already translated. No year, month or date; motion only as entrances.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_wedding_test.php` runs the kit and the file under Node: a section per block, one `h1` in the hero and one `h2` in the others with no skipped level, no fixed colour or `pwd-`/`wd-` class, `{var}` filled, no year, entrances kept by `_sdFxNormalize()`; the hero's `dt`/`dd` pairs, the story's three pictures, quote and suppliers, the checklist's six stages; the rules through `_pg_designer_block_css_parse()`.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
