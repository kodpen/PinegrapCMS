---
name: design-template-school-pages
description: School template 2.0.0: home, programmes and the day page on the School blocks: the builders, each page's composition, motion, what left, the test
---

## Which builders draw the blocks?
- Version 2.0.0 (#8233 family, then its pages, #8257): home, `programmes` and `day` place the School blocks ([[designer-blocks-school]]); their rules ship through `pg_design_template_user_styles(array('school-hero', 'school-ages', 'school-year'), $school_css)`.
- `pages_public.php`: `$fx`, `$rise` (slide-up on view with a stagger), `$programme_span` (each programme's ages on the scale and whether it is on the main path), `$school_hero()` (the page's only `h1`, `$school['tagline']`, Apply and Visit, the `nav.pg-school-finder` linking every `programme_<key>`, the open morning card from `$events['open_morning']`; words rise and picture fades on load), `$age_ladder($id, $title, $lead, $class, $more)` (from `$programmes` and `$classes`, each card linking its programme page), `$school_year($id, $class)` (three terms, two holidays, the foot linking `programme_summer`).

## What does each page hold?
- Home: school hero, age ladder (`sh-home-programmes`, with an All Programmes button), the day (list rises, picture fades), `home_events`, school year (`sh-home-year`), testimonials with `google_reviews`, `home_posts`. The figures band and "Why Families Choose Us" left the home; `about` keeps both.
- Programmes: page header, age ladder (`sh-programmes-ages`, the class groups under it), programme cards rising (`sh-programmes`). The class tiles and the ask band left.
- Day: the day list rising, then the school year (`sh-day-year`). The class tiles and the ask band left.
- Programme pages: their class tiles rise. Approach, campus, staff, about, fees and FAQ are unchanged.

## How is it tested?
- `test_school_assembly_pages_on_school_blocks` (`tests/design_template_school_assembly_test.php`): every `pg-school-*` class's rule ships, interactions kept by `pg_fx_normalize()`, something rises on each of the three pages, no "Write to Us", "Figures" or "Why Families Choose Us" band, one `h1`, the finder is a `nav` opening all five programme pages, the load entrance, both search ids, three terms and two holidays.

Related: docs/memory/designer/design-template-school.md, docs/memory/designer/design-template-own-look.md
