---
name: canvas-phone
description: The canvas screens at phone width (CO8, #9361): chips under the name, Full screen at the foot, icon tabs (.pg-seg-icons), New table floating on Data, what DL5f still draws
---

## What changes on the card below 576 px?
- `joined()` in `workspace_page_card.js` draws the part chips twice: beside the name from 576 px (`d-none sm:d-flex`), under it on a phone (`d-flex sm:d-none`), so the name keeps its row.
- Full screen moves to the card's foot beside Versions on a phone: the foot's button (`sm:d-none`) shows the Page tab and presses the page card's own button (`[data-page-full]`), so the frame is one. The page card's button gets `d-none sm:d-inline-flex` only when it shows: a display class beats the `hidden` attribute.
- The line of tables wraps as it did (`flex-wrap`); the single page card and a drawing's card are unchanged.

## How are its tabs drawn?
- Owner (2026-11-13, channel message 2296): the tabs take the modern segmented look of the Communication area (MA12). Built on look A's `.pg-seg` with the shared modifier `.pg-seg-icons`: each `.pg-seg-item` has its icon and a `.pg-seg-label`; below 576 px only the chosen one shows its label, the others their icon (the label kept for screen readers, the name as `title`).
- On the card: the Drawing / Page switch and the Versions drawer's All / Page / Drawing filter (icons, counts). The bind drawer's Read only / Read and write is a plain `.pg-seg`.

## And the first-write question?
- Already a sheet from the bottom on a phone, both buttons full width (`canvas-consents.md`); nothing changed.

## And the Data list?
- New table is the filled button floating at the bottom right on a phone (`.ws-wt-fab`, placing only: fixed, 16 px from the edges and the safe area, z-index 1010 under the menu drawer); the toolbar's New table shows from 576 px.
- The rows are the list table driver's phone cards (every column under its label). Its strips All / Mine / Channels / Shared with me and Tables / Deleted tables are icon segments (`ws_wtable_list_segment_icons()`), so both fit one line at 390 px.

## What did CO8 leave to DL5f?
- Built from the nearest recipe, no new one: the floating button is `pg-button-fill` with its label, not the drawing's 52 px icon tile. Not built: the drawing's compact row card (name, rows, place, time, canvases on two lines) and the groups "Channel tables" / "Mine and shared"; the list driver has neither.

## How was it checked?
- `tests/workspace_canvas_phone_test.php`. On a sandbox panel (#9361) a two-part canvas made through the API, shot dark and light at 390 and 1440 px: the wide card unchanged, the phone card and Data list as above, and CO7d's lines undone from the panel (a new binding taken off, a changed access put back).
