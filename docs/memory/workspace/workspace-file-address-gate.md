---
name: workspace-file-address-gate
description: Who get_file.php serves a Workspace channel's or note's file to by its address (#11476): readers of the channel or note; staff never by rank; users left to the folder's view right
module: workspace_file_gate
---

## Who opens a channel's file by its address?
- `pg_ws_channel_file_verdict()` (`includes/fn/workspace_file_gate.php`, loaded by `get_file.php` only) for a file in a channel's folder (`ws_channels.folder_id`): `open` for a reader of the channel - the team (Workspace right, not "Not in the team") for a public channel; a member for a private channel, guest room or mailbox room; staff who opened a private channel or guest room for inspection while no staff member is in it (never a mailbox room).
- Anyone else: staff `refuse` (404), a user or a visitor `folder` (the folder's private access; a view right an administrator gave by hand on Edit User stays theirs).
- A discussion's file sits in its channel's folder (`ws_store_file_bytes()`), so it follows the channel's readers, not the discussion's people.

## Who opens a note's file by its address?
- A note's folder shared in a public channel opens to the team as above. Otherwise `pg_ws_note_file_verdict()`: staff only while they read the note (owner, a person it is shared with, a member of a channel it is shared in; a channel's own note: a member of that channel); anybody else the folder. The notes lock check runs after it.

## Why?
- Before #11476 the folder alone decided, and staff pass every private folder (`check_private_access()`), so a manager opened any private channel's, mailbox's or note's file by its address, against "Admins do not see private channel content" (`workspace-module-decisions.md`) and "administrators get nothing by rank" (`workspace-notes.md`).
- A manager made a user stayed in their private channels with no view right on the folder (`ws_folder_grant()` gives it to users only), and their own channel's pictures answered 403; a member is now asked by membership.

## Where are the tests?
- `tests/workspace_channel_file_gate_test.php`: both verdicts and that `get_file.php` asks them.
