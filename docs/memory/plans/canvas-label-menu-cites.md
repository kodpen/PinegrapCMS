---
name: canvas-label-menu-cites
description: Plan (owner 2026-11-24): buttons that open the Artifact Canvas say "Edit in Artifact Canvas"; its "?" is the help link and shortcuts get their own icon; places citing a menu row draw its new icon
priority: 6
---

## What is asked?
- Owner (2026-11-24, agent channel message 12353, with a picture): three things, his words in `workflow/decisions-made-panel-18.md`.

## CL1 (S): what do the buttons say?
- Every button and menu entry that opens the Artifact Canvas on an existing picture reads "Edit in Artifact Canvas", Turkish "Yapıt Tuvalinde Düzenle" (`php tools/tr_add.php`): the File Manager's row and preview, the Page Design Studio's "Edit Image" entries (`_sdT()`, `style_designer.src.js`), a Workspace file card, and any other the worker finds by the callers of the canvas's open function.
- Where the label does not fit (an icon button, a narrow toolbar) it is the tooltip and the screen-reader name. Entries that make a new canvas ("New canvas", "Blank canvas") keep their words.
- The worker gets message 12353's picture from its coordinator and names every place it changed in the PR.

## CL2 (S): what does the canvas's "?" do?
- The question mark (`bi-question-circle`, label "Help") is the help link of issue #11185 (landed in #11195): the documentation page in a new tab, the open tool's anchor, hidden with no address (`PRIVATE_LABEL`).
- The shortcuts list keeps a button of its own with the keyboard icon (`bi-keyboard`), label "Keyboard shortcuts"; the `?` key still opens it. No second question mark is left in the canvas.
- If #11195 already left it so, the step is the check on a sandbox and a line in the canvas's memory file.

## CL3 (S, after MI2 of `plans/main-menu-icons-redraw.md` lands): which icons follow the menu?
- A place outside the menu that stands for a menu row draws that row's icon from `pg_menu_icon()`: at rest, in the place's own size and colour, no tile unless the place has one, no move.
- Known: the Workspace's Notes part and the header's new note button. The worker lists the rest by searching each redrawn row's old glyph (`plans/main-menu-icons-redraw-rows.md`) in the Workspace's tools dock, the header's quick buttons, the dashboard cards' heads, the command palette and the empty states.
- An icon that stands for an action or an object and not for the module (a note's own icon in a list, a file type) stays.
- The PR lists each place before and after; the designer agent looks at it when it lands.

## Which order, and what does the close-out do?
- CL1 and CL2 both enter the canvas's script: one worker, CL1 then CL2. CL3 apart, after MI2.
- The last to land writes the label and the "?" into the canvas's file in `ui/`, CL3's rule into `ui/main-menu-icons.md`, and deletes this file.

Related: docs/memory/plans/main-menu-icons-redraw.md, docs/memory/workflow/decisions-made-panel-18.md
