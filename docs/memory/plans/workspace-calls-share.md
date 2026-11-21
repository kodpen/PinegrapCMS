---
name: workspace-calls-share
description: Workspace calls, step CL4: how a shared screen is shown large: the same window grown to a stage, the tile rail, one's own share, the narrow width, Make smaller and Full screen
---

## Where is the picture?
- `plans/workspace-calls/share-stage.jpg`, drawn from `share-stage.html` with the look A sheet kit; boards A to D. The plan: `plans/workspace-calls.md` (CL4, "shown large").
- Architect (2026-11-21, asked by CL4's worker in #10908, agent channel message 11310): a look, decided here and not asked of the owner.

## Where does the large screen go?
- In the same call window, in a second size: no panel over the channel. The window stays fixed on the body and across channels (`workspace/calls-screen.md`).
- While another member shares, the window is a stage (`is-stage`), grown from the same bottom right corner: `width: min(72rem, 100vw - 2rem)`, `height: min(45rem, 100vh - 2rem)`.

## How is the stage laid out?
- Head (name, length) and the bar of buttons as today. The body in two columns: the share area (`1fr`) and, at its right, a rail of tiles 11rem wide, one under the other, one's own first, 16:9, the speaking ring kept.
- Under 992 px the window covers the whole view (`inset: 0`, no rounded corners): the share area on top takes the height left, the tiles in one row under it (four equal shares, 4.5rem high), the bar last. No sideways scroll at 390 and 360 px.
- The share area: the video `object-fit: contain`, never cropped, on the dark ground a video tile has; at the top left a small name strip, "%s is sharing".

## Which states are there?
- Another member shares: the stage. The sharer's tile stays in the rail (camera or name) with a screen icon beside the name. One who joins during a share sees the stage at once; a voice call the same.
- One's own share: one's own screen is not shown (a mirror in a mirror). The window keeps the card size and the grid; under the head one line, `.alert.theme-info.small`, "You are sharing your screen", with a small danger button "Stop sharing"; the bar's "Share screen" is pressed (`aria-pressed="true"`). The browser's own stop bar ends it too (the track's `ended`).
- While one shares, the others' "Share screen" is disabled, its title "%s is sharing"; a race is settled by the server's rule and the loser reads the toast.
- The share ended or the sharer left: back to the grid and to the size before the share; full screen is left.
- "Share screen" is drawn only where `navigator.mediaDevices.getDisplayMedia` exists: a phone only watches.

## Which controls does the share area have?
- Two `pg-icon-button`s at its top right, on a dark half-clear ground, reached by keyboard, each with an `aria-label`:
  - "Make smaller" / "Make larger": between the stage and the card size. At the card size the share takes the grid's place (16:9, contain) and the tiles are one row of small tiles under it. The choice holds for that call and is not stored.
  - "Full screen" / "Exit full screen": `requestFullscreen()` on the share area, only where `document.fullscreenEnabled`; Esc leaves.
- No fit or fill control: always contain.

## What must be true?
- No new colour; sizes in rem, look A variables; the classes go into the call window's own stylesheet. The Design examples screen does not draw the Workspace, so this file is the recipe.
- Tests: the stage's state as a pure function in `tests/workspace_calls_screen_test.php` (who shares, is it me, the size chosen: classes and button states); Chromium with fake media at 1440 and 390 px: nothing overflows, four tiles show, full screen enters and leaves.

Related: docs/memory/plans/workspace-calls.md, docs/memory/workspace/calls-group.md, docs/memory/workspace/calls-screen.md
