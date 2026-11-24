---
name: common-region-component-usage
description: A converted common region's shared component counts as used on every page printing the region's tag; the delete confirmations and delete_many read it
module: designer
---

## When is a converted common region's component "used"?
- Architect (2026-11-24), reading the owner's 2026-10-27 decision: a shared component that draws a converted common region counts as used. Before PR #PRNUM the palette's Delete said "(It is not used on any page.)" and `delete_many` removed it without `force` (#11502).
- `pg_shared_component_usage()` adds, for such a row, the pages where the region's `<cregion>` tag stands, read by `pg_shared_component_region_usage_read()` (`includes/fn/designer.php`): the page's desktop or mobile style code, a page region, a designer region embedding the tag (one level, as `get_page_content.php` expands it), a `common`/`designer` region node of the page's tree. Only the row that draws the region (lowest id, `pg_common_region_components_read()`) counts.
- Each such entry carries `common_region: <region name>` beside the usual `page_id`, `page_name`, `style_id`, `style_name`, `binned`.

## What do the delete paths do with it?
- `delete_many` keeps the row unless `force`, like any used row.
- The designs home's "Shared components and system widgets" panel names those pages with `common region tag "<name>"` and a line saying deleting it brings back the region's earlier content there.
- The palette's single Delete (`style_designer.src.js`) names each such page with `common region tag "<name>"` (`_sharedUsageText()`), adds the same line, and asks "Delete it?" instead of "breaks the references" when every page is a tag. It still deletes after the confirmation: deleting the component is the region's undo (`common-region-to-shared-component.md`).

## Tests?
- `test_common_region_component_usage_read` in `tests/common_region_component_test.php`.
