---
name: picture-text-translation-draw-server
description: The Design Canvas Languages list's server side (#10208): what a language's draw is checked by, where its picture is kept, how it is hidden from file lists, tests
module: picture_translation
---

## What does the server check and keep?
- Action `picture_language_draw` (`pg_picture_tr_draw_answer()`): `pg_image_editor_file()` first (edit right on the folder, Recycle Bin, ERP), then `pg_picture_tr_draw_refusal()`: a target language, not right to left, a live document (`pg_picture_tr_live_doc()`: a canvas, or a photo whose base row is still there), every text translated (`pg_picture_tr_untranslated()`), and the stamp equal to now.
- The stamp (`pg_picture_tr_stamp()`) is file id, language, doc hash and the words: another file's or language's picture, a changed document or changed translations get 409 "This picture or its translations changed meanwhile. Open the Languages list again."
- A canvas's PNG must be the artboard's size; a photo's picture is drawn on its base (`core/picture-text-translation-draw-photo.md`). First draw: a new `files` row in the source's folder named `<stem>-<lang>.<ext>` (made unique); a later draw writes over that row, id and name kept, unless its format changed (a new row, the old one deleted). The `file_translation` row is upserted: `picture_file_id`, state 1, `fit`, `doc_hash`, `drawn_at`, `drawn_by`. Then `pg_page_cache_mark_dirty()`.

## Where is a language's picture hidden?
- `pg_picture_tr_hidden_sql()` in both File Manager lists (`includes/explorer/handle_list.php`), the file pickers (`pg_picker_where_for()`) and the Design Canvas's pictures from Files (`pg_image_editor_action_sticker_files()`); Where used answers "could not find the file" for one (`pg_picture_tr_is_picture()`).
- Moves, deletes, the bin, a folder copy and Disk Usage of these pictures: `core/picture-text-translation-doors.md`.

## Tests?
- `tests/picture_translation_test.php`: the fit and its mark, the language document, Draw all, the states after a draw, the refusals and the stamp, the hidden lists, `pg_tr_load()` before the target languages (`pg_tr_target_languages()` lives in `includes/translate/seo.php`, read only by it: without it the list answered 500). Checked in a sandbox (#10208): every state, Draw and Draw all, a changed translation turning a drawn row Out of date.
