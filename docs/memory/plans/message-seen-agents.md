---
name: message-seen-agents
description: Plan (owner 2026-11-13, #9314): agents listed among who has seen a message, under the message and in the "Who has seen it" drawer, counted from their requests with no new column
---

## What is asked?
- Owner (2026-11-13, #9314): "hem mesajların altında hemde kimler gördü alanında gören ajanların bilgileri yer almıyor. bu hem burası hemde normal kanallarda önemli". Record: `workflow/decisions-made-workspace-23.md`.
- Today `ws_message_seen_by()` counts the channel's people by `last_read_id` (`workspace/workspace-message-seen-by.md`); `ws_channel_agents` has no read mark, so a message naming three agents read "Everyone in the channel has seen it".

## How is an agent counted? (architect, technical)
- Seen: the message woke the agent and it took the request (`ws_ai_requests` claimed or answered, with the time), or the agent answered or reacted to the message. For every agent of the channel: app agents, model assistants, Claude, Pinegrap AI.
- Not seen yet: the message woke it and the request still waits; the row says "woken, not taken yet".
- Not woken: an agent of the channel the message made no request for is named apart in one muted line, never as seen. Its reading of the history leaves no mark.
- No table or column. A mark for every message the API hands an agent needs a table: asked through the architect first, not built here.

## Which steps?
- SA1 (M) Agents in `ws_message_seen_by()` and where it is drawn: the count and faces under a message, the drawer's two groups and the muted line. An agent's row as in Channel info (square face, the "AI agent" badge, `workspace/channel-members-agents.md`), with "took it" or "answered" and the time.
  - "Everyone in the channel has seen it" only when no woken agent waits. The API's message gives the same list where it gives people's. Unit tests for the three states; real screenshots (dark and light, wide and phone) posted in the agent channel before the PR goes ready (`workflow/owner-contact-channel.md`).
- SA2 (S) Close-out: the rules into `workspace/workspace-message-seen-by.md`, this plan deleted.

## What does every step keep?
- People are counted as today; a guest's page and a channel closed to an agent show nothing new. Read for the opened message only, never per row of the message list beyond what the list already reads.
- New texts through `lang()` and `php tools/tr_add.php`. Claim as `[plan:message-seen-agents] <step> — <topic>`.
