---
name: workspace-menu-tools
description: Tools tiles and More in the Workspace list (#3817, M2 of plans/workspace-menu-layout.md): which places, short names, colours, the More rows, ws-side-open, the tour opening what hides a place
---

## Which places are tiles, which wait behind More?
- `ws_nav_groups()`: `tools` = My Notes, Tables, Flows, Work Calendar, Planning Board, Decision Timeline, E-mails, each under its gate, in that order; `more` = Scheduled actions or messages, My MCP connections, Calendar subscription, Archived channels, Private channels (staff). `work` and `records` are gone; `ws_nav_group_titles()` names Tools and More (`tests/workspace_nav_tools_test.php`).
- A tile shows `short` (Calendar, Board, Decisions) or else the label; the full label is its title and `data-pg-menu-label`. `ws_side_nav()` passes `short` on. Short names keep a tile's name whole in the 250px menu with a scrollbar.

## How is it drawn?
- `.ws-side-tiles`: a two-column grid of `.ws-side-tile` links (`data-ws-nav`), the icon in the colour its row had (the `:is(.ws-channel-link, .ws-side-tile)[data-ws-nav]` rules, a lighter tone in the dark theme; Tables and E-mails got theirs; `tests/panel_contrast_test.php` reads them), else `--pg-fg-2`.
- The last tile is the More button (`aria-expanded`, `aria-controls="ws-side-more"`), drawn pressed while open, no chevron; open, `.ws-side-more` lists `more` as ordinary rows under the tiles. Its state is the person's (`ws-side-open` in localStorage) and it opens by itself on one of its screens. A closed Tools section still shows the open screen's tile or row.
- In the dark theme a mention count's ink is `--pg-on-accent`: white on the light lavender accent read at 2.2:1.

## How does the tour reach a place the list hides?
- `PGWsSide.has(key)` says the list holds a place, drawn or not; `PGWsSide.openFor(key)` opens Tools, and More for one of its places, then draws again. `workspace_tour.js` counts such a place as present and calls `openFor()` before ringing it (`present()`, `showInList()`).

## Why?
- Owner (2026-10-30, "Menü çok karışık"; `workflow/decisions-made-workspace-5.md`): seven sections became four; the screens of a working day at a glance, the rest one click away.
