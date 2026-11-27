---
name: workspace-screen-bar-notes-data
description: The one-line bar and empty screen of My Notes (startNotes(), "⋯" with the list's order, Pin to the menu, Import, Export all) and Data (ws_wtable_list_bar(), ws_empty_screen_html())
---

## What does My Notes' bar hold?
- `startNotes()` in `assets/js/workspace.js`, the rule of `workspace-screen-bar.md`: New note (filled); My highlights and Lock (`PGWsNoteLock.button()`, ghost, via `PGWsKit.lookButton`); the grow slot; the search (`barSearch()`); "⋯" (`pg-icon-button`).
- "⋯" holds Sort by (Last changed, Name, Created; `workspace-notes-list-layout.md`), then Pin to the menu or Unpin from the menu (once `ws_notes` says `menu.ready`), Import (`CFG.notes_import`), Export all (`CFG.urls.notes_export`). It is always shown, since the order is in it. The pin's call, toast and menu row are as before.
- On a phone My highlights and Lock keep their icons; the word sits in `.ws-bar-label`, hidden from the eye only (`barLabel()`).

## How is My Notes' empty screen drawn?
- In the list's pane, once: with no note, `emptyScreen()` in a `pg-card` (amber `bi-journal-text`, "No notes yet", the sentence `notes_intro`, New note). The Mine and Shared with me sections stay under it, so New group stays reachable.
- A search that finds no note: `barEmptyFound()` with "No note matches." and Clear, which empties the search and loads again.

## What does Data's bar hold?
- `ws_wtable_list_bar()` in `includes/workspace/wtable_list.php`, printed by the server in a `.ws-bar-frame`, no longer `#button_bar` (its id rules wrap the line and outweigh `.ws-bar`): New table (filled, from 576 px; the phone's floating one stays), Import from file (quiet, its word in `.ws-bar-label`).
- Then the scopes with their counts as `pg-seg` links plus a `select[data-ws-bar-go]` the stylesheet shows when narrow (a script in `workspace_wtables.php` goes to the chosen address); the grow slot; the list's search (`data-pg-list-search`); Deleted tables as `pg-icon-button`, its count in its name.
- In the deleted view no scope is pressed (each leads back to its list), the select names Deleted tables and the icon button is pressed, leading back to the tables.
- No table yet: `ws_empty_screen_html()` (`includes/workspace/screen.php`, the PHP twin of `emptyScreen()`), amber `bi-table`, New table as the quiet action. A search finding nothing stays the list driver's own line.

## What was kept?
- Every address (`?scope=`, `?view=deleted`), the list's sort and saved choices, every button and menu item; only their places changed (#11214).

## What pins it?
- `tests/workspace_wtable_list_test.php` (`test_ws_wtable_list_bar`), `tests/workspace_canvas_phone_test.php`.
