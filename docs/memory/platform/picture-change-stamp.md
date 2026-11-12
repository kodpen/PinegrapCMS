---
name: picture-change-stamp
description: A panel picture drawn by a file's bare address carries the file's change stamp (?v=<mtime>, pg_file_picture_url()), so a picture saved again shows at once (#8882)
module: files
---

## What is the rule?
- A picture of the File Manager that the panel prints by its bare address (`PATH . files.name`) goes through `pg_file_picture_url($name, $base = null)` (`includes/fn/files.php`): the address plus `?v=<file's change time>`, the time `get_file.php` sends as `Last-Modified`. `pg_stamped_url($url, $stamp)` adds the stamp (`&v=` after a query; none for 0).
- A missing file keeps its bare address. Without `init.php` (the external API) the default `data/files` folder is read.

## Why?
- Owner (2026-11-12, #8882): a GIF made in the Design Canvas was his profile picture; he saved it again and the header's face kept the old pixels for days, after the site's cache, the browser's and Ctrl+F5.
- A Replace save writes the same file id and name (`ui/image-editor-versions.md`), and `get_file.php` answers a picture with `public, max-age=604800`: a browser or an edge cache before the site may keep the address for a week. A GIF has no derivative whose name carries the change (`image-derivatives.md`). The panel's service worker answers only page loads (`sw.js`), so it keeps no picture.
- The stamp moves with every way the file changes: a canvas save, the File Manager's replace, rotate and optimize.

## Where is it used?
- The header's face (`includes/fn/output.php`), `ws_avatar_url()` (Workspace people), `pg_chat_avatar_src()` (`chat.php`: the chat, Who is online widget 4), Edit Contact, Edit User's identity picture, the contacts list (`contact_list.php`), the order's customer (`view_order.php`), the site's account widget (`widgets_account.php`).
- Not yet: the panel search's contact results (the script builds the address from the bare name, `includes/panel/search.php`).

Tests: `tests/picture_stamp_test.php`. Built in PF1 (`plans/picture-fresh-after-save.md`).
