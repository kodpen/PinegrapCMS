---
name: bug-hunt-areas-workspace-calls
description: The bug hunt of Workspace voice and video calls (CL1-CL8, CN1-CN7), 2026-11-26, #11765: four faults fixed, the ring in a muted channel asked, what read clean
---

## What was the area?
- Given by the architect on 2026-11-26 (agent channel request 3034): Workspace calls, `includes/workspace/calls.php`, `api_calls.php`, `call_lines.php`, `calls_settings.php`, `calls_background.php`, `workspace_call_background.php`, `assets/js/workspace_calls.js`. Start file `workspace/calls-overview.md`.

## What did it fix? (#11765, tests/workspace_calls_hunt_test.php)
- A person taken out of a public channel during a call kept their place for good, heard every new joiner's offer and went on sending; out of a private channel the place stayed 90 s. Now the next read or signal takes it out, and a refused read of a private channel does at once (`ws_call_party_out_if_barred()`).
- In a conversation of two shared with a guest, the other person's Decline ended the call the guest was in. Refused now while a guest is in the call (`ws_call_has_guest()`).
- A call ended by the clean-up (tabs closed without their leave) wrote its length up to whenever the next Workspace read came, hours later. It now ends when it was last heard (`ws_call_heard_at()`).
- `GET /workspace/channels/{id}/call` reported such a call as on, with its people, until somebody opened the Workspace. The API read runs the clean-up first.

## What was asked?
- A call in a conversation the person muted (notify Never) or during their quiet hours still rings, with the card and the tones: `calls-screen.md` says it rings "where a mention would", and a mention there does not. A product choice, left to the architect.

## What read clean?
- Who may start and join: a member only, a guest in its own room never starting, read-only guests, archived, mailbox and discussion channels; four at most under the channel lock; agent channels (agents are not channel members).
- Signals: only to a live member of the same call, read only by their addressee, a window's old `member_id` changes nothing; share and hand marks the sender's own; the limits a minute.
- Ending: only the starter or a manager for everyone; the one-writer guard on the end line; missed and declined lines only in a conversation of two.
- The TURN secret reaches only a granted start or join; the background files are served only while the switch is on and their digests match. There is no picture upload: the background effects are blur and removal only.
- Not run: real browsers in a call; the fixes are checked by unit tests only.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workspace/calls-overview.md, docs/memory/workspace/calls-server.md
