---
name: calls-window
description: The Workspace call window's head (CN2, #11415): Make larger / Make smaller remembered, Full screen of the whole window, a tile pinned large, Hide my picture in the window's menu
---

## What is in the call window's head?
- The head is the window's first line: the call's name, its state, then `pg-icon-button`s "Make larger" / "Make smaller" and "Full screen" / "Exit full screen" (pressed while on), and the window's menu (three dots, "More"). They are there at all times, with or without a share.
- Make larger gives the stage size (`min(72rem, 100vw - 2rem)` by `min(45rem, 100vh - 2rem)`, the whole view under 992px); with nothing on the stage the grid fills it. Make smaller keeps the card, a share or pin shown at card size.
- The size is remembered in `pg_ws_call_choices` as `size` (`large`, `small`, empty), saved on each click (`calls-choices.md`). Empty: large while something is on the stage, else the card (CL4's way).
- Full screen is `requestFullscreen()` on the whole window (`.ws-call`), only where `document.fullscreenEnabled`; it is always drawn large, and Esc leaves. A share ending no longer leaves it.

## How is a tile pinned?
- Every member tile, one's own too, holds a transparent `button.ws-call-tile-pin` over it, labelled "Show large": a click puts that tile's picture on the stage in the share's place (`is-showing`, `is-pinned`), its tile leaves the rail, every share goes to the rail. A second click, Unpin at the stage's top right, or a click on a share tile lets it go.
- A pin is the viewer's own, kept for the call in the window (`mine.pin`: -1 none, 0 one's own, else the member id), never sent. The pinned member leaving, or one's own picture hidden, unpins.
- In a voice call, or one's own camera off, the stage shows the person icon over the picture's place.
- On a phone the window keeps its place (`calls-endurance.md`); a pinned tile takes the width, the rail one row under it.

## What does Hide my picture do?
- The window's menu (`ul.menu.ws-call-menu`, opened by the module itself, so the guest page needs no Bootstrap script) holds "Hide my picture" / "Show my picture": it hides one's own tile on this screen only; the others still see it, the camera stays on. Not remembered.

## How was it checked?
- `tests/workspace_calls_window_test.php` (the size read and kept, the pure `stageState()` with sizes, full screen and pins, the wiring in both sheets), `tests/workspace_calls_screen_test.php`. Not run: a browser with fake devices at 1440 and 390 px.
