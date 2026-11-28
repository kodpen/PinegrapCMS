---
name: person-status-screens-ui
description: A person's own status on screen (PS2): the Status dialog, the account menu's row, the mark beside names, the card's line, the composer's lines, CFG.person_status
module: person_status
---

## Where are the screens built?
- `includes/workspace/person_status_screen.php`: each kind's icon and tone (`ws_person_status_looks()`: available teal check-circle, busy rose dash-circle, meeting blue people, out amber door-open, sleeping violet moon, leave gray airplane), the "Clear after" options, `CFG.person_status` (`kinds`, `ends`, `max`, `mine`; null for a non-member and before the column) and the `ps_*` texts.
- The script parts are in `workspace.js` beside the person card: `personStatusDialog()`, `personStatusMark()`, `personStatusCardLine()`, and the pure `personStatusActive()`, `personStatusShown()`, `personStatusWords()`, `personStatusComposerLines()` (`tests/person_status_screens_test.php`).

## What does the Status dialog hold?
- The six kinds as the choice cards (`.pg-start-choice`) three across, a native radio group (the arrow keys walk it); Note with its count of 80; "Clear after" with an extra "Until morning" (the end `morning`, Sleeping's default, which the screens file had no option for); "Choose a time…" shows a date and time field.
- Choosing a kind sets its default end until the person changes the select. A chosen time is sent as a Unix time read in the browser's zone.
- Foot: Clear status (only while one is set) at the left, Cancel and Save. A refused field's message is said under that field.

## Where is it opened?
- The account menu's row above My Account (`ws_person_status_menu_row()` in `pg_account_menu()`): "Set a status" or the status with its end. It links to `workspace.php?panel=status`, which opens the dialog after boot; on a screen running `workspace.js` a click opens it in place.
- The reader's own person card: "Change", or "Set a status" with none.

## Where is a status shown?
- The 14 px mark (`.ws-person-status-mark`, tooltip and hidden words) beside a message's sender, a member in Channel info (label and end also on the role line), a discussion's people and a row of the @ list. Available with no note is told only on the card.
- An ended status is dropped when drawn (`until` passed); no poll. The reader's own comes from `CFG.person_status.mine`, updated on save.
- The composer: one quiet line per person named with @ whose status is not Available, at most two, then "and N more"; it never stops the send.

## What is not drawn?
- Workspace channels have no direct messages (they are the panel chat, `chat_backend.src.js`, open to people outside the team), so the side list's direct-message row and the composer's always-shown line in one are not drawn.
- The line under an agent question is PS3's.
