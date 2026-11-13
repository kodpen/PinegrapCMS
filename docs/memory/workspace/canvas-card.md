---
name: canvas-card
description: The joined card of a canvas with a drawing and a page (CO7c, #9274): file.joined, part chips, the Drawing / Page switch, one Versions drawer with Show and Put back, which file each panel action reads
---

## When is it drawn?
- For a message whose canvas has two parts (`workspace/canvas-parts.md`): `ws_canvas_joined_card()` (`includes/workspace/canvas_card.php`) fills `file.joined` in the message payload, and `filePreview()` in workspace.js draws `PGWsPageCard.joined()` (`workspace_page_card.js`) before a part's own card. A canvas with one part keeps its own card (the page card, or the canvas picture).
- `file.joined`: `name`, `drawing` {`url` (the picture's `?v=` address), `version`, `pending` (the version a member's browser draws, 0 for none)}, `page` (the page card's state from `ws_page_card()` on the page part), `versions`, `can_restore` (may write in the channel).

## What does the card show?
- The name, part chips Drawing, Page and "Data n" when tables are bound, the agent's line, a segmented switch Drawing / Page, and Versions at the foot. Drawing shows the picture (a click opens the image viewer); Page holds the page card itself (`card(…, {bare: true})`: no name, maker or Download), so Open, Full screen, View source, Undo, the line of tables, Bind a table and the first-write question stay as they are.
- Leaving the Page tab stops the page (`close()`); the tab is remembered per message while the conversation redraws the card.
- A drawing part still pending is drawn by the member's browser through `workspace_file_save.php?message=<id>&part=drawing` (`ws_file_edit_target()` takes the part).

- Owner (2026-11-13, #9274): approved from sandbox screenshots as built, with the part chips at the right of the name row and no Show on a drawing version.

## What does the Versions drawer do?
- One list over both parts, newest first (`ws_canvas_card_versions()`, pure): "Page · version 7" or "Drawing · version 2", the maker (@handle, or Put back for a restore), the time, Now on each part's newest; a segmented All / Page / Drawing with counts.
- Show on a page version opens View source at that version. Put back this version (for whoever may write in the channel, not on the newest) is `ws_canvas_restore` {message_id, part, version} → `ws_canvas_part_restore()`: a new version with source `restore`, every version kept; a drawing version still pending is refused.
- A drawing version has no Show yet: its pictures are read through the image editor's file rights, not the channel's.

## Which file do the panel actions read?
- The page part, through `ws_canvas_part_message($message, 'page')`: `live_page.php` (the frame), `ws_page_source`, `ws_page_undo`, the bridge, bindings and the first-write question (`workspace/canvas-parts.md`).

## How was it checked?
- `tests/workspace_canvas_card_test.php` (the list, the texts, the hooks). In a sandbox panel (#9274) both two-part canvases drew in dark and light, wide and phone: the drawing's picture drawn by the browser through the part, the page frame run, the drawer listed, and a page version put back. Times in the drawer and View source follow the panel's language and the site's zone (`CFG.locale`, `CFG.timezone`).
