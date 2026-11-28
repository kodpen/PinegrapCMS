---
name: main-menu-counts-active-screens
description: Plan main-menu-counts-active, how each part is drawn: the main menu's open row with a filled tile, the Workspace list's "Notifications" row, the quiet count on Products
priority: 6
---

## How is each screen put together?
- The open row: its tile is filled with the row's category colour and the drawing turns white (in the dark theme the menu's ground colour); the row takes the neutral quiet fill (`--pg-quiet`), its words `--pg-fg-emphasis` at weight 600. No blue fill, no blue words. A soft shadow in the tile's colour under the filled tile, only in the light theme if it muddies the dark one.
- On the rail the filled tile alone tells the open row; a count stays on the tile's corner.
- A panel's current entry takes the same: filled tile, quiet fill.
- Hover stays `--pg-hover` and never fills a tile, so hover and open are never mistaken.
- The Workspace list: a row "Notifications" right under "Today", the bell's icon, the unread count at its right edge as a red pill, nothing at zero. It opens the bell on Team (`window.pgBellOpen('workspace')`) and is never the list's current entry.
- The quiet count on Products: the number in `--pg-fg-2` with a dot in the warning tone before it, no pulse; on the rail the dot alone on the tile's corner. `pg_menu_live_count()` keeps its green pulsing dot for what is going on now.

## Where are the steps?
- `plans/main-menu-counts-active.md` (MB1 to MB5); its close-out deletes this file with it.
