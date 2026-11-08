---
name: designer-blocks-hotel
description: The Hotel block family (hotel- prefix, own palette group): room showcase, stay clock; general blocks placed instead; rules, test
---

## Where does it live?
- `assets/js/style_designer_blocks_hotel.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `hotel-`, palette group "Hotel" (`bi-buildings`). Built for the Hotel template's polishing round ([[design-template-hotel]], `plans/design-template-own-looks-order.md`, `hotel-1`); usable by any hotel, guest house, inn or holiday flat.

## Which blocks does it hold?
- `hotel-room`: one room as a guest weighs it. A `figure.pg-hotel-gallery` of three pictures (`pg-hotel-shot`; the first `pg-hotel-main` spans two rows from md up) with the view as an icon-and-word badge over it; beside it eyebrow (the kind of room), `h2` name, a line, the rate as "Starting from" + amount + "/ night" in separate nodes (`pg-hotel-figure`), a `dl.pg-hotel-facts` (Guests, Floor Area, Beds, Room view), `h3` "In the room" with four amenities, Ask for This Room / All Rooms. Pictures fade, facts rise (stagger 80) on view.
- `hotel-stay`: header (eyebrow, `h2`, a line), then a row `pg-hotel-clock` of three `pg-hotel-end` cards joined by a dashed line from lg up: Arrival (`h3`, `<time datetime>` 15:00, early arrival and transfer notes), During your stay (reception, breakfast hours, towels, Wi-Fi, parking), Departure (11:00, late check-out and bags); under it the reception with a `tel:` link. Header fades, cards rise (stagger 120) on view.

## Why are these not reused blocks?
- The showcase is not `services-image-cards` / `showcase-floating` / `locations-spaces`: one room in depth with a picture grid, its facts as a description list, the rate a night and its amenities. The clock is not `hours-card` / `timeline-horizontal` / `marina-arrival`: two times of a stay joined on one line with what a guest can ask for around each.

## Which general blocks does a hotel site place instead?
- Kinds of room: `services-icon-cards`. Board plans and offers: `pricing-tiers`. Spa treatments: `services-price-list`. Restaurant and bar: `services-icon-cards` / `menu-*`. Spaces to hire: `locations-spaces`. Floors: `locations-floors`. Facilities: `features-grid`. Figures: `stats-icons`. Guests' words: `testimonial-grid`. Getting here: `contact-map`, `features-list`. Request call: `booking-split`, `cta-inline`. Questions: `faq-categorized`. Rooms stay a collection list widget.

## What do its rules and texts follow?
- Rules `pg-hotel-gallery` (grid columns in one `@media (min-width: 768px)`), `pg-hotel-facts`, `pg-hotel-figure` (tabular figures), `pg-hotel-clock`; each colours with a theme variable. Sample texts reuse the template's keys (Junior Suite, amenities, breakfast hours, check-in and check-out times); no year or month. "View" reads as a verb in Turkish, so the fact is "Room view"; "From" reads "Kimden", so the rate says "Starting from".

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_hotel_test.php` runs it through `tests/helpers/designer_family_run.php`: own group, a section per block with one `h2` and only `h3` under it, no fixed colour or `pht-`/`ht-` class, `{var}` filled, no year or month, entrances kept by `_sdFxNormalize()`; three pictures and the view badge, the facts and rate nodes, three ends with their times; the `figure`, `dl`, `<time>` and `tel:` read from the file; the rules through `_pg_designer_block_css_parse()`.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
