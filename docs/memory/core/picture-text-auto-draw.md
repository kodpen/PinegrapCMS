---
name: picture-text-auto-draw
description: Translated Artifact Canvas pictures drawn by themselves (#10409 to #10448): how rows, text jobs, the waiting mark, the panel's and the server's draw fit; the architect's rules; what is left out
module: picture_translation, picture_server_draw
---

## What happens with no press?
- A page saved in the Visual Page Editor writes the waiting rows of the pictures in its code and sends their texts to the languages set to translate on save; a Artifact Canvas save of a picture with a row does the same for it (#10409, `picture-text-auto-rows.md`).
- Once every text of a language is translated, the row is drawn: by an open panel page in the background (#10428, `picture-text-auto-panel.md`), by the Artifact Canvas right after a Save, and with nobody in the panel by the translation job where the server draws the picture whole (#10433, `picture-text-auto-server.md`).
- The Pictures group's note, the "Draw the waiting pictures" view's lead and the Languages list's note say so (#10448); Draw, Draw all and the queue view stay and draw at once.
- The owner means to announce it after the launch (`workflow/decisions-made-product-75.md`).

## What still waits for a person?
- A picture only a shared part, a widget or a menu shows: its row comes with its first visit in another language, as the save reads the page's own code only.
- A text no engine translates (no "translate on save" or no ready engine): typed on the Translations screen; the written text sets the mark.
- A row the server cannot draw whole (a photo, a sticker, an effect, a picture layer, a font or letter it has no file of): drawn by the next panel page (`picture-text-auto-server.md`).

## Which rules did the architect set?
- Architect (2026-11-17): nothing waiting costs nothing. One mark, set by the hooks and cleared by an empty queue, says whether a panel page or a job run looks at the queue at all: no request and no query per panel page while it is clear.
- Architect (2026-11-17): the background draw loads the Artifact Canvas bundle only when a row waits, on an idle and visible tab, one tab at a time; never on the site's pages.
- Architect (2026-11-17): the server draws only a document its own verdict passes whole (`pg_image_draw_plan()`); its picture is kept by the browser's function (`pg_picture_tr_keep()`, `drawn_by` 0). No table and no column.
- Architect (2026-11-17): the fit is one rule in two languages: the PHP twins of `M.languageDoc()` and `M.languageFit()` run the same fixtures as the script.
- Architect (2026-11-17): a picture the server drew is not drawn again by a browser while it is up to date (state 1 is in no queue).
- Architect (2026-11-17): no switch. The engines follow "translate on save", and a draw calls no engine.
- The words of the three screens are the architect's, on looks the owner accepted (polls 118, 130, 140); a new control goes to the owner.

## Which owner decisions bind it?
- Drawn by itself and by the server (poll 143), only once every counted text is translated (poll 131), the source shown until then: the overview's list (`picture-text-overview.md`). A visitor never opens an engine job (`workflow/decisions-made-product-38.md`).

## What is left out?
- Drawing on the site's pages for a signed-in visitor; a browser's redraw of the server's picture; a toast or a count of what was drawn; flat pictures, svg words and right-to-left languages, as in the first version.

## What was run, and what not?
- A sandbox run of #10428 drew a saved, translated picture with no press, again within a second of a Save, and in one of two open tabs.
- The server's paint, file write and compression ran nowhere: no Imagick on the local machine or in CI (#10433). The database paths of the rows, jobs, mark and keep ran only through injected queries.
