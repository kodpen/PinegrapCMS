---
name: designer-blocks-overview
description: Start file for the designer-blocks-* files: what a block family is, the rule that binds it, every family by its palette group label, which file holds what, how a new family is added
---

## What is a block family?
- A set of ready sections in the visual designer's Blocks palette, one `componentType` prefix each (`zoo-today`, `zoo-map`). A dropped block is ordinary nodes the editor can change; its CSS rules (`pg-zoo-*`) reach the site through `pg_designer_block_css()`.
- Since step B0 a family lives in its own file, `assets/js/style_designer_blocks_<family>.js`, one `StyleDesigner.registerBlockFamily()` call; the older families (news, creative, maintenance, local business…) stay in `style_designer.src.js` (`designer-block-family-files.md`).

## Which decision binds it?
- Owner, 2026-11-07 (`workflow/decisions-made-product-37.md`, `design-template-own-look.md`): a design template builds the sector sections the palette lacks as a block family first, reuses an existing block instead of a copy, and puts a general section in a general group. Each family file therefore has a "reused instead" list: read it before adding a block.
- Design template plans are paused (`workflow/coordinator-no-plans.md`): no new family is started for a template now.

## Which families are there?
- Own palette group, by its label: App Studio (`appstudio-`), Architecture, Bakery, Bike Shop, Brewery, Campsite, Car Rental, City Hall, Clinic, Construction, Coworking, Driving School, Ferry, Film Studio (`filmstudio-`), Florist.
- Own palette group, continued: Gym, Home Services (`homeservices-`), Hotel, Library, Product Launch (`launch-`), Marina, Museum, Music, Pharmacy, Photo, Planetarium, Podcast, Restaurant, School, Ski Resort, Sports Club, Travel, Wedding, Zoo. File `designer-blocks-<prefix>.md`, the prefix without spaces (`drivingschool-`, `skiresort-`, `sportsclub-`, `cityhall-`, `bikeshop-`, `carrental-`).
- General sections in core groups: `hours-seasons`, `locations-spaces`, `locations-floors`, `locations-zones` (Hours & Locations) and `booking-week`, `booking-windows` (Services & Booking): `designer-blocks-general.md`, which also lists the existing block for each shared section (feature split, stats, steps, pricing, team, logos, timeline, venues, hours, booking call).
- In the core file: Maintenance & Launch, News & Magazine, Creative: `designer-blocks-news-maintenance-creative.md`.

## Which file holds what?
- A family file: its source file and group, each block and what it draws, the blocks placed instead and why a near one is not reused, its `pg-*` rules, motion, translation choices, its test.
- How every block is built: `designer-block-markup.md`, `-style.md`, `-theme-colours.md`, `-theme-surfaces.md`, `-sample-content.md`, `-forms.md`, `-motion.md`; palette groups and tabs: `designer-palette-tabs-and-blocks.md`; component rules: `palette-component-rules.md`.
- The template that places a family: `design-template-<prefix>.md`.

## How is a new family added?
1. Search the palette and the family files' "reused instead" lists; add only what no block covers.
2. Copy `tests/fixtures/designer/style_designer_blocks_sample.js` to `assets/js/style_designer_blocks_<family>.js`: `prefix`, `group: { title: _sdT('…'), icon }` (left out for a general family), `items`, `build(ctype)`, the `_SD_BLOCK_CSS` and `_SD_BLOCK_CSS_NEEDS` text in the shape the PHP reader parses (`designer-block-family-files.md`). Neither the core nor `output.php` changes.
3. Labels as single-quoted `_sdT('…')`, Turkish with `php tools/tr_add.php`; site output stays on Bootstrap 5.3.8; colours only from theme classes, no `!important`, no year in the sample.
4. `php tools/build_js.php`; a Node test `tests/designer_blocks_<family>_test.php` through `tests/helpers/designer_family_run.php`; `tests/designer_blocks_test.php` and `designer_block_families_test.php` check every file.
5. Write `designer-blocks-<prefix>.md` and add the family to this file.
