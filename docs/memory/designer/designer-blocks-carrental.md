---
name: designer-blocks-carrental
description: The Car Rental block family (carrental- prefix, own palette group): trip hero, class line-up, cover levels, sample quote; the driving car, the excess meter; general blocks placed instead; test
---

## Where does it live?
- `assets/js/style_designer_blocks_carrental.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `carrental-`, palette group "Car Rental" (`bi-key`). Built for the Car Rental template's polishing round ([[design-template-carrental]], `carrental-1`, PR #8669).

## Which blocks does it hold?
- `carrental-hero`: the page's `h1` very large, a line; under it a trip slip (`pg-carrental-slip`, a card) over a 4:3 car picture (`pg-carrental-photo`): Pick-up desk and Return desk with day and `<time>`, a dashed road between them (`pg-carrental-trip`), the class with its model "or similar", the day rate, Book a Car / Our Fleet; three facts. On load the words rise, the picture slides in, the slip rises, the car drives along the road, the facts fade in.
- `carrental-lineup`: an `ol` of six classes (`pg-carrental-lineup`) on a rail that scrolls sideways and snaps (`pg-carrental-rail`, a named region), cheapest first. Each card: 16:9 picture, `h3` class, model "or similar", seats, bags, gearbox, day rate and deposit in a `dl`. The cards come in from the right on view (stagger 90).
- `carrental-cover`: a dark band of three covers (Basic, Plus marked Most chosen, Full): price a day against the excess, the excess drawn as a Bootstrap `.progress` whose bar slides in on view (Full's meter is empty), what each covers; a left-out line is struck through and says "Not covered:" to screen readers.
- `carrental-quote`: words and Book a Car beside a receipt (`pg-carrental-receipt`, a `table` with row headers and a `tfoot`): Mid-size 7 days, Full Cover 7 days, Unlimited mileage, Seven Days for Six, the total and the deposit held. The lines fade in one after another.
- Sample texts are the template's own; the quote uses the line-up's and the cover's figures, so the blocks agree on one page.

## How does the car drive?
- The car icon's node (`pg-carrental-car`) is as wide as the road and holds the icon at its far end; the road clips it (`overflow: hidden`). A `slide-right` on load with no stagger is capped to the node's width, so it starts off the road's left edge and drives to the Return desk. The excess bars use the same trick inside `.progress`.

## Why are these not reused blocks?
- No general block draws a trip from desk to desk, a class guaranteed "or similar" with a deposit on a snapping rail, two opposing amounts (price against excess) with a meter, or a bill that adds up. Not `locations-spaces` (rooms to hire), `launch-specs` (one product's specs), `drivingschool-licences` (licence classes), `pricing-tiers` / `skiresort-passes` (one price per tier).

## Which general blocks does a car rental site place instead?
- Desks: `locations-cards`, `contact-map`, `hours-location`. Pick-up, trip and return: `steps-numbered` or the Hotel family's `hotel-stay`. Terms: `features-grid`, `faq-categorized`. Extras: `services-icon-cards` / `services-price-list`. Business rentals: `pricing-tiers`. Figures: `stats-counter`. Words: `testimonial-grid`. Booking band: `booking-cta` / `cta-inline`. The fleet and the posts stay collection widgets.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_carrental_test.php` runs it through `tests/helpers/designer_family_run.php`: the shared checks (headings, no fixed colour or `pcr-` / `cr-` class, `{var}` filled, no year or month, interactions kept).
- Per block: the slip's desks, six classes in rising day rate, three covers with falling excess, a receipt that adds up from the other blocks' figures; `aria-hidden`, the region, `ol`, `dl` and the table read from the file; rules with no colour, motion or child selector.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
