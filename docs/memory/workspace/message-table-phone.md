---
name: message-table-phone
description: A table written in a channel message at phone width (#10816, PR #10879): natural column widths, 11rem cells, the frame scrolls sideways; a wide screen unchanged
---

## How is a message's table drawn on a phone?
- Markdown table in a message, thread or forwarded copy: `.ws-table-wrap > table.ws-table` (`includes/workspace/render.php`), styled in `assets/css/backend.src.css`, section "Tables and checklists written in a message".
- Below 576px the table is `width: max-content` and each cell wraps at `max-width: 11rem`; the frame (`overflow-x: auto`, `overscroll-behavior-x: contain`) scrolls to the columns past its edge. Cells and `code` in them get `overflow-wrap: anywhere`, so a link or path longer than 11rem breaks inside its cell. `code` is named because Bootstrap's own wrap on `code` does not lower the cell's width; without the rule, one URL stretched the frame to 815px. The page itself never scrolls sideways.
- A wide screen keeps `width: auto` and the 24rem cell. Calculation blocks (`.ws-calc-table`) and the table editor (`.ws-table-edit`) are not inside `.ws-table-wrap` and keep their own rules.

## Why?
- With `width: auto`, a table wider than the frame is laid out at its narrowest: every column one word wide, a sentence one word per line, and the right-hand column a sliver at the frame's edge. The owner saw it cut off on a phone (#10816, 2026-11-20).
- 11rem keeps a two-column table (a short first column and a sentence) inside a 360px forward copy, so it needs no scroll.

## What could not be reproduced?
- In Chromium's phone emulation (360, 393, 414px), a sideways swipe did scroll the frame before the fix too. A phone browser that does not scroll it was not found. If it shows up again, check that browser first.

## What pins it?
- `tests/workspace_message_table_phone_test.php`.
