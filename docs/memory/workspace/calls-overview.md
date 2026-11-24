---
name: calls-overview
description: Start here for Workspace voice and video calls (plan CL1-CL8, closed with #11156): what is built, the rules every change keeps, which file answers what
---

## What are Workspace calls?
- Voice and video calls in the Workspace, browser to browser (WebRTC, a mesh of every pair), between two people, a channel's members (four at most) and a guest in its room; screen sharing, several members at once (CN4). Owner (2026-11-20): asked in agent channel message 9832, scope poll 157 ("Aşamalı"), tables poll 158 (`workflow/decisions-made-workspace-40.md`, `workflow/decisions-made-schema-29.md`).
- About one participant in ten needs a relay (TURN) the site's owner enters; a larger meeting would be an outside meeting service in a plan of its own.

## Which rules does every change keep?
- No library, no CDN, no outside service but the STUN and TURN addresses of the Calls card. No recording, no transcript, nothing of the media stored.
- A call rings only while the Workspace is open in a browser: no push service.
- The signalling rides the existing live reads; an idle channel costs one file check (`calls-signalling.md`). A change must not raise that.
- Texts through `lang()`; look A; tests for the server's rules and, for the browser side, Chromium with fake media devices.
- `Permissions-Policy` keeps `camera=(self), microphone=(self)` (`waf/waf-security-headers-hsts.md`).

## Which file answers what?
- Tables, who may, start/join/leave/end, limits, API: `calls-server.md` (CL1, #10499).
- Which live read carries a call and its cost: `calls-signalling.md`.
- Two people, the ring, the window: `calls-screen.md` (CL2, #10522).
- The Calls card, STUN/TURN, the connection test: `calls-settings.md` (CL6, #10608).
- A group of four, Join, the speaking mark: `calls-group.md` (CL3, #10757).
- Screen sharing and its stage: `calls-share.md`, `calls-share-stage.md` (CL4, #10908).
- A guest in a call: `calls-guest.md` (CL5, #10975).
- The channel's call lines: `calls-lines.md` (CL7, #11125).
- Phone, ICE restart, a closed tab, a second window: `calls-endurance.md` (CL8, #11156).
- Raise hand, the weak-connection mark, "You are muted": `calls-hand.md` (CN6, #11385).
