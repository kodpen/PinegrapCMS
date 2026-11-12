---
name: designer-blocks-dealership
description: The Car Dealership block family (dealership- prefix, own palette group): showroom panel, stock finder, three ways in; general blocks placed instead; rules, test
---

## Where does it live?
- `assets/js/style_designer_blocks_dealership.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `dealership-`, palette group "Car Dealership" (`bi-car-front`). Built in step `dealership-1` of `plans/design-template-own-looks-order.md` (PR #8640) for [[design-template-dealership]]; usable by any car, motorbike, boat or caravan dealer.

## Which blocks does it hold?
- `dealership-showroom` (Showroom Panel): one vehicle a window tall (`pg-dl-panel`, 92svh). The photo is the section's `style` background (Background edits it), never an `<img>` placed over the canvas. A `pg-dl-veil` overlay (`data-bs-theme="dark"`, dark at top and foot, clear in the middle) holds the kicker (`pg-dl-kicker`), the `h2` name (`pg-dl-type display-3`), "Cash price" + amount as two spans, Book a Test Drive (btn-primary) and More Like This (btn-secondary). At the foot a `dl.pg-dl-figures` of three `pg-dl-figure` (dt label, dd number + unit, number shown above). The head fades, the figures rise (stagger 120) on view.
- `dealership-finder` (Stock Finder): kicker, `h2`, a line, New Cars / Used Cars / All Cars; a `ul.pg-dl-shapes` of five body styles (large icon + word, a line in the theme colour on hover or focus) between two hairlines; a row of make links. Header fades, the styles rise (stagger 80).
- `dealership-ways` (Three Ways In): header, then `pg-dl-ways`, three `article.pg-dl-way` tiles (60vh, photo as background, words on the core `pg-shade-bottom` scrim with `data-bs-theme="dark"`): Test drive, Trade-in, Finance, each `h3` and one action. The tiles rise (stagger 150).

## Why are these not reused blocks?
- The panel is not `hero-image-bg`: a full-window stage for one product with its figures as data, used several times in a row. The finder is not `features-grid` or chips: a filter entry point in a hairline row. The ways are not `services-image-cards`: tall full-bleed tiles that each open a request.

## Which general blocks does a dealer site place instead?
- The sticky order and test-drive bar: `cta-sticky` ([[designer-blocks-cta]]). The dealer's figures: `stats-counter` (the template draws it with `pg-dl-type`). Services: `services-icon-cards`. Ways to pay: the template's finance cards or `pricing-tiers`. Customers' words: `testimonial-grid`. Questions: `faq-categorized`. Stock lists stay collection list widgets.
- Colour switch on a vehicle page: the Variant Picker (`variant-picker`, [[designer-block-variant-picker]]) with the template's own swatch classes (step `dealership-2`). An exterior/interior word switch and a wheel switch do not exist yet.

## What do its rules and texts follow?
- Rules `pg-dl-panel`, `pg-dl-veil` (scrim in rgba black, as `pg-shade-bottom`), `pg-dl-type` (weight 300, tight tracking; also `.pg-dl-kicker`, wide tracking), `pg-dl-figures`, `pg-dl-shapes` (transition off under reduced motion), `pg-dl-ways`. No hex colour, no `!important`.
- The thin type needs a light weight of the heading font: the template loads Barlow 300.
- "From" reads "Kimden" in Turkish, so the price says "Cash price" (Peşin fiyat) beside the amount. Sample makes and models are invented; no year or month.

## How is it tested?
- `tests/designer_blocks_dealership_test.php` (with the cta file): own group / Marketing, a section per block with one `h2` and only `h3`, no fixed colour, `pdl-` or `opacity-` class, no year or month, interactions kept by `_sdFxNormalize()`; the panel's background, veil and three figures; five styles and four makes; three tiles on the scrim; the rules through `_pg_designer_block_css_parse()`; `dealership-ways` brings `.pg-shade-bottom`.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
