---
name: legacy-page-designer-l5-list
description: What step L5 (retire page_designer.php) reroutes or removes beyond the three files: Edit Layout links, help row, menu case, panel actions used only by page_designer.js
---

## What else must L5 reroute or remove?
- "Edit Layout" links to `page_designer.php?type=layout` in `edit_page.php` ~353 and `view_fields.php` ~183: to the designer's layout tab (`pg_layout_tab_param()`); a page still on a classic style goes to `edit_custom_style.php`.
- `get_help_url.php` row `page_designer`; `output.php` ~3962 menu case.
- Panel actions used only by `page_designer.js` (`includes/panel/actions.php` ~181-195): `get_page`, `get_style`, `update_style`, `get_layout`, `update_layout`, `get_file`, `update_file`, `get_items_in_style`, `get_design_files`, `get_designer_region`, `get_dynamic_region(s)`, `update_designer_region`, `update_dynamic_region`, `create_design_region`, `create_dynamic_region`, `update_page_designer_properties`. `get_styles` and `get_designer_regions` stay (`migration.php`).

Related: docs/memory/plans/legacy-page-designer-retirement.md, docs/memory/plans/legacy-page-designer-parity.md
