---
name: calls-signalling
description: Which live read carries a Workspace call (CL1 census): ws_sync and the guest page's state, the data/temp/ws_calls_live stamp that keeps an idle channel's cost, how often a browser in a call looks
---

## Which live read carries a call's signals?
- No new poll. The panel's `ws_sync` (every 4 s while a channel is open, `CFG.poll.active`) and the guest page's `state` action carry a `call` part: the channel's live call, its members and the signals sent to the reader.
- Signals are written by their own actions (panel `ws_call_signal`, guest `call_signal`), never by a read.

## Why does an idle channel cost the server nothing more?
- A stamp file `data/temp/ws_calls_live` exists only while a call is live somewhere on the site. Without it, neither read runs a call query: one file check, no database.
- With it: one indexed read of the channel's live call, and the reader's signals only while the reader is in that call.
- Where `data/temp` cannot be written, the reads ask the database each time (one indexed row of the channel's live call), as there is no stamp to trust; the clean-up then runs only where a call is live.

## How often does a browser in a call look?
- Every second while it is in a call, with `ws_call_sync` (CL2): only the call's part for the call's own channel, not the channel's look, so moving to another channel keeps the call; at the channel's usual 4 s otherwise.
- The open channel's `call` part also drives the head's Join button of a group (`calls-group.md`): no extra read.
- The 4 s `ws_sync` also carries `rings` (calls that ring the reader in any channel of theirs), only while the stamp exists (`calls-screen.md`).
- A browser in a call sends a keep-alive signal every 30 s: a call with no signal for 2 minutes ends and its signals are deleted (`calls-server.md`).
