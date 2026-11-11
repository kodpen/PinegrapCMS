---
name: designer-blocks-homeservices
description: The Home Services block family (homeservices- prefix, own palette group): hero with a job sheet, emergency line; the general arrival windows; blocks placed instead; test
---

## Where does it live?
- `assets/js/style_designer_blocks_homeservices.js` ([[designer-block-family-files]]); prefix `homeservices-`, group "Home Services" (`bi-tools`). Built for `homeservices-1` of `plans/design-template-own-looks-order.md` ([[design-template-homeservices]]); any cleaning, plumbing, electrical or repair company places it.
- The arrival windows are general: `booking-windows` in `style_designer_blocks_booking.js` ([[designer-blocks-general-booking]]).

## Which blocks does it hold?
- `homeservices-jobsheet`: eyebrow, `h1`, line, Book a Visit and Get a Free Estimate (`btn-outline-primary`, a light band), the emergency line as a `tel:` link. Beside it the job sheet (`pg-homeservices-sheet`): a paper with a primary stripe on a tinted second sheet turned 2°.
- On the sheet: "Job sheet" and `#0427` (monospace), the job as `h2`, a "Confirmed" stamp (turned -6°, success colour), a `dl.pg-homeservices-facts` under a dashed line (Arrival time, Where, Price, Guarantee), "We bring" (`h3`) with three ticks, and the technician's picture, name and role.
- On load the words rise, the sheet grows, the ticks follow one by one (stagger 150).
- `homeservices-emergency`: dark band (`data-bs-theme="dark"`, no outline button). Eyebrow, `h2` "Burst pipe, no power, locked out?", line, then the phone mark (`pg-homeservices-call`, `text-bg-danger`, `aria-hidden`) beside the number as a `tel:` link.
- The mark rings three times on load and rests; none under reduced motion.
- Below the number: three promises, "We come out for" (`h3`) with seven jobs as badges, and a "Not urgent? Book a visit instead" link.
- Its card "Before we arrive" (`h3`): an `ol.pg-homeservices-safety` of four steps (water, electrics, bucket, photos) with numbers in the warning tint, then a gas warning on `bg-warning-subtle`. On view the words rise, the card and its steps follow.

## Why are these not reused blocks?
- The hero is not `hero-split`: its second half is a visit's facts as a `dl`, not a picture. It is not `architecture-sheet` either, whose facts sit beside a photograph with no stamp or ticks.
- The emergency band is not `booking-cta` (one call to book) or the clinic's care levels (three triage cards): one number, the jobs that cannot wait and the safety steps before help comes.

## Which blocks does a home services site place instead?
- Price guide: `services-price-list`. How it works: `steps-numbered`. Why us: `features-grid`. Figures: `stats-icons`. Team: `team-grid`. Reviews: `testimonial-grid`. Book or call: `booking-cta`. Questions: `faq-accordion`.
- Service areas with a call-out charge share their structure with a florist's delivery zones (zone, places, charge, days), so no family block was added for them: `homeservices-2` places a general one (`locations-cards`, or a zones block shared with the florist).

## What do its rules and texts follow?
- Each rule colours with a theme variable; no `!important`. The template's Turkish: "usta" for technician, "ziyaret talep edin", "fiyat teklifi"; "Job sheet" is "İş emri". The shared "Arrival" reads "Giriş" (hotels), so the fact is "Arrival time" ("Geliş saati"). No year or month; the weekday is plain text.

## How is it tested?
- `tests/designer_blocks_homeservices_test.php` runs it under Node (`helpers/designer_family_run.php`): headings, no fixed colour or `phs-`/`hs-` class, entrances, the dark band, seven jobs; the `tel:` links, `dl`, `ol` and the hidden mark from the file; the rules through `_pg_designer_block_css_parse()`, three rings and reduced motion.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
