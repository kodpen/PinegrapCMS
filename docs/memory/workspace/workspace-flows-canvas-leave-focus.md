---
name: workspace-flows-canvas-leave-focus
description: Flow canvas: one keepalive save as the page is left (state.leaveSent) and the box's focus after C › Add the arrow (#4318, #4319, PR #4322)
---

## Why did leaving the page send the save twice?
- `beforeunload` sent the keepalive `ws_flow_save`; the page's close rejects that request's promise although the browser still delivers it. The `catch` set `dirty` again and cleared `state.saving`, so `pagehide` sent the same draft and the server refused it with a 409 (#4318).

## How is it sent once now?
- A keepalive save rejected with no server answer (`error.field` undefined) stores the `state.changes` it carried in `state.leaveSent` and shows no error. `saveOnLeave()` (`beforeunload`, `visibilitychange`, `pagehide`) skips while `state.leaveSent === state.changes`; a new change sends again.
- A page that stays after all (the promise rejected with no answer) keeps the changes dirty and saves them after `SAVE_DELAY` like any unsent change. If that keepalive did reach the server, the retry meets a 409 and "Load it again"; in practice a page that stays gets the keepalive's answer.
- Any save, sent or answered, resets `leaveSent` to -1.

## Where does the focus go after C › Add the arrow?
- `askConnect()` draws the arrow at once, but the box is redrawn while the `#ws-ask` dialog holds the page inert and its close gives the focus back to the removed box (#4319). The box is selected and focused once the dialog's `hidden` has come (and the answer), so Add and Cancel both end on the box.
- `canvasKey()` resets `state.tap`: a click before C and one after the dialog, within 450 ms, are two clicks, not the double tap that opens the box's first field.

## How was it checked?
- `node tests/js/flows_canvas.js` (harness of PR #4316): 28 of 30 before, 30 of 30 after, from sources and with `--min`; `--motion` 28 of 30, the two fade-timing focus losses in `workspace-flows-canvas-harness.md`.
