---
name: designer-blocks-campsite
description: The Campsite block family (campsite- prefix, own palette group): hero with a kind-of-stay chooser, comfort scale, pitch drawing; general and other blocks placed instead; rules, test
---

## Where does it live?
- `assets/js/style_designer_blocks_campsite.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `campsite-`, palette group "Campsite" (`bi-tree`). Built for the Campsite template's polishing round ([[design-template-campsite]], `plans/design-template-own-looks-order.md`, `campsite-1`); usable by any campsite, holiday park or glamping site.

## Which blocks does it hold?
- `campsite-hero`: a wide `ratio-21x9` picture, then a text card lying over its lower edge from md up (`pg-campsite-lift`, negative top margin): eyebrow, the page's `h1`, a lead, Request a Stay and Prices buttons with two facts (arrival and departure, the season), then the kind-of-stay chooser (`nav.pg-campsite-kinds` with `aria-label`): a tile per kind (icon, name as a `stretched-link`, who it sleeps). Picture fades, card rises, on load.
- `campsite-comfort`: the kinds of stay from the wildest to the cosiest. From lg up an `aria-hidden` pair of scale ends and a line (`pg-campsite-scale`, border colour to primary); an `ol` of four cards (`pg-campsite-stop`: a dot on the line above each from lg up), each with icon, `h3`, who it sleeps, "You bring" and "Ready for you" (`h4` + list), a link. Rises on view (stagger 120).
- `campsite-pitch`: a `figure` with a pitch seen from above (`ratio-4x3` > `pg-campsite-plot`, `aria-hidden`): car place and tent area (`pg-campsite-area`), four numbered points (`pg-campsite-marker`) placed with Bootstrap position utilities (`top-*`, `start-*`, `translate-middle`), no rule per place. Beside it the legend: an `ol` of the same numbers, each with an `h3` and a line. Drawing scales in, legend rises, on view.

## Why are these not reused blocks?
- The hero is not `hero-split` or `school-hero`: a full-width picture with the card over it and a chooser of the kinds of stay. The scale is not `pricing-comparison` / `pricing-tiers`: no price or plan, the cards lie on a comfort axis and list what a guest brings. The drawing is not `zoo-map` (a map picture and its legend by zone): one pitch drawn from nodes, its points numbered on it.

## Which blocks does a campsite site place instead?
- Park map: `zoo-map` (picture and legend by zone). Facilities and why guests come: `features-grid`, `bento-features`. Seasons and prices: `hours-seasons` (a column per season) or `pricing-tiers`. Activities: `features-list` or `events-cards`. A day in the park: `timeline-vertical`. Rules: `features-list`. Figures: `stats-icons`. Guests' words: `testimonial-grid`. Groups and the stay call: `booking-split`, `cta-inline`. Questions: `faq-categorized`. Getting here: `contact-map`, `hours-location`.

## What do its rules and texts follow?
- Rules `pg-campsite-lift`, `pg-campsite-kinds`, `pg-campsite-scale`, `pg-campsite-stop`, `pg-campsite-plot`, `pg-campsite-area`, `pg-campsite-marker`; colours are theme variables (the plot's grass is `--bs-success-bg-subtle`). Each rule names only its own class: the plot's rule once styled `.pg-campsite-plot .pg-campsite-area`, so the drop and `pg_designer_block_css()` took the area rule as present and left it out (fixed in the campsite-2 PR). Sample content is the template's own (kinds of stay, who each sleeps, what is ready, arrival times, the park's tagline keys reused). No year, month or date.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_campsite_test.php` runs the kit and the file under Node: a section per block, one `h1` in the hero and one `h2` in the others with no skipped level, no fixed colour or template class, `{var}` filled, no year or month, entrances kept by `_sdFxNormalize()`; the chooser's four kinds and two facts, the scale's four stops and eight lists, the drawing's two areas and eight markers (four on it, four in the legend); the rules through `_pg_designer_block_css_parse()`.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
