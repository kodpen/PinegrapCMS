---
name: design-files-modify-dialog
description: Design files list Modify Selected is an in-page pgUI dialog (no popup); its fields belong to the list form, edit_files.php only takes the post
module: files
---

## How does Modify Selected on the design files list work?
- `pg_design_files_modify_dialog($user, $form_id)` (`includes/fn/files.php`) returns a `<dialog id="design_files_modify_dialog">`: Folder (`move_to_folder`), Design (`edit_design`, role 1 or above only) and Optimize Images (switch + mode). The pick lists carry `form="<form_id>"`, so they post with `view_design_files_form`, which holds the token, `action`, `send_to` and the ticked `files[]`.
- The switch and mode select have no name; Save writes the mode (`optimize` / `resize`) or '' into the dialog's hidden `optimize` field, sets `action=edit` and submits in `pgUI.dialog.hide(dialog).then(...)` (`dialog-result-after-close.md`). With optimizing on, it first prepends the "Optimizing images..." status notice to `#content` and posts 500 ms later (`bootstrap-6-loader-metrics.md`).
- A button with `data-pg-modify-open` opens it empty (pick lists back to the first option, switch off).
- `edit_files.php` on GET redirects to `view_design_files.php`; its POST (edit, delete) is unchanged.

## Why not the popup any more?
- It was a 500x500 `window.open()` writing into `opener.document.form` (#6523), like Products before #6617: stopped by popup blockers, a new tab on phones, no focus trap, a hidden theme menu and Bootstrap 3 `col-xs-` columns.

## What is not part of it?
- Delete Selected still asks with `window.confirm()` (the `data-confirm-content` handler in `backend.src.js`), see `product-modify-dialog.md`.

## Where is it tested?
- `tests/bulk_popup_texts_test.php` (`test_bulk_popup_texts_modify_files_is_a_dialog`), plus pins in `tests/panel_help_lines_test.php`, `tests/content_edit_names_test.php` and `tests/marketplace_products_names_test.php` (the notice).

Related: docs/memory/ui/product-modify-dialog.md, docs/memory/ui/design-files-screens.md
