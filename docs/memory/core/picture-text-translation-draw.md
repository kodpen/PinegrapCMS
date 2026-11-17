---
name: picture-text-translation-draw
description: The Design Canvas Languages list (#10208): its rows and states, drawing only when every text is translated, the browser draw and the 70% fit floor
module: picture_translation
---

## Where is the Languages list?
- A "Languages" button in the Design Canvas's top bar (`assets/js/pg_image_editor_artboard.js`, after Import), shown only for a canvas file opened from Files whose texts are in the store and when the site has a target language. It opens a box: one row per target language with a thumbnail, the state, "{n} of {m} texts translated", Draw; "Draw all" below.
- States (`pg_picture_tr_list_state()`): Not translated (some text has no translation), Translated, Picture drawn, Out of date (row state 2), Look by hand (drawn with `fit` 1). "Drawn" alone is not the label: its Turkish is already "Berabere" (a tied match).
- A right-to-left language is listed with "Right to left: the source picture is shown." and no Draw.
- Owner (2026-11-17, poll 131): a language is drawn only once every counted text is translated into it. Until then Draw is disabled (`M.languageDrawable()`) and the row says how many texts are not translated and that visitors see the source picture; the server refuses such a draw (`untranslated`, 409). Draw all takes Translated and Out of date languages that are drawable. A text that stays the same (a brand name) is translated by typing the same word; the list's note says so.
- An Out of date row says "Visitors see the source picture until this one is drawn again." in place of the count. A drawn picture's thumbnail is a link opening it at full size in a new tab, named "Open the picture: {name}".

## How is a picture drawn?
- Action `picture_languages` (`pg_picture_tr_languages_answer()`) gives the live canvas document, its hash and per language the texts (`id`, `field`, `text` or null) and a stamp. The browser draws from that saved document, never the unsaved one ("Changes not saved yet are not in these pictures.").
- `M.languageDoc()` puts the words in by layer id and field; each changed text layer is fitted (`M.languageFit()`): the size shrinks to the source's box width and height, never under `LANGUAGE_FLOOR` 0.7; below it the text is drawn at 70% and the draw is marked (`fit` 1). The aligned side stays across (`M.textFit()`), the box's middle stays upright.
- A painter on the editor's prototype (as `workspace_canvas_picture.js`) waits for fonts (`layersReady()`) and draws with `drawArtboard()`, the save's renderer.

## What does the server check and keep, and where is the picture hidden?
- `core/picture-text-translation-draw-server.md`.
