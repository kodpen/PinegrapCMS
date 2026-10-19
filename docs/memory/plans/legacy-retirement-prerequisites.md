---
name: legacy-retirement-prerequisites
description: Plan (2026-10-19, not started): regions editable in the visual designer before classic pages move: common/designer regions as shared components with content rights, dynamic, page, menu
---

## Why, and what is missing today?
- Owner (2026-10-19): the legacy page designer may retire only when every existing page moves without loss; common and designer regions must be editable from the visual designer, as shared-component-like blocks. Not ready:
  - The designer only prints a `region` node (`_render_region_tag()`); the content stays in `cregion` / `dregion`, is edited outside (`edit_common_region.php`, `edit_designer_region.php`, front-end inline editing) and the canvas shows a "Region: name" placeholder. `SD_LEGACY_REGIONS` says a shared component does their job; nothing converts them.
  - A shared component is never opened to the content level (`designer/designer-content-roles-save-gate.md`), but managers and role-3 users granted a common region (`users_common_regions_xref`) edit it today. Converting as is takes their content away.
  - The converter parses with DOMDocument (`includes/designer_import.php`): PHP inside a designer region (allowed, evaluated after expansion in `get_page_content.php`) would be mangled; nested designer regions expand one level.
  - `<dregion "x">name</dregion>` hands `$dregion_attribute` to the code; the region node has no attribute.
  - Menus (`menus`, `users_menus_xref`), ad regions and the tag cloud are data-driven; a static shared component nav loses the menu screen and its editors.

## What has to be built? (one PR each)
- R1 Content-editable shared component: a flag on `shared_components` (schema step, ask) that opens its `_editable` blocks to the content level, plus per-component user rights on the `users_common_regions_xref` model, enforced in `pg_designer_merge_restricted_tree()` for the component tree.
- R2 Common region -> shared component (one per `cregion` with `cregion_designer_type = 'no'`): content through the converter into one `_editable` block, rights carried over. One source of truth while classic styles still print `<cregion>name</cregion>` (see open question).
- R3 Designer region -> designer-only shared component: PHP blocks lifted to `custom_php` nodes before parsing, nested designer regions as nested shared refs, inner placeholders as region nodes (the placeholder half of L1).
- R4 Dynamic region stays PHP: region node gains the attribute; its code opens as a code-editor tab in the designer (designer role), like CSS/JS files.
- R5 Page regions: converting a page moves its `pregion` content into the page tree as `_editable` rich-text blocks; page edit rights unchanged.
- R6 Widgets for menu / menu sequence (items still edited in the menu screen), ad region (rotation) and tag cloud. Mobile switch and PDF stay region nodes unless asked.
- Missing system widget types: `system-widget-missing-types.md`.

## Open questions?
- R2 source: the component (a classic placeholder renders it) or the `cregion` row until L5?
- Front-end inline editing of common and page regions ends for converted pages; content people edit in the designer. Accepted?

## Where to start?
- R1: it fixes how R2-R5 keep content rights. L1-L5 in `legacy-page-designer-retirement.md` wait for R1-R3.
