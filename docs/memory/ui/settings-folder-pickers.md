---
name: settings-folder-pickers
description: Site Settings folder pickers (product images, Grant Private Access, API uploads) leave out the Workspace's folders and the Recycle Bin; their saves refuse them; a stored one is shown marked
module: auth
---

## Which pickers?
- E-Commerce › Store "Folder for product images" (`product_upload_folder_id`), E-Commerce › Payment "Grant Private Access" (`ecommerce_private_folder_id`, private folders only), Integrations › Uploads "Upload Folder" (`api_upload_folder_id`).

## What do they leave out, and why?
- The Workspace's folder (`config.ws_folder_id`, read by `pg_workspace_folder_id()`, never created there: `ws_root_folder_id()` makes it on first use) and everything below it. Its files belong to conversations: other customers' guest channels, internal notes. "Grant Private Access" there gave every buyer an `aclfolder` row reaching all of them; product images there could not be shown to visitors; API uploads showed up as a channel's files (#5024).
- The Recycle Bin, as `select_folder()` already did.

## How?
- `pg_settings_select_folder($stored, $access_type)` (includes/fn/auth.php): `select_folder()` with the Workspace folder as its excluded folder (the exclusion drops its subtree). `select_folder()` itself is unchanged: page and user rights pickers still list the Workspace, a product question left open.
- A stored folder that is no longer a choice is appended selected with "(no longer offered)", so the screen shows what is saved ([[stored-choice-kept]]).
- Saves (`commerce.save.php`, `api.save.php`) ask `pg_settings_folder_choice_ok()`, pure part `pg_settings_folder_choice_verdict($id, $rows, $workspace, $bin)` (0 passes), and mark the field "Choose a folder outside the Workspace and the Recycle Bin."; nothing is written.
- Tests: `tests/settings_folder_choice_test.php`.
