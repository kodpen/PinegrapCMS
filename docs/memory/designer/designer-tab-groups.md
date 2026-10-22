---
name: designer-tab-groups
description: Page tab order and named coloured groups in the designer (style.style_tab_layout, 2026.4.8): shape, own endpoint apart from Save, twin normalisers, colours, folding per browser, new tabs, drag.
---

## Where are tab order and groups kept?
- The order is the order of `_pages`; a tab's group is `p.tabGroup` (`''` or a key of `_pgTabGroups`). Stored on the design in `style.style_tab_layout` (2026.4.8, step 8.19) as `{v:1, groups:{gid:{name,color}}, tabs:[{id[,g]}]}`, saved pages only.
- Written only by `designer/tab_layout` → `pg_designer_tab_layout_save()`, the moment the strip changes: not tied to Save and not part of the dirty check (it is how the editor looks; the site never reads it). Without the column (`pg_style_tab_layout_ready()`, `sdDesign.tabLayout.ready`) the strip is plain and the endpoint refuses.
- A content-level operator sees the layout and folds groups but does not rearrange (`_pgTabLayoutEditable()`).

## Which code changes together?
- The normaliser has two faces, changed in lockstep: `pg_designer_tab_layout_normalize()` (PHP, `tests/designer_tab_layout_test.php`) and `_pgTabsNormalizeGroups()` (JS, run by `_pgTabsRender()` on every draw). Unknown or repeated ids are dropped, missing pages appended, a group's tabs pulled together where its first tab stands, empty groups dropped; names cut to 60 characters, at most 50 groups, group id `^[a-z0-9_-]{1,20}$`.
- The colours use the same keys in three places: `pg_designer_tab_layout_colors()`, `_PG_TAB_GROUP_COLORS` and the `[data-color]` rules in `style_designer.css`; an unknown colour becomes grey.

## What stays in the browser?
- Folding is not stored on the design: `localStorage` `pg_sd_tabgroups_<styleId>`. The tabs of a folded group stay in the DOM, hidden with `sd-tab-folded`; the open page stays visible, and switching to a tab of a folded group unfolds it.

## How does new code open a tab?
- With `_pgTabsInsertAfterActive(p)`, not `_pages.push`: right of the active tab, in its group.
- Dragging uses pointer events (`_pgTabsDragBind()`); it is off at content level and without the column.
