---
name: design-template-museum-pages
description: Museum template 2.0.0: home, exhibitions and collection on the Museum blocks: the builders, each page's composition, motion, what left, the test
---

## Which builders draw the blocks?
- Version 2.0.0 (#8306 family, then its pages): home, `exhibitions` and `collection` place the Museum blocks ([[designer-blocks-museum]]); their rules ship through `pg_design_template_user_styles(array('museum-exhibition', 'museum-object', 'museum-trail'), $museum_css)`.
- `parts.php` (end): `$fx`, `$rise` (slide-up on view, stagger 80), `$exhibition_hero($key, $others, $tag, $id, $buttons, $class)` (an exhibition of `$exhibitions`: its state as eyebrow, the title as `$tag`, `dl.pg-museum-facts` When / Where / Curator / Admission (the last of its `details`), the buttons, the poster; "Also on" only when `$others` is not empty), `$look_details` (three close-ups per work), `$look_closer($key, $id)` (wall label from `$objects`, numbered details, a button to the work's department), `$highlights_trail($keys, $id, $class)` (stops from `$objects`, the room is its `gallery`; Highlights Tour opens `events?kind=` Guided Tours).

## What does each page hold?
- Home: the exhibition hero (`mz-home-exhibition`, `pmz-hero` wash, the only `h1`; `light` with `clay` and `faces` also on; Book Tickets, Plan Your Visit), `home_exhibitions`, the highlights trail (`mz-home-trail`; coin, harbour, dancer, tile), the visit band, events, learning, testimonials, news. The old photo hero, the figures band and the department cards left the home; `about` keeps the figures, `collection` the departments.
- Exhibitions: page header, the exhibition hero as `h2` (`mz-exhibition-now`, no "Also on": the list under it holds the others; Book Tickets, Become a Member), then the list.
- Collection: page header, look closer (`mz-collection-closer`, Harbour at Dusk), the featured widget, the department cards rising, the list.
- The visit, support, about, faq and record pages are unchanged.

## How is it tested?
- `test_museum_assembly_pages_on_museum_blocks`: version, every `pg-museum-*` class's rule ships, interactions kept by `pg_fx_normalize()`, something moves and one `h1` on each of the three, no Figures band on the home, the load entrance, the four facts, "Also on" on the home only, the trail anchor, three numbered details, the wall label as caption.
- `test_museum_public_home_trail`: four stops in walking order with their rooms, no department card on the home. The objects filter test counts the look-closer's department link.

Related: docs/memory/designer/design-template-museum.md, docs/memory/designer/design-template-own-look.md
