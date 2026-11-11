---
name: legacy-group-design-steps
description: Steps of plans/legacy-group-design.md: LG1 the theme, LG2 {path} addresses, LG3 the legacy key and badge, LG4 sets in the report and dry run, LG5 the run, LG6 the frame split, LG7 back to classic
---

## Which steps are there?
- S under 400 product lines, M about 1,000; no schema; each step its tests and its memory topic. They are built in the order written: each waits for the one before it.

- LG1 (M) The theme of a moved design. Done (#8694): `designer/legacy-design-theme.md`. Its sandbox move found that the bulk run writes no stylesheet or script rows, so every other file of the classic head is missing; no step below names it yet.
- LG2 (M) `{path}` and `{software_directory}` in addresses, for every design. Done (#8732): `designer/design-asset-path-placeholders.md`.
- LG3 (S) The `legacy` key and the badge.
  - `pg_design_frameworks()` gets `legacy` (`bootstrap => false`, a `legacy` flag); `pg_design_framework_key()` accepts it; the literal tests (`=== 'custom'` in `includes/designer_ai.php`, `=== 'bootstrap5'` in `includes/designer_screen.php`) ask the flags; the callers of `pg_classic_style_mark_design()` pass `legacy`; LG1 asks the flag.
  - A data-only migration step in the open version (rerun twice): a `custom` design holding a `classic_style:<style_id>` frame gets `legacy`.
  - The Visual Designs list: the badge `lang('Legacy')`, Turkish "Eski". Topic `designer/legacy-design-framework.md`.
- LG4 (M) Sets in the report and the dry run.
  - A pure function gives a classic style's set key: its ordered external CSS and JS references (after LG2's expansion), `collection`, theme; order counts, inline blocks do not. `pg_ltd_report()` groups by it; the screen lists a set, its styles and page count; `pg_ltd_plan()` takes a set and shows the one design, the frame per style, the asset rows limited to a style's pages, and what blocks.
  - The PR shows how mobile styles, pages styled by their folder (`page_style = 0`), `style_head` and `additional_body_classes` fall; a style the set cannot hold moves alone as today.
  - Topic `designer/legacy-set.md`; `legacy-to-designer-report.md` corrected.
- LG5 (M) The set's run. `pg_ltd_steps()` for a set: the design row once, then per style its regions, frame component and pages (`page_style` to the set's design, tree = root + `shared_ref`), on today's cursor and lock; a resume skips what is written; the kept report names every page moved. Topic `legacy-to-designer-run.md` corrected.
- LG6 (M) The frame split: page content in the page's tree. Starts from `designer/page-region-into-page-tree.md`; may be cut (LG6a the frame into two components, LG6b the regions into the tree).
- LG7 (S) "Back to classic" for a moved set or one page, by a designer: `page_style` back to the origin style, the trees kept for a later move.
  - Close-out: `designer/legacy-overview.md` lists the topics above, this plan's two files are deleted, the architect is told to write the Bootstrap 5.3.8 helper's plan.
