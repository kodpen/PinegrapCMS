---
name: workspace-api-message-people
description: Workspace API routes for a message's readers (seen by and read receipts), bringing its mentioned people into the channel, and forwarding messages into a note (note_id on the forward route)
---

## What does GET /workspace/messages/{id}/readers answer?
- `workspace.messages.readers` (`ws_api_messages_readers()`, api_resources.php), read scope, MCP tool. Same answer as the panel's Seen by (`ws_message_seen_by()`) plus the read receipts (`ws_ack_people()`), in one call (#9767).
- `seen` / `unseen`: members as {user_id, name}; the writer is left out, and a public channel counts only its members. `agents`: seen, waiting and quiet agents in that order, each {key, app_id (null for an assistant without one), name, handle, state, at}.
- `ack` is null unless read receipts were asked. When they were asked: `listed` true with `read` ({user_id, name, read_at}) and `unread`, only for the writer, whoever asked and staff (`can_list` of `ws_acks_map()`). Anyone else gets `listed` false and empty lists, not a 403.
- 404 for a deleted message, a channel the owner cannot read, or a channel Claude's key may not use (`ws_api_channel_or_404()`). A message of an earlier version of the conversation is still answered, as the panel answers it.

## What does POST /workspace/messages/{id}/mention-invite do?
- `workspace.messages.mention_invite` (`ws_api_messages_mention_invite()`), write scope, dry run, MCP tool. It is the panel's `ws_mention_invite` action: `ws_message_mention_invite()` adds the people the message names with `<@user:n>` who are outside its channel, and gives each of them the mention.
- The check is `ws_message_mention_invite_check()` (messages.php). The panel's write runs it too. It needs `ws_channel_can_invite()` (may post, and is a member unless the channel is public), otherwise 403. It lists who is outside with `ws_message_mentions_outside()`; in an archived channel that list is empty.
- `user_ids` narrows the list (ids that are not on it are dropped). The dry run's `detail.user_ids` is who would be added. The answer is {message_id, channel_id, added, message}. `ws_channel_add_members()` may still skip someone, for example a non-staff person in a guest room.
- Not person-only: like `workspace.channels.members.add`, an application key brings people in under the owner's rights.

## How does forwarding into a note work?
- Send `note_id` (int, 0 = a new note) to `workspace.messages.forward`. Its handler then calls `ws_api_messages_forward_into_note()`. In this case the path `id` is the channel the messages come from, and messages from another channel get 422 on `message_ids`.
- The check is `ws_forward_to_note_check()` (forward.php): `ws_forward_pick()`, the block of text, then `ws_note_save_check()`. The panel's `ws_forward_to_note()` runs the same check, then `ws_note_save()`.
- An existing note goes through the same gates as POST /workspace/notes/{id}: 404 (`ws_api_note_or_404()`), 423 if locked, 403 if it is shared read-only, 409 if it changed meanwhile. A new note is called "From #channel"; the note limit and size limit give 422 on `note_id`.
- The answer is the note (WorkspaceNote) with 201, not a message. The route's `returns` still names WorkspaceMessage because a route declares only one answer type; the description says which answer comes when.
- Tests: tests/workspace_api_message_people_test.php.
