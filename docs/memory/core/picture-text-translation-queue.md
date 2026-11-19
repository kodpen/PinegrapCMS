---
name: picture-text-translation-queue
description: The picture drawing queue (#10266): "Draw the waiting pictures" on the Pictures group, the draw=1 view, which rows it draws, Stop and Continue
module: picture_translation
---

## Where is the queue?
- The Pictures group of the Translations screen shows "Draw the waiting pictures" with its count (`pg-count`) while at least one row would be drawn; it opens `translations.php?group=pictures&draw=1`, a view of the same screen (no root file).
- Its lead (#10448): drawn now here, and by themselves without the view (an open panel; else the server where it can).
- The view lists each waiting row (picture name, language, Not drawn yet / Out of date, a state pill) and reads out the count left (`role="status"`). The group's button is `pg-button-quiet`. "Back to the pictures" returns to the group. With nothing waiting it says "Nothing is waiting to be drawn." and loads no editor.

## Which rows does it draw?
- `pg_picture_tr_queue_sql($languages, $limit)` (pure): `file_translation` rows in state 0 (waiting) or 2 (out of date) of the given languages, whose file has texts in `translation_uses` and none untranslated (no row or empty text) in the row's language; ordered by name and language. Codes other than letters, digits and hyphens are dropped; no language gives ''.
- `pg_picture_tr_queue($limit = 200)`: the target languages written left to right (`pg_picture_tr_languages()`; an RTL language is never drawn), bounded to 200 rows (so is the button's count, `count()` of it), kept only for a folder the user may edit (`check_edit_access()`) and not in the Recycle Bin (`pg_folder_binned()`). Before the tables exist it returns nothing. A query finding no row clears the waiting mark (`picture-text-auto-rows.md`).
- The translation job draws the rows of every folder the server can draw whole (`picture-text-auto-server.md`).

## How is a row drawn?
- `assets/js/picture_queue.js` (standalone, not in a bundle) loads the Design Canvas bundle (`pg_image_editor_includes()`, `pgImageEditor.load()`) and draws each row with `PgImageEditor.parts.drawWaiting()`, shared with the background draw (`picture-text-auto-panel.md`). A picture's live document is read once (`picture_languages`); each language is posted to `picture_language_draw`, which checks right, stamp and every text translated.
- One row at a time; the background draw does nothing in this view. A row drawn meanwhile (another tab, the server) ends as its state (`M.languageRowPlan()`).
- A row ends as Picture drawn, Look by hand (a text fell under the fit floor), Not every text is translated (`languageDrawable()` false since the page was built) or failed with the server's message. Once its draw ends the reason it waited (Not drawn yet / Out of date) goes; the line keeps the language and the pill says the result (`reasonShown()`).
- A Look by hand row offers "Edit in the Design Canvas" (`data-pg-image-edit`, the editor the view already loads) once the run has ended or stopped, never while a draw runs (`editShown()`).
- At a narrow width the pill goes under the name (`pg-list-row-head-wrap`), which is never cut with an ellipsis.

## What do Stop and Continue do?
- Stop ends the run after the picture being drawn ("Stopping…", button disabled); the count reads "Stopped: {n} left" and the button becomes Continue, which starts again from the next waiting row.
- Ranks (architect, channel 25 message 8927): Stop is quiet; Continue after a stop is the view's one fill; a running or finished view has no fill (`buttonTone()`).
- At the end the count reads "Done; {n} could not be drawn." when a row failed, else "All the waiting pictures are drawn; {n} to look at by hand." when a row ended Look by hand (architect, channel 25 message 8978), else "All the waiting pictures are drawn." (`endText()`); Stop hides when nothing is left.

## Where are its tests?
- `tests/picture_translation_test.php`: the query (states, languages, untranslated left out, bound, codes), the screen's link and view, and under Node the script's count left; a row's plan and end: `tests/picture_auto_test.php`.
