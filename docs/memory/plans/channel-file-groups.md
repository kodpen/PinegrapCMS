---
name: channel-file-groups
description: Plan (owner 2026-11-28, poll 255): files sent together in a channel are one group, drawn as one message with a grid, by ws_messages.file_group; steps FG1 to FG5
priority: 6
---

## What is asked?
- Owner (2026-11-28, agent channel messages 15440, 15443): three pictures put into one message fell into the channel as three messages, each with its own upload card: "birlikte konulan görseller grup gibi olsun".
- He chose the column (poll 255, "A: tek sütunla gerçek grup"): `workflow/decisions-made-schema-56.md`.

## What is there today?
- A message carries one file. `uploadFiles()` (`workspace.js`) posts the waiting files one after the other (`ws_attach`, `ws_library_attach`), the words with the first, a new upload card for each. An agent asked in the words reads the first file only.

## What did the architect settle? (not the owner's words)
- `file_group` holds the id of the group's first message, the head; the head carries its own id, 0 is a message alone. A group is made when a second file arrives; a file sent alone writes nothing.
- A message still carries one file: the Files tab, file addresses, reads by message id and the webhook per message stay.
- The head carries the words, reactions, replies, the pin and an agent's request. A member is never drawn or counted as a message.
- At most 10 files a group; the eleventh starts a new one. A voice recording, a `/create` command's file and a forward are never members.
- A refused group mark never loses a file: it is posted alone.
- No backfill. `ws_file_group_ready()` probes the column; before it the send is as today.
- How it is drawn and sent: `channel-file-groups-screens.md`.

## Which steps? (S under 400 product lines, M about 1,000; each waits for the one before it)
- FG1 (S) Schema and write: the step (column and key, rerun twice); `ws_file_group_check()` in `includes/fn/` (pure: the head is in the same channel and discussion, by the same sender, carries a file, is not deleted, is under 10 minutes old and has under 10 files); `ws_attach` and `ws_library_attach` take `group`, write the member and mark the head; tests. Nothing sends `group` yet.
- FG2 (M) Read and rules: the page of messages returns whole groups (one cut by the page's edge is read by the key), `file_group` on each; unread counts and the channel list's last line count a group once; a member sends no push or notice of its own; deleting the head deletes its members, one file is taken out alone and the head keeps its words; forward and pin take the group; a jump to a member lands on the head; search and the Files tab keep a row a file. Tests.
- FG3 (M) Screen: `uploadFiles()` sends `group` and shows one upload card for the send; the group drawn as the screens file says in a channel, a discussion, the pinned list and the guest page; Quick Look steps through the group.
- FG4 (S) API, MCP and agents: `file_group` on a message (REST, MCP, OpenAPI, `check_api_schema.php`) and in the webhook's payload; `POST /workspace/channels/{id}/files` takes `group`; a request's brief lists every file of the group; the head's requests are held while files still follow (`ws_ai_requests.held_until`, as `workspace/agent-edit-hold.md`), released by the last, and run out by themselves when a send dies.
- FG5 (S) Close-out: sends of 2, 5 and 12 files tried on dev, a file manager copy among them, on a phone too; `workspace/workspace-attachment-uploads.md` and `workspace-editor-pasted-files.md` corrected and the group's own memory written; this file and the screens file deleted.

## What is left out?
- Grouping messages stored before the column; adding a file to a group later; reordering; a title for the group.
- "Send later" with a waiting file stays refused.
