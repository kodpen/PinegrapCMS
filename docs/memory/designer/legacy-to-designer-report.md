---
name: legacy-to-designer-report
description: The "Move to the visual designer" readiness report (LD1): what it lists per classic style, how the state is worked out, what it does not do
module: legacy_to_designer
---

## Where is it, and who sees it?
- `legacy_to_designer.php`, linked from the toolbar of All Page Styles (`view_styles.php`); `validate_area_access($user, 'designer')`, roles 0-1, as the conversion. It writes nothing; each row's magic button is the existing `edit_system_style.php?id=<style>&classic=1` (`classic-style-to-design.md`).

## What does it list per style?
- Every `style` row that is not `visual_designer`, by name (`pg_ltd_report()`, `includes/fn/legacy_to_designer.php`).
- Pages, binned ones left out: own (`page_style`) and by folder (no `page_style`, the folder's desktop style by `pg_classic_style_folder_styles()`), grouped by `pg_ltd_pages_by_style()`. Pages that already have a tree are counted as "already designed" and left out of the other counts. Mobile styles are not counted.
- Regions of the style's code by type (`pg_ltd_style_tags()`, the same tags and skipped blocks as `pg_di_lift_region_tags()`/`pg_di_classic_placeholders()`). `<cregion>` is split by its row (`pg_ltd_region_counts()`): designer region, common region already a component (`pg_common_region_converted_ids()`), common region, or a name with no row.
- The number of `pregion` rows on its unconverted pages; pages with `layout_type = 'custom'` on a type `check_if_page_type_supports_layout()` accepts.
- Live designer locks on its pages (`designer_page_lock`) and users with the style open (`designer_presence`), both within `PG_COLLAB_STALE`.

## How is the state worked out?
- `pg_ltd_style_state()`: `blocked` when `pg_classic_style_refusal()` names a reason; else `attention` for PHP in the style's code (the style importer has no PHP lift, so `<?php` is not kept), a common region tag with no row, a held page, the style open now; else `ready`.
- Notes never change the state: dynamic regions (stay PHP), custom layouts (keep drawing inside the system region), no page uses the style. These feed LD5's "stays classic" list.

## Tests?
- `tests/legacy_to_designer_test.php`: tags in order and the skipped blocks, PHP detection, the common region split, pages by style, the states. The collector and the screen were not run against a database or in a browser.
