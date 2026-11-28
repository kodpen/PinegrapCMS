---
name: workspace-file-groups
description: Files sent together in a channel as one group: ws_messages.file_group (step 11.0.291), when a file may join, how a member is written (FG1 #12266) and read, counted, deleted, forwarded and pinned (FG2)
module: ws_file_groups
---

## What is a file group?
- Files sent together in a channel, read as one message (owner, poll 255: `workflow/decisions-made-schema-56.md`). Each file is still its own message with one file.
- `ws_messages.file_group` holds the id of the group's first message, the head; the head carries its own id; 0 is a message alone. Key `idx_file_group`. Added by step 11.0.291; no stored message is grouped afterwards.
- `ws_file_group_ready()` probes the column; before it every file is posted alone.

## When may a file join a group?
- `ws_file_group_check()` (pure, `includes/fn/ws_file_groups.php`): the head is in the same channel (a discussion is its own channel), by the same sender (kind and id), carries a file, is not deleted, is under 10 minutes old (`WS_FILE_GROUP_SECONDS`), is no member of another group, and its group holds under 10 files (`WS_FILE_GROUP_MAX`).
- `ws_file_group_head()` reads the head and counts its group's undeleted files (an unmarked head counts 1). A voice recording neither heads nor joins a group.

## How is a member written?
- `ws_attach` and `ws_library_attach` take `group` (the head's message id) and pass it as the `file_group` option of `ws_message_send_file_bytes()`. A `/create` command's file passes 0.
- The head is checked before the message is written; after it, `ws_file_group_mark()` writes the member and marks the head (only while it is 0). Both routes answer `file_group`: the head joined, or 0 when the file was posted alone.
- A refused group never loses a file: it is posted alone, and the screen may start a new group with it (the eleventh file).

## What does a member send?
- `ws_message_send()` takes the checked head as its `file_group` option and writes the member's column in the insert; `ws_file_group_mark()` then marks the head. A member sends no mention notice, checklist ring or application notice (`workspace.app.mentioned`); `workspace.message.created` stays one per message.

## How is a group read?
- `ws_messages_page()`, `ws_messages_around()` and `ws_messages_since()` pass their rows through `ws_file_group_whole()`: a group the page's edge cut is read whole by `idx_file_group`, merged by id, oldest first. A new file brings its whole group back through since.
- The panel's message payload carries `file_group`. The API's message object gets it in FG4.
- A link or jump to a member (`message_id` of the channel open) lands on the head (`ws_file_group_target_id()`).

## How is a group counted?
- Unread counts count a group once, by its head: `ws_file_group_counted_sql()` in `ws_channels_for()` (channel list, Workspace menu, API), the discussion counts in `threads.php`, and the channel briefing's new count and first unread.
- The home digest's last line reads the head's message (the words), never a member.
- A member arriving after the reader read the head is not unread again.

## How is a group deleted, forwarded, pinned?
- Deleting the head (panel `ws_delete`, `DELETE /workspace/messages/{id}`) deletes its members too (`ws_file_group_message_deleted()`).
- `ws_delete` with `file: 1` takes one file out (`ws_file_group_take_out()`): a member is deleted as a message; the head gives up its file (`file_id` 0) and keeps its words, reactions and replies; a head with no words and no other file is deleted. A head left with no file, no words and no member after a member's delete is deleted too.
- `ws_forward_pick()` takes every file of a chosen file's group (`ws_forward_pick_groups()`), in the order chosen; the 20-message bound counts the files.
- Pinning a member pins its head.
- Search and the Files tab keep one row per file (no change).

## What is not done yet?
- The screen does not send `group` or draw the grid yet (FG3); the API, MCP, webhook payload and agents' requests come in FG4 (`plans/channel-file-groups.md`).
