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
- LG5 (M) The set's run. Done (#8795): `designer/legacy-set-run.md`. For LG7: a moved set's classic styles stay listed with no page.
  - Its sandbox found: a common region made a shared component draws its `<img>` in a `<picture data-pg-picture>`, and the classic theme's scripts.js reads `.background-image-holder` `children('img')` for the background, so a moved page (alone or in a set) loses those photos (`url(undefined)`). `designer/responsive-images.md` says imported markup keeps a bare `<img>`. No step names it yet.
- LG6 (M) The frame split: page content in the page's tree. Done (#8819): `designer/legacy-frame-split.md`, one step (the editor's open, the run and the dry run). A wrapper around chrome and content gives a top and a bottom part per level, not two components in all.
  - For LG7 and later: designs moved before LG6 keep their whole frame (no step splits them); a region node between two page regions stays in each page's tree, so the `frame` step makes no widget of it.
- LG7 (S) "Back to classic" for a moved set or one page, by a designer: `page_style` back to the origin style, the trees kept for a later move.
  - Close-out: `designer/legacy-overview.md` lists the topics above, this plan's two files are deleted, the architect is told to write the Bootstrap 5.3.8 helper's plan.
