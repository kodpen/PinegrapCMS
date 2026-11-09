---
name: design-template-hotel-pages
description: Hotel template 2.0.0: home, rooms and rates on the Hotel blocks: the builders, each page's composition, motion, what left, the test
---

## Which builders draw the blocks?
- Version 2.0.0 (#8325 family, then its pages #8336): home, `rooms` and `rates` place the Hotel blocks ([[designer-blocks-hotel]]); their rules ship through `pg_design_template_user_styles(array('hotel-room', 'hotel-stay'), $hotel_css)`.
- `parts.php` (end): `$fx`, `$rise` (slide-up on view, stagger 80), `$room_showcase($key, $id, $tag, $class, $buttons)` (a sample room of `$rooms`: its three `room-<key>` pictures with the view badge, the kind as eyebrow, the name as `$tag`, the rate from `$room_rates` through `$money()`, `dl.pg-hotel-facts` Guests / Floor Area / Beds / Room view, the first four amenities; default buttons Ask for This Room `book?room=` and All Rooms; an `h1` comes in on load, otherwise on view), `$stay_clock($id, $class)` (`<time>`s from `$hotel` check-in and check-out, the reception's words and `tel:` from `$hotel`).

## What does each page hold?
- Home: the hero (only `h1`; words rise and the photo fades on load), the room showcase (`ht-home-room`, Junior Suite), the featured rooms, the facilities rising, the stay clock (`ht-home-stay`), the offers rising, dining, spa, testimonials, the journal. The figures band left the home; `about` keeps it.
- Rooms: page header, the showcase (`ht-rooms-showcase`, Terrace Suite; its second button goes to the rates, not back to the rooms), the filtered list.
- Rates: the rates table, the board plans rising, the stay clock (`ht-rates-stay`), what every stay includes.
- The room record page, about, dining, spa, location, offers and faq are unchanged.

## How is it tested?
- `test_hotel_assembly_pages_on_hotel_blocks`: version, every `pg-hotel-*` class's rule ships, interactions kept by `pg_fx_normalize()`, something moves and one `h1` on each of the three, no Figures band on the home, the load entrance, the four facts, the two `<time>`s, the `tel:` link, the ask link with the room, the search anchors, the rooms page not linking to itself.

Related: docs/memory/designer/design-template-hotel.md, docs/memory/designer/design-template-own-look.md
