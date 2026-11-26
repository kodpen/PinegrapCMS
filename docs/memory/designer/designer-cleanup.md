---
name: designer-cleanup
description: The designer's "Clean up" (command palette row and the Publish check's button): what it lists, what it never counts as unused or removes, and which file holds each part (#11560, #11668, #11864)
---

## What is it?
- Owner (2026-11-24): a design worked on for months is made light again without fear: style rules, files, shared components and variables nothing uses are listed with the reason and removed only when ticked (level 2 of `workflow/decisions-made-product-108.md`; kept simple and quick by `designer-light-and-simple.md`).
- Opened from the command palette's "Clean up" or the quiet "Clean up" of the Publish check's "Rules no page of the design uses". A window with four lists ("Style rules", "Files", "Shared components", "Variables"), each with a count and, for rules and files, the size it would free. Nothing is ticked at the start; "Remove {n}" removes what is ticked.
- No table or column of its own.

## What must it never do?
- Count as unused a class that a script, an interaction, a condition, a classic page, another design's page or a system widget's default layout names; a class written by a setting or a record's value; a rule under a comment that says to keep it. When it cannot tell, the row is not listed.
- Remove anything by itself, on a schedule or at Publish.
- Touch a stylesheet that is not the design's own (a library's, the look's).

## Which file holds what?
- The finder, pure (`includes/fn/design_cleanup.php`), its cases it cannot tell: `design-cleanup-finder.md` (#11560).
- The window, what opens it, the four lists, "Show", the states, `designer/cleanup_list`: `designer-cleanup-window.md` (#11668).
- "Remove": the snapshot "Before clean-up", the server's re-check, the closing line, rows that stay, `designer/cleanup_remove`: `designer-cleanup-removal.md` (#11864).
- The drawing: `designer-cleanup-drawing.md` (boards U1, U2) and `designer-cleanup-states-drawing.md` (U3, U4); it decides where the two differ (`workflow/screen-follows-drawing.md`).

## How is a new kind of unused thing added?
- A fifth list keeps the window's fixed order and adds its kind to the finder's lists (`pg_cleanup_design_lists()`), to the server's re-check and removal (`pg_cleanup_remove_pick()`, `pg_cleanup_remove()`), to the snapshot when it can be brought back, and to the closing line's counts; it errs towards "used" like the rest.
