---
name: calls-background
description: Blur/Remove background in a call (CN7, #11412): the Background effects switch, pinned MediaPipe files fetched into data/call_background, the browser's own blur first
---

## Which model and runtime, and why not shipped?
- Architect (2026-11-24, #11412): MediaPipe Selfie Segmenter float16 (256×256, 249,537 bytes) on `@mediapipe/tasks-vision` 1.2.2 (Apache-2.0, the version older than two weeks at build time). Not in the repository or the update package: 15.4 MB for every site, for an effect most never switch on, is too much.
- The four files and their size and SHA-256 are written in `ws_call_background_files()` (`includes/workspace/calls_background.php`): `vision_bundle.mjs`, `wasm/vision_wasm_internal.js` and `.wasm` from cdn.jsdelivr.net (npm, pinned version), the model from storage.googleapis.com (`float16/1/`, never `latest`). A new pin is a code change: fetch the files, write the new sizes and digests.

## How do the files get to the site?
- Calls card, switch "Background effects" (off by default; config_text `ws_calls` key `background`). Saving it on runs `ws_call_background_fetch()`: each file via `pg_curl_run()` into `data/call_background/<file>.part`, its size and SHA-256 checked, then renamed; one that differs or fails removes the whole folder and refuses the card's save with "The background files could not be fetched (…); nothing was kept." A manifest `pinned.json` (name → digest) is written last.
- Saving it off deletes the folder (`ws_call_background_remove()`). Already there for the same pins: no fetch.
- An update keeps the folder while the pins are unchanged; with new pins the manifest no longer matches (`ws_call_background_ready()` false), the buttons need the model go away and the card says "The files are not there for this version: Save fetches them again."

## How does the browser get them?
- `workspace_call_background.php?f=bundle|loader|wasm|model&v=<digest start>` (root, no session): 404 unless the switch is on, the folder is ready and the file's SHA-256 still matches its pin (read on every answer; the file-integrity check leaves data/ out, so this is the check). Cache-Control public, a year, immutable; ETag the digest.
- The addresses reach the call window as `calls.background` of the panel's screen config and the guest page's (`ws_call_background_config()`), null while off or not ready. Browsers never fetch from outside.

## When are the buttons drawn and how do they run?
- In the gear panel ("Microphone and camera") of a video call, under the selects, label "Background": "Blur background", "Remove background", pressed while on, a second click turns it off (`backgroundDraw()` in `workspace_calls.js`).
- `assets/js/workspace_call_background.js` (`PGWsCallBackground`, bundled before `workspace_calls.js` in both bundles): `offer()` draws Blur where the camera lists `backgroundBlur: true` (no download), or with the files, WASM SIMD, `canvas.captureStream` and canvas `filter`; Remove with the files, SIMD and captureStream. Blur takes the camera's own blur first (`route()`).
- The model runs on CPU at 24 frames a second on a canvas; its track replaces the camera's in the stream and on every connection but the screen's line (`videoSwap()`); a camera change stops and restarts the effect. The choice is kept as `background` in `pg_ws_call_choices` and started quietly with the next video call where it can run.

## What could not be checked?
- Chromium with a fake camera ran the pipeline from local copies (model loaded in about 2 s, both modes give a live track). Not run: a real person, Safari, a hidden tab (timers slow to once a second there, so the picture stalls), the PHP endpoint on a live site. Tests: `tests/workspace_calls_background_test.php`.
