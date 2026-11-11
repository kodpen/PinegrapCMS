---
name: designer-blocks-general
description: General sections the sector templates share: the existing block that covers each, and the ones added in general family files (hours, locations, booking)
---

## Which block does a template place for a shared section?
- Searched before adding (G1, G2 of `plans/design-template-own-looks-order.md`); a template places these with its own content instead of building a band:
- Feature split with a picture: `features-alt` (alternating rows, `h3` items), `showcase-floating` (one band: badge, `h2`, checklist, buttons, picture; its floating cards can be deleted), `booking-split`.
- Stat band: `stats-counter` (plain figures), `stats-icons` (icon, figure, label on a tinted band), `stats-progress`.
- Process steps: `steps-numbered` (numbered cards), `timeline-horizontal`.
- Plan cards: `pricing-tiers`, `pricing-toggle`; a price per age group is `skiresort-passes`.
- Team grid: `team-grid`, `team-minimal`, `team-profiles`, `team-leadership`.
- Logo wall: `logos-grid`, `logos-divider`, `logos-marquee-static`, `marquee-logos` (moving).
- Timeline: `timeline-vertical`, `timeline-alternating`, `timeline-horizontal`.
- Venue cards: `locations-cards` (branches: address, hours, phone), `hours-location`; spaces to hire: `locations-spaces` (added); a building's floors: `locations-floors` (added with `coworking-1`).
- Opening hours: `hours-card` (one week); by season: `hours-seasons` (added).
- Booking call: `booking-cta`, `booking-split`, `cta-inline`, `cta-simple`.
- Weekly class timetable: `booking-week` (added with `gym-1`); arrival windows: `booking-windows` (added with `homeservices-1`).

## What was added, and where?
- General families in their own files ([[designer-block-family-files]]), each with a core group's prefix and no `group`, so they join the core group ("Hours & Locations", "Services & Booking"); one prefix per file.
- `style_designer_blocks_hours.js` → `hours-seasons`: a `table` in `table-responsive` (named region), a column head per season (icon, name, months, `scope="col"`), a row head per day (`scope="row"`), a closed day in a word; three note cards (last entry, closures, groups) that rise on view. Rule `pg-hours-table` (tabular figures).
- `style_designer_blocks_locations.js` → `locations-spaces`: a card per space (picture `pg-ratio-16x9`, name `h3`, guests, floor area, layout, amenity pills, "From" + price + `/day` as separate nodes, "Ask for a date"); the row rises on view (stagger 120). Rule `pg-space-facts`; it also needs the core `pg-ratio-16x9`.
- `style_designer_blocks_locations.js` → `locations-floors` (added in step `coworking-1`): an `ol` of floors from the roof down, each a level square (`pg-floor-level`, a figure or a word), name `h3`, a line, what is on it as pills, who may go up (key icon and words); the list rises on view (stagger 100). For a coworking house, a hotel, a museum, an arcade.
- `style_designer_blocks_booking.js` → `booking-week`, `booking-windows` (Services & Booking): [[designer-blocks-general-booking]].
- "Area" translates as "Bölge" (a region) in the shared keys, so the fact is named "Floor Area".

## How is it tested?
- `tests/designer_blocks_test.php` checks the files like every family file; `tests/designer_blocks_general_test.php` runs the kit and both files under Node: no own group, a section with one `h2` and no skipped level, no fixed colour class, `{var}` filled, no year, one entrance kept by `_sdFxNormalize()`, the table's head cells and scopes, the cards' facts and pills, the rules in the block stylesheet; the floors, their level squares, pills and access words. The Node runner is `tests/helpers/designer_family_run.php`.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-palette-tabs-and-blocks.md
