---
name: calls-group
description: A group's Workspace call (CL3): no ring in a group, the head's Join button (n/4, full), the mesh grid of four, the speaking mark, five minutes alone
---

## Where does a group's call start and how do others come in?
- The call buttons are in every channel of two members or more the viewer is a member of (`callable()` in `assets/js/workspace_calls.js`); a channel of one has none.
- A group (more than two members, `isGroup()`) rings nobody: `ws_call_rings()` stays for conversations of two, so a channel of fifty is never rung at once.
- While a call is on in the open channel, the channel's 4 s look (`ws_sync`'s `call` part) drives the head (`PGWsCalls.channelCall()`): the two start buttons give way to "Join the call (n/4)"; at four it reads "This call is full." and is disabled. Nothing is shown to one already in it.
- The server's limit stays the rule: `WS_CALL_MAX_MEMBERS` (4) in `ws_call_join_check()`; a fifth whose Join raced the look reads the same "This call is full." as a toast. The screen config carries `calls.max`.

## How do four browsers connect?
- A mesh: every two members hold one connection; of each pair the later joiner (higher member id) offers. Three connections a browser at four.
- One connection of a group broken for 15 s greys its tile (`is-lost`); only when every connection is broken does the call end here ("The connection was lost.").

## What does the window show?
- A grid of tiles, one a member, one's own first and named "You"; the names come from the call part's members. Two columns from two tiles; a video call of two keeps the other one large and one's own small in the corner.
- A voice call shows each tile's name with a person icon; the sound still plays from each tile's hidden video element.
- The one speaking is ringed (`is-speaking`): each stream goes into an analyser of one AudioContext a call, measured every 150 ms; a level (RMS) of 0.04 or more marks it, held 600 ms. One's own tile is not marked while muted.
- A group's window is wider (28rem) and carries the channel's name.

## What happens when one is left alone?
- A group's call waits ("Waiting for others…") for others to come or come back, up to 5 minutes (`ALONE_FOR`), then ends here. A call of two still ends when the other one leaves, and goes unanswered after the ring's 45 s.

## How was it checked?
- Unit tests: `tests/workspace_calls_screen_test.php` (group, Join state, alone outcome, level and speaking under node, wiring).
- Chromium with fake media devices, five pages against an in-memory stand-in of the call actions: four in a video call each received the three others' pictures, the speaking marks followed the fake microphones, the fifth read "This call is full.", and one leaving left three tiles. A real server with two machines was not run.
