---
name: design-template-carrental-pages
description: Car Rental template 2.0.0: home and extras on the Car Rental blocks, motion on the other public pages: the builders, each page's composition, the cover data, what could not be done, the test
---

## Which builders draw the blocks?
- Version 2.0.0 (`carrental-1` #8669 family, then its pages, `carrental-2`): the home and the extras page place the Car Rental blocks ([[designer-blocks-carrental]]); their rules ship through `pg_design_template_user_styles(array('carrental-hero', 'carrental-lineup', 'carrental-cover', 'carrental-quote'), $carrental_css)`, ahead of the `pcr-` rules.
- `parts.php`: `$fx`, `$rise($node, $stagger = 90)` (slide-up on view), `$fade_in` (fade on view, 700 ms, for section headings), `$times($prices, $n)`, `$covers` (basic, plus, full), and for the tile `$tick`, `$model_line`, `$day_rate`, `$class_car`. `$class_tile` is now the line-up's card (picture, class, model "or similar", seats, bags, gearbox, day rate, deposit); `$class_rate` is gone.
- `pages_public.php`: `$hero()` (the only `h1`), `$lineup()` (classes cheapest first), `$cover_band($more, $extra)` (dark band; `$more` adds All extras, `$extra` the search anchor), `$quote_band()`.

## What does each page hold?
- Home: hero, line-up (`cr-home-classes`), sample quote (`cr-home-quote`), Featured Cars, cover levels with All extras, desks, deals, testimonials with Google reviews, the journal, the booking band. Figures, How It Works and the extras grid left the home.
- Extras: the cover levels first (`cr-cover`), then the extras (`cr-extras`) under a heading, Good to Know.
- Locations, deals, terms, about: rows rise on view, section headings fade in; the FAQ's accordion fades in. No sideways slide on page rows: only the hero (`overflow-hidden`) and the rail (it scrolls) move sideways.

## Why these covers and figures?
- Full Cover is the bookable extra (`$extras['cover']`), so it is marked Most chosen on the template (the block marks Plus): Basic comes with every rental, Plus is asked for in the request's notes or at the desk.
- Full Cover halves the class's deposit (`'deposit' => 0.5`), as the FAQ promises a smaller one; the fourth cover line says so. "Roadside help" was not used: the template sells roadside assistance as a car feature.
- The quote: Mid-size 7 days + Full Cover 7 days − one day (Seven Days for Six), each currency worked out from the data.

## What could the editor not do?
- Covers and the quote are page content written from the data, edited by hand; a changed rate in `$classes` is not seen on a site already made.
- The car's drive is a load effect: on the canvas it shows only with Play.
- Rail cards carry no `pcr-lift`: the rail's overflow would clip the lift.

## How is it tested?
- `test_carrental_assembly_pages_on_carrental_blocks` (`tests/design_template_carrental_assembly_test.php`): version, every block class's rule ships, interactions kept by `pg_fx_normalize()`, one `h1`, the block labels, search ids, motion on both pages, three covers with falling excess and one chosen, the road `aria-hidden`, the line-up cheapest first with rate and deposit, a receipt that adds up.
- `design_template_carrental_test.php` reads each tile's words for its rate and deposit.

Related: docs/memory/designer/design-template-carrental.md, docs/memory/designer/design-template-own-look.md
