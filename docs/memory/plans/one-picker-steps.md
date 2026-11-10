---
name: one-picker-steps
description: Steps PKV and PK1-PK10 of plans/one-picker.md: screens, registry and endpoint, dialog, upload, pages, the field, page fields, editor and designer bridges, remaining screens, close-out; open questions
---

## Which steps are there?
- S under 400 product lines, M about 1,000; each step with its tests and its topic file `ui/picker-<part>.md`; from the tenth such file on, `ui/picker-overview.md`.

- PKV (S, no product code) The screens. Done in #8511: the dialog and the field drawn as pictures in `plans/one-picker/`, desktop and phone: files as a grid and as a list, pages, an empty folder, search results, Upload, a chosen value, a missing or binned value; what each shows and which step builds it: `one-picker-visuals.md`. The owner approves them through an Approval issue (`workflow/architect-asking.md`) before PK2 starts.

- PK1 (M) Registry and endpoint. `includes/fn/picker.php` and `picker.php`: the kinds `file`, `image`, `public_image`, `site_icon` and `page`; the requests `list`, `search`, `tree` and `resolve`; the access verdict, the folded name match, paging. `file_picker_search.php` answers through it with its contract unchanged. No screen changes.

- PK2 (M) The dialog. After PKV and PK1. `assets/js/pg_picker.js` and its styles in the panel bundle: tree, grid and list, search, sort, one pick, keyboard, the phone sheet; the file kinds. Shown on one screen (Settings, Site Icon) behind a Browse button; the owner approves the built dialog the same way.

- PK3 (M) Upload, Recent and several picks. Upload by drop or by choice into the open folder under the folder's edit right; a person's recent picks, kept in the browser; `multiple` for the product pictures.

- PK4 (M) Pages in the dialog. Kind `page`: folders, the page type filter, search, archived and binned marks, and extra entries a caller hands in (the designer's unsaved tabs, `tab:<key>`).

- PK5 (M) The field. `pg_picker_field()`: the combobox with Browse and the chosen value with its picture; the Settings pictures and the short link File fields move to it. This delivers what F3 of the search plan asked for.

- PK6 (M) Page fields. `select_page()` and the selects built from `get_page_options()` move to `pg_picker_field('page', …)`; Create Page and Edit Page drop their `<template>` fill.

- PK7 (M) The editor bridge. CKEditor's Link and Image dialogs open the picker (pages, files, short links, pictures; Unsplash kept as a source); `editor_select_image.php` and `editor_select_page_or_file.php` are retired.

- PK8 (M) The designer bridge. `sdOpenFilePicker()` and `sdOpenImagePicker()` call `pgPicker` and the fake `CKEDITOR` goes; the widget page pickers (`_swPageOptions()`) get Browse; texts through `_sdT()`.

- PK9 (M) The remaining screens. The product builder's pictures, the contact photo, the image editor's From the site lists, the theme designer's file list, and the Workspace's "from the files" picker with its chat attachment refusal kept (`platform/file-copy-chat-attachments.md`).

- PK10 (S) Close-out. Helpers nothing calls are removed, `ui/picker-overview.md` is current, the PR body names the `[NEW]` changelog line, the four plan files and `plans/one-picker/` are deleted.

## What is left out?
- The later kinds (a product, a quote, a shipping method, a contact) and a `folder` kind for `select_folder()`: a step each when the owner asks, a kind row with its search and its list.
- The bridge of the software's own editor: written with that editor.

## Which questions are open?
- Each is asked as an `architect` issue before the step that needs it.
- PK7: is the previous editor (TinyMCE 3) bridged to the picker, or does it keep its own lists until it is removed?
- PK7: do the two retired browser addresses answer with a redirect for one release, or go at once?
