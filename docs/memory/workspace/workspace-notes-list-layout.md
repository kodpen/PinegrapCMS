---
name: workspace-notes-list-layout
description: My Notes screen layout: list column widths, the note time on the line under the title, the New note button in the empty pane (#5194)
---

## How wide is the note list?
- `startNotes()` in `assets/js/workspace.js` puts the list in `lg:col-5 xl:col-4 ws-nb-list-col` and the note in `lg:col-7 xl:col-8`. Until #5194 it was `lg:col-4 xl:col-3`: at a 1063 px window with the side menu open the list was 271 px and titles kept 92-114 px ("Launch bu…").

## What sits on a list row's head line?
- Only the icon, the name, the unread dot, the pin mark and the ⋯ button. The time (`.ws-nb-item-time`) is the first item of `.ws-nb-item-meta`, the line under the excerpt, which is now always drawn; the unread colour rule still reaches it there.

## What does the pane show before a note is picked?
- `placeholder()`: the icon, "pick a note" text and a New note button that calls `startNew()`, the same as the toolbar's.
