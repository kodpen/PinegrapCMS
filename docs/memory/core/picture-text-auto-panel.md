---
name: picture-text-auto-panel
description: Pictures drawn by an open panel page in the background (picture_auto.js): the footer's tag, the lock, the order, the stop, a failed row, the draw after a Save, the shared drawing
module: picture_translation, output
---

## When does a panel page draw?
- `output_footer()` prints `pg_picture_tr_auto_tag()`: the Design Canvas's loader (`pg_image_editor_includes()`) and `<script id="pg_picture_auto" defer>` with `data-rows` (file id and language only). Only for a signed-in person, only while the mark is set (`pg_picture_tr_marked()`, no query while it is clear) and the person's queue (`pg_picture_tr_queue()`, folders they may edit, not in the Recycle Bin) is not empty. Never on the site's pages, nor in `output_footer_secure()`.
- The queue view (`#pg_pq`, "Draw the waiting pictures") draws its own rows: the script does nothing there.

## How does it draw?
- 3 s after the page loads, once the tab is visible and the browser idle (`requestIdleCallback`), one tab of the browser takes the lock `pg-picture-draw` (`navigator.locks` with `ifAvailable`; without it, a lease `pg_picture_draw_lease` in `localStorage`, 60 s, renewed every 20 s while it draws; with neither, the tab draws on its own). A tab refused looks again every 30 s while visible.
- It loads the Design Canvas bundle (`pgImageEditor.load()`) only then, and draws the rows one at a time in the queue's order (picture name, language), waiting for an idle moment before each.
- Hidden, it stops after the row being drawn and lets the lock go; shown again it goes on from the next row. A row is tried once in a page view: one that failed waits for the next page. Nothing is shown on the page.

## What is the queue's drawing, and who shares it?
- `PgImageEditor.parts.drawWaiting(fileId, code, cache)` in `assets/js/pg_image_editor_artboard.js`: a painter on the editor's prototype reads the picture's languages once per cache (action `picture_languages`) and draws the row with `drawLanguage()`, as the Languages list does. Answers drawn, look, skipped or failed with the server's message.
- `M.languageRowPlan()` reads the row again before drawing: a language drawn meanwhile (by another tab or the server) keeps its state and is not drawn again; one with a text no longer translated is skipped.
- Used by the queue view (`picture_queue.js`), the background draw and the Design Canvas after a Save: no copy in either script. The background draw reads the languages again in each run.

## What does the Design Canvas do after a Save?
- A Save of the file its Languages list belongs to (the same file id, so not a copy) draws that picture's rows at once once the editor has closed (`PgImageEditor.parts.drawPicture()`): the languages whose row waits or is out of date (`queued` in the `picture_languages` answer) and whose every text is translated (`M.languagesWaiting()`). A language without a row is shown by no page and is not drawn. Only when the list had words and languages at open.
- A page that reloads after the save cuts it; the footer's tag on the next page takes the rest.

## Where is it tested?
- `tests/picture_auto_test.php`: under Node the lock (`navigator.locks` and the lease), the order one at a time, the stop when hidden, a failed row not tried again, the model's plan of a row and the languages after a Save; in PHP the footer's tag (mark clear: no query; queue empty: nothing; the rows), the shared drawing's places, and the screens' words (the Pictures group's note and empty text, the queue view's lead, the Languages list's note) with their Turkish.
