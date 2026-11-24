---
name: calls-popout
description: The Workspace call's Pop out (CN5, #11455): the call window moved into a Document Picture-in-Picture window above other windows and tabs, what it keeps, how it comes back
---

## What does Pop out do?
- A `pg-icon-button` "Pop out" (`bi-pip`) in the call window's head, after Full screen, moves the call window into a small window that stays above other windows and tabs (Document Picture-in-Picture, `documentPictureInPicture.requestWindow()`).
- Drawn only where the browser has it (Chromium on a computer); nowhere else, not on a phone. No setting, nothing remembered.
- The same nodes move (`popOut()` in `assets/js/workspace_calls.js`), so the connections, handlers and sound stay; each picture is played again where it lands (`mediaReplay()`).

## What does the small window show?
- The head's name and state, the tiles (a share or a pinned tile on the stage as on a phone), Mute, camera and Hang up, and the "You are muted" and sharing notices.
- `.ws-call.is-popped` fills the window; `.ws-call-not-popped` hides Make larger, Full screen, the window's menu, Pop out, Share screen, Raise hand, Microphone and camera and its panel. Both sheets: `backend.src.css`, `workspace_guest.css`.
- The small window's page gets the page's root attributes (theme, language) and its stylesheets: linked ones by address, inline ones by their rules (`popDocument()`).
- Its size comes from the pure `popState(can, popped, kind, count)`: video of two 360×320, a group's video 480×420, voice 320×180.

## How does it come back?
- Closing the small window (its own close or back-to-tab button) fires its `pagehide`; `popBack()` returns the call window to the page's body and redraws the stage.
- Hang up, or the call ending, closes the small window (`finish()`); a call in full screen leaves it before popping out.

## How was it checked?
- `tests/workspace_calls_popout_test.php` (the pure `popState()`, the wiring in the script, calls.php and both sheets). Not run: a browser with fake devices (#11455), as Document Picture-in-Picture needs a user click in headed Chromium.
