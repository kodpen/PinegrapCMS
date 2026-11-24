---
name: calls-choices
description: Workspace calls remember the chosen microphone, camera and speaker and the mute and camera state in the browser (CN1): the key, the fallback, the Speaker or headphones select
---

## What does a call remember, and where?
- The microphone, camera and speaker last picked in the call window's gear panel, and whether the person was muted or had the camera off, in the browser's `localStorage` under one key, `pg_ws_call_choices` (JSON). Never sent to the site: device ids mean nothing in another browser.
- The same key keeps the window's size, `size`: `large`, `small` or empty (CN2, `calls-window.md`).
- Each change is saved when it is made: Mute, Turn camera off/on, a select of the gear panel. Reads and writes are in try/catch (`choicesLoad()`, `choicesSave()` in `assets/js/workspace_calls.js`); a private window or blocked site data remembers nothing and the call goes on with the defaults.
- Owner (2026-11-24): the devices fell back to a default every time (`workflow/decisions-made-workspace-50.md`).

## How are they used at the next call?
- Every start, join, Join here and Accept goes through `begin()`, which asks `getUserMedia` with the remembered devices as `deviceId: { ideal }` (`mediaAsk(kind, devices, true)`), so a device no longer there gives way to the browser's default instead of failing. A device picked during the call is asked `exact`.
- A call that ended muted or with the camera off starts so: the tracks are off from the first moment, and the buttons show the pressed state.
- The microphone asks for noise suppression, echo cancellation and automatic gain (already so since CL2).

## Where is the speaker chosen?
- "Speaker or headphones", a third select under Microphone and Camera in the gear panel, only where the browser has `HTMLMediaElement.setSinkId` (Chromium; not Safari or Firefox by default). It plays every other member's tile sound on that output, new tiles included; a remembered output no longer listed shows the browser's default (`speakerOf()`). The key "Speaker" was taken by the events template's "Konuşmacı".

## How was it checked?
- Unit tests: `tests/workspace_calls_choices_test.php` (reading broken or wrong stored text, one change keeping the others, the soft and exact device asks, the speaker fallback, the wiring). Not run: a browser with real devices unplugged between calls.
