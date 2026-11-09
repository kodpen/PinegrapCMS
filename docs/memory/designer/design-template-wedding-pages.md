---
name: design-template-wedding-pages
description: Wedding template 2.0.0: home, timeline, packages and venue on the Wedding blocks: the builders, each page's composition, motion, what left, the test
---

## Which builders draw the blocks?
- Version 2.0.0 (#8167 family, then its pages, #8196): home and `timeline` place the Wedding blocks ([[designer-blocks-wedding]]); their rules ship through `pg_design_template_user_styles(array('wedding-hero', 'wedding-story', 'wedding-checklist'), $wedding_css)`.
- `pages_public.php`: `$fx`, `$rise` (slide-up on view with a stagger), `$ornament($class)` (`pg-wedding-rule`, `aria-hidden`), `$invitation_hero()` (wide `ratio-21x9` photo fading in on load, `pg-wedding-invite` card rising on load: the page's only `h1`, `$planner['tagline']`, three facts as `dt`/`dd`, visit and packages buttons), `$wedding_story($key, $id, $topics)` (one sample of `$weddings` in the `wedding-story` structure: `pg-wedding-mosaic` of its `photo_1`-`photo_3` seeds, facts, quote, suppliers as `dt`/`dd`, link to `weddings`).
- `parts.php`: `$timeline_stage($n)` draws a `wedding-checklist` stage card (`li.col`, label "Timeline Stage" kept for the tests).

## What does each page hold?
- Home: invitation hero, services (rise), the Selin and Emre story (`wd-home-story`), `home_weddings`, spaces (rise), packages (rise), testimonials with `google_reviews`, `home_posts`. The figures row, "Why Choose Us" and the timeline story band left the home; `about` keeps the figures and why tiles.
- Timeline: page header, then the checklist section (`wd-timeline`): header with the ornament, the six stages rising (stagger 100), the foot linking `services`. The old vertical line list and the `wd-timeline-help` story band left.
- Packages: cards rise; its ask band left (each price row already asks). Venue: spaces and the gallery preview rise; its estate band stays.
- `weddings` keeps its filter bar and list (the record is the signature page, drawn by `wedding_detail`).

## How is it tested?
- `test_wedding_assembly_pages_on_wedding_blocks` (`tests/design_template_wedding_assembly_test.php`): every `pg-wedding-*` class's rule ships, interactions kept by `pg_fx_normalize()`, something rises on each of the four pages, no "Write to Us", "Figures" or "Why Choose Us" band, one `h1`, `dt`/`dd` pairs, the mosaic's three pictures, the load entrance and `wd-home-story` on the home.

Related: docs/memory/designer/design-template-wedding.md, docs/memory/designer/design-template-own-look.md
