---
name: designer-blocks-florist
description: The Florist block family (florist- prefix): bouquet hero with the tucked gift card, card message band, subscription with week strips; the general locations-delivery block; tests
---

## Where does it live?
- `assets/js/style_designer_blocks_florist.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `florist-`, palette group "Florist" (`bi-flower1`). Built in step `florist-1` (PR #8763) for the Florist template ([[design-template-florist]]); `florist-2` places it on the pages.

## Which blocks does it hold?
- `florist-hero` (Florist: Bouquet Hero): the page's `h1`, a line, Send Flowers / See the Bouquets, the last order time for delivery today (`<time>`). Beside it the bouquet in an arched frame (`pg-florist-arch`, 4:5) with the gift card (`pg-florist-note`) over its lower left corner from md up, under it on a phone (`pg-florist-frame`, `pg-florist-tuck`).
- Under the hero's row an `h2` and six occasions as pill links. On load: words rise, frame grows, card slides up 0.7 s later, occasions fade in one by one.
- `florist-card` (Florist: Card Message): the card drawn large with a sample message (grows on view) beside an `h2`, three facts (ink, 200 characters, unsigned) and four samples, each an `h3` occasion and italic words (rise one by one).
- `florist-subscription` (Florist: Flower Subscription): weekly, fortnightly (Most chosen) and monthly plan cards. Each draws its twelve weeks (`pg-florist-weeks`, `aria-hidden`): a coloured square with a flower per delivery week, grey otherwise, popping in on view; then the count in words, price per delivery, the twelve weeks' total, what is included.

## Why are these not reused blocks?
- `bakery-hero` is an order tag and oven batches; no hero holds a written card, no block shows a message as the product. `pricing-tiers` gives one price per tier; the strip shows how often a plan comes. Another sector's subscription places `pricing-tiers`.

## Which general block was added?
- `locations-delivery` (Locations: Delivery Zones, "Hours & Locations", [[designer-blocks-general]]): three zones as rings around a shop icon (`pg-delivery-map`, `aria-hidden`) beside the zone list: key (`pg-delivery-key`), `h3`, fee, places, Same day / Next day badge.
- Under the list the delivery times (three `<time>` ranges) and the same-day cut-off; one entrance, the row rises on view. The outer zone is dashed through `--bs-border-style`, since `.border` is `!important`. For a florist, bakery, market, pharmacy or restaurant.

## Which general blocks does a florist site place instead?
- Bouquet sizes: `pricing-tiers`. Occasions grid: `services-image-cards`. Seasons and care: `features-grid`. Weddings: `services-*`, gallery blocks. Shop: `locations-cards`, `hours-card`. Words: `testimonial-grid`. Products stay shop widgets.

## Translation choices
- "Hero" is "Giriş". Plan prices reuse the `$40`/`$45` keys and the new totals agree with them (`$480` ₺15.000, `$270` ₺8.400, `$60` ₺2.100, `$180` ₺6.300). "From" is "Kimden", so time layers are Start Time / End Time.
- No sample says "May": the no-month check reads it as a month.

## How is it tested?
- `tests/designer_blocks_florist_test.php` (Node runner `tests/helpers/designer_family_run.php`): shared checks (headings, no fixed colour or template class, `{var}` filled, no year or month, interactions kept); one frame, arch, tuck and card; six occasions; four samples; 36 week squares matching the counts in words; price × count = total; rules with no colour, motion or child selector.
- `tests/designer_blocks_general_test.php` covers `locations-delivery`: rings, keys, fees rising with distance, badges, times.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-overview.md
