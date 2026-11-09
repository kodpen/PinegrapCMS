---
name: workspace-note-lock-gate
description: Notes lock gate: ws_note_behind_lock(), code note_locked on the screen's note actions, list and presenter while closed, locking notes and groups, shares refused
---

## Which notes are behind the lock?
- `ws_note_behind_lock($user_id, $note)`: the person set a PIN and the note is their own with `ws_notes.locked`, or they keep it in one of their own locked groups (a note shared with them included). `ws_note_lock_has()` caches "set a PIN" per request.
- `ws_note_lock_gate($viewer, $note)`: '' or the note_locked sentence; renews the open lock. In actions `ws_action_note_locked()` answers code `note_locked` with the note's id and title (or "Locked note"): `ws_note_get` (versions too), `ws_note_save`, `ws_note_delete`, `ws_note_file`, `ws_note_claude`, `_deliver`, `ws_note_place`, `ws_note_group_take_out`. `ws_note_save_check()` and `ws_note_delete_check()` gate too, so the API's writes are refused (no session: always closed).

## What do the list and a presented note show?
- `ws_notes_present()` adds `locked` (the owner's mark), `lock_group`, `lock_closed`; closed, `ws_note_lock_present_closed()` keeps the title (else "Locked note") and empties excerpt, body, html, labels, claude, scheduled. Every presenter caller, API included, gets this.
- `ws_notes_list()`: notes in a locked group are left out while the lock is closed for the list; a search finds a closed note by title only (`ws_note_lock_list_filter()`). `ws_note_groups()` gives a closed locked group `count` null and `lock_closed` (`ws_note_lock_groups_redact()`).

## How are notes and groups locked?
- Actions `ws_note_lock_note` (note_id, locked, confirm, pin) and `ws_note_group_lock` (group_id, …), owner only, after a PIN is set. Locking a shared one answers code `confirm` with `shares` until asked with confirm, then takes back direct shares (`ws_note_unshare()` each) and lets G5's sync drop group rows (`ws_note_group_shares_note_changed()`, `ws_note_group_shares_forget()` for a group, which also sends others' notes out). Unlocking needs the lock open (for the note; for a group the list) or `pin`.
- Sharing is refused by `ws_note_lock_share_refusal()` inside `ws_note_share_people()`, `ws_note_share_channel()` and `ws_note_to_channel()`; a locked group by `ws_note_group_share_check()`.
- `ws_note_place`: an own shared note going into an own locked group answers `confirm`, then loses its shares (`ws_note_lock_place_check()` / `_after()`). A note shared with the person may go into their locked group; its owner's shares stay.

## Where are the tests?
- `tests/workspace_note_lock_test.php`: `ws_note_lock_behind()`, `ws_note_lock_present()` (closed keeps the title only), `ws_note_lock_list_filter()`, `ws_note_lock_groups_redact()`. Locking with shares and the action gates are not unit-tested.
- Every other reader (API 423, assistants, files, export, blocks, history): `workspace-note-lock-readers.md`.
