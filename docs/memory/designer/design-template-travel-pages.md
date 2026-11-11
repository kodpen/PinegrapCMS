---
name: design-template-travel-pages
description: Travel Agency template 2.0.0: home, tour page and destination page on the Travel blocks, motion on about, travel information, deals and FAQ: the builders, each page's composition, what left, the test
---

## Which builders draw the blocks?
- Version 2.0.0 (`travel-1` #8337 family, then its pages, #8657): the home, the `tour_detail` and `destination_detail` widgets place the Travel blocks ([[designer-blocks-travel]]) and the general `hero-image-bg`; their rules ship through `pg_design_template_user_styles(array('hero-image-bg', 'travel-itinerary', 'travel-destination'), $travel_css)`, ahead of the `ptv-` rules.
- `parts.php` (the tours test reads only parts.php and tours.php, so they live here): `$fx`, `$rise($node, $stagger = 90)` (slide-up on view), `$fade_in` (fade on view, 700 ms, for section headings).
- `pages_public.php`: `$home_hero()` (the only `h1`; photo, dark shade, two buttons, the four regions as pills to `{{page:destinations}}?region=`; words rise on load, regions after a delay).
- `$day_by_day($key, $notes, $start, $id, $topics)`: Travel (Day by Day), `ol.pg-travel-days` beside a sticky Trip at a glance (`dl.pg-travel-glance`, price in `pg-travel-figure`).
- `$destination_guide($key, $alt, $seasons, $facts, $id, $topics)`: Travel (Destination Guide), photo beside `ul.pg-travel-seasons` (`pg-travel-best`), facts, tours and enquiry links with `?destination=`.

## What does each page hold?
- Home: hero, featured tours, the Cappadocia tour day by day (`tv-home-itinerary`; nights and meals only here, `$home_day_notes`), its guide (`tv-home-destination`), featured destinations, deals, testimonials with `google_reviews`, `home_posts`. Figures, Why travel with us and the story band left the home; `about` keeps the first two.
- Tour page: header rises on load, photos fade in, highlights and itinerary fade on view; the sticky aside is Trip at a glance (duration, group size, departures, price in `pg-travel-figure`), the one Book this tour link, the enquiry link, the deposit note.
- Destination page: the cover fades beside country, `h1`, summary, Best time to go and Region (`dl.pg-travel-glance`), the one tours link and the enquiry link; About the place under them.
- About, travel information, deals, FAQ: motion only. The Write to Us band left about and travel information; headers link travel information and the FAQ, and the FAQ ends with a contact box (the public test wants more than eight page links).

## What could the editor not do?
- A multi-line field drawn as a list (one `li` per line), so a tour record's itinerary could take the `pg-travel-days` markers and its highlights a check list: the tour page keeps them as text.
- A per-season judgement on a destination record, so the guide's seasons could be bound: the destination page shows the season field as words.
- A parallax that moves a section's background picture (pg_fx moves the node itself): the hero stands still.

## How is it tested?
- `test_travel_assembly_pages_on_travel_blocks` (`tests/design_template_travel_assembly_test.php`): version, every block class's rule ships, interactions kept by `pg_fx_normalize()`, motion on all seven trees, one `h1` on home, tour, destination, the removed bands, the three block labels, search ids, region links, four days, four seasons (two best), bound price, group size, season.

Related: docs/memory/designer/design-template-travel.md, docs/memory/designer/design-template-own-look.md
