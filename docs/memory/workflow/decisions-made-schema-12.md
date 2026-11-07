---
name: decisions-made-schema-12
description: Decisions list, schema part 12 (2026-11-07 on): note groups, note places, group shares, PIN locks and two columns for Workspace notes (plans/workspace-notes-groups-lock.md, step N0)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the migration shape.

## Which tables and columns were approved?
- Note groups and the notes lock (owner, 2026-11-07, in chat; step N0 of `plans/workspace-notes-groups-lock.md`): one re-runnable sub-step of `2026.11.0.php` under the next free Workspace label, read at push; `install_create_table()`, `install_add_column()`, `install_add_index()`; every new table in `get_tables()`. All columns INT UNSIGNED NOT NULL DEFAULT 0 unless named.
  - `ws_note_groups`: id (AUTO_INCREMENT), user_id, name VARCHAR(100) NOT NULL DEFAULT '', color TINYINT UNSIGNED (a `ws_palette()` place, 0 none), sort, locked TINYINT(1), created_at, updated_at; KEY idx_user (user_id, sort).
  - `ws_note_places`: user_id, note_id, group_id, sort, created_at; PRIMARY KEY (user_id, note_id), KEY idx_group (group_id, sort). One group per person and note; a group's notes are its rows, whoever put them there.
  - `ws_note_group_shares`: id (AUTO_INCREMENT), group_id, user_id, channel_id, can_edit TINYINT(1), message_id, shared_by, created_at; KEY idx_group (group_id), KEY idx_user (user_id, group_id), KEY idx_channel (channel_id, group_id), as `ws_note_shares`.
  - `ws_note_locks`: user_id PRIMARY KEY, pin_hash VARCHAR(255) NOT NULL DEFAULT '', open_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 5 (0 every time, 65535 till sign-out), fails TINYINT UNSIGNED, wait_until, created_at, updated_at.
  - `ws_notes.locked` TINYINT(1) NOT NULL DEFAULT 0; `ws_note_shares.via_group` with INDEX idx_via_group (via_group, user_id): the group whose share wrote the row, 0 for a direct share.
- Why this shape: a group's share written as ordinary note shares keeps every reader of `ws_note_shares` (access, files, the list, sync, deletions) as it is, and taking the group back deletes only its rows. The PIN hash has its own table, out of `ws_profiles`, which more places read. The e-mail code and the open lock live in the session. Running sites change nothing until a person makes a group or sets a PIN.
