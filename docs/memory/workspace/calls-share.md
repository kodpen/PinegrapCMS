---
name: calls-share
description: Screen sharing in a Workspace call (CL4, #10908): one share at a time by share/unshare marks, the share ends when its sharer leaves, the screen line, the share button
---

## How does the server know who shares?
- No schema: two signal kinds addressed to nobody (as the keep-alive ping), `share` and `unshare`, in `ws_call_signals` (its `kind` is a VARCHAR).
- `ws_call_sharer_from()` (pure): the call's newest share/unshare mark decides; a `share` counts only from a member still in the call and only for `WS_CALL_SHARE_LIFE` (75 s). The browser renews it with its keep-alive every 30 s.
- The live reads' call part says who: `call.sharer` (member id, 0 for none) from `ws_call_present()`. The API's call route does not name the sharer.

## What are the rules?
- Any member in the live call may share (a guest too, once CL5 lets guests in); one share at a time: a `share` while another member's share holds is refused with "Someone else is sharing their screen."
- `ws_call_share_send()` reads the sharer and writes the mark under the channel's call lock, so two shares at once cannot both win.
- `unshare` is the sharer's own: from anyone else nothing is written (it would end another's share).
- The share ends when its sharer leaves (`ws_call_leave()` deletes the member's signals, its marks with them), when the call ends, and 75 s after a closed tab's last renewal. Marks count in the member's 300 signals a minute.

## How does the screen travel?
- Every connection has a line for the screen from its first offer: the offerer adds a `sendrecv` video transceiver after its own tracks; the answerer takes the offer's matching line (`screenSlot()`: a video call's second video line, a voice call's only one) and sets it to `sendrecv`. A share only puts the screen's track on it (`replaceTrack()`), so no new offer is sent; a stop puts none.
- The screen line's track arrives with no stream of the member's own; the window keeps it as the member's `screen`. Changing the camera never replaces the screen line's track.
- `getDisplayMedia({ video: true, audio: false })`: the browser lets the person choose a screen, window or tab. The browser's own "Stop sharing" ends the share as the button does. Nothing of the screen reaches the server.

## Where is the button?
- In the call window's tools after Mute and the camera: `pg-icon-button` `bi-display` "Share screen"; while sharing `is-active`, `aria-pressed="true"`, `bi-display-fill`, "Stop sharing"; disabled with "{name} is sharing" while another member shares (`shareState()`). Not drawn without `getDisplayMedia` (phones only watch).

## How is a shared screen shown?
- In the call window's stage: `calls-share-stage.md`.

## How was it checked?
- Unit tests: `tests/workspace_calls_share_test.php`.
- Unit tests: `tests/workspace_calls_screen_test.php` (the stage's states and wiring).
- Chromium with fake devices and fake screen capture (`--use-fake-device-for-media-stream`, `--auto-select-desktop-capture-source`), four pages against an in-memory stand-in of the call actions, with the panel's stylesheets, at 1440 and 390 px: the others decoded the sharer's screen; a held button and a refused share; no sideways scroll, four tiles in view; full screen in and out; the sharer leaving ended the share and left full screen.
