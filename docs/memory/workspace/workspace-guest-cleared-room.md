---
name: workspace-guest-cleared-room
description: A guest's requests on one message (file address, tick, reaction, vote) reach only the room's current conversation, after its clear floor, as the guest page lists it
module: includes/workspace/guests.php
---

## What does a guest reach after a room is cleared?
- Only messages after `ws_channels.era_floor` (`ws_guest_room_floor()`), the ones `ws_guest_messages()` lists. The earlier conversation stays for the team (`eras.php`), never for a guest.
- `ws_guest_file_send()`, `ws_guest_check()` and `ws_guest_react()` hold their message to `ws_guest_message_reach_sql()` (room, after the floor, not deleted); `ws_guest_vote()` holds the poll's message to `ws_guest_message_in_reach()`.

## What did it fix?
- `workspace_guest.php?file=<message id>` streamed a file staff had put in the room before it was cleared: message ids are sequential, so a guest (or a guest shared into a team channel after its clear) read files the page never showed them. Ticks, reactions and votes on those messages were taken too. Test: `tests/workspace_guest_cleared_room_test.php`.
