---
name: dashboard-v2-notes-card
description: The new dashboard's My notes card as a shortcut to My Notes (PR #8693): the add box, the latest notes, who is offered it, the one move of the old card's text
---

## What does the card do?
- A shortcut to My Notes (owner, `workflow/decisions-made-panel-5.md`). Code `includes/dashboard/v2/cards/notes.php` and the `notes` renderer in `assets/js/pg_dash_cards_personal.js`. Before PR #8693 it was a plain text of 2,000 characters kept in the layout (#4981), apart from the notes.
- An "Add a note" box one line high grows to four lines with Save, Cancel and the hint when focused, and shrinks when left empty. Save or Ctrl+Enter posts the workspace's `ws_note_save` action with an empty title, so the first line names the note: the header's quick note's path, with its rights and limits (`workspace/workspace-notes.md`).
- Under it the person's own five latest changed notes by `ws_notes_latest()`: name and date, a row opens `workspace_notes.php?note=<id>`. A note behind a closed lock shows as the notes list shows it (`ws_notes_present()`, the lock's list filter), with a lock icon. A saved note goes on top at once; "All notes" opens My Notes.

## Who is offered it?
- Gate `notes`, `pg_dashboard_v2_notes_gate()`: a member of the team by `pg_dashboard_workspace_rights()`, while `ws_notes_ready()`. Not tied to the layout lock: the notes are the person's own.

## How is the old card's text moved?
- `pg_dashboard_v2_notes_move_stored()` (`includes/dashboard/v2/personal.php`) runs when the card is drawn. Under the named lock `pg_dash_notes.<md5(database.user id)>` it reads the layout's `notes` again, writes it as a new note through `ws_note_save()` and only then writes the layout with `notes` = ''.
- An empty text makes no note, blanks are cleared without one, a refused save leaves the text for the next visit; once cleared nothing moves again, and a second request meeting the lock moves nothing. Text of a person whose layout lost the card moves when the card comes back.
- `dashboard_personal_save` refuses the part `notes`, so an old tab typing into the plain box gets "The change could not be saved.". Test: `tests/dashboard_v2_notes_card_test.php`.
