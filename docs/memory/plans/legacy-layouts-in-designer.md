---
name: legacy-layouts-in-designer
description: Plan (2026-10-19, not started): how a classic page's custom layout (layout_type 'custom', data/layouts/<id>.php) lives on in the visual designer once page_designer.php retires; Y1-Y6
---

## What is a layout, and why is it the hardest part of the retirement?
- 26 page types (`check_if_page_type_supports_layout()`) draw their system region through `render_layout()`: the generated default `includes/templates/<type>.php`, or, when `page.layout_modified`, the PHP file `data/layouts/<page_id>.php` written from `page_designer.php?type=layout` (`pg_panel_update_layout()`; `generate_layout_content()` is the reset). Hosted sites ignore the file (`pg_hosted()`).
- A layout is a view template over a fixed data contract (`$items`, `$product_groups`, `$field`, `$recipient`…): no queries, no includes; 5 of 88 carry an inline `<script>`.
- The starting site shows the weight: 88 of 180 pages have a custom layout, 86 of them modified (`data/backups/english_default/layouts`, 35k lines: form list view 26, custom form 13, order form 10, photo gallery 6, catalog 5…). Half the look of a typical site is in these files.
- The designer has no such thing: a system widget is a tree with bindings. A `region` node of type `system` prints `<system></system>` and the classic renderer, layout included, fills it (`get_page_content.php`): the look survives conversion, editing it does not.

## What blocks it today?
- Nine designer queries take `layout_type = 'system'` as "page of a design" (`includes/fn/designer.php:1925,2000,2439,2448,2530`, `includes/designer_screen.php:596,674,1606,2869`): a converted custom-layout page is not listed, counted or asset-cleaned.
- `edit_page.php:116` sends every visual-design page to the editor, so its type fields (`pg_page_form_type_fields()`: product group, columns, detail page, form settings…), the Layout Type radio and the Edit/Generate Layout buttons (`:347-362`) become unreachable.
- L5 removes the only layout editor; L4's parity list does not name layouts.

## What has to be built? (one PR each)
- Y1 Queries: the test is `pg_page_is_visual_design()` (`page_style` + own tree), never `layout_type`; test with a custom-layout page on a design.
- Y2 Page-type pane in the designer's settings modal, classic pages only: `pg_page_form_type_fields()` plus the Layout Type radio, saved by the `edit_page.php` handler (`?properties=1` skips the redirect); roles as today.
- Y3 Layout as a code tab: the `system` node of a `layout_type = 'custom'` page opens "Layout (PHP)" in the code editor like a CSS/JS file (R4 pattern). Read and save are `pg_panel_get_layout()` / `pg_panel_update_layout()` moved to `includes/fn/`; whitespace-equal → unmodified stays; Regenerate button; designer role; read-only on hosted; the field-change warning (`add_field.php:956`) stays.
- Y4 Canvas preview (optional): the placeholder shows the region drawn by the server for the signed-in user (`designer/legacy_region_preview`), read-only.
- Y5 Replace with widget, opt-in per page: a system widget of the matching type with its default tree beside the region; the designer restyles it by hand and removes the `system` node when they say so. Classic `/<page>/<group>` addresses vs widget routes need 301s. Never automatic.
- Y6 Retirement check: every `layout_modified` page of a converted style opens its layout tab; Ctrl+G (`page_designer_button`, `includes/fn/output.php:4980`) lands there.

## Open questions?
- `page_type = 'standard'` with `layout_type = 'custom'` (2 starter pages, no layout exists): normalise to `system` on conversion?
- Hosted product: layout tab read-only, or hidden?
- The master-frame question (one tree per page vs one shared component) is in `legacy-page-designer-retirement.md`; it decides L1, not the layouts.

## Where to start?
- Y1 now: safe, independent of R1-R5. Y3 builds on the code editor's file tabs (#522). Y2-Y3 before L4; Y5 after `system-widget-missing-types.md`.
