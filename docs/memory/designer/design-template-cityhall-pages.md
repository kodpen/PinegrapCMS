---
name: design-template-cityhall-pages
description: City Hall template 2.0.0: home, council and the service-area pages on the City Hall blocks: the builders, each page's composition, motion, what left, the test
---

## Which builders draw the blocks?
- Version 2.0.0 (#8291 family, then its pages): home, `council` and every `service_<key>` place the City Hall blocks ([[designer-blocks-cityhall]]); their rules ship through `pg_design_template_user_styles(array('cityhall-hero', 'cityhall-service', 'cityhall-council'), $cityhall_css)`.
- `parts.php`: `$fx`, `$rise` (slide-up on view, stagger 80), `$agenda` (four items: subject, committee key, status key) and `$agenda_statuses` (icon, word, badge class), `$task_hero($eyebrow, $title, $lead, $tasks)` (the page's only `h1`; a nav named "Common tasks" of tiles `array(icon, title, text, href)`; the citizen line from `$city`; words then tiles rise on load), `$service_guide($key)`, `$council_agenda($id, $buttons, $class)`.

## What does each page hold?
- Home: task hero (`pcy-hero` wash; Report a problem, Book a meeting, Public Notices, Council meetings, Service points, Useful numbers), service areas rising, `home_notices`, the council agenda (`cy-home-council`, tinted), `home_events`, the mayor band, `home_posts`. The quick links, the hero photo, the figures band and the commitments left the home; `city` keeps figures and commitments.
- Council: page header, the council agenda (`cy-council-agenda`; Agenda and Decisions, Book a meeting), members rising, committees.
- A service area: page header, the area's icon and text (`cy-area-<key>`), then the guide (`cy-area-<key>-services`): each service as an article (`h2`, a line, `dl.pg-cityhall-guide` of Where to apply and What to bring rising, a request on `?topic=`), beside the department's sticky aside card fading in. `$service_card` stays for the tests and other uses.
- `services` only lets its area cards rise; mayor, departments, city, service points, numbers and FAQ are unchanged.

## How is it tested?
- `test_cityhall_assembly_pages_on_cityhall_blocks` (`tests/design_template_cityhall_assembly_test.php`): version, every `pg-cityhall-*` class's rule ships, interactions kept by `pg_fx_normalize()`, something moves and one `h1` on each of the three, no Figures, Our Commitments or Quick Links band on the home, the load entrance, six tiles each one link, the named nav, the `tel:` citizen line, statuses in words, four numbered items, a guide per service and the aside.

Related: docs/memory/designer/design-template-cityhall.md, docs/memory/designer/design-template-own-look.md
