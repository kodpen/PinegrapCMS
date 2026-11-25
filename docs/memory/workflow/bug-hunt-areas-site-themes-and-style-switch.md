---
name: bug-hunt-areas-site-themes-and-style-switch
description: The bug hunt in site themes and the style switch (area 51, #11474): activating a theme, copying and deleting a style, what a page keeps when its style goes; what was fixed, read clean and left
---

## Which area was it?
- Area 51 "site-themes-and-style-switch": activating a theme file and the page styles it moves (`includes/forms/theme_file_form.php`, `includes/fn/theme_list_edit.php`, `edit_theme_file.php`), copying a style (`duplicate_style.php`), deleting one (`delete_styles.php`, `edit_custom_style.php`, `pg_designer_delete_design()`), the Recycle Bin's style record (`includes/explorer/recycle_pages_styles.php`) and the style a page falls back to (`get_style()`, `get_activated_style()`).

## What was fixed?
- A theme set to No alone and later to Yes moved the pages back to the styles of its last replacement, undoing the styles chosen while it was on: its rows are now written when it goes off either way (`platform/theme-activation-page-styles.md`).
- Duplicate of a design copied its pages in the Recycle Bin too; the copies sat in the bin folder with no bin row, never purged (`designer/style-copy-delete-binned-pages.md`).
- A classic style used only by pages in the bin could not be deleted, the message naming a page nobody could find; once the bin holds styles those pages no longer hold it (same file).

## What was read and found clean?
- `pg_designer_delete_design()`: refusals before writes, the style's record before its pages move, folders and mobile styles cleared and put back on restore, prototype links ended.
- `pg_recycle_style()` / `pg_recycle_restore_style()`: the record before the deletes, the id or a new one, the renamed system style's body class, settings put back only where nothing else was chosen.
- `get_style()` / `get_activated_style()`: a missing folder ends the walk, mobile falls back to desktop.
- `duplicate_style.php`: a missing id refused, framework, look, colour mode, meta tags and asset rows carried, schedule times and home flag not.

## What is left?
- A page with no style of its own gets no theme row; after a round trip through another theme it keeps that theme's style (`platform/theme-activation-page-styles.md`).
- Not checked: what a copied design's form pages keep of their `custom_form_pages` row (Duplicate copies page rows only; Duplicate Page copies the form, `designer/custom-form-page-copy.md`).
- Deleting a theme on Edit Theme File does not call `pg_file_versions_delete_file()` as the file screens do; outside this area's faults, not changed.
