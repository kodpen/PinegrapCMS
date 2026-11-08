---
name: workspace-notes-groups-lock-steps-2
description: Steps L1-L4, M1-M3 and N9 of plans/workspace-notes-groups-lock.md: PIN lock store, locking and its gate on every reader, lock screen, media previews in a note, close-out
---

## Which steps are there?
- Sizes and rules as in `workspace-notes-groups-lock-steps.md`.

- L1 (M) Lock store. Done (PR #8162); `workspace/workspace-note-lock.md`. New `includes/workspace/note_lock.php`: set up (a PIN of 4-8 digits twice, `password_hash()`), change (the PIN or an e-mail code), the time (every time, 1, 5, 15, 30 or 60 minutes, till sign-out; 5 by default), turn off (the PIN; every note and group lock taken off); the open lock in the session, renewed by each use of a locked note, and Lock now; five wrong PINs pause entry 15 minutes (`ws_note_locks`); the e-mail code: 6 digits, 10 minutes, once, five wrong ends it, three sends an hour (`waf_rate_*`), shaped as the passkey e-mail code; actions `ws_note_lock_*`.

- L2 (M) Locking and the screen's gate. Done (PR #8200); `workspace/workspace-note-lock-gate.md`. Lock and unlock a note (its owner) and a group (its owner); a shared one asks, then takes back its shares and those of the owner's notes in it; sharing, a channel made from it and a shared note moved into a locked group are refused or asked; `ws_note_behind_lock()`. While the lock is closed, get, save, delete, pin, versions, files, assistant asks and moves out answer `note_locked`; the list keeps the title (a locked group its name only).

- L3 (M) Every other reader. After L2. API and MCP note routes (`locked: true`, body null, writes and versions 423 `note_locked`), sync, search and record history (title only), block pull and `ws_blocks`, meetings, workflow table import, export (one note or print with the lock open; Export all leaves locked notes out and says how many), `get_file.php` for a locked note's folder and `workspace_file_save.php?note=` (#8198), no assistant request from a locked note; a guard test listing every reader of `ws_notes` and its gate.

- L4 (M) Lock on the screen. After L1, beside L2. New `assets/js/workspace_note_lock.js`: the toolbar's Lock and its dialog, the lock pane (PIN, Open, Forgot your PIN → e-mail code → new PIN), lock marks in the list and tabs, Lock and Unlock in the menus, Lock now, the open note closed when the time ends or an answer says `note_locked`.

- M1 (M) Media, read view. Done (#8150). A line holding only a link to a file of this site drawn by `ws_render_body(…, $note = true)` after `ws_file_kind()`: a picture (lazy, bounded height), video and audio players, a PDF or other file as a card (icon, name, size); other hosts stay links; the read-only view and the print page; styles in `backend.src.css`.

- M2 (M) Media, writing box. Done (#8178). `workspace_editor.js`: such a line as a `media` card (contentEditable false, its markup kept) opening `viewImages()` or `previewFile()`; `attach()` and `drawCanvas()` put the file on a line of its own (`insertFile()`); a voice link on a line of its own takes the inline player (`PGWsVoice.player()`), and "Recordings in this note" keeps only the others.

- M3 (S) A canvas edited again. Done (#8198). On a canvas picture in a note, Edit the picture opens the editor with its layers (`workspace_file_save.php?note=&file=`); the save replaces the file, the old one kept as an earlier version.

- N9 (S) Close-out. Last. A keyboard and screen-reader pass over groups, tabs, drag and drop and the lock; lasting decisions into topic files; these four plan files deleted; index.

## Where does the next session start?
- L1-L3 and M1-M3 done; L4 is #8205. N9 is next once L4 lands. Lock notes: `workspace/workspace-note-lock.md` (store), `-gate.md` (screen gate, list, locking), `-readers.md` (API 423, assistants, files, export, guard test). Open ends: no unit test drives the database paths of locking with shares; the API cannot open the lock, by design.
