---
name: workspace-note-lock
description: Notes lock store (note_lock.php): PIN set-up and checks, open lock in the session, times, wrong-PIN pause, e-mailed code, actions ws_note_lock_*; the gate in workspace-note-lock-gate.md
---

## Where is the lock kept?
- `ws_note_locks` (one row per person, `workspace-note-groups.md`): `password_hash()` of a 4-8 digit PIN, `open_minutes`, `fails`, `wait_until`. No row means no lock: `ws_note_lock_row()` is null and `ws_note_lock_is_open()` answers true.
- The open lock is `$_SESSION['software']['ws_note_lock']` (user_id, used_at, note_id); the e-mailed code `$_SESSION['software']['ws_note_lock_code']` (its sha256, never the code). Sign-out or another browser closes the lock.

## When does an open lock hold?
- `ws_note_lock_open_holds()` (pure): until sign-out (65535) always; a time in minutes while the last use is within it; "every time" (0) only for what it was opened for (`ws_note_lock_open` with `note_id`; 0 = the list, which then shows a locked group's titles while each note still asks), used within 15 minutes (`WS_NOTE_LOCK_EVERY_TIME_IDLE`). `ws_note_lock_is_open($user_id, $note_id)` reads the row and the session.
- `ws_note_lock_touch()` renews it on each gated use. `ws_handle_action()` closes the session early (`ws_note_lock_*` keep it open), so the touch reopens it with `session_start()` for its one write, only when the request had a session (never in the external API). `ws_note_lock_close()` is Lock now.

## What do wrong PINs do?
- `ws_note_lock_verify()` counts a wrong PIN in one UPDATE (`fails`); the fifth in a row sets `wait_until` 15 minutes out and starts the count again. While paused every PIN check answers code `wait` with the seconds left; the e-mailed code still sets a new PIN, which ends the pause.

## How does a forgotten PIN work?
- `ws_note_lock_code_send()`: six digits to the account's `user_email`, in the person's language (their own request), sent now with `secret`, at most three an hour (`waf_rate_exceeded(user_id, 'note-lock-mail', 3, 3600)`); answers the masked address. `ws_note_lock_code_check()` (pure): ten minutes, used once, the fifth wrong guess ends it. Shaped as the passkey e-mail code (`includes/fn/passkeys.php`).

## Which panel actions are there?
- `ws_note_lock` (state), `ws_note_lock_setup` (pin, pin2, minutes), `ws_note_lock_open` (pin, note_id), `ws_note_lock_now`, `ws_note_lock_change` (pin, pin2 and current, or code), `ws_note_lock_time` (pin, minutes), `ws_note_lock_off` (pin; every note and group of the person unlocked, the row deleted), `ws_note_lock_code`.
- Each answers `lock` = `ws_note_lock_state()` (ready, set_up, minutes, times with labels, open, wait, email masked). A refusal adds `code` (no_lock, not_ready, set_up, pin, pin_shape, pin_twice, wait, code, code_expired, minutes, email_none, email_limit, email_send), `wait` and `lock`.
- Setting up, opening, a new PIN and a new time open the lock in the session.

## Where are the tests?
- `tests/workspace_note_lock_test.php`: PIN, times, `ws_note_lock_open_holds()`, the code, masked address; the gate's pure parts too (`workspace-note-lock-gate.md`). Database paths (counting, pause, turn off) are not unit-tested.
