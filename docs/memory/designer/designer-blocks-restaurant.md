---
name: designer-blocks-restaurant
description: The Restaurant block family (restaurant- prefix): evening hero with a slip and a plate, tasting menu card with wines, chef's counter drawing; blocks placed instead; tests
---

## Where does it live?
- `assets/js/style_designer_blocks_restaurant.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `restaurant-`, palette group "Restaurant" (`bi-egg-fried`). Built in `restaurant-1` (PR #8878) for [[design-template-restaurant]]; `restaurant-2` places it.

## Which blocks does it hold?
- `restaurant-hero` (Restaurant: Evening Hero): a dark band (`data-bs-theme="dark"`) with the `h1` in giant type (`pg-display-xl`), two buttons and a slip (`pg-restaurant-slip`, `h2` This evening): dinner's first and last table and the kitchen's close as `<time>`s, walk-in seats. Beside it the signature dish on a round plate (`pg-restaurant-plate`), a `figure` with its caption.
- `restaurant-tasting` (Restaurant: Tasting Menu Card): a printed card (`pg-restaurant-menu`, an inset outline): `h2` Seasons Menu, an `ol` of seven courses (`h3` each) with the wine under each, the menu's and the pairing's price per guest, a booking button and Wine List; a picture each side that drifts on scroll (parallax). On a phone the card comes first, the pictures paired under it.
- `restaurant-counter` (Restaurant: Chef's Counter): `h2`, a line, the evening as an `ol` of four `<time>`s (`pg-restaurant-evening`), "Starting from" a price per guest, two buttons; beside it a drawing (`pg-restaurant-counter`, `aria-hidden`): four kitchen stations as badges, the counter bar, six numbered seats that grow one by one.

## Why are these not reused blocks?
- No general hero has a slip of times beside a dish. `menu-list` and `menu-tabs` hold a menu's sections, not courses in order with their wines; `travel-itinerary` (steps beside a sticky price card), `museum-object` (an annotated picture) and `menu-origins` (sourcing) were not copied. No block draws seats at a counter.

## Which general blocks does a restaurant site place instead?
- Menu: `menu-tabs`, `menu-list`, `menu-cards`, `menu-specials`, `menu-legend` (diet key). Wine list: `menu-board` with Glass and Bottle as the size columns. Private rooms: `locations-spaces`. Events: `events-cards` or `events-list`. Team: `team-profiles`. Hours: `hours-card`, or `hours-seasons` with the sittings as columns. Reserve band: `booking-cta`, `booking-split`. Also `testimonial-grid`, `stats-icons`, `faq-accordion`, `newsletter-split`. Gift vouchers stay catalog widgets.

## How are the drawings made?
- The plate is a round `img` in a `box-shadow` rim; from lg up it is 115% of its column, clipped by the band's `overflow-hidden`.
- The kitchen's warm wash mixes `--bs-primary` with `color-mix()` behind `@supports`; seats and gap use `clamp()` with `vw`, so six seats fit a 320 px phone.
- The course divider is an `li::before` rule, not a sibling selector, as the canvas wraps every node ([[designer-block-style]]). Eyebrows and times use `text-primary-emphasis`; second buttons `btn border link-body-emphasis` ([[designer-block-theme-colours]]).
- The sample is the template's: Seasons Menu at $95, its wines and dishes, six seats.

## Translation choices
- Hero is "Giriş", the slip "Bu akşam", walk-ins "Rezervasyonsuz gelenler", the pass "Pas tezgâhı", pairing "Şarap eşleştirmesi", per guest "kişi başı". Layer names avoid keys translated for another sense ("Plate" = "Plaka", "Courses" = "Kurslar"): Dinner Plate, Course List.

## How is it tested?
- `tests/designer_blocks_restaurant_test.php` (under Node): the shared checks plus no light primary text or outline button; per block words, headings, entrances; seven courses each with its wine; six seats, four stations; times, lists, figure, hidden drawing from the file; rules: theme variables, no `!important`, child selector or fixed colour, `@supports`, `clamp()`.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-overview.md
