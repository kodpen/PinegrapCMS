---
name: common-region-to-shared-component
description: A common region made a shared component (R2): one _editable block opened to content editors, grants carried over, the <cregion> tag drawing the component, the old write paths refused
---

## What does it do?
- A designer (role 0-1) converts a common region (`cregion_designer_type = 'no'`) on its screen (`edit_common_region.php`, "Make shared component") or from a `common` region node in the designer (`_sdDesignerRegionConvert()`, sub-action `from_common_region`, needs `sharedContentReady`). Opt-in per region, never automatic; until then the region works as before.
- `pg_common_region_component()` (`includes/designer_region_import.php`) gives back the component made before (category `common_region:<cregion_id>`, `pg_common_region_component_category()`), else inserts one: `content_editable = 1`, every `users_common_regions_xref` grant copied to `users_shared_components_xref`. Refused without the 11.0.8 schema: the region's editors would lose it.

## How is the content converted?
- `pg_di_common_region_tree()`: the R3 converter (`pg_di_designer_region_tree()`, PHP and raw blocks lifted the same way) with every node put in one `semantic` div marked `_editable`, so managers and granted users edit all of it; code nodes stay as stored under the content merge. Placeholders inside stay region nodes (no nested component is made).

## How is it drawn?
- Owner 2026-10-27: the component is the one source. The common pass of `get_page_content.php` asks `pg_common_region_components()` (one query per request for the common regions on the page, cached) and draws the component with `pg_common_region_component_html()` (nested components, widgets, custom PHP: the page's own passes ran before). The row's content is no longer read, so a classic page, a designer `region` node and a placed `shared_ref` all show the same thing.
- Deleting the component brings the row's content back (as it was at conversion): that is the undo. The row, its content and its grants stay after the legacy page designer retires (#3156): the tag finds the component through the row, and nothing clears them on update.

## Which old paths stop?
- Front-end inline editing (owner: accepted): a converted region gets no editor in edit mode, and `save_region_content.php` refuses a write.
- `edit_common_region.php`: shows the component's name, no content field and no type change; a rename still works (the tag finds the row by name, the row the component by id). Its convert form posts no `name` and carries `disable_shortcut` (Ctrl+S would send it nameless); fixed in #2253, before which the button did nothing.
- Left out of the other region paths (`pg_common_region_converted_ids()`, `pg_common_region_not_converted_sql()`, PR #2270): the user screens' common-region grant list (`edit_user.php` keeps the grants of converted regions for the undo), find and replace (`cregion_common`), the image editor's `cregion` write. Find and replace does not search component trees.

## Who edits it?
- In the designer only, as an opened component (`shared-component-content-editing.md`), opened on its own from the region screen or the edit-mode link (`shared-component-opened-alone.md`).

## Tests? (PR #2229)
- `tests/common_region_component_test.php`; the DB parts (conversion, grants) need a database.
