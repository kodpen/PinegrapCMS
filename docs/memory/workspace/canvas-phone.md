---
name: canvas-phone
description: The canvas screens at phone width (CO8, #9361): chips under the name, Full screen at the card's foot, New table floating on Data, what DL5f still draws
---

## What changes on the card below 576 px?
- `joined()` in `workspace_page_card.js` draws the part chips twice: beside the name from 576 px (`d-none sm:d-flex`), under it on a phone (`d-flex sm:d-none`), so the name keeps its row.
- Full screen moves to the card's foot beside Versions on a phone: the foot's button (`sm:d-none`) shows the Page tab and presses the page card's own button (`[data-page-full]`), so the frame is one. The page card's button gets `d-none sm:d-inline-flex` only when it shows: a display class beats the `hidden` attribute.
- The line of tables wraps as it did (`flex-wrap`); the single page card and a drawing's card are unchanged.

## And the first-write question?
- Already a sheet from the bottom on a phone, both buttons full width (`canvas-consents.md`); nothing changed.

## And the Data list?
- New table is the filled button floating at the bottom right on a phone (`.ws-wt-fab`, placing only: fixed, 16 px from the edges and the safe area, z-index 1010 under the menu drawer); the toolbar's New table shows from 576 px.
- The rows are the list table driver's phone cards (every column under its label).

## What did CO8 leave to DL5f?
- Built from the nearest recipe, no new one: the floating button is `pg-button-fill` with its label, not the drawing's 52 px icon tile. Not built: the drawing's compact row card (name, rows, place, time, canvases on two lines) and the groups "Channel tables" / "Mine and shared"; the list driver has neither.

## How was it checked?
- `tests/workspace_canvas_phone_test.php`. On a sandbox panel (#9361) a two-part canvas made through the API, shot dark and light at 390 and 1440 px: the wide card unchanged, the phone card and Data list as above, and CO7d's lines undone from the panel (a new binding taken off, a changed access put back).
