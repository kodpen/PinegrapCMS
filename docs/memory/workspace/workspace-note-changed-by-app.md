---
name: workspace-note-changed-by-app
description: The application that changed a Workspace note: updated_app_id / app_id (11.0.257), the save, "Changed by @handle", the API's changed_by, the card and version lists (PR #10797, #10904)
---

## What is kept?
- `ws_notes.updated_app_id` and `ws_note_versions.app_id`, INT UNSIGNED NOT NULL DEFAULT 0, added by `upgrade_2026_11_0_ws_note_app()` (label 11.0.257). 0 means a person saved; existing rows stay 0. Columns bind: `workflow/decisions-made-schema-37.md` (poll 180).
- Why: every application key acts for a person, so `updated_by` alone named that person for every agent that wrote in a note.
- Readers probe `ws_note_app_ready()` (both columns) before naming them: files land before the step runs.

## Who writes the application?
- `ws_note_save()` writes `updated_app_id = ws_note_saver_app($data)` on a new note and on every change, beside `updated_by`. `$data['app_id']` comes only from the API: `ws_api_notes_create()` / `ws_api_notes_update()` set it from `ws_api_message_writer()['app_id']`, `ws_note_channel_write()` passes the route's. A person's own device and an OAuth client give 0, as for messages.
- The panel's `ws_note_save` action builds `$data` from a fixed field list, so a screen can never name an application.
- `ws_note_claude_deliver()` (the answer the person's screen writes in) sets 0.
- `ws_note_version_keep()` stores the kept state with the `updated_app_id` it had, and keeps a version when the application changes, as it does when the person changes.

## How is it shown?
- `ws_notes_present()` (so the card map `ws_note_cards_map()` too) gives `updated_app` (`ws_app_sender()`, null for a person) and `changed_by`, the words "Changed by @handle" (`ws_note_changed_by_words()` over `ws_note_changer()`): a person by `@username`, an application by its name (it has no handle).
- `ws_note_versions()` gives `app` and `changed_by` per version.
- API: `changed_by` (`WorkspaceMessageSender?`, `ws_api_note_changer()`) on a message's `note_card` and on `WorkspaceNote`; user_id null with app_id for an application. `updated_by` stays the person.
- The channel card's foot (`workspace_channel_notes.js` `changedWords()`) draws `changed_by`, then ` · ` and the time; nothing when it is empty. The script builds no words from `updated_by`; the old `cn_changed` template is gone (PR #10904).
- The card's Versions drawer (`versionWho()`) and the notes screen's version list (`startNotes()` in `workspace.js`) draw each version's `changed_by`; the drawer says "Person" when it is empty, the list draws no line.
- Why: an agent's key acts for a person, so `updated_by.name` named that person for every agent change.
- Not changed: the notes screen's own foot and its "Last change" line still name `updated_by` (`notes_last_change`).
- Tests: `tests/workspace_note_app_test.php`.
