---
name: picture-change-stamp
description: A panel picture drawn by a file's bare address carries its change stamp (?v=<filemtime>): pg_file_fresh_url(), pg_url_change_stamp(), the places that print a contact's face, why (#8882)
module: files
---

## What is the rule?
- A panel picture printed by a stored file's own address (`PATH . <name>`) carries the file's change time as `?v=<filemtime>`: `pg_file_fresh_url($name)` in `includes/fn/files.php`. The pure part is `pg_url_change_stamp($url, $stamp)` (`&v=` after a query, a fragment stays last, no stamp for 0 or a missing file), tested in `tests/picture_change_stamp_test.php`.
- Any new place that prints a contact's or user's picture by its file name uses `pg_file_fresh_url()`, never `PATH . $name`.

## Why?
- `get_file.php` answers a file `public, max-age=604800` (a week) with `Last-Modified`; the browser and an edge cache (Cloudflare keeps `.gif`, `.png`, `.jpg` by extension) answer that address without asking the site.
- A picture saved again under the same name (image editor replace, a canvas or animation "Replace {name}", File Manager rotate, optimize, an upload over it) keeps its address, so the old bytes showed for up to a week, past the site's cache reset, an emptied browser cache and Ctrl+F5 when the edge kept them (#8882: a GIF profile picture saved again from the Design Canvas).
- The canvas and animation replace write over the same file (`ui/image-editor-canvas-save.md`, `ui/image-editor-animation-save.md`); `filemtime` changes with it, so the address changes and every cache fetches the new bytes. The image editor's own answers already carried `?v=` (`includes/fn/image_editor.php`).
- `get_file.php` ignores `v`; `w` and `f` still choose a derivative (`image-derivatives.md`, `image-same-address.md`).

## Which places print a face with it?
- `ws_avatar_url()` (`includes/workspace/people.php`), the header's account menu (`pg_account_menu()`, `includes/fn/output.php`), the chat and the dashboard's people widget (`pg_chat_avatar_src()`, `chat.php`), Contacts list (`includes/fn/contact_list.php`), Edit Contact, Edit User, View Order.
- Not stamped: a contact's `image` (an address typed by hand, not a stored file), and the site's account widget (`includes/fn/widgets_account.php`), which is a site page held by the page cache.

## Does the site keep a copy itself?
- No. The panel's service worker (`sw.js`) caches only `offline.html` (`ui/panel-offline-and-install.md`); a GIF has no derivative at its bare address. The copies that answered old bytes were the browser's and the edge's, which the stamp passes by.
