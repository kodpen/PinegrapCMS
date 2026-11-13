---
name: workspace-message-seen-by
description: The "Who has seen it" drawer (ws_message_seen_by()): people by last_read_id, agents by their requests (#9366), the two groups, the muted not-woken line, the writer-alone line (#4238)
---

## Who is counted?
- People: `ws_message_seen_by()` (`includes/workspace/messages.php`) reads the channel's members; the writer of a person's message is left out. Seen is `last_read_id` at or past the message.
- Agents (#9366): `ws_message_seen_agents()` (`includes/workspace/message_seen_agents.php`) for Claude and Pinegrap AI where they can be asked in the channel, and the registry agents (app and model) whose channel row allows them. None in a guest room or a channel shared with guests; the agent that wrote the message is left out. No table or column.

## When has an agent seen a message?
- Seen: its newest `ws_ai_requests` row for the message was answered ("answered …") or taken (running, or `claimed_at` set, a failure after it was taken included: "took it …"), or its application answered (`parent_id`) or reacted under the message ("reacted …"). The strongest wins: answered, took, reacted. The time in `ws_seen_ago_words()`.
- Not seen yet: a request it has not taken (queued, sent, or cancelled or failed before it was taken): "woken, not taken yet".
- Not woken: an agent the message made no request for and that left no mark. Named apart in one muted line, "Not woken by this message: @…", never counted as seen. Its reading of the history leaves no mark.
- Pure sorting: `ws_message_seen_agents_sort()`; tests `tests/workspace_message_seen_agents_test.php`.
- A mark for every message the API hands an agent would need a table: asked through the architect first, not built.

## What does the drawer show?
- Two groups, Seen · n and Not seen yet · n, people then agents, each with its own empty text ("Nobody has seen it yet.", "Everyone in the channel has seen it."). An agent's row is drawn as in Channel info: square face (`agentFace()`), the "AI agent" or "Assistant" badge, under it what it did and when.
- "Everyone in the channel has seen it" only when no person is unseen and no woken agent waits (`seenByParts()` in workspace.js).
- When both groups are empty (nobody but the writer, no woken agent) it shows one line, "Nobody but the writer is in this channel.", not the two empty groups whose texts contradicted each other (#4238). `tests/workspace_channel_info_refresh_test.php`.

## Where is it read, and where not?
- Only for the opened message, from the drawer (`ws_message_seen` panel action): the requests by `ws_ai_requests.idx_message`, replies through the channel's index from the message on (`ws_messages` has no index on `parent_id`), reactions by `ws_reactions.idx_message`. The message list reads nothing new.
- Under a message the line per woken agent stays as it was (`agent-shown.md`); the count and faces are in the drawer.
- The REST API gives no seen-by list for people, so none for agents either.

## Why?
- Owner (2026-11-13, #9314): a message naming three agents read "Everyone in the channel has seen it" and said nothing of them (`workflow/decisions-made-workspace-23.md`). Screens approved by the owner in the agent channel (2026-11-13).
