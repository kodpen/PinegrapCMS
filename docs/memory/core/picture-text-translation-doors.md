---
name: picture-text-translation-doors
description: The file doors of a language's picture: it follows its source on a move, goes with it on a delete, is never swapped while the source is binned, counts in Disk Usage, the daily sweep's catch-up
module: picture_translation
---

## Where does a language's picture sit?
- Always in its source's folder: the page pass joins it only there (`p.folder = f.folder`), so `get_file.php` serves it with the source's rights. Found only through `file_translation.picture_file_id`, hidden from file lists (`picture-text-translation-draw-server.md`).

## What does a move do?
- `pg_picture_tr_follow($file_ids)` after the write moves each picture out of place to its source's folder (pure rule `pg_picture_tr_follow_moves()`) and marks the page cache. Called by File Manager's move (`pg_explorer_handle_move()`), the file window's save, a restore or drag out of the Recycle Bin (`handle_recycle.php`), Edit File and Edit Selected Files' "Move to folder" (`edit_files.php`).
- While the source is in the Recycle Bin (`pg_folder_binned()`) its picture stays where it was, out of the bin's list; a restore anywhere brings it along.
- The External API cannot move a file (only bin it); a Workspace channel's folder takes only files with folder 0.

## What does a delete do?
- For good: `pg_file_versions_delete_file()` calls `pg_picture_tr_forget()` (#10028): the rows, each picture's `files` row, versions and bytes. Every panel delete door, the bin's Empty and a folder deleted for good (`pg_delete_file_record()`) pass there.
- A delete that skips it is caught by the daily sweep below.

## Is a binned source's picture shown?
- No. A binned file is parked as `pgbin_<id>_<name>`, so no page names it. A file inside a binned folder keeps its name, so `pg_picture_tr_page_apply()` drops rows whose `f.folder` is binned (the page pass hands it `pg_folder_binned`): no swap, no waiting row. The tree is already read for the page's own access check.
- The daily prune drops a binned source's rows and pictures (reason `binned`, `picture-text-translation-store.md`) but keeps its texts; a restore shows the source until a visit writes its waiting row and it is drawn again. Before #10569 the texts went too and a restored picture was never drawn until saved again in the Artifact Canvas.

## Is it in Disk Usage?
- Yes, with no code of its own: `pg_files_disk_usage()` sums every `files` row (`pg_file_storage_usage()`) and every version; the draw writes the size through `pg_image_editor_record()`. The object store's move job moves it as any row.

## What does the daily sweep do?
- `pg_file_versions_sweep()` ends with `pg_picture_tr_prune()`. First `pg_picture_tr_follow(null)` moves every picture a door left behind and `pg_picture_tr_lost()` sets a row whose picture is gone (deleted with a folder) to waiting with `picture_file_id` 0: two queries, run whatever the budget.
- Then, in the budget, a source deleted, binned or changed outside the editor loses its rows and pictures. Each run goes on after the file id the last one stopped at (`pg_picture_tr_prune_order()`, cursor in `data/temp/picture_prune_<md5>.txt`), so every picture is checked in turn (#10569; before, the same first ones each day).
- A picture dropped or lost marks the page cache (the job is no signed-in request); `pg_picture_tr_mark_check()` runs last, after any budget.

## What does a folder copy do?
- Copy of a folder (`view_folder_and_files_f.php`) leaves the languages' pictures out (`pg_picture_tr_hidden_sql('id')`): a copy would be a stray visible file with no row.

## Where is it tested?
- `tests/picture_translation_doors_test.php`: the move rule, each move door's call after its write, the delete chain, the binned folder on a page, Disk Usage's sum, the sweep, the folder copy. Static place tests; the database paths were not run.
