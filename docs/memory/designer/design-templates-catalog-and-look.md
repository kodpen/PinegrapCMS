---
name: design-templates-catalog-and-look
description: A design template's own data and looks: catalog and contact groups made on opening, their placeholders, suggested look and palette, highlights, gallery drawing, dark sections, ratio classes.
---

## What data does a template bring?
- `'catalog'`: product groups and products, made when the template opens and only with the shop (`_pg_tpl_catalog()`). A group is found again by its name (`title`, in the opening language) under its parent, a product by its SKU; only what is missing is made. Pictures ship in `includes/design_templates/<id>/` and are copied to a folder of their own. Prices per currency in major units (`'USD' => 89, 'TRY' => 3499`); a new product ships to every shipping zone.
- `'contact_groups'`: one of that name is used, otherwise made (`_pg_tpl_contact_groups()`).
- Placeholders: `{{product_group:<key>}}` (id; `root` = the site's top group, 0 without the shop), `{{product_group_path:<key>}}` (its address on a catalog page), `{{product_group_image:<key>}}` (its picture, a sample photo when none), `{{contact_group:<key>}}`. A value that is only the placeholder becomes a number.

## How does a template suggest a look?
- `'look'` / `'palette'`: the suggested theme (`pg_design_template_theme()` keeps only keys this installation has); `'highlights'`: the points on the installer's card.
- `'thumb'`: the gallery drawing, e.g. `'store'` → `_pg_design_thumb_store()`; without one the generic drawing. `pg_design_thumb_svg($look, $palette, $class, $label, $kind)` (`includes/fn/design_themes.php`) finds `_pg_design_thumb_<kind>()`. A new drawing uses the same CSS variables (`--tp`, `--ts`, `--tr`, `--tbr`, …): the template window changes only the variables when the palette changes.

## Which Bootstrap traps do templates hit?
- A dark section needs `data-bs-theme="dark"` together with `bg-body text-body`: the theme variables change, but `color` is inherited from the body.
- Bootstrap has no `ratio-3x4`: only `ratio-1x1`, `ratio-4x3`, `ratio-16x9`, `ratio-21x9`.
