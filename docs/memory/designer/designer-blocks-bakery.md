---
name: designer-blocks-bakery
description: The Bakery block family (bakery- prefix, own palette group): fresh-from-the-oven hero with oven times and an order tag, cake size guide drawn to scale; blocks placed instead; test
---

## Where does it live?
- `assets/js/style_designer_blocks_bakery.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `bakery-`, palette group "Bakery" (`bi-cake2`). Built for the Bakery template's polishing round ([[design-template-bakery]], `plans/design-template-own-looks-order.md`, `bakery-1`, owner's line of 2026-11-11: [[design-template-owner-line]]); a patisserie or a cake shop places the same blocks. The template places both since `bakery-2` ([[design-template-bakery-home]]).

## Which blocks does it hold?
- `bakery-hero`: the way in. A badge (icon, "Ovens lit at", a `<time>`), the `h1`, a line, two buttons; "Out of the oven today" (`h2`) over an `ol.pg-bakery-oven` of three batches (`<time>` + `h3`). Beside it a 4:5 picture (`pg-bakery-photo`) with the order tag (`pg-bakery-tag`: a punched card, the last order `<time>`, "Last orders for the next day") over its corner from md up, under it below md.
- Hero motion, on load: the words rise, the picture grows (`scale`), the batches rise one by one (delay 400, stagger 120), the tag slides in (delay 700). The tag hangs at -4° from md up and straightens on hover (CSS, no transition under reduced motion); its entrance sits on the holder `pg-bakery-hang`, so the slant and the entrance's transform never meet.
- `bakery-cakes`: the cake size guide. Header (eyebrow, `h2`, a line), an `ol` of four sizes, smallest first: the cake drawn to scale (`pg-bakery-cake`, `aria-hidden`; `pg-bakery-layer` + `pg-bakery-d15`/`-d20`/`-d25` = 60/80/100 % wide; two tiers = two layers), the size `h3`, servings, "Starting from" + amount. Then sponges and fillings (`h3`, pills) and a note with the lead time and Order a Cake. On view the header fades, the cakes grow one by one (`scale`, stagger 150), the rest fades.

## Why are these not reused blocks?
- The hero is not `hero-split` / `showcase-floating` / `gym-hero`: it carries today's oven times and the order cut-off. Three batch times are not `zoo-today` (a day's schedule with places and a next-slot marker).
- The size guide is not `pricing-tiers` (plans with features), `menu-board` (a price table per cup size) or `bikeshop-sizes` (a size chart keyed to a lettered drawing): cakes compared by a drawing to scale and servings, with the flavours to choose.

## Which blocks does a bakery site place instead?
- Daily bake: `menu-tabs` (a tab per kind of bake) or `menu-cards`; diet badges: `menu-legend`; bake of the week: `menu-specials`. Wholesale: `catering-packages` / `services-icon-cards`. How ordering works: `steps-numbered`; how far ahead: `stats-icons` / `features-list`. Opening hours: `hours-card`. Figures: `stats-icons`. Customers' words: `testimonial-grid`. Order band: `booking-cta` / `cta-inline`. Loyalty: `loyalty-stamps`. Baking classes and journal posts stay collection list widgets.

## What do its rules and texts follow?
- Rules `pg-bakery-photo`, `pg-bakery-tag`, `pg-bakery-oven`, `pg-bakery-cake`; each colours with a theme variable. Sample texts reuse the template's keys and existing price keys (`$45`, `$65`, `$80`, `$240`). "From" reads "Kimden" (a sender) in the shared keys, so a price says "Starting from". No year or month.

## How is it tested?
- `tests/designer_blocks_test.php` checks it like every family file; `tests/designer_blocks_bakery_test.php` runs it through `tests/helpers/designer_family_run.php`: own group, one top heading per section, no skipped level, no fixed colour or `pbk-`/`bky-` class, `{var}` filled, no year or month, entrances kept by `_sdFxNormalize()`; the badge, batches, tag, sizes, layers, prices; the `ol`s, `<time>`s and `aria-hidden` read from the file; the rules through `_pg_designer_block_css_parse()`.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
