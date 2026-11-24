---
name: bug-hunt-areas-channel-notes-share
description: Bug hunt of channel notes (CN1-CN3) and the share door (SL1-SL6): six faults fixed, what was read clean, what was left
---

## Which area was read?
- Channel notes with the share door: who reads and changes a channel's note, what My Notes lists, a note moved or copied, a channel deleted; the guest's read-only door (`workspace_share.php`): a link ended, taken back, an unknown token, the item deleted after sharing.
- Files: `includes/workspace/notes.php` (access, save, move, copy, delete, purge, cards, readers, inbox), `api_channel_notes.php`, the notes routes of `api_resources.php`, `share_links.php`, `share_links_text.php`, `api_share_links.php`, `workspace_share.php`, the panel actions in `actions.php`.

## What did it fix?
- A channel note's writer could take its card's share back (`ws_note_unshare()` counted the writer as owner): the card said "no longer shared" and a share link to the note closed. Now refused: "A channel's note keeps its card in its channel."
- Somebody's edit of a channel's note wrote a "note_updated" bell line to its writer, even after they had left the channel. `ws_note_change_told()` (pure) tells nobody for a channel's note.
- A channel note's files stayed open to its writer after they left the channel: `ws_note_readers()` always added `user_id`. `ws_note_readers_of()` (pure) gives a channel's note to its channel's members only.
- Claude's application reached a channel's note of a channel closed to Claude whenever the owner could post there (`ws_api_note_reachable()` asked only `view` notes). `ws_note_claude_reach_of()` (pure) asks the note's channel first.
- A record's mentions (`ws_ref_notes_open()`, refs.php) read notes without `owner_channel_id`, so a channel note's writer counted as owner and saw its title and text after leaving the channel.
- Taking back a link that had already expired or closed wrote "<who> stopped sharing " with an empty name. `ws_share_link_revoke_line()` writes the line only for a link that was open.

## What was read and found clean?
- `ws_note_access_of()`, the guard of `ws_note_own_sql()` (My Notes, its search, the API pages, Export all), the move's plan and checks, the copy's limit, the delete check, `ws_note_channel_purge()`, the cards map.
- The link's state rule, the make check (agent channel, may post, app agent row, clear floor, one open link an item, 200 a channel), the marks of the screen, the channel's Sharing, the sweep, the open count, the 404 for every fault, the offence for an unknown token only, the file headers, the note's page and Markdown with tags as plain names, the table and checklist page and CSV.

## What was left as it is?
- A link closed but not ended (its message deleted) still counts in the channel's 200 until it ends. Rare.
- A shared note's pictures do not load on the guest page (they need a session): a later yes, not a fault.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workspace/channel-note-owned.md, docs/memory/workspace/share-links.md
