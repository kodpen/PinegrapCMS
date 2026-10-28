---
name: designer-sticky-panel-layout
description: Designer panel docking (docked drawer vs in-flow, 32px rail, sd-dock-* classes, _SD_DOCK_BP 992px, one layer at a time, three-strip bottom panel, rail icons) and the bottom panel edge drag.
---

## What states do the side panels have?
- **In flow**: `.sd-body` flex flow, the classic layout. **Docked**: `position:absolute` drawer sliding over the canvas; a 32px rail (`.sd-rail`) takes its place in flow.
- State classes on `.sd-body`: `sd-dock-left` / `sd-dock-right` (docked), `sd-open-left` / `sd-open-right` (open).

## What are the rules?
- **Below 992px docking is forced** (`sd-dock-forced`); above, the panel-header button chooses and writes `localStorage` (`pinegrap_designer_dock_left` / `_right`). Threshold `_SD_DOCK_BP`; measured with `window.innerWidth`, not `matchMedia`, so CSS and JS do not carry the same number in two places: that constant is the single source.
- **Write no rule that stacks panels vertically on narrow screens.** The `@media (max-width:768px)` rule deleted on 2026-09-16 did exactly that and turned the canvas into a letterbox. The narrow-screen solution is docking.
- **Docked panel width is `clamp(240px, var(--sd-left-width), calc(100vw - 64px))`.** Edge dragging still writes `--sd-left-width` / `--sd-right-width`, so the user's chosen width applies in both states. Never write inline `width` on the panel.
- **Only one layer is open at a time.** Opening a drawer closes the other and the bottom-panel sheet. The backdrop (`#sd-dock-backdrop`) shows **only** under `sd-dock-forced`: on wide screens the canvas must stay clickable with a docked panel open.
- **A docked bottom panel is three strips.** `.sd-panel-bottom.sd-docked` turns the grid into vertical flex; three `.sd-bp-quad` headers shrink to 28px strips, the touched strip opens with `sd-dock-open` and the panel becomes 52vh. Draw no separate button for a strip: the headers are the buttons; a second label set would duplicate `_sdT()` keys and badges.
- **The rail is generated from the palette tab strip.** Every tab in `#sd-panel-tabs` has a rail counterpart, iconed via `_SD_RAIL_ICONS`; when adding a tab write its icon there, else it shows `bi-grid`. The rail button selects the tab with `click()`; it does not copy tab logic.

## How does the bottom panel's top edge drag?
- `#sd-bp-vresize` and the two column splitters run `_sdBpDrag()` (bottom panel builder): pointer capture, the canvas iframe's pointer events off for the drag, one height write per frame, cleanup on `pointerup`/`pointercancel`/`lostpointercapture`. With mouse events on the document (before #2600) the moves over the iframe went to its document (the drag stuttered) and a release there never ended it.
- The edge owns a strip 4px above and 5px below the line (z-index 20, over the canvas). The canvas frame's bottom and corner handles stand at `bottom:4px` inside the canvas; at `bottom:-6px` they covered the 6px above the line and a grab there resized the canvas instead (`initResize()`).
- `.sd-panel-bottom.sd-resizing.collapsed { transition: none }` follows the later `.collapsed` transition, so the snap-to-collapse preview does not animate mid-drag. The saved layout format is unchanged.
