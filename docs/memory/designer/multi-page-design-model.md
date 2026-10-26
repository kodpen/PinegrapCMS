---
name: multi-page-design-model
description: Data model of multi-page visual designer designs (style + page_style, page_tree_json), tree-reading helpers, design-page detection, start screen, recycle bin rules.
---

## What is a design and where is its data (2026.4.4, migration 4.22)?
- A design = a `style` row with `style_layout='visual_designer'` + N pages linked by the `page_style` FK.
| Where | What |
|---|---|
| `page.page_tree_json` | the page's layout tree |
| `page.page_tree_code` | HTML generated from the tree; render reads THIS (`get_page_content.php`) |
| `style.style_custom_css/js/fonts` | assets shared by all pages (Styles / JavaScript / Fonts / Theme panels) |
| `style.theme_id`, `collection`, `additional_body_classes`, `style_head` | already style-level |
| `style.style_tree_json`, `style_code`, `page.page_custom_*` | FALLBACK only; the new editor never writes them; save resets `page.page_custom_*` |

## How must queries read the tree?
- Always `pg_page_tree_sql_expr()` = `COALESCE(NULLIF(page.page_tree_json,''), style.style_tree_json)`; never LIKE directly on `style.style_tree_json`. Single page: `pg_page_tree_json($page_id)`.
- `pg_multi_page_design_ready()` probes all five columns at once; bridge rule: never assume a new column exists.

## What counts as a "visual designer page"?
- NOT `layout_type='system'`: that column is `system` on every standard page and legacy system layouts use it too.
- Criterion is `pg_page_is_visual_design()`: the page's own `page_style` is `visual_designer` OR it has its own tree.
- In SQL use its twin `pg_page_visual_design_sql()` (never `layout_type`): a converted classic page keeps `layout_type = 'custom'` and its layout file and is still listed, counted, scheduled and asset-cleaned with its design (#PR). Left on `layout_type`: only the legacy same-name bridge in `pg_designer_load_pages()`; the language redirect tests the tree alone, as `translate/render.php` does.
- Edit link everywhere via `pg_page_edit_url()` (view_pages, explorer, edit_page.php redirect).
- `select_style()` does not list visual designs: they cannot be assigned as folder/page default.

## What do the start screen and the lists show?
- `view_system_styles.php` (`pg_designer_start_screen`, menu `$menu_items[21]`): the four starts (New, Import -> `?start=import`, Paste -> `?start=paste`, Template -> `?start=template`), then the designs. Editor exit and delete return here. Layout, menu and rename: docs/memory/designer/designer-home-screen.md.
- `view_styles.php` shows only `style_layout <> 'visual_designer'`; `add_style.php` is to be removed.
- No table: the site's design large, the others as tiles. Menu Delete -> `designer/design_delete` (live pages go to the bin, the style with them once the bin holds styles).
- Delete confirmation: the page count comes from `pg_designer_list_pages()` (binned pages not counted), folders from `pg_designer_list_folder_counts()`; binned pages are left untouched. The one delete path, `pg_designer_delete_design()`, and why it leaves shared component rows: `shared-components-panel.md`.

## What are the recycle bin and folder rules?
- A binned page is not listed as a tab: every query reading a design's pages adds `pg_designer_not_binned_sql()`.
- Tab Delete -> `designer/page_delete` -> explorer `explorer_recycle_delete` (Recycle Bin, `page_style` kept).
- Tab Detach -> `api.php designer/page_detach` (new style with the source's framework, look, palette and meta tags, and the assets the page loaded: `pg_design_assets_for_page()`). The last saved page cannot be detached ("Delete the design instead"). The last page can be deleted: it goes to the Recycle Bin, the design stays with no pages and the editor returns home (`_pgLeaveEmpty()`).
- `page_folder` is never written as 0 (`pg_designer_save_page` turns it into root); a page with 0 is invisible in the File Manager and cannot be sent to the bin.

Related: docs/memory/designer/multi-page-designer-editor.md
