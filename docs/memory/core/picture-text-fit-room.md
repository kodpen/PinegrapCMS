---
name: picture-text-fit-room
description: A translated picture text grows into the free room around it before it shrinks (#11837): M.languageRoom(), languagePlace(), languageFitted(), PHP twins, fixtures, measure
module: picture_server_draw
---

## What does a translated text do now?
- It grows into its room at the source's size; it shrinks only past the room, never under its floor (`M.languageFloor()`, `picture-text-translation-fit-letters.md`), and under the floor it is drawn there and marked "Look by hand" (`fit` 1), as before.
- Owner's order (2026-11-26, poll 229, `workflow/decisions-made-product-113.md`): grow, wrap, shrink; the room's numbers are the architect's (2026-11-26, agent channel request 3129). Wider than the room it is wrapped between words first (`picture-text-fit-wrap.md`).
- `M.languageFitted(put, layout, board)` (`assets/js/pg_image_editor_model.js`) runs the loop for the editor's `drawLanguage()`; `pg_picture_tr_language_fitted()` is its server twin. All rooms are read before any text moves.

## How is the room read?
- `M.languageRoom(doc, id, board)` / `pg_picture_tr_language_room()`: a rectangle in the text's own turned frame (its box before the angle). It goes out across first, then upright with the new width.
- The picture's edge less a margin: the text's own distance when nearer than a tenth of the shorter side, else 4% of it. A layer under the text that holds its whole box and is smaller than the picture (a card) bounds the room the same way, with the card's shorter side.
- Another layer is turned into the text's frame and taken as its upright box; one in the way stops the room less the smaller of the distance and half the text's size; toward another text, the middle between the two less half that gap.
- Not read: a hidden layer, one with opacity 0, one under the text covering the whole picture, one above that covers the text whole, and one that partly overlaps the text's box (no side to read).
- Null (no artboard or picture size, no box): the old rule, the source's box. A sticker's label is not fitted; a sticker is a layer in the way.

## Where is the text put?
- `M.languagePlace()` / `pg_picture_tr_language_place()`: inside its source's box exactly as before (aligned side across, `M.textFit()`; middle upright). Past it, the same place slid inside the room toward the free side; wider than the room, the room's middle; for a turned text, turned around the source's middle.

## What stays of the old rule?
- `M.languageFit()` / `pg_picture_tr_language_fit()` take the room's width and height as two more arguments (the room less what the layout and the box differ by); without them they are the old rule, so `fit.json` is unchanged. The room holds the source's box, so no text ends smaller than before and no picture is marked that was not.

## How is it tested and measured?
- `tests/picture_text_fit_test.php` on `tests/fixtures/picture_language/room.json` (20 cases: the owner's three, four edges, side by side, stacked, a headline over a line, a card, turned texts, a sticker, layers not read, no size) with a stub layout, PHP and Node within 0.011.
- `measure.json`: 24 layouts, each with translations 1.0, 1.3, 1.6, 2.0, 2.5 times its width. "Look by hand" before the room and with the growth alone: 0/0, 0/0, 4/0, 8/0, 24/2 (36 of 120, then 2); with the wrap 0 (`picture-text-fit-wrap.md`). The test holds no text smaller than the old rule and fewer marks.
- `tests/picture_translation_server_test.php`'s fit test uses a document with no size for the old rule.
