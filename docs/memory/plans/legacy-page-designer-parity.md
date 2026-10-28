---
name: legacy-page-designer-parity
description: L4 parity list (2026-10-26): what page_designer.php does that the visual designer lacks, and the owner's build-or-drop answer per item (#2484)
---

## What was decided? (#2484, 2026-10-27)
- P1, P4, P5, P6 (both create actions), P8, P10 dropped: running sites lose only an editor convenience, the site output does not change. P7 built (`designer/classic-style-design-files.md`). P2, P3, P9 are parity already. L4's build list is P7 only.

## What does the page designer have that the visual designer lacks?
- P1 Live-site browsing (`init_preview_check()`, `get_page`, `get_items_in_style`): dropped; the toolbar's Ctrl+G on each page reaches its design.
- P2 Jump to the page's style: done for designed pages (`page_designer_button` branch, `includes/fn/output.php` ~5310: `edit_system_style.php?id=&page=`, layout tab per Y6). Only the classic `else` branch opens `page_designer.php`. Drop it in L5; a page left classic (`style_file`, not converted) keeps "Edit Page Style" (`edit_custom_style.php`).
- P3 Ctrl+G back to the browsed page (`close()`): parity, `_sdExitDesigner()` (`style_designer.js` ~7350) returns to `send_to`.
- P4 Ctrl+D (hide the toolbar in the preview) and Ctrl+E (`#grid_toggle`): dropped.
- P5 Console pane (`pdConsoleAdd/Clear/Toggle()`: `console.*`, `error`, `unhandledrejection` of the preview): dropped; browser devtools.
- P6 Region editors: designer region (R3), dynamic region code (R4), common region (R2). "Create Designer Region" (`create_design_region`) and "Create Dynamic Region" (`create_dynamic_region`): dropped; a new shared component, and `add_dynamic_region.php` then the region node.
- P7 Design files of the style (custom CSS/JS linked in `style_code`, from `get_items_in_style`) open as editable tabs. The designer opens the design's own managed files (`designer_file/read|save`, `style_designer.js` ~48933); the classic theme file is listed locked (~49688), edited in the theme screens. Built: the conversion makes them managed file entries (`pg_classic_style_managed_files()`).
- P8 Site-wide search of styles, design files and regions (`get_styles`, `get_design_files`, `get_designer_regions`, `get_dynamic_regions`, `update_page_designer_properties`): dropped. The panel palette's style result links `page_designer.php?style_id=` (`assets/js/backend.src.js` ~2005), which the page ignored: done in #2013, it opens `edit_<style_type>_style.php?id=`, `view_styles.php` without a type.
- P9 Device presets (six sizes, scaled frame, size badge, `localStorage` `deviceType`/`previewWidth`): parity, the designer's responsive preview with breakpoints.
- P10 System style read-only and the "System Tags" modal (`#editor_region_modal`): dropped; the code view and the palette's region nodes cover them.

Related: docs/memory/plans/legacy-page-designer-l5-list.md, docs/memory/plans/legacy-page-designer-retirement.md, docs/memory/designer/layout-code-tab.md, docs/memory/designer/classic-regions-in-designer.md
