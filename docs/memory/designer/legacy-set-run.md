---
name: legacy-set-run
description: The run of a set of classic styles into one Legacy design (LG5, #8795): its steps, the design row, the pages moved, a resume, the report, a set moved before
module: legacy_to_designer_run
---

## How does a set's run start?
- The dry run's Convert form carries `sets[]` for each set it did not skip, beside `styles[]`. `pg_ltd_run_start($style_ids, $user, $set_keys)` reads the sets from `pg_ltd_sets(pg_ltd_report())`, drops one with no page, and names the design `get_unique_name()` of the set's name ("home-2 set", Turkish "home-2 seti").
- The cursor lists the sets' styles first, set by set, then the styles chosen alone; each set is kept in `sets` (key, name, theme_id, collection, settings, styles, `design` = its style id, 0 until made).

## What does it do per style of a set?
- `pg_ltd_steps(true)`: `set_design`, `regions`, `set_pages`, `frame`. `pg_ltd_state_set()` tells which set the style moves with (-1 alone).
- `set_design`, at the set's first style only (`design` 0): `save_system_style(pg_ltd_set_design_row())`: framework `legacy`, the set's theme_id and collection, the majority sharing position, asset rows from `pg_ltd_set_assets()` (read again by `pg_ltd_set_design_assets()`, the dry run's `styles` names dropped) and the fonts; then the majority `layout_type`.
- `regions` and `frame`: as for a style alone.
- `set_pages`: `pg_classic_style_design_open()` makes the frame's parts (`legacy-frame-split.md`); each own page is saved to the design (`pg_ltd_save_page($design_id, …)` moves `page_style`): a page with no tree gets the split frame's tree, a page that had a tree keeps it; the page regions are taken in first (`pg_ltd_page_regions_into_tree()`), a held page is skipped.
- Folder pages: `pg_classic_style_convert_folder_pages($id, $user, $classes, $design_id)`, then `pg_ltd_run_page_regions()` on those pages. The style's body classes go on its pages; its row is not touched.

## What does a resume skip?
- A step marks `skip` ('style' or 'set'), read once by `pg_ltd_state_next()`. A design that cannot be saved or is gone skips the rest of the set.
- A design row written before the cursor was: a visual design of the run's name made since the run started, with no page, is taken again. A moved page is no longer on the classic style, so it is not loaded twice.

## What does the report say?
- One line for the design, one per page moved ("The page "x" moved to the design "y"."), one per style ("the style itself stays as it was", counted in styles).
- A set moved before is still listed (its classic styles stay, with no page): its dry run says "Skipped: no page uses a style of this set any more" (`skip` 'no_pages') without the style list, and Convert leaves it out.

## What did the sandbox show (turkish_default, 50 styles)?
- home-2 set (40 styles, 146 pages) killed after 4 s at style 10, resumed by the screen: 146 pages on the design, one frame per style, no second design, no PHP error. auto-dialog, home-1 and emailer-inline (27 pages) by the screen and the keyboard.
- A moved home-1 page loads the classic head's files in their order, then theme_modern.css. Its slider and parallax photos are missing: see `plans/legacy-group-design-steps.md`.

## Tests?
- `tests/legacy_to_designer_run_test.php`: set steps, cursor with sets, the walk through a set, skips, the design row, the wiring. `tests/legacy_set_test.php`: the `no_pages` dry run.
