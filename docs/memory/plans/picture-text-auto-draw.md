---
name: picture-text-auto-draw
description: Plan (owner 2026-11-17, agent channel poll 143): a translated Design Canvas picture is drawn by itself, in an open panel in the background and by the server with nobody there; steps PA1 to PA5
priority: 6
---

## What is asked?
- Owner (2026-11-17, in the architect's window), after trying picture text translation on dev: "çeviri çalıştı ancak çeviri ekranından tasarım tuvalini açıp çevir düğmesi bastım da oldu. bunu otomatik yapacak bir sistem geliştirmemiz lazım. lansman sonrası bu özelliği ses getirecek şekilde duyurmayı planlıyorum".
- Owner (2026-11-17, poll 143, message 9281): "Evet, ayrıca kimse yokken sunucu da çizsin" (`workflow/decisions-made-product-75.md`).

## What stands in the way today?
- Three things a person does by hand (`core/picture-text-translation-overview.md`): a page in another language must be opened once before the picture is listed (the page pass writes the waiting row); the picture's texts reach an engine only through the Translations screen; each language is drawn by pressing Draw in the Design Canvas's Languages list or "Draw the waiting pictures".

## What is reused?
- The store, the states and the hooks (`core/picture-text-translation-store.md`); the queue's query and drawing (`core/picture-text-translation-queue.md`); the draw's checks: right, stamp, every text translated (`core/picture-text-translation-draw-server.md`); translate on save and its job guard (`core/content-translation-jobs-duplicates.md`); the server's drawing with its verdict, limits and probe (`platform/image-server-draw.md`).

## What binds it?
- Owner (2026-11-17, poll 131): a language is drawn only once every counted text is translated; the source picture is shown until then.
- Owner (2026-11-09, `workflow/decisions-made-product-38.md`): a visitor never opens an engine job. A picture's texts go to an engine by themselves only on a signed-in save, and only for a language with "translate on save" and a ready engine, as a page's do.
- No table and no column: a row the server drew has `drawn_by` 0. No right-to-left language, as before.

## What is built?
- Steps: `picture-text-auto-draw-steps.md`. Claim as `[plan:picture-text-auto-draw] <step> — <topic>`.
- In short: the waiting row and the texts' job are made when a page or a picture is saved, not at the first visit; an open panel draws the waiting rows in the background, one at a time, with the queue's own drawing; with nobody there the job run draws the rows the server can draw whole and leaves the others to a panel; the buttons stay and the screens say that nothing needs pressing.

## What did the architect decide? (2026-11-17)
- Nothing waiting costs nothing: one mark, set by the hooks and cleared by an empty queue, says whether a panel page or a job run looks at the queue at all. No request and no query per panel page while it is clear.
- The background draw loads the Design Canvas bundle only when a row waits, on an idle and visible tab, one tab at a time; never on the site's pages.
- The server draws only a document its own verdict passes whole (`pg_image_draw_plan()`): a photo with text layers, a sticker, an effect or a font it has no file of waits for a panel. Its picture is kept by the same function as a browser's.
- The fit is one rule in two languages: the PHP twins of `M.languageDoc()` and `M.languageFit()` run the same fixtures as the script.
- A picture the server drew is not drawn again by a browser while it is up to date.
- No switch: the engines are governed by "translate on save", and a draw costs no engine call.

## What is left out?
- Drawing on the site's pages for a signed-in visitor; a browser's redraw of the server's picture; a toast or a count of what was drawn; flat pictures, svg words and right-to-left languages, as in the first version.

## Whose look?
- The architect's: PA4 changes words on screens the owner accepted (polls 118, 130, 140) and draws nothing new. A new control, if a step turns out to need one, goes to the owner.
