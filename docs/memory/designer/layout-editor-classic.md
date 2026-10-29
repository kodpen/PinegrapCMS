---
name: layout-editor-classic
description: The layout editor screen edit_page_layout.php (L5a, #3084) for a page not on a visual design: who reaches it, the layout_code action, hosted sites, where Edit Layout goes
module: layouts
---

## What is it?
- `edit_page_layout.php?id=<page>`: the custom layout (`data/layouts/<id>.php`) of a page whose style is not a visual design (a classic style not yet converted, a `style_file` style, an older cell-based style), alone in the code editor bundle (`pg_code_editor_attach()`, language `php`). Decided in #3071 (b): the layout file does not depend on the style, so its editor must not wait for the style's conversion (`workflow/decisions-made-product-2.md`).
- The screen draws the editor only. Read, Regenerate and Save go to api.php `layout_code` (`includes/panel/designer.php`), the designer's layout tab action: token, role <= 1 and the page's custom layout checked there again; `pg_layout_read()` / `pg_layout_save()` underneath. Regenerate puts the generated text in the editor, unsaved. A save equal to the generated text, white space aside, leaves the page unmodified (toast says so).

## Who reaches it, and when?
- `validate_area_access($user, 'designer')`: roles 0-1, as the classic layout editor. A missing page, or one without `layout_type = 'custom'` on a page type of `check_if_page_type_supports_layout()`, gets a 404 screen.
- `pg_layout_edit_url($page_id, $style_id, $designed, $role, $hosted)` (`includes/fn/layouts.php`): a page whose own style is a visual design goes to `edit_system_style.php?id=<style>&page=<id>` (+ `&layout=1` per `pg_layout_tab_param()`); any other page to `edit_page_layout.php?id=<id>`. The screen asks the same function and redirects a designed page to the designer.
- "Edit Layout" in `edit_page.php` and `view_fields.php` use it (`view_fields.php` adds `send_to`; the screen's back control falls back to `edit_page.php?id=`).

## Hosted sites?
- `pg_hosted()`: the editor is read only with a note, no Save or Regenerate; read shows the generated layout, which is what the site draws.

## What is left for L5?
- `page_designer.php?type=layout` is no longer linked from Edit Layout; `get_layout` / `update_layout` go with `page_designer.php` (`plans/legacy-page-designer-l5-list.md`). Help row `edit_page_layout` in `get_help_url.php`.

## Tests?
- `tests/designer_layout_retirement_test.php`: the URL choice (designed -> layout tab, other -> this screen, hosted too) and the screen's gates and wiring.
