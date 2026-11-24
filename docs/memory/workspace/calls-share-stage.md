---
name: calls-share-stage
description: How a Workspace call shows shared screens (CL4 #10908, CN4): the stage, the tile rail, other shares as rail tiles a click swaps, phone layout, smaller and full screen, the sharer's notice
---

## How is a shared screen shown? (architect, agent channel 11318)
- The same window, never a new panel: while another member shares it takes `is-showing` and `is-stage` and grows from its corner to `min(72rem, 100vw - 2rem)` by `min(45rem, 100vh - 2rem)`. The share area (1fr) and a tile rail of 11rem beside it, "You" first, 16:9. Under 992px it covers the view (`inset: 0`, no radius), the tiles in one row of four (4.5rem) under the screen.
- The screen is never cut (`object-fit: contain`) on the tiles' background; "{name} is sharing" at its top left; the sharer's tile keeps its place with a screen icon by its name. A member joining during a share sees the stage at once; voice calls too.
- "Make smaller" / "Make larger" and "Full screen" are in the window's head at all times since CN2 (`calls-window.md`): made smaller, the screen replaces the grid at card size, 16:9, the tiles in one row under it; the size is remembered. The share area's top right holds only Unpin, while a tile is pinned.
- The sharer's own screen is not shown: under the head a `.alert.theme-info.small` "You are sharing your screen" with a small danger "Stop sharing"; with no other share the window stays a card with the grid.

## How are several shares shown? (CN4)
- The stage holds one share: the one the viewer chose, else the other sharer with the lowest member id. Every other share is a tile at the rail's end, after the members' tiles: a `button.ws-call-tile.is-screen` with the screen (`object-fit: contain`, shown in voice calls too) and the sharer's name by a `bi-display` icon, labelled "Show the screen of {name}".
- A click on such a tile puts that share on the stage; the one there goes to the rail. The choice is kept for the call in the window (`watching`), not stored; when the chosen share ends the first other takes the stage.
- A sharer sees the others' shares: the stage and its own notice together. One's own share is never on the stage nor the rail.
- The share tiles do not count as members: `data-count` and `is-group` count the members' tiles only. On a phone they wrap under the row of four.
- The pure `stageState(sharers, me, size, can, full, chosen, view)` gives the classes, the buttons' states, `main` (the share on the stage, 0 for none) and `rail` (the other shares).

## When does the stage go?
- The last other share ending, or its sharer leaving, returns the window to the grid and its chosen size; full screen is the whole window's (CN2) and stays.

Related: docs/memory/workspace/calls-share.md, docs/memory/workspace/calls-group.md
