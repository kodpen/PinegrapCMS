---
name: picture-fresh-after-save
description: Plan (owner 2026-11-12, #8882): a picture saved again from the Design Canvas shows its new pixels at once wherever the panel draws it, first of all as a profile picture
---

## What is wrong?
- Owner (2026-11-12, #8882): a GIF made in the Design Canvas was his profile picture; he changed it in the canvas and saved. Three days later the header's face still showed the old pixels, after the site's cache was reset, the browser's emptied and Ctrl+F5; the File Manager's preview showed the new ones. "tuvalden güncellenen görselin önbelleği sıfırlanmalı."
- A face is the contact's file at its bare address (`ws_avatar_url()` in `workspace/people.php`: `PATH . image_file_name`; the header prints it with `pg_avatar_markup()` in `includes/fn/output.php`). The address stays the same when the pixels change, and a GIF has no derivative whose name carries the source's change time (`platform/image-panel-thumbnails.md`, `image-derivatives.md`).
- Not known yet: which cache answered the old bytes (the panel's service worker, an edge cache before the site, the answer's own `max-age`: `platform/image-same-address.md`), and whether the canvas wrote over the same file (`ui/image-editor-versions.md`).

## Which steps are there?
- PF1 (S) Find it and fix the face. `free`.
  - Repeat it on dev first: a GIF as the profile picture, saved again from the canvas; say on #8882 which cache answers the old bytes and whether the save replaced the file. If the canvas wrote a new file beside the old one, stop and report there: then the save's target is the fault.
  - The address a face is printed with carries the file's change stamp (`?v=<change time>`): `ws_avatar_url()` and each place the panel prints a contact's picture by its bare address. A test for the pure part.
  - Memory: a topic in `platform/` for the rule ("a panel picture drawn by a file's bare address carries its change stamp").
- PF2 (S) A save drops the site's own copies. After PF1, only if PF1 found a cache the site itself keeps (the service worker's): a save over a file (the canvas, File Manager's replace, rotate, optimize) drops that address from it. Skipped with a line on #8882 when PF1 shows the stamp is enough.

## Who holds it?
- A coordinator's worker; claim as `[plan:picture-fresh-after-save] PF1 — the face's address carries the change stamp`. Close-out: the rule to `platform/`, this file deleted.
