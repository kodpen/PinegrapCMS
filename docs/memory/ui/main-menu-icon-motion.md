---
name: main-menu-icon-motion
description: Main menu row icons in motion: one hover move per drawing (data-m, pg-mi-* keyframes), the live Automation flows dot and Calendars day, the Design examples pattern "Menu row icon"
module: output
---

## How do the icons move?
- One move per icon (#MI2PR), `backend.src.css` after the tile rules: the moving part's `data-m` value names its keyframes (`.pg-mi [data-m="up"] { --pg-mi-k: pg-mi-up; }`), copied from `ICON_CSS` of the drawing. Played once, 0.5 s, `cubic-bezier(0.4, 0, 0.2, 1)`, on the row's `:hover` and `:focus-visible`, only inside `prefers-reduced-motion: no-preference`. The icon does not grow or change colour.
- A new drawing's move needs its `--pg-mi-k` line and `@keyframes pg-mi-<move>`; `tests/menu_icon_motion_test.php` checks every `data-m` of `output_menu()`'s drawings has both.
- Live, Automation flows: the dot (`data-l="run"`, hidden at rest) runs the connector in a 1.5 s loop while the row carries `data-pg-flows-running`, by translate keyframes (`pg-mi-run`); `offset-path` is not taken by SVG parts in every browser.
- Live, Calendars: the server prints the day (`.pg-mi-date`) and puts the site's time of day and date on the svg (`data-pg-day`, `data-pg-day-date`); `initMenuDay()` in `backend.src.js` moves the day on at the site's midnight. The Dashboard row is tiles: its clock, `data-pg-clock` and the clock's script went.
- Design examples' pattern "Menu row icon" (`pg_look_examples_menu_icon_pattern()`) draws a tile in each category's colour with the real `pg_menu_icon_tile()`; its items (`.pg-look-mi-item`) play the move on hover and focus, and the flows item's dot runs.

Related: docs/memory/ui/main-menu-icons.md, docs/memory/plans/main-menu-icons-redraw.md
