---
name: legacy-group-design-steps
description: Steps of plans/legacy-group-design.md: LG1 the theme, LG2 {path} addresses, LG3 the legacy key and badge, LG4 sets in the report and dry run, LG5 the run, LG6 the frame split, LG7 back to classic
---

## Which steps are there?
- S under 400 product lines, M about 1,000; no schema; each step its tests and its memory topic. They are built in the order written: each waits for the one before it.

- LG1 (M) The theme of a moved design. Done (#8694): `designer/legacy-design-theme.md`. Its sandbox move found that the bulk run writes no stylesheet or script rows, so every other file of the classic head is missing; no step below names it yet.
- LG2 (M) `{path}` and `{software_directory}` in addresses, for every design. Done (#8732): `designer/design-asset-path-placeholders.md`.
- LG3 (S) The `legacy` key and the badge. Done (#8757): `designer/legacy-design-framework.md`.
- LG4 (M) Sets in the report and the dry run. Done (#8776): `designer/legacy-set.md`. For LG5: the design takes the set's most common sharing position and layout override, which the dry run shows per style; the set's asset rows come from `pg_ltd_set_assets()`.
- LG5 (M) The set's run. `pg_ltd_steps()` for a set: the design row once, then per style its regions, frame component and pages (`page_style` to the set's design, tree = root + `shared_ref`), on today's cursor and lock; a resume skips what is written; the kept report names every page moved. Topic `legacy-to-designer-run.md` corrected.
- LG6 (M) The frame split: page content in the page's tree. Starts from `designer/page-region-into-page-tree.md`; may be cut (LG6a the frame into two components, LG6b the regions into the tree).
- LG7 (S) "Back to classic" for a moved set or one page, by a designer: `page_style` back to the origin style, the trees kept for a later move.
  - Close-out: `designer/legacy-overview.md` lists the topics above, this plan's two files are deleted, the architect is told to write the Bootstrap 5.3.8 helper's plan.
