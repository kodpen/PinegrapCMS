---
name: approval-guest-frozen-channel
description: An approval link sent outside the team opens as closed while the channel is gone or archived, or the request is in an earlier version of a cleared conversation
---

## What does an outsider's link show when the channel is frozen?
- `ws_approval_guest_view()` (`includes/workspace/approval_guests.php`) passes `ws_approval_guest_channel_frozen()` to `ws_approval_guest_state()`. A pending link answers `closed` when the request's channel is gone or archived, when it is a discussion whose channel is archived or gone, or when the request's message is in an earlier version of the conversation (`ws_message_in_past()`).
- An answer already given stays `answered`. `ws_approval_guest_decide()` reads the same view, so the link takes no answer there.

## Why?
- Before this, the link stayed open after the channel was archived or cleared, while the team's own answer is refused there (`ws_approval_decide_check()` asks `ws_can_post_channel()`). An outsider's answer settled the request: `ws_approval_settle()` wrote a system result line into the archived channel and a flow's approval box went on.

## What is left as it was?
- Archiving does not close open requests; their deadline expiry still settles them (`ws_approval_evaluate()`).
- Test: `tests/approval_guests_test.php` (`test_approval_guest_state_frozen_channel_closes_an_unanswered_link`, `test_approval_guest_channel_frozen`).
