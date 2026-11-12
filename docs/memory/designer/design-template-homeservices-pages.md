---
name: design-template-homeservices-pages
description: Home Services 2.0.0: home on the Home Services blocks and Arrival Windows, service areas on the Zones block, motion elsewhere; builders, data, limits, test
---

## Which builders draw the blocks?
- Version 2.0.0 (`homeservices-1` #8773 made the family, `homeservices-2` the pages): the home places the Hero with Job Sheet and the Emergency Line ([[designer-blocks-homeservices]]) and the general Booking: Arrival Windows ([[designer-blocks-general-booking]]); the service areas place the general Locations: Zones (PR #8784).
- Their rules ship through `pg_design_template_user_styles(array('homeservices-jobsheet', 'homeservices-emergency', 'booking-windows', 'locations-zones'), $homeservices_css)`, ahead of the `phs-` rules.
- `parts.php`: `$time` (a `<time datetime>`), `$fx`, `$rise($node, $stagger = 90)` (slide-up on view), `$fade_in` (fade on view, 700 ms, for section headings) and `$arrival_windows` (morning, afternoon, evening: icon, hours, words, days, tag).
- `pages_public.php`: `$jobsheet_hero()` (the only `h1` of the home), `$windows_band()`, `$emergency_band()` (`data-bs-theme="dark"`), `$zones_band()`. Each mirrors its block's tree and classes; headings wear `phs-display`, eyebrows `phs-eyebrow`.

## What does each page hold?
- Home: job sheet hero, categories (`hs-home-categories`), arrival windows (`hs-home-windows`), Featured Services, emergency line (`hs-home-emergency`), team, testimonials with Google reviews, tips. Figures, How It Works, Why Choose Us and the areas sentence left the home.
- Service areas: the zones (`hs-call-out`), then the area cards (`hs-areas`) under a heading, then Outside these areas? with Get an Estimate.
- Prices: each category's table rises on view (`hs-prices-<category>`); How It Works (`hs-how-it-works`) moved here from the home.
- About, questions, reviews: rows rise, section headings fade in.

## Why this data?
- Job sheet: the Leak and Tap Repair in the first window of the visit form, at its price "agreed before we start", stamped with the second visit status (Confirmed), the first team member coming; job number `#0427` is a sample.
- Arrival windows are the visit form's `$time_windows` (same order, names and hours): the test fails if the two drift apart. Why Choose Us said "two-hour window"; it now says "An arrival window you choose".
- Zones: 1 the core areas (no call-out charge), 2 the outer areas with `$money($call_out)` on their visit days, 3 further afield by estimate. Every name comes from `$areas`.
- The emergency line calls `$company['emergency']`; the chips leave out Locked out (the company is no locksmith).

## What could the editor not do?
- Windows, zones and the job sheet are page content written from parts.php's data; a changed window or charge is not seen on a site already made.
- The job sheet's scale and its ticks are load effects: on the canvas they show only with Play.
- The section label `Booking (Arrival Windows)` is the palette's name, Turkish "Rezervasyon …": the template's word rule exempts that one block name (`test_homeservices_public_turkish_words`); visitor text still never says "rezervasyon".

## How is it tested?
- `test_homeservices_assembly_pages_on_homeservices_blocks` (`tests/design_template_homeservices_assembly_test.php`): version, every block class's rule ships, interactions kept by `pg_fx_normalize()`, one `h1` per page, block labels, search ids, motion on home, areas and prices, the sheet's facts, a window card per form window with `datetime`s, the dark band's `tel:` link and four safety steps, three `aria-hidden` rings and keys, every area in the zones with the charge where it applies.

Related: [[design-template-homeservices-fit]] (header, price tables, dark bands), docs/memory/designer/design-template-homeservices.md, docs/memory/designer/design-template-own-look.md
