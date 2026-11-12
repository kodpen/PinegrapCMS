---
name: legacy-layouts-output-join
description: Where a page type's layout output joins the page, the names built outside a written class attribute, and the gate a map at output needs (LL1 census, #8968)
---

## Where does the layout's output join the page?
- In one place: `get_page_content.php` replaces each `<system>` tag (the page's own and a named one of another page) with `$system_output`, the `get_<type>()` result, right before `preg_replace('/<system>…')` (about line 5384). `render_layout()` (`includes/fn/content.php`) draws the template or `data/layouts/<id>.php` inside it.
- The canvas preview of the region (`pg_layout_region_preview()`, [[legacy-region-preview]]) runs the same `get_page_content()`, so a map there reaches the canvas too.
- Elsewhere: `includes/fn/forms.php` draws the view order screen in the classic secure frame (not a design page); `widgets_order_preview.php` reads `get_order_preview()` values only.

## What does the join know about the design?
- `$pg_design_fw = pg_design_framework($row['style_framework'])` (line ~228) on the same request. An empty or unknown framework reads as `bootstrap5`, and a classic style has none, so the gate must be `style_layout == 'visual_designer'` and key `bootstrap5`, never the key alone.

## Which names are not in a written class attribute?
- Tags with PHP inside them: 2189 in the starting layouts. `pg_lbs_html()` matches `<[a-zA-Z][^<>]*>`, so a tag holding `<?= … ?>` is skipped; their names: btn-default 124, input-group-btn 104, img-responsive 82, visible-xs-inline-block 72, center-block 68, btn-xs 16. A pass on the file text misses them; a pass on the output sees them.
- No `'class' =>` arrays and no class names in PHP string literals in the layout files.
- Markup a get_*.php puts in a variable (`$system`, `$currency_system`, `$search_system`): hidden fields and scripts, no Bootstrap 3 names; liveform messages and inline feedback are already 5.3.8 (`alert`, `invalid-feedback`, `btn-close`).
- Layouts kept in the database, outside any file: `form_item_view_pages.layout` (38 rows per site) and `form_list_view_pages.layout` and `search_advanced_layout` (104 texts per site): 40 + 60 renamed uses per site (col-sm-offset-1 24, `label` 10, `embed-responsive` 12, Themify icons). A converted copy of them needs a column.
- Header and footer texts of the type rows and visitor content (descriptions, form answers) reach the same output.

## Which names are ambiguous at output?
- `label`, `checkbox`, `radio`, `panel`, `well`, `media`, `thumbnail`, `caret`: Bootstrap 3 names that a designer's own content can use with a style of its own.
- `pg_lbs_theme_names()` (`bg-secondary` → `legacy-bg-secondary`) is for a Legacy theme's sheets; a Bootstrap 5.3.8 design has no such sheet, so a layout pass must not carry that rule.
