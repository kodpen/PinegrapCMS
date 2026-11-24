---
name: message-table-phone
description: A channel message table at phone width (#10816, #11319): natural column widths, 11rem cells, sideways scroll, below 768px as tall as the table up to 90vh; wide screens unchanged
---

## How is a message's table drawn on a phone?
- Markdown table in a message, thread or forwarded copy: `.ws-table-wrap > table.ws-table` (`includes/workspace/render.php`), styled in `assets/css/backend.src.css`, section "Tables and checklists written in a message".
- Below 576px the table is `width: max-content` and each cell wraps at `max-width: 11rem`; the frame (`overflow-x: auto`, `overscroll-behavior-x: contain`) scrolls to the columns past its edge. Cells and `code` in them get `overflow-wrap: anywhere`, so a link or path longer than 11rem breaks inside its cell. `code` is named because Bootstrap's own wrap on `code` does not lower the cell's width; without the rule, one URL stretched the frame to 815px. The page itself never scrolls sideways.
- Below 768px the frame (`.ws-table-wrap`) is `max-height: 90vh`, `overflow-y: auto`, `flex: none`: as tall as its table up to 90vh, the rest scrolling inside, so a long table shows at least 70vh of rows and a short one keeps its own height. Vertical overscroll is not contained: a swipe on a full table moves the conversation on (#11319).
- A wide screen keeps `width: auto` and the 24rem cell. Calculation blocks (`.ws-calc-table`) and the table editor (`.ws-table-edit`) are not inside `.ws-table-wrap` and keep their own rules.

## Why?
- With `width: auto`, a table wider than the frame is laid out at its narrowest: every column one word wide, a sentence one word per line, and the right-hand column a sliver at the frame's edge. The owner saw it cut off on a phone (#10816, 2026-11-20).
- 11rem keeps a two-column table (a short first column and a sentence) inside a 360px forward copy, so it needs no scroll.

## Why the 90vh frame?
- #11319 (2026-11-24): on a phone a message's table sat in a box about 100px high. In Chromium's phone emulation at 390px the frame was already its table's height (no cap found), so the rule pins the expected height instead of undoing a found cap; at 390x844 a 25-row table's frame measured 760px, a two-row one 63px. The phone's browser was not at hand.

## What could not be reproduced?
- In Chromium's phone emulation (360, 393, 414px), a sideways swipe did scroll the frame before the fix too. A phone browser that does not scroll it was not found. If it shows up again, check that browser first.

## What pins it?
- `tests/workspace_message_table_phone_test.php`.
