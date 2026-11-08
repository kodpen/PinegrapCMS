---
name: workspace-note-group-shares
description: Shared note groups (note_group_shares.php, G5): group shares written as ws_note_shares rows with via_group, the sync, who puts notes in, leaving, bell lines, the hooks the lock calls
---

## How is a group shared?
- `ws_note_group_shares` rows (person with can_edit, or a channel with the card's `message_id`); the group's notes reach people through `ws_note_shares` rows with `via_group` = the group, one per note and person or channel. A channel gets one card for the group ("Shared a group of notes: …"), none per note; the screen and the card: `workspace-note-groups-shared-screen.md`.
- `ws_note_group_share_sync($group_id, $by)` lays the wanted rows (`ws_note_group_share_wanted()`, pure) over the rows there are (`ws_note_group_share_diff()`, pure): insert, change the right in place, delete. It runs after every change below; nothing else writes `via_group` rows.

## Which rows does a group want?
- Each note put in by its own owner (`ws_note_places.user_id` = `ws_notes.user_id`), to the group's owner (edit), to each person share (their right) and each channel share (read), never to the note's owner. A locked group or a locked note wants none. A note shared with the owner and kept in the group is not passed on.

## Who puts notes in?
- `ws_note_group_reach_of()` (pure): owner, edit / view by name, view through a readable channel. `ws_note_group_place_refusal()` (pure): the owner puts in any own note (and, into a group nobody else has, notes shared with them, as in G1) and moves or takes out any note; edit puts in, moves, takes out own notes only; view nothing. `ws_note_place_check()` asks it through `ws_note_group_place_gate()`; `ws_note_place()` moving a note somebody else keeps in the group only changes its sort. `ws_note_group_take_out()` takes out whoever's note.
- Leaving (`ws_note_group_unshare()` with share 0) or a share taken back by the owner takes the person's notes out (`ws_note_group_share_drop()`). Downgrading edit to read keeps their notes in.

## What else keeps it in step?
- Group deleted: `ws_note_group_delete()` calls `ws_note_group_shares_forget()` first (shares taken back, others' notes out, rows gone). Note deleted: `ws_note_delete()` already drops every share and place. A row deleted by the sync writes a `ws_note_deletions` row for the person (as `ws_note_unshare_deletion_row()`), forgets their place and, with no share left, their inbox lines; touched notes run `ws_note_folder_sync()`.
- Direct shares are separate rows: `ws_note_share_people()` / `_channel()` look only at `via_group = 0`; `ws_note_unshare()` refuses a `via_group` row ("take it out of the group" / "leave the group").
- The lock (L steps) calls `ws_note_group_shares_note_changed($note)` after setting `ws_notes.locked` and `ws_note_group_shares_forget($group)` when a group is locked; `ws_note_group_share_check()` refuses a locked group.

## Bell lines?
- `note_group_shared` (separate line, sentence "Group “…” · Can add notes / Can read" in `ws_scheduled_notices`, as `wtable_shared`), once per person when first shared. `note_group_added` (note_id, quiet) to each person the note now reaches by name in that group, deduplicated while unread; in `ws_note_inbox_kinds()` and shown while the note is readable.

## Panel actions?
- `ws_note_group_shares` (who has it, reach), `ws_note_group_share` (user_ids + can_edit, or channel_id), `ws_note_group_unshare` (share_id, 0 to leave), `ws_note_group_take_out` (group_id, note_id). Tests: `tests/workspace_note_group_shares_test.php`.
