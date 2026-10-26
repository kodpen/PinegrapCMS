---
name: dynamic-region-in-designer
description: A dynamic region placed in the visual designer: the region node's attribute ($dregion_attribute), its PHP code as a code-view tab for administrators, the dynamic_region_code endpoint
---

## What does a dynamic region node print?
- `_render_region_tag()` (PHP) and `_sdRegionTag()` (JS) print `<dregion "x">name</dregion>` when `props.regionAttr` is set, else `<dregion>name</dregion>`. `get_page_content.php` reads the text between the first two double quotes of the tag as `$dregion_attribute` and runs the `dregion` row's code; nothing changed there.
- `pg_dregion_attribute_clean()` / `_sdDregionAttr()` drop `"`, `<`, `>` and control characters, trim, cap at 255 characters; the rest stays raw (a style's own tag is raw too). Why: a quote would cut the value, a `>` would end the tag, and the page is evaluated as PHP before dregions are expanded, so `<?` must never reach it.
- The Region panel shows the Attribute field for the dynamic type only (`_sdDregionPropsHtml()`).

## Who may edit the code from the designer?
- Administrators (role 0) only, the right `edit_dynamic_region.php` asks (`validate_area_access($user, 'administrator')`). Designers (1) place the region and set its attribute, but get no code button. Never widen this: the code runs as PHP on every page placing the region.
- `_design.dynamicRegionCode` (`includes/designer_screen.php`) shows the "Edit PHP code" button; `_sdDregionCodeOpen(name)` opens a file tab (`dregion:<name>`, language `php`, which the editor shows as HTML as the classic screen does).
- api.php action `dynamic_region_code` (`includes/panel/designer.php`), not exempt (general gate: session, role <= 1), then `validate_token()` and a role 0 check before any query. `read` returns `content`; `save` writes `dregion_code`, `dregion_user`, `dregion_timestamp` of the row found by name and logs "dynamic region (name) was modified", like the classic screen. Page cache: `save` dirty, `read` clean.
- Renaming and deleting stay on the classic screen.

## Tests?
- `tests/designer_dynamic_region_test.php`: cleaning, both emitters equal (Node), the attribute as get_page_content.php's expressions read it, the action row and the handler's check order, the screen flag.
