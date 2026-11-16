---
name: message-highlights-features-2
description: Plan message-highlights, features H10 to H22: the mini toolbar on a selection, how a highlight is drawn in its maker's colour, its card, My highlights in My Notes and bulk removal
---

## What does the reader see on a message?
- (H10) The mini toolbar shows when a selection as H1 settles (pointer up; 200 ms after the last change by keyboard or touch): above the selection, below it when there is no room and always below on touch, so the system's own menu is not covered. Two buttons with the editor's Highlight icon (`bi-highlighter`): "For everyone", "For me"; "For everyone" is absent where H4 refuses. A selection over 1,000 characters shows them disabled with "Select a shorter part".
- (H11) It takes no focus when it shows (the selection stays), is reached with Tab, has `role="toolbar"`; Escape, a click elsewhere, a collapsed selection or the list scrolling closes it.
- (H12) A press saves, clears the selection and draws the highlight at once. A refusal is a toast; nothing is drawn.
- (H13) Drawn as `<mark class="ws-hl">` around each piece of its text, the background from its maker's colour (`senderTone()`, the tint tokens of `workspace/workspace-message-box-width.md`), readable on the message's own tinted box in light and dark. A `me` one has a dotted underline too. Overlaps nest, the newer inside.
- (H14) Hover, keyboard focus or a tap opens its card: the maker's name, "For everyone" or "Only you", the note when there is one; "Add a note" or "Edit note" for the maker; "Remove" for who may (H7). Overlapping ones are listed in one card. Reached from the keyboard the way a message's reactions are.
- (H15) A highlight whose quote is not in the drawn text is not drawn; no error is shown.
- (H16) Others' open conversations draw a new `all` highlight, a changed note and a removal without a reload (the stamped message).

## What is My highlights?
- (H17) My Notes' bar gets "My highlights" (`bi-highlighter`) beside "Import"; it opens the list where a note opens. It lists the person's own highlights of both scopes, never anybody else's.
- (H18) Rows, newest first, 50 at a time with "Show more": the highlighted text whole, marked in his colour; under it the channel or chat, the message's writer, the date, a chip "Everyone" or "Only me", and the note. Only rows whose message he can still read.
- (H19) A click on a row opens that conversation at the message, as a note's source link does, and flashes the highlight.
- (H20) Chips above the list: "All", "Everyone", "Only me", each with its count.
- (H21) Bulk removal: a tick on every row, "Select all" (the whole filter, rows not loaded yet too) and "Remove n" with one confirmation that says the ones everyone sees disappear for everyone. One request; each message is stamped once.
- (H22) With none: one line saying to select text in a message and press Highlight.
