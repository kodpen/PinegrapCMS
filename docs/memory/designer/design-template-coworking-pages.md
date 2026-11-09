---
name: design-template-coworking-pages
description: Coworking template 2.0.0: home, plans and amenities on the palette blocks (workspace hero, spaces to hire, floor directory, alternating features), builders, motion, tests
---

## How are home, plans and amenities built?
- On palette blocks (`coworking-2` of `plans/design-template-own-looks-order.md`, version 2.0.0): `pages_public.php` builds them with the blocks' structure and classes, their rules shipped by `pg_design_template_user_styles(array('coworking-hero', 'locations-spaces', 'locations-floors'), …)`, motion as `props._fx` (`$fx`). Blocks: [[designer-blocks-coworking]], [[designer-blocks-general]].
- `$cw_hero()`: the h1, the hub's line and "How do you work?", a way in per kind of `$kinds` (five: its `$way_lines` line, the lowest price of its spaces from `$kind_from`, the unit from `$unit_words`, opening `spaces?kind=<title>`), the free day and plans links, a two-picture mosaic; text and ways slide up on load, the mosaic fades in. Its wrapper is the `cw-home-kinds` search section; the hero keeps `pcw-hero`'s wash.
- `$space_cards($kinds, $action, $href, $id, $topics, $title, $lead)`: Spaces to Hire cards from `$spaces` (seats, floor, kind as facts; features as pills; "Starting at" + `$money()` + unit apart), the row revealed with stagger. Home: meeting rooms and the hall (book online); plans: offices (office enquiry) and meeting rooms.
- `$floor_directory()`: the three `$floors` from the top down with `$floor_notes` (level, line, who may go up) and the spaces on each as pills (`cw-floors`), on the amenities page.
- Amenities: the amenity grid (stagger), three alternating rows (Features: Alternating structure; each row the old `cw-coffee`, `cw-terrace`, `cw-address` search section, revealed on view), the floor directory.

## What left?
- From home: the figure row, the kind cards, the plan cards and the meeting-room story band. From plans: `$price_list` (dropped). From amenities: the three story bands. `$story_band`, `$figure_row` and `$kind_cards_row` stay for the about page, which stays plain.
- `Starting at` ("Başlangıç") replaces the shared `From`, which reads "Kimden".

## How is it tested?
- `test_coworking_assembly_signature_pages_on_blocks`: every `pg-` class's rule ships, interactions kept by `pg_fx_normalize()`, the motion of each page, five ways in, three floor levels. `test_coworking_public_plans_and_kinds` reads each office's and room's figure on the plans page.

Related: docs/memory/designer/design-template-coworking.md
