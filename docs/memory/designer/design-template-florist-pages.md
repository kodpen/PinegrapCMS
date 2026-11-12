---
name: design-template-florist-pages
description: Florist template 2.0.0: home, occasions, subscriptions and delivery on the Florist blocks and Locations: Zones, motion elsewhere: builders, each page, limits, the test
---

## Which builders draw the blocks?
- Version 2.0.0 (`florist-1` #8763, `locations-zones` #8784, then its pages, #8820): the pages place `florist-hero`, `florist-card`, `florist-subscription` and `locations-zones` ([[designer-blocks-florist]]); their rules ship through `pg_design_template_user_styles(array('florist-hero', 'florist-card', 'florist-subscription', 'locations-zones'), $florist_css)`, ahead of the `pfl-` rules.
- `parts.php`: `$fx`, `$rise($node, $stagger = 90)` (slide-up on view), `$fade_in` (fade on view, 700 ms); `$plan_card` is the block's plan card (week strip, price per delivery, three months' total, includes, one Start link); `$zone_card` is the block's zone row (key, title, fee, text, Same-day / Next-day badge); `$zone_rings` the ring look per zone. `$plan_price_text` and `$zone_fee_text` are gone.
- `$slots` entries hold `name`, `from`, `to` beside `title`: the title stays the order form's stored answer.
- `pages_public.php`: `$header_in()` (a section header that fades in), `$note()` (the gift card), `$hero()` (the only `h1`), `$subscription_band($header, $props)`, `$card_band($props)`, `$zones_band()`.

## What does each page hold?
- Home: hero (six occasions as links to the order request with `?occasion=`), featured bouquets, subscriptions, card band, weddings with Our Work, Why Choose Us (`fl-why-us`), testimonials with Google reviews, the journal. Figures, the occasion grid and In Season left the home.
- Occasions: the occasion cards rise, then the card band (`fl-card-message`). Subscriptions: the plans (`fl-plans`), how it works, the ask band.
- Delivery: the zones band (`fl-zones`: rings, zone list, time slots, cut-off), then When it arrives (`fl-delivery-times`).
- Bouquets, events, care, about: rows rise on view, section headings fade in; the FAQ's accordion fades in. Only the hero and the card band are `overflow-hidden`; rows are `g-4 g-lg-5` so nothing runs past a phone screen.

## Why do the pages differ from the blocks?
- The card band's second fact is "Tucked into the flowers", not the block's 200 characters: the order form's card message has no length limit.
- The hero's buttons are Order Flowers / See the Bouquets (the order request is the template's way to order); the cut-off is the `<time>` alone, the sentence stays on the delivery and FAQ pages.
- The zones section carries the block's label, Locations (Zones); the zone cards and plan cards keep the template's labels (Delivery Area Card, Plan Card).

## What could the editor not do?
- Plans, zones, fees and slots are page content written from the data, edited by hand; a changed price in `$plan_prices` is not seen on a site already made.
- The hero moves on load: on the canvas it shows only with Play.

## How is it tested?
- `test_florist_assembly_pages_on_florist_blocks` (`tests/design_template_florist_assembly_test.php`): version, every block class's rule ships, interactions kept by `pg_fx_normalize()`, one `h1`, the block labels, motion on each page, twelve weeks per plan with a flower per delivery and `aria-hidden`, a ring and a key per zone.
- `design_template_florist_public_test.php`: each occasion once as a request link on the home, the slots' names and hours, the cut-off `<time>` on the home.

Related: docs/memory/designer/design-template-florist.md, docs/memory/designer/design-template-own-look.md
