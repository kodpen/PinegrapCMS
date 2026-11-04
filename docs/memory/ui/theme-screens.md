---
name: theme-screens
description: Create and Edit Theme, the themes list and Private Label Branding on cards: labelled fields, theme tools in the toolbar, named list marks, the private label switch
---

## How are Create Theme and Edit Theme drawn?
- Both use the product_builder layout through `pg_design_file_form_layout()` (`design-file-shared-form.md`, #6746). Edit: Main Informations (Name, Description) in the form column; in the status column a Summary (Type System/Custom, File Size when the file is on the disk, Access; the missing-file note on top instead of above the toolbar), the Activate Theme to Make it Live switches and the shared File Access Control card; Save, Cancel, Delete in the bar. Create: Options (theme type, upload) and Description in the form column, File Access Control in the status column, Create in the bar (`#create_button`, relabelled by the type script).
- Every field has a `<label for>`: `name`, `folder` (from `pg_design_file_form_access_card()`), `description`; the two activation checkboxes are `.form-field` switch rows. Name, Description and the switches stay liveform fields, so a refused Save refills them.
- Posted names, the POST branches, the export and the missing-file check are unchanged.

## Where are the theme tools?
- Edit Theme / Edit CSS, Export (system theme) and Download File (custom theme) sit in the screen's `#button_bar` under the file name. The sticky save bar keeps Save, Cancel, Delete: with five actions it stacked into a ~242 px sticky column on a phone.

## How does the themes list name its marks?
- `view_themes.php`: the active desktop and mobile `task_alt` marks are `role="img"` with `aria-label` Yes; the `folder` ligature before a folder name is `aria-hidden` (`list-and-table-screens.md`).

## How is Private Label Branding drawn?
- The Private Label Branding card of Site Settings › Appearance (`includes/settings/appearance.php`, administrator only, #4095; `private_label.php` redirects there). The Enable Private Label switch is a `collapse-switcher` for `#private_label_fields` (opened with `show` when the setting is on), replacing an inline `style.display` script.
- Logo URL and Control Panel Stylesheet URL, then the five footer link label/URL pairs (drawn in a loop), each with a label and id equal to its posted name. The logo hint is `form-text` under the field.

## Which test covers it?
- `tests/theme_screens_names_test.php` (source scans); the save itself: `tests/private_label_config_test.php`.
