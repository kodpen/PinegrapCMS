---
name: calls-share-stage
description: How a Workspace call shows a shared screen (CL4, #10908): the window's stage, the tile rail, the phone layout, smaller and full screen, the sharer's own notice
---

## How is a shared screen shown? (architect, agent channel 11318)
- The same window, never a new panel: while another member shares it takes `is-showing` and `is-stage` and grows from its corner to `min(72rem, 100vw - 2rem)` by `min(45rem, 100vh - 2rem)`. The share area (1fr) and a tile rail of 11rem beside it, "You" first, 16:9. Under 992px it covers the view (`inset: 0`, no radius), the tiles in one row of four (4.5rem) under the screen.
- The screen is never cut (`object-fit: contain`) on the tiles' background; "{name} is sharing" at its top left; the sharer's tile keeps its place with a screen icon by its name. A member joining during a share sees the stage at once; voice calls too.
- Two buttons at the share area's top right on `--pg-scrim`: "Make smaller" / "Make larger" (card size: the screen replaces the grid, 16:9, the tiles in one row under it; kept for the call, not stored) and "Full screen" / "Exit full screen" (`requestFullscreen()` on the share area, only where `document.fullscreenEnabled`; Esc leaves).
- The sharer's own screen is not shown: the window stays a card with the grid, and under the head a `.alert.theme-info.small` "You are sharing your screen" with a small danger "Stop sharing".
- The share ending, or its sharer leaving, returns the window to the grid and its earlier size, and leaves full screen. The pure `stageState()` gives the classes and the buttons' states.

Related: docs/memory/workspace/calls-share.md, docs/memory/workspace/calls-group.md
