---
name: designer-blocks-drivingschool
description: The Driving School block family (drivingschool- prefix, its own palette group): the three blocks, the general blocks it reuses instead, its pg-drv-* rules, its test
---

## Where does it live?
- `assets/js/style_designer_blocks_drivingschool.js`, one `StyleDesigner.registerBlockFamily()` call ([[designer-block-family-files]]); prefix `drivingschool-`, palette group "Driving School" (`bi-car-front`). Built for the Driving School template's pages ([[design-template-drivingschool]]), usable by any driving or riding school.

## Which blocks does it hold?
- `drivingschool-licences`: a card per licence class (B, A2, C1, BE): the class letter as a number plate (`pg-drv-plate`), the vehicle icon, name `h3`, what it lets you drive, a `dl` of minimum age, lessons and gearbox, "See the class"; a note that class B can be learned in an automatic. The row rises on view.
- `drivingschool-signs`: the theory test, a pass-mark box (`{var:1} of {var:2}`, practice link) beside six topic cards, each led by a road sign drawn in CSS: a warning triangle (`pg-drv-sign-warn`, `--bs-warning`), a speed-limit circle (`pg-drv-sign-ban`, `--bs-danger` ring, the number as text) or an information square (`text-bg-primary rounded-3`). The sign is `aria-hidden`; the topic is named in its `h3` with its question count.
- `drivingschool-test-sheet`: the practical test, the day's six stages as an `ol` with a minutes pill each (rises on view, stagger 70) beside the examiner's sheet: a fault `table` (skill row heads `scope="row"`, driving / serious / dangerous columns `scope="col"`, hidden caption, named region), the three fault levels with their rule, a "Passed" pill (icon plus word) and the sheet's total. `pg-drv-sheet` keeps the figures tabular.

## What does it reuse instead of adding?
- Searched first (core, launch, skiresort, hours, locations). The school's other sections are placed blocks: the road to the licence (theory, lessons, mock test, test) is `steps-numbered` or `timeline-horizontal`; lesson packages are `pricing-tiers` (one price per package); instructors `team-grid` / `team-profiles`; pass rates `stats-icons` / `stats-progress`; branches `locations-cards`; manual versus automatic `compare-side-by-side`; the enrol call `booking-cta`.
- The licence card is not `pricing-tiers` (no price, facts of a class) and the test sheet not `compare-table` (faults counted per skill, not features compared).

## What do its rules and texts follow?
- Every `pg-*` class the file writes is a rule key; colours come from theme variables, the plate uses `--bs-emphasis-color` on `--bs-body-bg` so it stays readable in dark mode. No template class (`pdr-`, `drv-`) in the blocks.
- Sector keys are their own: "From {var} lessons", "{var} years old" (a bare "{var} years" would read as an age in every sector). Class letters and the speed figure are untranslated text.
- No date or year in the sample; nothing moves by itself.

## How is it tested?
- `tests/designer_blocks_test.php` checks the file like every family file; `tests/designer_blocks_drivingschool_test.php` runs the kit and the file under Node: a section with one `h2` and no skipped level, no fixed colour or template class, `{var}` filled, no year, one entrance per block kept by `_sdFxNormalize()`, four plates and facts, six hidden signs of three shapes, the table's scopes, the status pill, and the rules reaching the block stylesheet.

Related: docs/memory/designer/design-template-own-look.md, docs/memory/designer/designer-blocks-general.md
