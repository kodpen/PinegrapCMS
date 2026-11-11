---
name: design-template-travel-pages
description: Travel Agency template 2.0.0: home, tour and destination pages on the Travel blocks, motion on about, travel information and deals: the builders, each page's composition, what left, the test
---

## Which builders draw the blocks?
- Version 2.0.0 (`travel-1` #8337 family, then its pages): the home, the tour page and the destination page place the Travel blocks ([[designer-blocks-travel]]) and the general `hero-image-bg`; their rules ship through `pg_design_template_user_styles(array('hero-image-bg', 'travel-itinerary', 'travel-destination'), $travel_css)`.

## What does each page hold?
- Home: a photo hero with the regions as a route, the destination guide, the featured tours, a tour day by day, the featured destinations, deals, testimonials with `google_reviews`, `home_posts`.

## How is it tested?
- `test_travel_assembly_pages_on_travel_blocks` (`tests/design_template_travel_assembly_test.php`).

Related: docs/memory/designer/design-template-travel.md, docs/memory/designer/design-template-own-look.md
