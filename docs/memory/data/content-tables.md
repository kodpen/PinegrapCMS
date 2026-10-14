---
name: content-tables
description: Columns, joins and edit-screen URLs for page, pregion, cregion, dregion, forms/form_data, style, ads and comments; the form_data.id vs forms.id trap.
---

## What does `page` hold?
- `page_id`, `page_name`, `page_folder`, `page_title`. Frontend URL: `OUTPUT_PATH . page_name`.
- `sitemap` / `noindex` / `nofollow` are TINYINT flags. `sitemap = 1` and `noindex = 1` cannot coexist; the save side zeroes `sitemap`.

## `pregion` (page regions)
- `pregion_id`, `pregion_name`, `pregion_content`, `pregion_page` (FK -> `page.page_id`).
- Edited from the page's frontend URL, NOT edit_page.php.
- Needs JOIN: `LEFT JOIN page ON pregion.pregion_page = page.page_id`.

## `cregion` (common / designer regions)
- `cregion_id`, `cregion_name`, `cregion_content`, `cregion_designer_type`.
- `cregion_designer_type = 'no'` -> common region -> `edit_common_region.php?id={cregion_id}`.
- `cregion_designer_type = 'yes'` -> designer region -> `edit_designer_region.php?id={cregion_id}`.

## `dregion` (dynamic regions)
- `dregion_id`, `dregion_name`, `dregion_code`. Edit: `edit_dynamic_region.php?id={dregion_id}`.

## `forms` + `form_data`
- `forms.id` = main record of a submitted form -> `edit_submitted_form.php?id={forms.id}`.
- `form_data.id` = one row per field (`form_data.id` != `forms.id`!).
- `form_data.form_id` = FK -> `forms.id`. `form_data.data` = field value (searchable content).
- For links use `form_data.form_id`, not `form_data.id`.

## `style` (page styles)
- `style_id`, `style_name`, `style_code`. Edit: `edit_custom_style.php?id={style_id}`.
- Visual-designer designs are also `style` rows; see docs/memory/designer/multi-page-design-model.md.

## `ads` and `comments`
- `ads`: reachable only when `ADS === true`; edit `edit_ad.php?id={id}`.
- `comments`: edit `edit_comment.php?id={id}`.

Related: docs/memory/designer/multi-page-design-model.md
