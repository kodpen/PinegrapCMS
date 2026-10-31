---
name: workspace-message-seen-by
description: A message's "Who has seen it" drawer (ws_message_seen_by(), seenBy() in workspace.js): who is counted, the two groups, the one line when nobody but the writer is in the channel (#4238)
---

## Who is counted?
- `ws_message_seen_by()` (`includes/workspace/messages.php`) reads the channel's members; the writer of a person's message is left out. Seen is `last_read_id` at or past the message.

## What does the drawer show?
- Two groups, Seen · n and Not seen yet · n, each with its own empty text ("Nobody has seen it yet.", "Everyone in the channel has seen it.").
- When both are empty (nobody but the writer in the channel) it shows one line, "Nobody but the writer is in this channel.", not the two empty groups whose texts contradicted each other (#4238). `tests/workspace_channel_info_refresh_test.php`.
