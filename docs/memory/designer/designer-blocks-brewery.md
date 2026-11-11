---
name: designer-blocks-brewery
description: The Brewery block family (brewery- prefix, own palette group): pour hero, tap board, tasting flight, fresh release; the palette-toned glass; general blocks placed instead; rules, test
---

## Where does it live?
- `assets/js/style_designer_blocks_brewery.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `brewery-`, palette group "Brewery" (`bi-cup-straw`). Built for the Brewery template's polishing round ([[design-template-brewery]], `brewery-1`, PR #8666); a taproom or brewpub places the same blocks.

## Which blocks does it hold?
- `brewery-hero`: a dark band, the page's `h1` very large and uppercase, a line, See what is on tap / Book a tour, three facts; beside it a 4:5 brewhouse picture (`pg-brewery-photo`) with a glass (`pg-brewery-glass pg-brewery-pour`) over its lower edge. On load the words rise, the picture fades, the beer slides up into the glass (delay 700), the facts fade in one by one.
- `brewery-taps`: a dark tap board, an `ol` of six taps. Each tap: its number in a ring (`pg-brewery-tap`), a small glass in the beer's tone (`pg-brewery-mini`), `h3` name, style, ABV and IBU, keg news as a badge (Fresh keg, Last keg, Seasonal). The taps rise on view (stagger 90).
- `brewery-flight`: words and a booking button beside a paddle (`ol`, `pg-brewery-paddle`) of four tasters poured light to dark (tones 1 to 4), each slides up on view 250 ms after the one before; name, style and ABV under each.
- `brewery-release`: one fresh beer. A picture with parallax and a round "Limited release" sticker (`pg-brewery-sticker`); the name very large; Alcohol, Bitterness and Batch as large figures in a `dl`; Tasting notes as badges; Buy a pack / Find it on tap.
- Sample texts are the template's own (its beers and their figures); no year or month.

## How is the beer drawn?
- `pg-brewery-glass` is a tapered `clip-path` with flex to the bottom; the beer inside (`pg-brewery-beer`) has a foam band on top. `pg-brewery-tone-1..4` mix `--bs-primary` with `--bs-light` or `--bs-dark` under `@supports (color-mix)`, so each palette pours its own beer; an older browser shows the primary colour. The glasses are `aria-hidden`: the words say the style.

## Why are these not reused blocks?
- No general block draws a glass that fills, a board of numbered taps with strength, bitterness and keg news, a flight poured in order, or one beer's facts as large figures with a sticker. Not `launch-`: that sells one product on a launch day (countdown, specs, pre-order).

## Which general blocks does a brewery site place instead?
- Beer list with sizes and prices: `menu-board`. Beer cards with notes: `menu-origins`. Food: `menu-list` / `menu-cards`. Events: `events-list` / `events-cards`. Tours: `services-icon-cards`, `booking-split`, `booking-cta`. Beer club: `pricing-tiers`, `loyalty-stamps`.
- Brewing: `steps-numbered` / `timeline-horizontal`. Brewhouse: `features-grid` / `bento-features`. Hours: `hours-card` / `hours-location`. Story: `features-alt`. Words: `testimonial-grid`. Figures: `stats-counter`. Questions: `faq-categorized`. Spaces to hire: `locations-spaces`.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_brewery_test.php` runs it through `tests/helpers/designer_family_run.php`: own group, a section per block with one top heading and no skipped level, no fixed colour or template class, `{var}` filled, no year or month, interactions kept by the normalizer.
- Per block: six taps with ABV and IBU, four tasters with tones 1 to 4, the release's facts and sticker; `aria-hidden`, `ol` and `dl` read from the file; rules through `_pg_designer_block_css_parse()` with no colour or motion of their own.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
