---
name: deleted-picture-fallback
description: A contact or product picture whose file was deleted in the File Manager falls back to the letters or icon (pg_contact_picture_name(), is_file() on product thumbs)
module: contacts
---

## What does deleting a file leave behind?
- `pg_explorer_handle_delete_files()` removes the `files` row and the disk file but keeps `contacts.file_id` and `products.image_name`; nothing clears them, and they stay as they are (no stored data changes).

## What was the symptom?
- The order view, Edit User, Edit Contact and the Contacts list built the picture address as `PATH . $file['name']` with the row gone, so `src`/`data-src` was `/`: the browser fetched the site's home page as an image (once per Contacts list row), the screen showed the lazy failure mark or an empty circle, and `$file['name']` on null raised PHP 8 warnings.
- The Out of stock / Running low badge (`widget_9.php`) and the Create Order cart (`local_sale.php`) printed an `<img>` for a missing file: an empty coloured badge or a grey 40 px box.

## How do screens read a contact picture now?
- `pg_contact_picture_name($file_id, $names = null)` in `includes/fn/contacts.php` returns the files row's name, or `''` when the id is 0 or the row is gone; `$names` is an id => name map a list already read (`pg_contact_list_rows()`).
- On `''` each screen takes its own no-picture branch: the letters (`pg_avatar_markup_for()`) on the order view, Edit Contact and the Contacts list; the initial or the person icon on Edit User. `contacts.image` (an address) counts only when `file_id` is 0, as before.
- Edit Contact keeps a dangling id in its hidden `file_id` input, so a Save changes nothing.
- `print_order.php` no longer builds a contact picture: it was never printed, read a column its query lacked (`$row['image']`) and decoded the whole picture.

## And product thumbs?
- The two screens print the `<img>` only when `is_file(FILE_DIRECTORY_PATH . '/' . basename($image_name))`, else their icon (`bi-exclamation-diamond` / `bi-box-seam`).
- Not changed: the order view and print order product columns (an empty `alt=""` image draws nothing there), `output.php`, `widget_3.php`, `welcome.php`, `product_builder.php`.

## How is it pinned?
- `tests/contact_picture_fallback_test.php`: the helper, and source checks on each branch. Not rendered in a browser.

Related: docs/memory/ui/panel-avatar.md, docs/memory/ui/lazy-picture-failure.md
