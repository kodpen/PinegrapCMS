---
name: design-template-pharmacy-pages
description: Pharmacy template 2.0.0: home, on duty, health checks and three service pages on the Pharmacy blocks: the builders, each page's composition, what left, no motion, the test
---

## Which builders draw the blocks?
- Version 2.0.0 (#8160 family, then #8181 pages): the pages place the Pharmacy blocks ([[designer-blocks-pharmacy]]), their rule shipped by `pg_design_template_user_styles(array('pharmacy-readings', 'pharmacy-vaccines', 'pharmacy-schedule'), $pharmacy_css)`.
- `pages_public.php`: `$rx_cell`, `$rx_table` (the named, focusable `table-responsive` region with a hidden caption), `$rx_head`, `$rx_fill` (fills a `{var}` key read with `lang()`, so the block's keys are reused), then `$duty_panel($class, $id, $topics)` (answers from `$duty_facts`, what to bring from `$duty_tips`, the emergency line calling `$pharmacy['emergency']`), `$readings_section($class, $id, $topics, $title)`, `$vaccines_section(...)`, `$schedule_section(..., $book_href)`.

## What does each page hold?
- Home: hero, services, reference readings (instead of the check cards), team, duty panel (instead of the duty cards), shelves, customer stories with Google reviews, Health Advice. The figures row left the home (about keeps it).
- On duty: page header, duty panel (`rx-on-duty`), opening hours beside the way here (`rx-duty-hours`). The emergency button in the header, the duty cards and the night window section left.
- Health checks: check cards, readings ("What your figures mean", instead of the three tiles), before your check.
- Service pages: health checks shows the readings, travel health the travel vaccines, medicine review the schedule (its button goes back to the page's own request buttons, `#rx-service-medicine-review`, so the page asks one question on its topic). Each block is a search section `rx-service-<key>-more`.
- `$duty_card`, `$emergency_button` and `$check_tiles` were dropped.

## Why no motion?
- A quiet sector ([[design-template-own-look]]): no `_fx` anywhere; the assembly test holds it.

## How is it tested?
- `test_pharmacy_assembly_pages_on_pharmacy_blocks` (`tests/design_template_pharmacy_assembly_test.php`): each page places its blocks, every `pg-pharmacy-*` rule ships, every head cell has a scope, every table frame is a named region, no figures row or duty cards, the home calls `tel:112`.

Related: docs/memory/designer/design-template-pharmacy.md, docs/memory/designer/designer-blocks-pharmacy.md
