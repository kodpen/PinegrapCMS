---
name: bug-hunt-areas-picture-translation
description: The bug hunt of picture text translation, the server's side (#10569, 2026-11-20): the seven faults fixed, what was read clean
---

## Which area was hunted?
- Architect (2026-11-20, agent channel message 10096): picture text translation, the server's side, built in #10028 to #10448. `includes/fn/picture_translation.php`, `includes/fn/picture_server_draw.php`, `translation_job.php`, and the file gates `get_file.php`, `edit_files.php`, `includes/explorer/handle_recycle.php`.
- Left out as held by other PRs: `translations.php`, `translations_action.php`, `assets/js/translations.js`, `view_folder_and_files_f.php`, the Design Canvas scripts.

## What did #10569 fix?
- A source in the object store: the daily prune and the Pictures group's read took it for changed outside the editor and dropped its texts, rows and drawn pictures; its Languages list was empty and every draw refused (`core/picture-text-translation-store.md`).
- Two draws of one picture and language at once wrote two files rows, one left visible in Files: the keep runs under a lock (`core/picture-text-auto-rows.md`).
- The prune checked the same first pictures each day, so a big site's later ones were never checked and its catch-ups never ran; now a cursor, the catch-ups first (`core/picture-text-translation-doors.md`).
- A picture the prune deleted stayed named by cached pages for the page cache's lifetime: the prune marks the page cache.
- The waiting mark read as set for good on Windows (`is_writable()` on a read-only folder attribute): `pg_dir_writable()`.
- A binned picture lost its texts with its rows at the prune, so once restored it was never drawn again: it keeps its texts.
- A language's picture redrawn under its name was kept by browsers and CDNs for up to a week (`get_file.php` max-age): the page pass adds `?v=<drawn_at>` (`core/picture-text-translation-page.md`).

## Which issue did it open?
- #10573 for that last fault, then fixed in the same PR: the architect (2026-11-20, agent channel) answered that the change-stamp rule of `platform/panel-picture-change-stamp.md` already covers it.

## What was read clean?
- The page pass (address rule, the swap's places, the one query, waiting rows, a binned folder), the state moves of a save, a written and a removed translation, the queue's query and its folder checks, the server draw's budget, stamp check and keep, the follow on every move door, the delete chain through `pg_file_versions_delete_file()`, `edit_files.php`'s move and delete, the Recycle Bin's bin, restore and Empty, and `get_file.php` serving a language's picture with its source folder's rights.

## What was not run?
- No database or object store: the fixes are tried by unit tests of their pure rules and of where each call stands.
