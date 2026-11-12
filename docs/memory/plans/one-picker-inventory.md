---
name: one-picker-inventory
description: What chooses a file, a picture, a page or a folder in the software today (2026-11-10, with call counts): what the one picker takes over, and the record searches that model its later kinds
---

## How does a screen choose a file or a picture today?
- Type-to-search combobox (`file_picker_search.php`, kinds `site_icon`, `public_image`, `file`): the short link list's quick edit cell (`ui/file-picker-search.md`). Nothing is listed before a letter is typed. The five Settings pictures and the short link File field on its form and in its File Manager wizard are picker fields since PK5 (`ui/picker-field.md`).
- The editor's image browser `editor_select_image.php` (1,144 lines, a pop-up window: folder filter, search, thumbnails, Unsplash, quick upload through `add_file.php`). Opened by CKEditor's Image dialog (`filebrowserImageBrowseUrl`, `ui/rich-text-editor-files.md`), by `software_image_picker()` in `backend.src.js` (product builder pictures, the contact photo) and by the designer's `sdOpenImagePicker()`.
- The product builder's own picture strip, `pg_pb_render_image_picker()` (`product_builder.php`).
- The image editor: Import, From the site (the Sticker part's Files list, `openImportFiles()`), Video from the site, sounds from Files (`ui/image-editor-import.md`).
- The Workspace's "from the files" picker (`ws_library_file()`, `includes/workspace/library.php`).
- The theme designer's `select_files_for_theme_designer()` (`get_theme_designer_module.php`).
- The previous editor (TinyMCE 3): `get_file_list.php`, `get_image_list.php`, `get_media_list.php`, `get_page_list.php` and `get_folder_list.php` under `assets/lib/tiny_mce_3_5_10/`.

## How does a screen choose a page today?
- `select_page()`: 56 calls in 12 files, 36 of them in `includes/forms/page_form.php` (Create Page and Edit Page: picker fields since #8782, `ui/page-form-page-fields.md`); the rest in the product builder, Settings (commerce, security, privacy), the user screens and Edit Offer.
- `get_page_options()`: 19 calls in 9 files (short link forms, menu item, calendar event, e-mail campaign profile, panel actions).
- The visual designer's widget settings: `_swPageOptions()`, 46 calls, a value being a page id or `tab:<key>` for an unsaved tab, the list from `list_all_pages` (`designer/designer-page-pickers.md`).
- A link in content: the editor's link browser `editor_select_page_or_file.php` (638 lines, a pop-up with Pages, Files and Short links), opened by CKEditor's Link dialog and by the designer's `sdOpenFilePicker()` (8 calls), which plants a fake `CKEDITOR.tools.callFunction` on its window to catch the pick.

## How is a folder chosen?
- `select_folder()`: 47 calls in 26 files, a select of the folder tree (`ui/settings-folder-pickers.md`, `filtered-folder-picker-path.md`). No step of this plan: the owner named files and pages. The picker's tree makes a `folder` kind possible later.

## Which record searches exist already?
- Server-search comboboxes (`data-pg-source`): users (`user_search_action.php`, `ui/user-picker-search.md`), products and contacts on New cart quote (`add_cart_quote.php?request=products` and `contacts`), the Google product category, audience rules (`pg_audience_picker_search()`).
- They model the later kinds (a product, a quote, a shipping method, a contact): a kind with a search and a list, without a folder tree.

## What does the count come to?
- Three separate browsers of files (the File Manager, the image browser, the link browser), two families of page selects, one search field, and screens with lists of their own. The picker replaces all of them but the File Manager itself.
