---
name: picture-text-translation-draw
description: The Design Canvas Languages list (#10208): a canvas picture drawn per language in the browser, the 70% fit floor, the draw stamp, where the picture is kept and hidden
module: picture_translation
---

## Where is the Languages list?
- A "Languages" button in the Design Canvas's top bar (`assets/js/pg_image_editor_artboard.js`, after Import), shown only for a canvas file opened from Files whose texts are in the store and when the site has a target language. It opens a box, not a menu: one row per target language with the drawn picture's thumbnail, the state, "{n} of {m} texts translated", Draw; "Draw all" below.
- States (`pg_picture_tr_list_state()`): Not translated (some text has no translation), Translated, Picture drawn, Out of date (row state 2), Look by hand (drawn with `fit` 1). "Drawn" alone is not the label: its Turkish is already "Berabere" (a tied match).
- A right-to-left language is listed with "Right to left: the source picture is shown." and no Draw.
- Draw needs one translated text; a text with none keeps the source's words. Draw all takes Translated and Out of date (`M.languagesToDraw()`).

## How is a picture drawn?
- Action `picture_languages` (`pg_picture_tr_languages_answer()`) gives the live canvas document, its hash and per language the texts (`id`, `field`, `text` or null) and a stamp. The browser draws from that saved document, never the unsaved one ("Changes not saved yet are not in these pictures.").
- `M.languageDoc()` puts the words in by layer id and field; each changed text layer is fitted (`M.languageFit()`): the size shrinks to the source's box width and height, never under `LANGUAGE_FLOOR` 0.7; below it the text is drawn at 70% and the draw is marked (`fit` 1). The aligned side stays across (`M.textFit()`), the box's middle stays upright.
- A painter on the editor's prototype (as `workspace_canvas_picture.js`) waits for fonts (`layersReady()`) and draws with `drawArtboard()`, the save's renderer.

## What does the server check and keep?
- Action `picture_language_draw` (`pg_picture_tr_draw_answer()`): `pg_image_editor_file()` first (edit right on the folder, Recycle Bin, ERP), then `pg_picture_tr_draw_refusal()`: a target language, not right to left, a live canvas, and the stamp (`pg_picture_tr_stamp()`: file id, language, doc hash, the words) equal to now. Another file's or language's picture, a changed document or changed translations: 409 "This picture or its translations changed meanwhile. Open the Languages list again."
- The PNG must be the artboard's size. First draw: a new `files` row in the source's folder named `<stem>-<lang>.png` (made unique); a later draw writes over that row, id and name kept. The `file_translation` row is upserted: `picture_file_id`, state 1, `fit`, `doc_hash`, `drawn_at`, `drawn_by`. Then `pg_page_cache_mark_dirty()`.

## Where is a language's picture hidden?
- `pg_picture_tr_hidden_sql()` in both File Manager lists (`includes/explorer/handle_list.php`), the file pickers (`pg_picker_where_for()`) and the Design Canvas's pictures from Files (`pg_image_editor_action_sticker_files()`); Where used answers "could not find the file" for one (`pg_picture_tr_is_picture()`).
- Moves, deletes, the bin and Disk Usage of these pictures are PT6's (`plans/picture-text-translation-steps-2.md`).

## Tests?
- `tests/picture_translation_test.php`: the fit and its mark, the language document, Draw all, the states after a draw, the refusals and the stamp, the hidden lists, `pg_tr_load()` before the target languages (`pg_tr_target_languages()` lives in `includes/translate/seo.php`, read only by it: without it the list answered 500). Checked in a sandbox (#10208): every state, Draw and Draw all, a changed translation turning a drawn row Out of date.
