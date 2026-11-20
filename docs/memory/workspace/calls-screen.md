---
name: calls-screen
description: Workspace calls of two people on the channel screen (CL2, #10522): head buttons, the ring and Decline, the call window kept across channels, ws_call_sync each second, STUN default
---

## Where are the call buttons?
- In the channel head, before the tour: a telephone (Voice call) and a camera (Video call), `pg-icon-button`s from `PGWsCalls.headButtons()` (`assets/js/workspace_calls.js`, in `workspace.min.js`).
- In a channel of two people or more the viewer is a member of (`callable()`, groups in `calls-group.md`): not archived, not a mailbox, discussion or guest room, not an earlier version; only with WebRTC and `CFG.calls` (the call tables exist and the Calls card's switch is on). Marked `ws-head-wide`, so a phone's narrow head hides them until CL8.
- A group's call: `calls-group.md` (CL3). A guest's (CL5) is a later step.

## How does the other person hear it?
- Where a mention sounds: the channel screen's 4 s look (`ws_sync`) carries `rings` from `ws_call_rings_for()` (calls.php), asked only while the stamp says a call is live (`calls-signalling.md`).
- A call rings (`ws_call_rings()`, pure) in a channel of exactly two members, while its starter waits alone in it, for `WS_CALL_RING` (45 s). The card at the top right says "{name} is calling you" (with video), with Decline and Accept; two short tones every 2 s while the person's sound switch is on (`workspace-channel-sound.md`).
- Decline (`ws_call_decline`) ends the call (`ws_call_may_decline()`: only the person rung, while the caller is alone); the caller reads "The call was not answered.", the same as after 45 s with nobody.
- Like a mention, nothing rings while the tab is hidden or no channel is open: the look does not run then. No push service (plan rule).

## What does the call window do?
- Fixed at the bottom right on the body, so it stays while the person opens other channels; the name opens the call's conversation. Video: the other side large, one's own picture small; voice: the name.
- Mute, camera off/on (video calls), Microphone and camera (a select of each, `replaceTrack()` into every connection), Hang up (`ws_call_leave`). Calling…, Connecting…, then the length (m:ss).
- The call ends on this side when the other one leaves, when a connection stays broken 15 s ("The connection was lost."), and when the tab is closed (`pagehide`, `ws_call_leave` with keepalive).
- One call at a time: a second start says "You are already in a call.", and a ring during a call is not shown.

## How do the two browsers connect?
- `ws_call_sync` (call_id, call_after) every second while in a call: the call's `ws_call_live_part()` for its own channel, whichever channel is open, without the channel's look. A ping every 30 s (`ws_call_signal` kind ping).
- Of two members the later joiner (higher member id) sends the offer; candidates arriving before their description wait in a queue. Signals leave one after another.
- STUN: `ws_call_ice_servers()` gives the Calls card's addresses, else `stun:stun.l.google.com:19302`; the TURN relay and its credentials come only with a granted start or join (`calls-settings.md`). Without a relay about one pair in ten cannot connect (plan, poll 157).

## What could not be checked?
- Unit tests: `tests/workspace_calls_screen_test.php` (ring, decline, the pure browser parts under node, the wiring). Two real browsers with a camera were not run (#10522).
