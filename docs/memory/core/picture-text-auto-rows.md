---
name: picture-text-auto-rows
description: A translated picture's waiting rows written at a page save, its texts' translate-on-save job (scope file:<id>), the waiting mark in data/temp, the keep shared by every draw
module: picture_translation
---

## When does a picture start to wait?
- At a page save in the visual editor (`pg_designer_save_page()`, after the page's own translate-on-save job): `pg_picture_tr_page_saved()` reads the page's own code (`$tree_code` with `{path}` filled in) by the page pass's address rule, finds the files with words in one query (`pg_picture_tr_save_sql()`, no document decoded), leaves out a binned folder and writes a waiting row (state 0) per left-to-right target language (`INSERT IGNORE`, `pg_picture_tr_save_rows()`).
- A picture only a shared part, a widget or a menu shows is not in the page's code: its first visit in another language still writes the row (`picture-text-translation-page.md`).

## When are a picture's texts sent to an engine?
- `pg_picture_tr_texts_jobs($file_ids, $user_id)`: a job of scope `file:<id>` for each picture with a text untranslated in a language set to translate on save with a ready engine (`pg_picture_tr_auto_languages()`, right to left left out), through `pg_tr_auto_update()`: the same lock and open-job guard as a page's (`content-translation-jobs-duplicates.md`). One query (`pg_picture_tr_pending_files_sql()`) picks the pictures with something pending.
- Opened by a page save (the pictures it shows) and by a signed-in save of a picture with a row: a Design Canvas save, a photo's replace, a Restore (`pg_picture_tr_saved($file_id, $doc, $user_id)`). Never from a visit, never with user 0.
- `pg_tr_scope_extract('file:<id>')` extracts nothing (the saves keep the texts); the Translations screen's jobs list names such a job by the Pictures group ("Images").

## What is the mark?
- A file `data/temp/picture_draw_<md5(database@host)>.mark` holding a token; reading it (`pg_picture_tr_marked()`) costs no query. Where data/temp takes no file (`pg_dir_writable()`) it reads as set, so nothing waits unseen. Before #10569 `is_writable()` read a Windows folder's read-only attribute, so the mark read as set for good there.
- Set (`pg_picture_tr_mark_set()`) by a waiting row (a page save, a first visit), a save of a picture with a row, and a translation written for a text of a row waiting or out of date (asked only while the mark is clear).
- Cleared when the queue's query finds nothing: `pg_picture_tr_queue()` and `pg_picture_tr_mark_check()` (also run at the end of the daily prune), and only while the token is still the one read before the query, so a set meanwhile stays. A data/temp swept by Cleanup costs at most a day.

## Who keeps a drawn picture?
- `pg_picture_tr_keep($source, $language, $drawn, $fit, $doc_hash, $user_id)`: the language's files row (written over, or new in the source's folder), the `file_translation` upsert (`pg_picture_tr_keep_query()`, `drawn_by` the user or 0 for the server), the page cache. The Draw action calls it with the user's id.
- It writes (`pg_picture_tr_keep_write()`) under `GET_LOCK` of the picture and language (`pg_picture_tr_keep_lock_sql()`, 10 s; a null answer keeps without it): two draws at once (a tab and the job) each wrote a new files row before #10569, one left visible in Files.

## Where is it tested?
- `tests/picture_translation_auto_test.php`: rows at a page save (words, no words, another host, right to left, the bin), the hook's place, the job's scope and guards, the mark's moves and places, the keep's upsert with 0. The database paths were not run.
