---
name: design-template-marina-pages
description: Marina template 2.0.0: home, berths, the kind pages and arriving on the Marina blocks: the builders, each page's composition, motion, what left, the test
---

## Which builders draw the blocks?
- Version 2.0.0 (#8159 family, #8185 pages): home, `berths` and `arriving` place the Marina blocks ([[designer-blocks-marina]]), their rules shipped by `pg_design_template_user_styles(array('marina-pontoons', 'marina-arrival'), $marina_css)`.
- `parts.php` holds the data: `$pontoons` (A to D: name, use, berths, length range, largest boat, depth, amenities; the berths add up to the 420 of `$figures`), `$pontoon_amenities`, `$harbour['channel']`.
- `pages_public.php`: `$fx`, `$rise` (the blocks' entrance), `$metres`, `$pontoon_plan($id, $title, $text, $more_text, $more_href, $topics)` (the `marina-pontoons` section), `$arrival_band($id, $topics, $more)` (the dark `marina-arrival` band; `$more` goes under the tiles).

## What does each page hold?
- Home: hero (text rises on load), pontoon plan, arrival band, charter boats, harbour services, events, crews' quotes with the Google reviews, News. The figures band and "Why crews come back" left the home (about keeps them).
- Berths: the kind cards (rise on view), pontoon plan, rates; the ask band left. Each `berth_<key>`: its points rise on view. Arriving: arrival band (office hours and the map link under its tiles), pontoon plan, pilotage, directions; the harbour-plan picture and the old "By Sea" column left.

## How is it tested?
- `test_marina_assembly_pages_on_marina_blocks` (`tests/design_template_marina_assembly_test.php`): every `pg-marina-*` class's rule ships, interactions kept by `pg_fx_normalize()`, something rises on each of the eight pages, the block anchors placed, no figures row, ask band or accordion, the dark band and the hero's load entrance on the home, four pontoons.

Related: docs/memory/designer/design-template-marina.md, docs/memory/designer/design-template-own-look.md
