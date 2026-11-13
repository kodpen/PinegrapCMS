---
name: panel-picture-change-stamp
description: A panel picture drawn by a file's own address carries its change stamp (?v=, pg_file_stamped_address()), so a picture saved again under its name shows at once (#8913)
module: contacts
---

## What is the rule?
- A picture the panel draws by a file's own address (`PATH` + name) carries the file's change time: `pg_file_stamped_address($base, $name, $timestamp)` (`includes/fn/contacts.php`, pure) gives `<base><name>?v=<files.timestamp>`, bare when the stamp is 0 or missing. `get_file.php` ignores `v`.
- A contact's picture on a single-record screen: `pg_contact_picture_address($file_id)` (one query, '' for no files row, as `pg_contact_picture_name()`).

## Why?
- Owner (2026-11-12, #8882): a GIF made in the Design Canvas was his profile picture; saved again, the header showed the old pixels for three days, after a site cache reset, an emptied browser cache and Ctrl+F5, while the File Manager showed the new ones.
- A canvas Replace save writes over the same file (same id and name) and sets `files.timestamp` (`pg_image_editor_api()`, `ui/image-editor-versions.md`); `get_file.php` answers a picture `public, max-age=604800`, so the browser and any edge cache before the site may keep it a week under an address that never changed. The site's cache reset purges neither.
- The panel's service worker (`sw.js`) answers only page loads and keeps no picture: nothing of the site's own needs dropping on a save (PF2 of the plan was skipped).

## Where is it used?
- Faces: the header (`output.php`, the signed-in person), the Workspace (`ws_avatar_url()`, `ws_people_select()` reads `image_file_timestamp`), chat (`pg_chat_avatar_src()` and its three queries), the dashboard's Who is online widget (`widget_4.php`), the site account widget (`widgets_account.php`).
- Contact pictures: the contacts list (`pg_contact_list_rows()` reads the stamps with the names), Edit Contact, Edit User's identity photo, the order view.
- Not yet: the panel search's contact thumbnails (`includes/panel/search.php`), and pictures outside a contact (product, design files): a new place that draws a file by its address adds the stamp the same way.

Tests: `tests/file_stamped_address_test.php`, `tests/contact_picture_fallback_test.php`. Related: image-same-address.md, image-panel-thumbnails.md
