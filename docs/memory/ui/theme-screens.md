---
name: theme-screens
description: Edit Theme, the themes list and Private Label Branding on cards: labelled fields, theme tools in the toolbar, named list marks, the private label switch
---

## How is Edit Theme drawn?
- `edit_theme_file.php`: four `md:col-6` cards (Theme File Name, the shared File Access Control card for Folder, Theme File Description, Activate Theme to Make it Live) instead of a `table.field` that no panel CSS styled, with `<h2>` headings in its cells.
- Every field has a `<label for>`: `name`, `folder` (from `pg_design_file_form_access_card()`, `design-file-shared-form.md`), `description`; the two activation checkboxes are `.form-field` switch rows. Before, Name, Folder and Description had no id and a `<td>` caption, so a screen reader named none, and Folder was an unstyled native select.
- Posted names, the POST branch, the export and the missing-file note are unchanged.

## Where are the theme tools?
- Edit Theme / Edit CSS, Export (system theme) and Download File (custom theme) sit in the screen's `#button_bar` under the file name. The sticky save bar keeps Save, Cancel, Delete: with five actions it stacked into a ~242 px sticky column on a phone.

## How does the themes list name its marks?
- `view_themes.php`: the active desktop and mobile `task_alt` marks are `role="img"` with `aria-label` Yes; the `folder` ligature before a folder name is `aria-hidden` (`list-and-table-screens.md`).

## How is Private Label Branding drawn?
- `private_label.php`: one Branding card. The Enable Private Label switch is a `collapse-switcher` for `#private_label_fields` (opened with `show` when the setting is on), replacing an inline `style.display` script.
- Logo URL and Control Panel Stylesheet URL, then the five footer link label/URL pairs (drawn in a loop), each with a label and id equal to its posted name. The logo hint is `form-text` under the field.

## Which test covers it?
- `tests/theme_screens_names_test.php` (source scans); the save itself: `tests/private_label_config_test.php`.
