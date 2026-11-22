---
name: canvas-data-views-follow
description: Plan canvas-data-views, DV4's census: how a channel's canvas card with data layers shows the current rows (drawn in the card by the reader's browser), and what it leaves to DV5
---

## What had to be chosen?
- How a card with data layers shows the current rows without a model call and without a version per change. Two ways were named: the server's draw made again when a bound table's change stamp is newer than the picture, or the layers drawn in the card.

## Which is chosen, and why?
- The layers drawn in the card, by the browser of whoever looks at it: the whole document drawn again with the editor's own renderer from the rows that reader may read, shown in place of the stored picture. Nothing is written: no picture, no version, no stamp.
- Rights: the plan reads rows "with the reader's rights; rows the reader may not read give an empty layer that says so". The stored picture is one file for every reader of the channel; a server draw made again would show one person's rows to all, or need a picture per reader.
- Owner (channel message 9848): data written from the table "veri değişiklikleri için ek tuval çizme bedeli ödenmez". A card drawn from its rows costs one row read a table, no drawing kept.
- The server's draw often waits for a browser anyway (a face of `g:Inter` it has no file of, `platform/image-server-draw-data.md`), so the stamp way would still need one.
- No change stamp is compared: a card read when shown is current. A changed description is a version as any save; changed rows are not.

## Which tables does a card read?
- The tables the data layers of the canvas's newest version name (the open scene's layers, the ones its picture shows), each with `ws_canvas_data_rows()` for the reader. No `ws_canvas_tables` binding is asked: the editor's `data_rows` already gives a reader every table they may read, so a binding would guard nothing, and canvases made from the Data menu (DV2) bind none.

## What stays a still?
- The stored file itself draws its data layers with no rows (DV5): Download from the card, the Files tab's tile and a guest's page show it to readers who may not read the table. A copy into Files, a download from the editor and an animation keep the saver's rows and say so (`ui/image-editor-data-still.md`). The image viewer opened from the card shows the card's drawing.
