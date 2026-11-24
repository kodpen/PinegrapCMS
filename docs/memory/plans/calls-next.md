---
name: calls-next
description: Plan: Workspace calls after CL1–CL8: remembered devices, a window that grows, one's own picture, links in a new tab during a call, several screen shares at once, pop-out, raise hand
priority: 6
---

## Why is this a plan?
- Owner (2026-11-24, agent channel message 12760): the devices fall back to a default at every call, a click on an order ends the talk, only one person can share a screen, the window does not grow and one cannot show oneself; he asked for these and the usual features of a modern call (`workflow/decisions-made-workspace-50.md`).
- The rules of `workspace/calls-overview.md` stay: no library, no outside service, nothing of the media stored, four people at most, no schema here. Steps `[plan:calls-next] CN<n> — <topic>`, one worker each, in order where a step names one.

## What does each step build?
- CN1 Remembered choices. The chosen microphone, camera and speaker and whether the person joined muted or with the camera off are kept in the browser (`localStorage`, one key, per browser: device ids mean nothing elsewhere) and used at the next start, join and accept; a device no longer there falls back to the browser's default. New: a Speaker select where `setSinkId` exists. The microphone asks for echo cancellation, noise suppression and automatic gain.
- CN2 Window sizes and one's own picture. "Make larger" / "Make smaller" and "Full screen" are in the window's head at all times, not only during a share (`workspace/calls-share-stage.md` sizes); the size is remembered with CN1's key. A click on a tile pins it large, the others go to the rail; one's own tile can be pinned too. "Hide my picture" / "Show my picture" in the window's menu hides only one's own tile on one's own screen. After CN1.
- CN3 Leaving the Workspace during a call. While in a call every link of the Workspace that leaves the page (an order, a customer, a page, the panel's menu) opens in a new tab, so the call's page stays; a reload or a close asks through the browser's leave warning.
- CN4 Several shares at once. Every member of the call may share while others share: `ws_call_sharer_from()` becomes a list (`call.sharers`), the refusal "Someone else is sharing their screen." goes, each member still one share. The screen line is `sendrecv` already, so no new offer. The stage shows the share the viewer chose, the other shares as rail tiles with the screen icon, a click swaps; a sharer sees the others' shares. Replaces "one share at a time" of `workspace/calls-share.md`.
- CN5 Pop-out. "Pop out" puts the call into a small window that stays above other windows and tabs (Document Picture-in-Picture; where the browser lacks it the button is not drawn): tiles, Mute, camera, Hang up; closing it returns the call to the page. After CN2.
- CN6 Raise hand and connection mark. "Raise hand" / "Lower hand" (a signal kind as the share mark; a hand icon on the tile); a tile shows a weak-connection icon while its connection restarts or loses packets (`getStats()`), and "You are muted" when the person speaks while muted.
- CN7 Close-out: `workspace/calls-overview.md` and the calls files updated, this file deleted.

## How is each drawn?
- Look A, from the Design examples screen: `pg-icon-button`s in the window's head and tools, an active one `is-active` with `aria-pressed`; selects as the Microphone and camera ones today; the window's menu a `pg-menu`. Phone: under 576px the window keeps its place at the top (`workspace/calls-endurance.md`); a pinned tile takes the width, the rail one row under it.
- No step waits for a drawing (`workflow/screen-described-in-plan.md`).

## What is left out?
- Recording and transcripts (the plan's rule from poll 157), background blur (needs a model or library), more than four people (an outside meeting service, a plan of its own), a call that follows the person onto another panel page.

## How is it checked?
- Unit tests for the pure parts and the server's rules; Chromium with fake media devices and fake screen capture as in `workspace/calls-share.md`, at 1440 and 390 px.
