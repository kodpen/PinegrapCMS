---
name: calls-overview
description: Start here for Workspace voice and video calls (CL1-CL8, then CN1-CN7 closed 2026-11-24): what is built, the rules every change keeps, what is left out, which file answers what
---

## What are Workspace calls?
- Voice and video calls in the Workspace, browser to browser (WebRTC, a mesh of every pair), between two people, a channel's members (four at most) and a guest in its room; screen sharing, several members at once. Owner (2026-11-20): asked in agent channel message 9832, scope poll 157 ("Aşamalı"), tables poll 158 (`workflow/decisions-made-workspace-40.md`, `workflow/decisions-made-schema-29.md`).
- Owner (2026-11-24): the second round (agent channel message 12760) asked for remembered devices, a call that survives a click on an order, several shares, a window that grows, the usual features of a modern call and background removal (`workflow/decisions-made-workspace-50.md`).
- About one participant in ten needs a relay (TURN) the site's owner enters; a larger meeting would be an outside meeting service in a plan of its own.

## Which rules does every change keep?
- No library, no CDN, no outside service but the STUN and TURN addresses of the Calls card, and the background effects' pinned files, which the server fetches once when their switch is turned on (`calls-background.md`). No recording, no transcript, nothing of the media stored. No schema beyond the CL1 tables: new marks are signal kinds (`calls-share.md`, `calls-hand.md`).
- A call rings only while the Workspace is open in a browser: no push service.
- The signalling rides the existing live reads; an idle channel costs one file check (`calls-signalling.md`). A change must not raise that.
- What the browser remembers (devices, mute, camera, size, background) is one `localStorage` key, never sent to the site (`calls-choices.md`).
- Texts through `lang()`; look A (`pg-icon-button`s, an active one `is-active` with `aria-pressed`); a change of the window's look goes into both `backend.src.css` and `workspace_guest.css`; tests for the server's rules and the pure browser parts.
- `Permissions-Policy` keeps `camera=(self), microphone=(self)` (`waf/waf-security-headers-hsts.md`).

## What is left out?
- Recording and transcripts (poll 157), more than four people (an outside meeting service, a plan of its own), a call that follows the person onto another panel page (`calls-leave-page.md`).

## Which file answers what?
- Tables, who may, start/join/leave/end, limits, API: `calls-server.md` (CL1, #10499).
- Which live read carries a call and its cost: `calls-signalling.md`.
- Two people, the ring, the window: `calls-screen.md` (CL2, #10522).
- The Calls card, STUN/TURN, the connection test: `calls-settings.md` (CL6, #10608).
- A group of four, Join, the speaking mark: `calls-group.md` (CL3, #10757).
- Screen sharing, several shares and the stage: `calls-share.md`, `calls-share-stage.md` (CL4, #10908; CN4, #11392).
- A guest in a call: `calls-guest.md` (CL5, #10975).
- The channel's call lines: `calls-lines.md` (CL7, #11125).
- Phone, ICE restart, a closed tab, a second window: `calls-endurance.md` (CL8, #11156).
- Remembered microphone, camera, speaker, mute; the Speaker or headphones select: `calls-choices.md` (CN1, #11384).
- Make larger / smaller, Full screen, a pinned tile, Hide my picture: `calls-window.md` (CN2, #11415).
- Links open in a new tab during a call, a reload asks first: `calls-leave-page.md` (CN3, #11386).
- Pop out (Document Picture-in-Picture): `calls-popout.md` (CN5, #11455).
- Raise hand, the weak-connection mark, "You are muted": `calls-hand.md` (CN6, #11385).
- Blur background, Remove background, the Background effects switch: `calls-background.md` (CN7, #11412).
