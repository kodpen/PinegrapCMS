---
name: workspace-note-lock-screen
description: Notes lock on My Notes (workspace_note_lock.js): toolbar Lock and dialogs, Lock/Unlock in menus, list marks, the lock pane and e-mailed code, the time-out, note_locked answers
---

## Where is the code?
- `assets/js/workspace_note_lock.js` (`window.PGWsNoteLock`), bundled in `workspace.min.js` after `workspace_note_group_shares.js`. Texts `nlock_*` from `ws_note_lock_js_strings()` (`includes/workspace/note_lock_screen.php`), merged in `ws_js_strings()`. `CFG.notes_lock` is `ws_note_lock_ready()`.
- `startNotes()` in `workspace.js` hands it `lockCtx()` (kit, ctxMenu, current, reload, opened, closed, save); `drawLockPane()` and `lockListButton()` put the pane in the note's place. `api()` copies a refusal's `note`, `shares`, `wait` and `lock` onto the error.

## What does the person see?
- Toolbar Lock (`bi-lock`): no PIN yet, the set-up dialog (PIN twice, "Ask for the PIN again after"); set up, a menu: Lock now, Change PIN, Change the time, Turn off. Dialogs are `kit.ask()` with fields; a refusal opens the same dialog again with the reason.
- Lock / Unlock in an own note's menu (after Pin) and an own group's menu (`groupMenuExtra`). No PIN yet: the set-up comes first. Code `confirm` asks with the share count, then sends `confirm: 1`; code `pin` on unlock asks for the PIN.
- A listed note behind the lock carries `bi-lock-fill` (closed) or `bi-unlock` (open for now); tabs mark `note.locked` themselves. A closed locked folder lists no notes and shows "Open with your PIN" (`ctx.lockedBody` in `workspace_note_groups.js`), which draws the pane for the list (`note_id` 0).

## How does the pane work?
- Title, masked numeric PIN, Open (`ws_note_lock_open` with `note_id`), "Forgot your PIN?" (`ws_note_lock_code`, says the masked address) then code + new PIN twice (`ws_note_lock_change` with `code`). Code `wait` shows the minutes left. A right PIN reopens the note (`openNote(id, true)`) or reloads the list.
- `ws_note_get` answering `note_locked` (open, poll) and `ws_note_save` answering it draw the pane; what was not saved stays unsaved. `current.lockedPane` stops the poll and makes the note not editable (`access: 'locked'`).

## When does an open note shut again?
- Every 30 s `watch()` checks the open note behind the lock: with no key, pointer or input on the page for the chosen minutes (1-60) it saves, sends `ws_note_lock_now` and draws the pane. "Every time" and "until sign-out" never shut here; the server asks again for any other note under "every time". The poll's `ws_note_get` renews the server's open lock while a note is open, so the screen's own idle time is what ends it.

## Tests
- `tests/workspace_note_lock_js_test.php`: `pure.behind`, `pinOk`, `expired`, `waitMinutes` in Node; bundle place; every `nlock_` key the scripts use is sent. The dialogs and the pane are not run in a browser.
