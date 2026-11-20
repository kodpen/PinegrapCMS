---
name: designer-panels-numbers
description: Plan designer-panels' feature numbers D1 to D62 and K1 to K12: which PR built each, the memory file and the test that hold it; checked against the code at the close-out (#10760)
---

## How was it checked?
- At the close-out (2026-11-20) every step's PR was merged, each feature's function or label was found in `style_designer.src.js`, its part or `includes/designer_screen.php`, and its tests passed (`php tools/test.php designer_`). The look beside the drawings was not compared there: `designer-panels-differences.md`.

## Options panel (D1 to D28)
- D1, D2 right column and bottom strip: #10210, `designer-right-column.md`, `designer_right_column_test`, `designer_bottom_panel_resize_test`.
- D3, D6 to D9, D11 identity row, section cards, counts: #10240, `designer-options-section-cards.md`, `designer_options_changed_test`, `designer_undo_selection_test`.
- D4, D5, D14 three tabs: #10321; Style and Advanced #10443, `designer-options-tabs.md`, `designer-options-style-advanced.md`, `designer_options_tabs_test`. Several selected: #10468, `designer-options-multiple.md`.
- D10, D12, D13 controls, changed dot, size badge: #10285, `designer-options-controls.md`, `designer_options_controls_test`.
- D16, D17 spacing box and size chooser: #10564, `designer-spacing-box.md`. D18 corners: #10633, `designer-corner-box.md`.
- D19, D20 layout by context: #10504, `designer-options-layout.md`.
- D21 to D24 search that adds: #10658, `designer-options-search.md`, `designer_options_search_test`, `designer_options_search_fold_test`.
- D25, D26 "Only what I changed": #10699, `designer-options-review.md`, `designer_options_review_test`.
- D15, D27, D28 quick strip, copy and paste style: #10719, `designer-options-copy-style.md`, `designer_style_copy_test`.

## Overview tree (D29 to D44)
- D29, D30, D33, D36 rows, sections, sticky path: #10207, `overview-long-page.md`, `designer_overview_rows_test`.
- D31, D32 folded chains and hints: #10235, `overview-chains-hints.md`.
- D34, D35 tree search and page map: #10303, `overview-search-map.md`, `designer_tree_search_test`.
- D37 to D39 wide drop targets: #10263, `overview-drop-targets.md`, `designer_overview_drop_test`.
- D40 long drags: #10309, `overview-long-drags.md`, `designer_overview_long_drag_test`.
- D41 to D44 moving without dragging: #10588, `overview-move-without-drag.md`, `designer_overview_move_test`.

## Page strip (D45 to D53)
- D45 the design's menu: #10206, `designer-page-strip.md`, `designer_design_menu_test`.
- D46 to D48 open group box, chips, "+N": #10245, `designer-tab-groups.md`, `designer_tab_strip_test`.
- D49 tab states: #10291, `designer-tab-states.md`, `designer_tab_states_test`.
- D50 to D52 Pages window: #10607, `designer-pages-window.md`, `designer_pages_window_test`.
- D53 suggested groups: #10708, `designer-tab-suggested-groups.md`, `designer_tab_suggest_test`.

## Canvas bar (D54 to D62)
- D54 to D57, D61, D62 zones, View menu, folding: #10205, `designer-canvas-bar.md`, `designer-narrow-screen-bars.md`, `designer_canvas_bar_test`.
- D58 Preview menu: #10244, `designer-preview-menu.md`, `designer_preview_menu_test`.
- D59, D60 Prototype mode in the bar: #10507, `designer-canvas-bar-prototype.md`.

## K1 to K12
- The chrome's tokens (#10179) and every step kept them: `designer-panels-keep.md`. Tests that hold them: `designer_chrome_contrast_test`, `designer_panel_names_test` (K2), `designer_panels_on_demand_test` (K3), `designer_panels_readonly_test` (K4), `designer_collapse_state_test` (K10), `designer_tab_layout_test` (K7), `designer_parts_test` (K12).
