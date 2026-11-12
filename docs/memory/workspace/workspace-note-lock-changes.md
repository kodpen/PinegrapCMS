---
name: workspace-note-lock-changes
description: A notes lock change moves the note's updated_at (ws_note_lock_moved(), #8647) so updated_since carries it; the API notes search finds a locked note by its title only
---

## What moves when a note goes behind a lock or comes out?
- Only `ws_notes.updated_at`, by `ws_note_lock_moved()` (note_lock.php). `revision` and `updated_by` stay: the words are the same, so a save sent with the revision read is not refused and the screen's 15-second poll, which goes by the revision, reloads nothing.
- Symptom (#8647, PR #8977): `GET /workspace/notes?updated_since=` never carried a lock, so a team device that keeps note text offline went on showing a note its owner had locked on the panel.

## Which writes call it?
- `ws_note_lock_note()`: the note, when its mark really changed.
- `ws_note_lock_group()`: the notes the owner keeps in the group, but for their own notes locked one by one (`ws_note_lock_group_follows()`, pure part `ws_note_lock_group_follow_ids()`), when the group's mark really changed.
- `ws_note_lock_turn_off()`: every locked note of the person and every note in their locked groups, read before the marks go.
- `ws_note_place()` and `ws_note_group_take_out()`: `ws_note_lock_place_moved()` compares `ws_note_behind_lock()` before and after for whoever keeps the note in the group, and moves the note only when its side of the lock changed.
- `ws_note_group_delete()`: a locked group's notes, read before their places go.

## What follows from moving updated_at?
- The note rises in the lists that run by the latest change (My Notes, the dashboard's notes card, the API's pages) and its last-change time is the lock's.
- A note somebody shared with the person, kept in the person's locked group, moves for its owner and its other readers too: it rises in their lists, nothing else (no bell line, no unread mark). A stamp of the reader's own would need a column: a place taken out leaves no row to carry one.
- A client that sends only `base` (no `revision`) and read the note before the lock can meet one 409 `changed_meanwhile` when the last writer was somebody else; it reads the note again.
- The next version kept of the note carries the lock's moment as `saved_at` (`ws_note_version_keep()` takes the note's `updated_at`).

## How does the API's search treat a locked note?
- `ws_notes_page()` hands `ws_notes_page_rows()` `behind_lock`, the query form of `ws_note_lock_behind()` (`ws_note_lock_behind_sql()`), when there is a search; `ws_notes_filter_sql()` then asks `ws_notes_search_closed_sql()`: the words in the title, or in the text of a note not behind the lock. It is in the query, so the pages and `page.total` agree. Before, a search matched a locked note's text and so told what it held.
- The screen's list (`ws_notes_list()`) keeps its filter after the query (`ws_note_lock_list_filter()`): a session can hold the lock open for one note, which a query cannot know.

## Where are the tests?
- `tests/workspace_note_lock_changes_test.php`: the pure parts, and guards that read the sources: a function that writes a lock mark or a row of `ws_note_places` calls the move, or is listed there with the reason. The search: `tests/workspace_notes_page_test.php`.
