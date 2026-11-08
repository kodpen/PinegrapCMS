---
name: design-template-campsite-pages
description: Campsite template 2.0.0: home, places to stay and the tent pitches page on the Campsite blocks: the builders, each page's composition, motion, the test
---

## Which builders draw the blocks?
- Version 2.0.0 (#8269 family, then its pages): home, `stays` and `stay_tent` place the Campsite blocks ([[designer-blocks-campsite]]); their rules ship through `pg_design_template_user_styles(array('campsite-hero', 'campsite-comfort', 'campsite-pitch'), $campsite_css)`.
- `parts.php`: `$fx`, `$rise` (slide-up on view, stagger 80), `$camp_hero($eyebrow, $title, $lead, $src, $alt)` (the page's only `h1`; the chooser's tiles link each `stay_<key>`, the facts are `$park['arrival']` and `$park['opening']`; picture fades, card rises, on load), `$comfort_scale($id, $class)` (reads each kind's `bring` and `ready` lists in `$kinds`), `$pitch_drawing($id, $more, $class)`. `$feature_section` in `pages_public.php` takes `$moving` to make its cards rise.

## What does each page hold?
- Home: campsite hero, the comfort scale (`cp-home-stays`), featured units, why guests come back rising, activities, testimonials rising with `google_reviews`, `home_posts`. The figures band and the kind cards left the home; `about` keeps the figures.
- Places to stay: page header, kind cards rising (`cp-stays`), the comfort scale (`cp-stays-comfort`), prices, ask band.
- Tent pitches: after the kind's section, the pitch drawing (`cp-stay-tent-pitch`); the other kind pages are unchanged. The kind page's units link stays the only one (a test counts it).
- Facilities, activities, prices, location, rules, about and FAQ are unchanged.

## How is it tested?
- `test_campsite_assembly_pages_on_campsite_blocks` (`tests/design_template_campsite_assembly_test.php`): every `pg-campsite-*` class's rule ships, interactions kept by `pg_fx_normalize()`, something moves and one `h1` on each of the three, no Figures band, the load entrance, a link to each kind's page and to `book`, four stops, eight markers on the tent page.

Related: docs/memory/designer/design-template-campsite.md, docs/memory/designer/design-template-own-look.md
