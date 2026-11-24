---
name: legacy-earlier-designs
description: The "Designs moved earlier" card on the move screen (LE11): each Legacy design's fully editable pages, its dry run drawer, its run of the page_parts and types steps only, a design moved before the frame split
module: legacy_earlier_designs
---

## What does the card show?
- On Move to the Page Design Studio (`legacy_to_designer.php`), under the Styles card: a list row per Legacy design with pages (`pg_lte_designs()`, by name), "132 of 146 pages fully editable", a pill (teal "All editable", amber "N pages left"), "Dry run" quiet small at the row's end, "Back to classic" in the row's menu when the Back to classic card lists the design (`#ltd-back-<id>`).
- Counts come from the screen's measure (`pg_ltd_closed_measure()`, which also returns `pages` page id => design id and the `components` it read); `pg_lte_rows()` is pure.

## What about a design moved before the frame split?
- Told by a component its pages place holding a Page region node (`pg_lte_holds_page_region()`): its page content sits in one shared part.
- The row says so and its one action is "Send back to classic, then move again" (to the Back to classic card); a design moved alone has no Back to classic, so it shows the line only. The dry run and the run refuse it.

## How does its dry run work?
- "Dry run" opens `?earlier=<id>#ltd-earlier`: a GET, nothing written; the drawer (`#ltd_earlier`, `pg_lte_drawer_html()`) opens with the screen, a bottom sheet under 576 px.
- `pg_lte_plan()` reads the design's pages and builds an entry shaped like a style's dry run (`pg_lte_plan_entry()`, pure), so `pg_ltd_plan_groups()` draws the three groups: no Page content row (it is in the trees), a held page under Skipped, `file` page types counted as Pages with a custom layout.
- Foot: "Close" quiet, "Convert" filled; no Convert while a move runs or with nothing under Becomes editable.

## What does its run do?
- `pg_lte_run_start()`: role 0-1, no move running, a design the card lists and not moved before the split. The cursor (`pg_lte_state_new()`) lists the design as its one style and in `earlier`; `pg_ltd_state_steps()` gives it `pg_ltd_steps(false, true)`: `page_parts`, `types`, on all its pages. The design's row is not touched.
- Starters are built in the browser as for a style (`pg_ltd_convert_script('ltd_earlier_convert')`, `legacy-run-starters.md`) and kept by `pg_ltd_starters_keep()`.
- A design that is no longer a visual design when its step runs is skipped. The progress says "designs"; "Open the design" opens it at the end (`pg_ltd_run_designs()` lists it as a style moved alone).

## Tests?
- `tests/legacy_earlier_designs_test.php`: the component check, rows, the entry and its groups, the two-step cursor, the wiring.

Related: docs/memory/designer/legacy-editable-measure.md, docs/memory/designer/legacy-page-parts.md, docs/memory/designer/legacy-types-step.md, docs/memory/plans/legacy-editable-screens.md
