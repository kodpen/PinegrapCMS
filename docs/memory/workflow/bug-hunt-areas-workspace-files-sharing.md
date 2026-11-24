---
name: bug-hunt-areas-workspace-files-sharing
description: Bug hunt 48, Workspace files sharing (2026-11-24, PR #11476): file addresses after leaving, shares, deletes, replaces, guests, limits, previews; four faults fixed
---

## What was the area?
- Hunt 48: files in Workspace channels and the Workspace file picker. To look at: who opens an attachment by its address after leaving the channel, a file shared into a second channel and deleted in the first, the guest's view, a replaced file's old version, size and type limits, previews of a file the viewer may not open.
- Files read: the workspace block of `get_file.php`, `includes/workspace/files.php`, `library.php`, `file_edits.php`, `forward.php`, the file parts of `guests.php`, `api_message_files.php`, the files routes of `api_resources.php` and `api_channel_reads.php`, the file case of `refs.php`, `channels.php` (join, leave, kind), the note folder sync of `notes.php`.

## What did it fix? (#11476)
- Staff opened a private channel's, guest room's or mailbox room's file by its address without being in it: the folder's private access passes staff everywhere. Now only the channel's readers (`workspace/workspace-file-address-gate.md`).
- A manager made a user kept their channels but lost their pictures (403): no view right on the folder. A member now opens the channel's file by membership.
- Staff opened anybody's note pictures by their address; now only a reader of the note.
- The file tag's search in a writing box offered staff the files of private channels they are not in, by name: the Workspace folder's branch is left out (`ws_ref_file_search_where()`, `tests/workspace_ref_file_search_test.php`).

## What read clean?
- Leaving a channel, a group or a mailbox takes the folder's view right back; joining gives it; make private/public needs nothing.
- A forward carries only the name; a file manager pick is a copy; deleting a message unlinks its file, which stays in the folder (`canvas-parts.md`, `poll-question-picture.md`); a discussion copy follows its original.
- The guest: only lines the page lists, after the clear floor, never a live page or history; tags as words.
- A replaced file: history by `ws_file_edits_visible()` (redaction decided, `decisions-made-schema-7.md`); a share link closes with the replace.
- Limits: name and bytes through `ws_upload_inspect()` for every door (upload, picker, note, replace); refused types as attachments with nosniff.
- Previews: the 320 px copy and the API's sizes after the same access; text, office and history actions ask `ws_can_read_channel()`.

## What is left as a question?
- A discussion's file sits in its channel's folder, so every reader of the channel opens it by its address, not only the discussion's people. The code says it on purpose; a folder per discussion would be a schema question.
- A user whose Workspace right is taken away keeps the folders' view rights of their channels; whether that right is cleared or the address refused is product behaviour.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workspace/workspace-file-address-gate.md
