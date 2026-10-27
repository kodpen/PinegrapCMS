---
name: shared-component-opened-alone
description: A shared component opened on its own in the designer (edit_system_style.php ?component=): who may, which design draws it, the one synthetic tab, Save writing only the component, the edit links
---

## What is it for?
- Owner 2026-10-27: a role-3 user granted a component but no folder edit right opens the component on its own, not from a page. It is the entry for content editors of a converted common region (`common-region-to-shared-component.md`); designers may use it too. PR #2253.

## Who may open it?
- `pg_designer_component_entry()` (`includes/designer_access.php`): roles 0-1 any component that is not a system widget; roles 2-3 one `pg_shared_component_content_editable_ids()` names (opened, and granted for role 3). Anyone else gets 403 "Access denied.". No folder right is read.

## Which design draws it?
- `pg_designer_component_style()`: the `id` asked for when it is a visual design, else the first design that places the component on a page outside the recycle bin, else the first visual design. A site with no visual design is refused ("There is no visual design to open the shared component in."): an all-classic site cannot reach it yet.

## How does the editor behave?
- `pg_designer_screen_render()` with `component`: one tab, key `c<id>`, named after the component, its tree `root > shared_ref`; `componentOnly` in the design payload; the pages group (add, settings, delete) and the publishing menu are not printed; the main button reads Save.
- JS `_sdComponentOnly()`: `saveAjax()` calls `_sdSaveComponentOnly()`, which runs `_flushDirtyShared()` (`content_update` at content level, `update` for designers) and never posts the design form; `isDirty()` looks only at dirty components; the tab has no menu and no rename.

## Where are the links?
- `edit_common_region.php` of a converted region: "Edit in the visual designer".
- Front-end edit mode: a converted region is outlined with the edit button, for those `pg_designer_component_editable()` admits; the link carries `send_to` back to the page.

## Checked how?
- Sandbox run (#2253): a role-3 user without folder rights refused, then granted, edited and saved; designer save wrote only the component. Tests: `tests/common_region_component_test.php` (style choice).
