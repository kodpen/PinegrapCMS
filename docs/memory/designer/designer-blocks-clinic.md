---
name: designer-blocks-clinic
description: The Clinic block family (clinic- prefix, own palette group): doctors' week, care guide; general blocks placed instead; rules, test
---

## Where does it live?
- `assets/js/style_designer_blocks_clinic.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `clinic-`, palette group "Clinic" (`bi-hospital`). Built for the Clinic template's polishing round (`plans/design-template-own-looks-order.md`, `clinic-1`); usable by any doctors' practice, health centre or polyclinic.

## Which blocks does it hold?
- `clinic-week`: header (eyebrow, `h2`, a line), then a `table.pg-clinic-rota` in a named, focusable `table-responsive` region: a column head per day Monday to Saturday (`scope="col"`), a row per doctor whose head (`scope="row"`) holds the name and specialty, a cell per day with a session pill (`pg-clinic-session`: icon + Morning / Afternoon / All day) or "Day off" (`pg-clinic-off`); under it the two session times and Book an Appointment. Header fades, the table rises on view.
- `clinic-care`: "Where to go": three `pg-clinic-care` cards, the tone on a thick top edge (`pg-clinic-now` danger, `pg-clinic-today` warning, plain primary): an icon in a round `pg-clinic-level`, `h3`, when it applies, four examples, one action (`tel:112`, the practice's `tel:`, booking); a line that the guide does not replace a doctor's advice. Cards rise on view (stagger 120).

## Why are these not reused blocks?
- The week is not `hours-card` / `hours-seasons` (the practice's hours, a row per day) nor `team-profiles` (a card per doctor): it reads who is in on which day across the whole team. The guide is not `pharmacy-duty` (a pharmacy's closed hours and night window) nor `features-grid`: three graded levels, each with its examples and its one action.

## Which general blocks does a clinic site place instead?
- Services: `services-icon-cards` / `features-alt`. Fees: `services-price-list`. Check-up packages: `pricing-tiers`. Doctors: `team-profiles`. Booking in three steps: `steps-numbered`. Figures: `stats-icons`. Patients' words: `testimonial-grid`. Opening hours: `hours-card`. Questions: `faq-accordion`.

## What do its rules and texts follow?
- Rules `pg-clinic-rota` (table, pills, muted day off) and `pg-clinic-care` (cards and their tones); each colours with a theme variable. Sample doctors and specialties reuse the template's keys. The shared "Session" key reads "Oturum" (a login), so the pill's layer is named "Time"; "Not in" was avoided for "Day off" (İzinli).

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_clinic_test.php` runs it through `tests/helpers/designer_family_run.php`: own group, a section per block with one `h2` and only `h3` under it, no fixed colour or `cl-` class, `{var}` filled, no year or month, entrances kept by `_sdFxNormalize()`; seven column heads, four row heads and 24 cells; three levels with their tones and icons; the region and the `tel:` links read from the file; the rules through `_pg_designer_block_css_parse()`.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
