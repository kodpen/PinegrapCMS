---
name: design-template-ferry-pages
description: Ferry template 2.0.0: home, routes, route pages, timetables and fares on the Ferry blocks: the builders, each page's composition, motion, what left, the test
---

## Which builders draw the blocks?
- Version 2.0.0 (#8123 family, then its pages): home, `routes`, every `route_<key>`, `timetables` and `fares` place the Ferry blocks ([[designer-blocks-ferry]]), their rules shipped by `pg_design_template_user_styles(array('ferry-departures', 'ferry-timetable', 'ferry-crossings', 'ferry-fares'), $ferry_css)`.
- `parts.php`: `$rise` (the blocks' entrance), `$timetable($key, $title, $class, $extra)` (the `ferry-timetable` section: facts, book link `?route=`, a badge and two direction cards per season), `$fare_table($only)` (the `ferry-fares` table: `foot`/`vehicle`/`extra` row groups, a column per crossing length or the one route, kind names booking `?fare=`, "Not offered" in words). `pages_public.php`: `$fx`, `$departure_board()` (dark, home only, the summer sailings in `$board_sailings` with a status each), `$crossing_strip($header, $id, $topics)`, `$fares_section($title, $id, $topics, $only)`.

## What does each page hold?
- Home: hero (text rises on load), departure board, crossing strip, travel notices, on board, travellers' quotes (a rule, no card) with the Google reviews, News. Routes: crossing strip, fares. A route: intro, timetable ("Sailings by season"), its fares, its ports. Timetables: one timetable section per route (`fy-timetable-<key>`). Fares: the fares section (`fy-fares`). The figures row, `why_us` and the ask band left these pages (about, ports and travel keep theirs); `.pfy-time` and `$sailing_times` were dropped.

## How is it tested?
- `test_ferry_assembly_pages_on_ferry_blocks` (`tests/design_template_ferry_assembly_test.php`): every `pg-ferry-*` class's rule ships, interactions kept by `pg_fx_normalize()`, something rises on each page, no figures row, ask band or accordion, the dark board and the hero's load entrance on the home. `test_ferry_tables` (`design_template_ferry_test.php`): row groups, a fare per kind and crossing length, "Not offered", every departure a `<time>`.

Related: docs/memory/designer/design-template-ferry.md, docs/memory/designer/design-template-own-look.md
