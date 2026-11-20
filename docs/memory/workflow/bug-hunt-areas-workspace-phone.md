---
name: bug-hunt-areas-workspace-phone
description: The bug hunt of a Workspace channel at phone width after the writing box change (#10495), 2026-11-20: what it fixed, the issues it opened, what read clean
---

## What was the area?
- Given on 2026-11-20 (channel message 9983): a Workspace channel at 390 and 320 px after the writing box change (#10495): the field and its tools row with the keyboard up and down, the box's menus, the expanded box, a discussion panel, a poll and a canvas card.
- Kept in a file of its own because two hunts open at the same time (#10537, #10543) both start `bug-hunt-areas-3.md`; the earlier areas: `bug-hunt-areas.md`, `bug-hunt-areas-2.md`.
- Run in headless Chromium on a `setup_sandbox.sh` instance, touch emulated, the keyboard as a 385 px high viewport (iPhone 13).

## What did it fix, and what became an issue?
- Fixed (`workspace_editor.js`, `tipFieldClass()`): the link and find tips' fields and the insert menu's windows' fields (title, formula, code language, decimals, unit) were 14px, so iOS zoomed the page in on a tap; now 16px on a touch screen (`workspace-composer-touch.md`).
- Issues, `backend.src.css` and `workspace.js` being held by #10522, all fixed in PR #10592: #10549 the expanded box pushed Send under the keyboard; #10550 the tools row and Send were 29 × 33 px; #10551 the table size picker's 22 px cells; #10552 the emoji search, poll form and code fields under 16px; and from the mail hunt #10543, #10545 the mail pane's 22 px channel pill.

## What read clean?
- No page wider than 320 px; the channel tabs scroll sideways inside their row.
- The attach, @, #, emoji and insert menus and the "/" list stay inside 320 × 385 (insert scrolls); the context menu of a message scrolls inside the screen.
- The discussion panel covers the channel at 320 px; its discussed message keeps 30% and scrolls; the head's name is cut with an ellipsis.
- A poll's long options and a long unbroken word wrap inside 320 px; the selection's format bar and the link tip fit.
- Not reached: a canvas card (sending a new canvas from the headless window wrote no message), so it was read in `message-parts-narrow-column.md` only.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-2.md, docs/memory/workspace/workspace-channel-composer.md
