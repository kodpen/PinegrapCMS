---
name: legacy-page-designer-l5-list
description: What step L5 (retire page_designer.php) reroutes or removes beyond the three files: Edit Layout links, help row, menu case, panel actions used only by page_designer.js
---

## What else must L5 reroute or remove?
- "Edit Layout" links to `page_designer.php?type=layout` in `edit_page.php` ~353 and `view_fields.php` ~183: to the designer's layout tab (`pg_layout_tab_param()`); a page not on a visual design goes to a small layout-only editor screen on the `layout_code` action (L5a, decided in #3071: `workflow/decisions-made-product-2.md`).
  - Done on a designed page for `view_fields.php` (PR #2137) and `edit_page.php` (PR #2148), both through `pg_layout_edit_url()`; the classic-style case of both still opens `page_designer.php`.
- `get_help_url.php` row `page_designer`; `output.php` ~3962 menu case.
- Panel actions used only by `page_designer.js` (`includes/panel/actions.php` ~181-195): `get_page`, `get_style`, `update_style`, `get_layout`, `update_layout`, `get_file`, `update_file`, `get_items_in_style`, `get_design_files`, `get_designer_region`, `get_dynamic_region(s)`, `update_designer_region`, `update_dynamic_region`, `create_design_region`, `create_dynamic_region`, `update_page_designer_properties`. `get_styles` and `get_designer_regions` stay (`migration.php`).

Related: docs/memory/plans/legacy-page-designer-retirement.md, docs/memory/plans/legacy-page-designer-parity.md
