---
name: picture-text-auto-server
description: Translated canvas pictures drawn by the server with nobody there: the job's draw, PHP twins of the language document and fit, what waits for a panel, budget and mark
module: picture_server_draw
---

## When does the server draw?
- `translation_job.php` (every five minutes) runs `pg_picture_tr_draw_due(20)` after `pg_tr_jobs_run_due()`, only while the waiting mark is set (`pg_picture_tr_marked()`, no query; `picture-text-auto-rows.md`). The module `includes/fn/picture_server_draw.php` is loaded there, not by `functions.php`.
- Nothing more is read where `pg_image_draw_can()` fails shapes or text (no Imagick, a failed probe, a pause; `platform/image-server-draw.md`) or before the tables exist.

## Which rows does it draw?
- The queue's query (`pg_picture_tr_queue_sql()`, 200 rows) over every folder: no user, so no edit right is asked; a folder in the Recycle Bin and an ERP document are left out, as `pg_image_editor_file()` leaves them for a browser.
- Canvas documents only (`pg_picture_tr_live_doc()` kind `canvas`): a photo is drawn on its base by the save's steps, a browser's work.
- One row after another (`pg_picture_tr_draw_due_with()`): a row starts only while one drawing's time (`pg_image_draw_limits()['seconds']`, 8) is left in the 20 seconds; each drawing gets that time as its own budget.

## How is a language's picture made?
- `pg_picture_tr_language_doc()` and `pg_picture_tr_language_fit()` are the twins of `M.languageDoc()` and `M.languageFit()`: the floor is 0.7, `fit` 1 below it. Both languages run `tests/fixtures/picture_language/fit.json` and `doc.json`; a change to one changes the other and the fixtures.
- `pg_picture_tr_language_fitted()` ports the loop of the editor's `drawLanguage()`: the size scaled and rounded to two places, the box kept on its aligned side (`M.textFit()`), the middle kept upright. The widths are the server's own (`pg_picture_tr_server_layout()`: the font file's letter advances, as `pg_image_draw_plan()` lays a text out), so a picture may differ a little from a browser's, as the owner was told (poll 143).
- Then `pg_image_draw_plan()`'s verdict on the fitted document, with no picture resolver: a sticker, an effect (a shadow, an outline), a picture layer, a system font list (`sans`), a face or a letter the server has no file of, a script it does not draw: the row is left as it is for an open panel, the mark kept.
- Poll 143 (`workflow/decisions-made-product-75.md`) named what the server draws as plain text and shapes; picture layers wait too, as their files would need a reader's rights.

## How is it kept?
- `pg_image_draw_guarded()` runs the paint; a PNG not of the artboard's size is dropped. It is compressed (`pg_process_image_file()`), then the document and the words are read again: a stamp (`pg_picture_tr_stamp()`) changed meanwhile leaves the row, as a browser's stale stamp gets 409.
- Kept by `pg_picture_tr_keep()` with user 0: `drawn_by` 0, the files row's `user` 0, the page cache marked. A row drawn here is state 1, so neither the queue nor a panel draws it again while it is up to date.

## When is the mark cleared?
- When the queue's query finds no row, the token read before it unchanged. After a run that drew something the query is asked once more (`pg_picture_tr_mark_check_with()`), which clears the mark when nothing is left. A row the server left keeps it for a panel.

## What shows it?
- System Status, row Canvas Drawing: shown with the Workspace on or a target language (`pg_tr_target_languages()`), its words naming agents' canvases, translated pictures or both.

## Tests?
- `tests/picture_translation_server_test.php`: the twins on the shared fixtures (PHP and Node), the fit with a stub layout, a plain document planned, a sticker, an effect, a missing font or letter, a picture layer and a photo left, the budget, the mark, the job's and System Status's places; the paint only with Imagick (not in CI). The database paths were not run.
