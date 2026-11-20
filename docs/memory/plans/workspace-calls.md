---
name: workspace-calls
description: Plan (owner 2026-11-20, polls 157 and 158): voice and video calls in the Workspace, browser to browser (WebRTC), two people and groups of four at most, guests, screen sharing; steps CL1-CL8
---

## What did the owner ask?
- Owner (2026-11-20, agent channel message 9832): voice and video calls in the Workspace, between two people, a channel's members, and members with a customer or guest, on shared hosting too.
- Scope, poll 157: "Aşamalı". Schema, poll 158: "Evet, böyle olsun". Records: `workflow/decisions-made-workspace-40.md`, `workflow/decisions-made-schema-29.md`.

## How does it work on shared hosting?
- Sound and picture go from browser to browser (WebRTC, every pair connected: a mesh); the site carries only who is in a call and the browsers' connection messages (offer, answer, candidates), read with the channel's own live reads. Nothing of a call's media reaches the server.
- Two limits, told to the owner in poll 157: about one participant in ten cannot connect directly and needs a relay (TURN) service the site's owner enters; a mesh loads every line, so a call holds four people at most. A larger meeting is the second stage, an outside meeting service, in a plan of its own.

## What is built?
- CL1, server (done, #10499; `workspace/calls-server.md`). The three tables by a migration step; start, join, leave, end; a signal sent to one member and read by it only; a call left by everyone, or silent for 2 minutes, ends; signals deleted then. Who may: a channel's members, in a direct conversation both people, a guest in its own room only. Limits on calls started and signals sent a minute. API and MCP: read only (a call's state), nothing starts a call for a person.
- CL2, two people. A call button (voice, video) in the channel's head; the other side rings where a mention rings today, with Accept and Decline; in the call: mute, camera, device choice, hang up, a small window that stays while the person moves between channels. `Permissions-Policy` lets the camera and microphone through on the Workspace and the guest page only (`waf/waf-security-headers-hsts.md`).
- CL3, a group. Up to four in one call, a grid; a fifth sees "This call is full"; who speaks is marked.
- CL4, screen sharing: one share at a time in a call, shown large.
- CL5, guests. The guest page shows a room's running call with "Join": no install, no account; the guest's name as the room knows it. A guest never starts a call.
- CL6, settings. A "Calls" card in the Workspace settings: on or off (off at first on an upgraded site), the STUN addresses in use (the default named on the card, as it is a third party the browsers reach), an optional TURN service (address, user, secret kept sealed, short-lived credentials handed to a call's members only) and "Test the connection".
- CL7, the channel's lines: "Call started", "Call ended" with its length and who took part, a missed call to the one who did not answer; counts as activity, never as a message to an agent.
- CL8, phone and endurance: the call screen on a phone, the route kept when the network changes (ICE restart), a closed tab leaves the call, a second tab of the same person does not join twice.
- Order CL1, CL2, CL6, CL3, CL4, CL5, CL7, CL8; one PR each, a and b where a step passes the size.

## Which rules does every step keep?
- No library, no CDN, no outside service but the STUN and TURN addresses of the card. No recording, no transcript, nothing of the media stored.
- A call rings only while the Workspace is open in a browser; the plan adds no push service.
- The signalling route's shape (which live read carries it, how often) is CL1's census, `workspace/calls-signalling.md`; it must not raise what an idle channel costs the server.
- Texts through `lang()`; look A; tests for the server's rules (who may, the full call, the clean-up) and, for the browser side, Chromium with fake media devices.

## How does the plan close?
- A `workspace/calls-overview.md` and its topic files say the state; `waf/waf-security-headers-hsts.md` names the policy; this plan is deleted.
