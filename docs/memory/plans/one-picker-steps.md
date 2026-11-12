---
name: one-picker-steps
description: Steps PKV and PK1-PK10 of plans/one-picker.md: screens, registry and endpoint, dialog, upload, pages, the field, page fields, editor and designer bridges, remaining screens, close-out; open questions
---

## Which steps are there?
- S under 400 product lines, M about 1,000; each step with its tests and its topic file `ui/picker-<part>.md`; from the tenth such file on, `ui/picker-overview.md`.

- PKV (S, no product code) The screens, drawn in `plans/one-picker/` (`one-picker-visuals.md`). Done in #8511, approved by the owner (2026-11-10, #8514).

- PK1 (M) Registry and endpoint: `includes/fn/picker.php`, `picker.php`, the kinds and the requests. Done (#8515, ui/picker-registry.md)

- PK2 (M) The dialog: `assets/js/pg_picker.js`, tree, grid and list, search, keyboard, the phone sheet. Done (#8562, ui/picker-dialog.md)

- PK3 (M) Upload, Recent and several picks. Done in #8630 (`ui/picker-upload.md`).

- PK4 (M) Pages in the dialog. Kind `page`: folders, the page type filter, search, archived and binned marks (neither can be chosen, owner 2026-11-10, #8514), and extra entries a caller hands in (the designer's unsaved tabs, `tab:<key>`). Done in #8727 (`ui/picker-pages.md`).

- PK5 (M) The field. `pg_picker_field()`: the combobox with Browse and the chosen value with its picture; the Settings pictures and the short link File fields move to it. This delivers what F3 of the search plan asked for. Done in #8751 (`ui/picker-field.md`).

- PK6 (M) Page fields, first part. Typed page kinds and the field's page wording; Create Page and Edit Page move to `pg_picker_field()` and drop their `<template>` fill Settings (Commerce, Privacy, Security) and the User Start Page; a stored archived or binned page stays, marked, and Browse opens on its folder. Done in #8782 (`ui/page-form-page-fields.md`).

- PK6b (M) Page fields, second part. The rest of `select_page()` and `get_page_options()`: the product builder, Edit Offer, the short link form and the File Manager short link window, the menu item, the calendar event; whether quick edit keeps its own lists. Done in #8803 (`ui/picker-page-fields-screens.md`).

- PK6c (S) The e-mail campaign profile's Body Page (by view access) and the form page selects (`any form`, the product builder's custom form). Done in #8824 (`ui/picker-page-fields-screens.md`).

- PK7 (M) The editor bridge: CKEditor's Link and Image dialogs open the picker; TinyMCE 3 keeps its lists (#8835). Done in #8858 (`ui/picker-editor-bridge.md`).

- PK8 (M) The designer bridge: link and picture fields through `pgEditorPicker`, the fake `CKEDITOR` gone, Browse under the widget page selects, the page type pane on picker fields. Done in #8884 (`ui/picker-designer-bridge.md`).

- PK8b (S) The link window `editor_select_page_or_file.php` and `filebrowserBrowseUrl` deleted, no redirect, listed in Cleanup (#8836). Done in #8896.

- PK9 (M) Product builder pictures, contact photo (`software_image_picker()`), theme designer file list; `editor_select_image.php` deleted. Done in #8904 (`ui/picker-remaining-screens.md`).

- PK9b (M) The image editor's From the site lists (after V10, #8854) and the Workspace's "from the files" picker with its chat attachment refusal kept (`platform/file-copy-chat-attachments.md`).

- PK10 (S) Close-out. Helpers nothing calls are removed, `ui/picker-overview.md` is current, the PR body names the `[NEW]` changelog line, the four plan files and `plans/one-picker/` are deleted.

## What is left out?
- The later kinds (a product, a quote, a shipping method, a contact) and a `folder` kind for `select_folder()`: a step each when the owner asks, a kind row with its search and its list.
- The bridge of the software's own editor: written with that editor.

## Which questions are open?
- Each is asked as an `architect` issue before the step that needs it.
- None now: the two PK7 questions are decided on #8835 and #8836 (the PK7 and PK8 lines).
