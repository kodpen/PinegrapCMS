---
name: workspace-note-groups-shared-screen
description: Shared note groups on the screen (G6) — workspace_note_group_shares.js (Share dialog, Shared with me folders, channel group card, ?group=N) and the list fields shared_groups / shared_group
---

## What does the list answer for shared groups?
- `ws_notes_list()` adds `shared_groups` (`ws_note_groups_shared_with()`: groups shared with the person by name, not own, not locked; `ws_note_group_present()` + `owner`, `reach` edit/view, `shared`, `count` of the notes the person reads) and marks own groups `shared` (`ws_note_groups_mark_shared()`).
- Each listed note gets `shared_group`; `ws_note_groups_shared_assign()` (pure, `tests/workspace_note_groups_shared_screen_test.php`) puts a note not in an own group into the shared group that holds it (the lower group id when two), with that group's `group_sort`. So `group_id` of a note may name a shared group; own folders ignore unknown ids.
- A group reached only through a channel is not listed (as a note shared in a channel is not): its card is the way in.

## Where is the screen code?
- `assets/js/workspace_note_group_shares.js` (`window.PGWsNoteGroupShares`), bundled after `workspace_note_tabs.js`, before `workspace_replies.js`. It does not touch `workspace_note_groups.js`.
- Shared with me (`section()`): shared groups as folders (dot, name, "Belongs to …", count; closed ones in `localStorage` `pg_ws_note_groups_shared_closed`), menu Leave the group (`ws_note_group_unshare` share 0); then single shared notes. An own note put in a shared group is drawn in that folder, not in My notes (`groupsCtx().mine` leaves it out).
- Share dialog (`share()`, owner only): people picker + Can read / Can add notes, or a channel; Who can see it with Stop sharing per share (`ws_note_group_shares`, `ws_note_group_share`, `ws_note_group_unshare`). Reached from the tab row's person-plus button and from `groupsCtx().groupMenuExtra(group)`, which the folder menu of `workspace_note_groups.js` can concat.
- Tabs (`workspace_note_tabs.js`) get own and shared groups: a people mark for a shared group, no + when `reach` is view, the share button only on own unlocked groups.

## How does a channel show a shared group?
- `ws_note_group_cards_map()` gives `note_group_card` on a message (`messages.php`): group name, colour, owner, the notes the channel was given (its `via_group` rows, in the group's order, only those the viewer reads), `url` = `workspace_notes.php?group=N`. Such a message is not editable. The card lists the notes (each opens `openNoteView()`) and Open in My Notes.
- The Workspace API carries it as `note_group_card` on every message (#PRNUM): `group_id`, `name`, `color` (hex), `owner` {`user_id`, `name`}, `notes` [{`id`, `name`, `updated_at`}], the same notes the screen draws for the reader; null on a deleted message or one sharing no group (a locked group's shares are forgotten). Component `WorkspaceMessageNoteGroupCard`; `ws_api_message_note_group_card()`, tested in tests/workspace_api_note_group_card_test.php. Claude's key reaches the card only through a message of a channel it may be asked in, and the notes are those that channel was given.

## What does ?group=N do?
- After the list loads: an own or shared group opens its folder and its first note (the tabs then show the group); any other group says it is not among the person's notes.
