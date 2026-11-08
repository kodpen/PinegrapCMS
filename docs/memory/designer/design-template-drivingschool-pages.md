---
name: design-template-drivingschool-pages
description: Driving School home and sector pages on its block family since 1.1.0: the block behind each section, entrances, what was dropped
---

## Which pages follow the own-look rule?
- Since template 1.1.0 (family #8117, pages #8138): `home`, `licences`, `licence_<key>`, `theory`, `practical_test` ([[design-template-own-look]]). `instructors`, `about`, `faq` and the request, account and standard pages stay as built (they may stay plain).
- The builders sit in `pinegrap-drivingschool/pages_public.php` under "The palette blocks, with the school's data"; they draw the blocks' structure and classes from `parts.php`'s data, and the template's `assets` css asks `pg_design_template_user_styles()` for `drivingschool-licences`, `-signs`, `-test-sheet`, so the `pg-drv-*` rules ship beside the `pdr-` ones.

## Which block is each section?
- `$licence_board` = `drivingschool-licences`: a card per entry of `$licences` (plate `pg-drv-plate pdr-display`, minimum age, lessons, gearbox from `$ds_gearbox`), "See the class" to `licence_<key>` and "See the courses" to `courses?licence=`. On home (tinted) and `licences`.
- `$road` = the palette's `steps-numbered`: four steps from `$road_steps` (assessment drive, theory, lessons with one instructor, mock test). On home and `licences`.
- `$theory_signs` = `drivingschool-signs`: `$theory_topics` with their `sign` (`warn`, `ban` with `mark`, `info`) and `sign_icon`, beside a practice-app box (no pass mark: it differs by country). On home and `theory`.
- `$test_sheet` = `drivingschool-test-sheet`: `$test_day` with its `time` pill beside the sample fault table (`$sheet_skills`), the three fault levels ("a few are allowed", no figure) and a Passed pill. On `practical_test`.

## What does each page hold now?
- Home: dark opening (words rise on `load`, a plate link per class), licence board, steps, featured courses, theory signs, pass stories with Google reviews, News. The figures band, the why-us tiles and the theory story band are gone from it (about keeps them).
- `licences`: header, board, steps. `licence_<key>`: the class section with a plain `$picture` (no `$framed`), points rising, the test-day steps. `theory`: header, signs, classes and tips side by side. `practical_test`: header, test sheet, "how we get you ready" beside the picture. No ask band on these pages.
- `$licence_card` (parts.php) was removed with the old grids.

## How is motion set?
- `$rise($node, $trigger = 'view', $stagger = 90)` writes `props._fx` slide-up; the opening uses `load`, the stages stagger 70. pg_fx honours reduced motion; no template JavaScript.

## Which tests cover it?
- `design_template_drivingschool_public_test.php` (`…_place_the_blocks`): no `Picture Frame`, `Figures` or `Write to Us` band on those pages, entrances valid, plates, six hidden signs, four step cards, the table's scopes, each class linked twice from home. `…_assembly_test.php`: version 1.1.0 or later, every `pg-drv-*` class used has its rule in User Styles.
