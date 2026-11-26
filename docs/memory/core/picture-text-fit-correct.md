---
name: picture-text-fit-correct
description: A picture text corrected by hand for one language (#11888): the layer's corrections field, the save's check, both draws taking it in place of the fit, the mark, the count, what is left out
module: picture_server_draw
---

## Where is a correction kept?
- On the text layer in the canvas document: `corrections`, an object by language code, each `{ x, y, w, size }` (the box's place, its width, the letters' size) in the layer's own space. No words: the translation stays in the store. No table or column (architect, 2026-11-26, agent channel request 3129). When the translation changes later the correction stays and the new words wrap in the same box.
- A layer deleted takes its corrections, a copy (a clone) takes them along, a code no longer a target language is never read.

## What does the save check?
- `pg_image_edit_validate()` refuses (`error` `corrections`, "Invalid request.") a text layer's `corrections`, open or in a closed scene, that is not an object of at most 100 codes (`pg_image_edit_correction_code()`: letters 2-3, then `-`/`_` parts, 20 chars at most), each box JSON numbers in range: x, y ±100000, w 1-100000, size 0.5-10000 (`pg_image_edit_correction()`).
- The editor's readers apply the same rule (`M.languageCorrection()`); a malformed box is not read there, the text is fitted.

## How is a corrected text drawn?
- `M.languageDoc(doc, texts, code)` / `pg_picture_tr_language_doc($doc, $texts, $code)` answer `corrected` by layer id, any text layer with a box for the code (its words changed or not). Without a code nothing is read (the old callers' answers are unchanged).
- `M.languageFitted()` / `pg_picture_tr_language_fitted()` put it with `languageCorrectedPut()` / `pg_picture_tr_language_corrected_put()` in place of the room, wrap and shrink: the correction's size, wrapped between words to its width (`M.languageWrap()`), from its place; narrower than the width it keeps its alignment inside it, moved along its own turned line. No room is read for it; the others' rooms still see its source box.
- The mark (`fit` 1, "Look by hand") comes from a corrected text only when its turned box runs past the picture by more than half a px; with no picture size never. So a language whose every unfitting text is corrected inside the picture is drawn.
- The browser (`drawLanguage()` passes `lang.code`) and the server (`pg_picture_tr_server_plan(..., $code)`, its language from the queue row) draw the same numbers; the server's widths are its own.

## What does the Languages list get?
- `picture_languages` answers per language `corrected`: how many text layers carry a well formed correction for it (`pg_picture_tr_corrected()`, twin `M.languageCorrected()`), for the row's "Corrected by hand: {n} text(s)" (`picture-text-fit-view.md`).
- A save of the document changes its hash, so the stamp turns the drawn pictures out of date and they are drawn again, as every save does.

## What is left out? (architect, 2026-11-26)
- Other words, another font or another colour for one language; a layer shown in one language only; a correction for the source language.

## What does the editor call?
- `M.languageCorrect(l, code, box)`: sets a text layer's box, rounded to two places, or takes it off with `null`; the last off removes the field; a malformed box, a non-text layer, a bad code or a 101st language changes nothing. Answers whether the layer changed. The screen: `picture-text-fit-view.md`.

## Tests?
- `tests/picture_text_correct_test.php` on `tests/fixtures/picture_language/correct.json` (11 cases: wrap, right and centre alignment, the edge, past it, turned, words unchanged, another language, a string number, no width, a text beside one corrected), PHP and Node; the reader, the save's check, the setter.
