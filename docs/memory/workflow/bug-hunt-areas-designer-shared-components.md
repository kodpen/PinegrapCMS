---
name: bug-hunt-areas-designer-shared-components
description: Bug hunt of the designer's shared components, server side (area 54, 2026-11-24): four faults fixed in the hunt's PR #11498, two issues, what was read clean
---

## What was the area?
- Server side only: the `shared_component` panel handler (`includes/panel/shared_component.php`), usage and delete (`pg_shared_component_usage()`, `pg_shared_component_delete_split()`), the content role's gate (`includes/designer_access.php`), drawing (`_expand_shared_refs()`, `pg_expand_nested_components()`, `get_page_content.php`), the common region made a component (`includes/designer_region_import.php`, `edit_common_region.php`).
- `style_designer.src.js` and the Prototype scripts were not touched (held by the DV, SK and ST plan steps).

## What did it fix? (PR #11498 lists each with file and line)
- A converted common region, and a designed pop-up drawn from a component, are drawn after the page's passes for page conditions and page links: a "signed in only" element showed to every visitor. `pg_common_region_component_finish()` decides them. Tests: `tests/common_region_component_test.php`.
- On a custom form page, a form widget inside a placed shared component was not seen, and the classic form drew under the designed one. `pg_shared_component_reach_read()` follows nesting. Tests: `tests/designer_components_test.php`.
- `update`, `update_config` and `rename` of a component deleted meanwhile answered success; the editor took the change as saved. They now refuse with `pg_shared_component_gone_message()`, and the editor keeps the change unsaved.
- The single `delete` wrote no activity log line (the bulk delete did); a row already gone answers success.

## What became an issue?
- #11500: a converted common region's component gets none of the scripts its blocks bring (theme switcher, scroller, Listen, map, interactions): the inject passes run before the common region pass.
- #11502: "where it is used" ignores a converted region's tag, so the delete confirmations say "not used on any page"; counting it as used needs a decision.

## What was read clean?
- The content role's gate: `content_update` merges into the stored tree; locked nodes (shared_ref, custom PHP/HTML) are restored inside editable areas; a widget never opens; grants go with a deleted component or user.
- Loops: refused on page save and on `update`, and drawn as an inert comment; nesting capped at depth 8.
- Recycle Bin: usage counts binned pages under their original name; a deleted row draws a comment and the next page save drops the orphan.
- The page cache: every write sub-action is in the dirty list.
