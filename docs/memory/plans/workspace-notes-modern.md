---
name: workspace-notes-modern
description: Plan (owner 2026-11-27, message 14812): My Notes modernised: the note written as a page with one row of tools, the list as rows with a tinted choice and an order menu; steps NM1 to NM3
priority: 6
---

## What is asked?
- Owner (2026-11-27): the Workspace's own screens modernised (`plans/workspace-areas-modern.md`, whose rules every step here keeps). This plan is My Notes (`workspace_notes.php`, `startNotes()` in `assets/js/workspace.js`, the editor in `workspace_editor.js`).

## Which steps? (M about 1,000 product lines, S under 400; each waits for the one before it)
- NM1 (M) The note as a page. In the note's pane the title is one large plain line (the screen title's size, no field frame), and the text is written on the card itself: no frame and no focus box around the writing area, a column of at most 46rem in the middle of a wide pane.
  - The two rows of tools become one row that stays at the pane's top while the note scrolls; tools that do not fit its width go into a "More" menu at its end, in the order they stand today. "Saved", the last change and the history, share and "⋯" buttons keep the pane's head, in the quiet colour.
  - Only My Notes' pane changes: a channel's note, the task description and every other place the editor is used look as before, pinned by a test on the classes.
- NM2 (M) The list. Each note is a `.pg-list-row`: icon, title, an excerpt of two lines, the time, the pin and the unread dot as today (`workspace/workspace-notes-list-layout.md`). The chosen note is tinted (`--pg-tint-select`) with an accent edge, not a filled box. Groups and "Shared with me" are group labels with a count that fold on a click or Enter (kept per browser).
  - The bar's "⋯" gets an order: Last changed (as today), Name, Created; kept per browser and applied to what the paged list has loaded, the server's order staying the paging's. The empty pane keeps its New note button.
- NM3 (S) Close-out: `workspace/workspace-notes-list-layout.md` corrected, new `workspace/workspace-note-page.md` (the page, the one row of tools, what the other editors keep), this file deleted.

## What is left out?
- What a note may hold, its limits, versions, lock, groups and shares (`workspace/workspace-notes.md`) are unchanged.
- A note inside a note and a note's own cover or icon: each needs a column, so each is the owner's to ask for.
