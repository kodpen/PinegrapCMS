---
name: workspace-writing-box-look
description: Which look recipe each button of the writing box's tips and windows, highlights, saved replies, voice player and translation line takes
---

## Which buttons does the writing box draw, and in which rank?
- All of `workspace_editor.js` but its context menu rows: link tip Save `pg-button-fill` (icon only), Open and Remove `pg-icon-button-sm`; find tip arrows and close icon buttons, Replace / Replace all `pg-button-quiet`; keyboard help close an icon button.
- A block window (table, checklist, code, calculation): Cancel `pg-button-ghost`, Insert / Update `pg-button-fill`. Table and checklist tools: with a label ghost small, icon only `pg-icon-button-sm`; the align button pressed as `is-active`; Delete row / column / item keep `ws-tool-danger` (red on hover).
- Calculation window: its tools ghost or icon buttons by whether they carry a label; the names to pick `pg-button-quiet pg-button-sm ws-calc-name`; "Reset" in a block head ghost small.
- The row of tools (`PGWsEditor.toolbar()`, `workspace-editor-toolbar.md`): `pg-icon-button pg-icon-button-sm`. A pressed mark keeps the accent tint (`.ws-rich-tools .pg-icon-button[aria-pressed="true"]`: `--ws-accent` at 15%) over the icon button's grey pressed fill; disabled undo, redo are faded with no hover.

## Why does the context menu keep `btn btn-link`?
- `ws-ctx-item` rows are menu rows, not buttons: menus are left out of the look plan (`plans/workspace-look-leftover.md`, "What is left out?"). The same row in `workspace.js` stays too. It is the editor's one count in `tools/check_look_baseline.json`.

## What do highlights, saved replies, voice and translation draw?
- Highlight card: Add / Edit note quiet small, Remove `pg-button-danger pg-button-sm`; the note form Save fill, Cancel ghost.
- My highlights: the filter (All, Everyone, Only me) is one `pg-seg pg-seg-wrap` of `pg-seg-item` with `aria-pressed` and a `pg-seg-count`; Remove n danger small; Show more quiet small.
- Saved replies drawer: the two Add buttons quiet small (two in one view, so neither is fill); a row's Use, Edit, Delete icon buttons (Delete with `ws-tool-danger`); the form's Save fill, Cancel ghost.
- Voice: recording row Discard icon button, Stop fill small; player Play `pg-button-fill ws-voice-play` kept round by `.ws-voice > .ws-voice-play`; speed ghost small; reply and download icon buttons; on a phone they keep the 2.5rem touch size.
- Translation line: the Show original / translation toggle is a ghost button drawn inline in the sentence (`.ws-msg-tr .ws-msg-tr-toggle`: no height, no pad).
