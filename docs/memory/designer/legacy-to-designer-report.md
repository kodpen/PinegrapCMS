---
name: legacy-to-designer-report
description: The "Move to the Page Design Studio" readiness report (LD1, #6219): what it lists per classic style, its sets (LG4), its state, the "What stays classic" card (#6488), the rules the whole move keeps
module: legacy_to_designer
---

## Where is it, and who sees it?
- `legacy_to_designer.php`, linked from the toolbar of All Page Styles (`view_styles.php`); `validate_area_access($user, 'designer')`, roles 0-1, as the conversion. It writes nothing; each row's magic button is the existing `edit_system_style.php?id=<style>&classic=1` (`classic-style-to-design.md`).

## What does it list per style?
- Every `style` row that is not `visual_designer`, by name (`pg_ltd_report()`, `includes/fn/legacy_to_designer.php`).
- Pages, binned ones left out: own (`page_style`) and by folder (no `page_style`, the folder's desktop style by `pg_classic_style_folder_styles()`), grouped by `pg_ltd_pages_by_style()`. Pages that already have a tree are counted as "already designed" and left out of the other counts. A page served by a mobile style is not counted for it; `mobile` counts the folders and pages naming it.
- Regions of the style's code by type (`pg_ltd_style_tags()`, the same tags and skipped blocks as `pg_di_lift_region_tags()`/`pg_di_classic_placeholders()`). `<cregion>` is split by its row (`pg_ltd_region_counts()`): designer region, common region already a component (`pg_common_region_converted_ids()`), common region, or a name with no row.
- The number of `pregion` rows on its unconverted pages; pages with `layout_type = 'custom'` on a type `check_if_page_type_supports_layout()` accepts.
- Live designer locks on its pages (`designer_page_lock`) and users with the style open (`designer_presence`), both within `PG_COLLAB_STALE`.
- Its set key; the styles are listed by set, then those that move alone (`legacy-set.md`).

## How is the state worked out?
- `pg_ltd_style_state()`: `blocked` when `pg_classic_style_refusal()` names a reason; else `attention` for PHP in the style's code (the style importer has no PHP lift, so `<?php` is not kept), a common region tag with no row, a held page, the style open now; else `ready`.
- Notes never change the state: dynamic regions (stay PHP), custom layouts (keep drawing inside the system region), no page uses the style.

## What stays classic, and where is it shown?
- The card "What stays classic" under the styles table, from `pg_ltd_stays_classic()` (pure), with the style names per line: styles the designer refuses (`pg_classic_style_refusal()`: older cell-based styles, PHP template files); dynamic regions (stay PHP, `dynamic-region-in-designer.md`); pages with a custom layout (drawn by the page's `system` node, a widget only when the designer chooses, `layout-widget-offer.md`); the card gateway when it is one of `pg_payment_legacy_gateways()` that no driver serves (frozen in `submit_order.php`; the checkout widgets post to the classic handlers, so a moved checkout keeps it, `commerce/payment-driver-layer-decisions.md`).
- Kept unread, not listed: a converted common region's `cregion` row and grants stay, decided in #3156 (`classic-regions-in-designer.md`).

## Which rules does the whole move keep?
- Owner 2026-11-03 (#6006): nothing classic is removed, the renderers stay, conversion never runs by itself: a designer (role 0-1) chooses styles, sees the dry run, starts the move. Undo is the existing paths (deleting a component, page revisions).
- The bulk move writes only what the editor's single Save writes, through the same converters (`legacy-to-designer-bulk-run.md`, `-run.md`); normalised output is fine, byte-identical is not a goal; no schema. Search over trees and Migration carrying designs complete it (`tree-search.md`, `migration-carries-designs.md`).

## Tests?
- `tests/legacy_to_designer_test.php`: tags in order and the skipped blocks, PHP detection, the common region split, pages by style, the states. LG4's sandbox ran the collector and the screen (`legacy-set.md`).
