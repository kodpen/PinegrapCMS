---
name: design-template-photo-pages
description: Photography Studio template 2.0.0: home, how it works, packages and about on the Photo blocks: the builders, each page's composition, motion, what left, the test
---

## Which builders draw the blocks?
- Version 2.0.0 (#8232 family, then its pages, #8255): home and `experience` place the Photo blocks ([[designer-blocks-photo]]); their rules ship through `pg_design_template_user_styles(array('photo-hero', 'photo-proofing', 'photo-shot'), $photo_css)`. The `pph-hero` rule left with the old hero.
- `pages_public.php`: `$fx`, `$rise` (slide-up on view with a stagger), `$filmstrip_hero()` (the page's only `h1`, tagline, two buttons, promise, fading in on load; under it the `pg-photo-strip` `ol` sliding in on load, one numbered frame per `$categories` entry, its caption a link to `galleries?category=` in the Category list's words; the list is a named region; search section `ph-categories`), `$contact_sheet($id, $eyebrow, $title, $intro, $topics, $more)` (three steps, "Your selection", client area button plus `$more`; twelve proofs in `pg-photo-sheet`, 04, 07, 11 ringed `pg-photo-picked` with a hidden "Selected"), `$behind_shot($id, $topics)` (one photograph, its story, six settings as `dt`/`dd` in `pg-photo-exif`, link to the Portraits galleries).

## What does each page hold?
- Home: filmstrip hero, `home_galleries`, packages (rise), behind the shot (`ph-home-shot`), contact sheet (`ph-home-clients`, plus a How It Works link), testimonials (rise, header links About) with `google_reviews`, `home_posts`. The figures row, the category tiles (the strip's captions replace them), the how-it-works band and "Why Choose Us" left the home; `about` keeps the figures and why tiles.
- How it works: steps (rise), preparing tiles (rise), contact sheet (`ph-proofing`), delivery story band. Packages: cards and add-ons rise, the add-ons column links the questions. About: values, why tiles and testimonials rise; the story band's button asks to meet (contact). All three ask bands ("Write to Us") left.

## How is it tested?
- `test_photo_assembly_pages_on_photo_blocks` (`tests/design_template_photo_assembly_test.php`): version 2.0.0, every `pg-photo-*` class's rule ships, interactions kept by `pg_fx_normalize()`, something comes in on each of the four pages, at most one `h1`, no ask band (nor figures or why band but on about); on the home six frames, twelve proofs, three picks, six `dt`/`dd`, the load entrance and `ph-home-clients`.

Related: docs/memory/designer/design-template-photo.md, docs/memory/designer/design-template-own-look.md
