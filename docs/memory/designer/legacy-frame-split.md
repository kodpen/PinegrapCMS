---
name: legacy-frame-split
description: A classic style's frame split at its page regions (LG6, #8819): page content in each page's tree, the rest shared parts ("Main top"), their categories, the open and the bulk move
module: classic_style_design
---

## What does the split do?
- Owner (2026-11-11, #8671, `decisions-made-product-43.md`): "İçerik sayfada düzenlensin", the page's own content is edited in the page. `pg_classic_style_frame_split()` (`includes/fn/classic_style_design.php`) cuts the frame at its page regions: the content goes into each page's tree, the frame around it becomes shared components.
- The content runs from the first to the last child holding a page region, at their deepest common ancestor. A page region inside a placed component (`shared_ref`) is not the frame's: the walk does not enter components.
- A frame with no page region is not split: one component, category `classic_style:<id>`, as before (about 20 of the starting sites' styles).

## Why several parts, not one top and one bottom?
- A wrapper often holds chrome and content together (`main-container` with a nav, the content and a footer). The wrappers on the path down to the content are copied into the page tree; on each level the siblings before it are one top part, the siblings after it one bottom part.
- Starting sites: mostly 3 parts; styles 717, 712, 760 have 4-5; 741 a bottom only; 400 none (its page tree holds the content alone). Every starting style prints the same HTML split as whole (`test_lfs_starting_site_styles_print_the_same`).

## Names, categories, ids?
- Parts in document order, keys `top:1`, `top:2`, `bottom:1`; category `classic_style:<id>:<key>`, found by `pg_classic_style_part_category_like()` (`legacy_design_theme.php`). Names "Main top", "Main bottom", "Main top 2" when a side has more than one (`pg_classic_style_part_name()`).
- In the page tree the parts' refs are `sd_frame`, `sd_frame_2`, …; the content keeps the frame's `sd_N` ids.

## Where is it used?
- `pg_classic_style_frame_page_tree()` makes the parts and the page tree for the editor's open (`?classic=1`, `pg_classic_style_design_open()`, also `frame_tree`) and for folder pages (`pg_classic_style_convert_folder_pages()`). Those pages arrive with page region nodes; "Move page content into the page" (`page-region-into-page-tree.md`) turns them into blocks.
- The bulk move does that itself: `pg_ltd_run_design()` and `set_pages` take each page's page regions in before its save; folder pages get `pg_ltd_run_page_regions()` after their tree is saved. The `frame` step works every part row (`pg_ltd_frame_rows()`).
- Dry run: facts `frame_split`, `frame_parts`; writes count each part, and a folder page twice; lines "The style's frame is split into N shared components…" and "The page regions go into each page's own tree…".

## What is not covered?
- A region node between two page regions (designer, menu, common) sits in each page's tree, not in a part: it prints the same, but the `frame` step makes no widget or component of it.
- Designs moved before LG6 keep their whole frame. A whole frame row left by an earlier open stays unused.

## Tests?
- `tests/legacy_frame_split_test.php`; the dry run in `tests/legacy_to_designer_plan_test.php`.
