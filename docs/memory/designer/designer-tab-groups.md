---
name: designer-tab-groups
description: Page tab order and named coloured groups in the designer (style.style_tab_layout): shape, own endpoint apart from Save, twin normalisers, colours, the open group, the fitting strip, drag.
---

## Where are tab order and groups kept?
- The order is the order of `_pages`; a tab's group is `p.tabGroup` (`''` or a key of `_pgTabGroups`). Stored on the design in `style.style_tab_layout` (2026.4.8, step 8.19) as `{v:1, groups:{gid:{name,color}}, tabs:[{id[,g]}]}`, saved pages only, plus an optional `proto` (#6232): the Prototype view's shared frame places, `p<page_id>` (a page of the design) or `s<shared id>` → `{x, y}`, whole units within ±1,000,000, at most 500 (`pg_designer_tab_layout_proto()`; JS twin `PGProtoHotspots.places()`). The strip's save carries them (`_pgTabProto`); the key is left out when there are none.
- Written only by `designer/tab_layout` → `pg_designer_tab_layout_save()`, the moment the strip changes: not tied to Save and not part of the dirty check (it is how the editor looks; the site never reads it). Without the column (`pg_style_tab_layout_ready()`, `sdDesign.tabLayout.ready`) the strip is plain and the endpoint refuses.
- A content-level operator sees the layout and folds groups but does not rearrange (`_pgTabLayoutEditable()`).

## Which code changes together?
- The normaliser has two faces, changed in lockstep: `pg_designer_tab_layout_normalize()` (PHP, `tests/designer_tab_layout_test.php`) and `_pgTabsNormalizeGroups()` (JS, run by `_pgTabsRender()` on every draw). Unknown or repeated ids are dropped, missing pages appended, a group's tabs pulled together where its first tab stands, empty groups dropped; names cut to 60 characters, at most 50 groups, group id `^[a-z0-9_-]{1,20}$`.
- The colours use the same keys in three places: `pg_designer_tab_layout_colors()`, `_PG_TAB_GROUP_COLORS` and the `[data-color]` rules in `style_designer.css`; an unknown colour becomes grey.

## Which group is open, and how does the strip fit?
- One group is open at a time: always the open page's (`_pgTabStripOpenGroup()`), drawn as a box with its name, colour and tabs; every other group is a chip with name and count, "Other" (pages without a group) last. Nothing about folding is stored; the old `pg_sd_tabgroups_<styleId>` key is no longer read (#10245).
- A chip lists its pages (first five, "N more pages", "Open the group in the strip": that group's last open page, else its first). A page picked there opens, and its group with it.
- The strip never scrolls: `_pgTabsFit()` measures it and `_pgTabStripPlan()` (pure, `tests/designer_tab_strip_test.php`) picks the first of the six steps that fits; hidden tabs go behind "+N", chips behind "+N groups" / "N groups". Steps: `plans/designer-panels-measures-7.md` until close-out.

## How does new code open a tab?
- With `_pgTabsInsertAfterActive(p)`, not `_pages.push`: right of the active tab, in its group.
- Dragging uses pointer events (`_pgTabsDragBind()`), over the box's head and tabs and the chips in strip order; "Other" is not dragged. It is off at content level and without the column.

## Where is every page listed?
- In the Pages window (DP20): the Pages button `#sd-tabs-all-btn` after the strip and Ctrl+P open it; it took the place of the "All pages" menu (`designer-pages-window.md`).
