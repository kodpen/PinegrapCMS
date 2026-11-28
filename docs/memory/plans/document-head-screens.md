---
name: document-head-screens
description: Plan document-head: how the shared window head is put together (identity, history and slot, acts), name and state, the main button, the ⋯ menu's order, the phone, what each window puts into it
---

## How is the head put together?
- One row 52 px high on the window's surface with a line under it, padding 0 10 0 14. Three groups left to right; a divider 1 px wide and 20 px high stands between identity, history and slot. Drawn dark and light in `ui/document-head-drawing/head.html`.
- Identity: Back (an icon button; only on a page with a list behind it, and on the phone), the kind's 28 px tile in its tone, the name 15/600 on one line, the meta in 12.5 px third text, the state.
- History and slot: Undo and Redo as 32 px icon buttons, dim while there is nothing to take back, not drawn where the body has no Undo. Then the kind's own controls, at most five: small ghost buttons, icon buttons with a tooltip, one segmented control.
- Acts at the right end, in this order: faces (24 px), "Share" (small ghost button), Download (an icon button, view windows only), the main button (small), ⋯, grow or Full screen, Close.

## What are the name and the state?
- Name: text. Who may rename clicks it and types in place: Enter or leaving saves, Esc restores, the field takes the accent ring. Too long: cut with an ellipsis, whole in the tooltip.
- State: a 6 px dot and words in 12.5 px. "Saving…" (grey), "Saved" (teal, the time as tooltip; a note adds "· 2 min ago"), "Unsaved changes" (amber, ringed), "Could not save" (rose, the reason as tooltip). A view-only window shows none.
- The state takes the place of a "Saved" toast and of the foot's "You have unsaved changes." line.

## What is the main button?
- The kind's main act. "Save", filled, where the person saves by hand, at 45 % strength until something changed. "New row" on a table. In a view window the edit door ("Edit", "Edit in Artifact Canvas"), quiet, not filled. A note has none.
- The canvas's split button stands here unchanged.

## What does ⋯ hold?
- Groups in this order with a line between, a row the kind lacks not drawn: Open in a new tab, Full screen; Copy link, Send to a channel; Download, Export…, Print; Versions; the kind's own rows; Help, then Delete in rose, last.
- A menu of one row is not drawn: the row stands as an icon button (the canvas's Help).
- A head or foot button of today that is neither the main act nor in the slot goes here.

## What does each window put in?
- Note: amber tile; the state with the time; Undo and Redo leave the tool row; faces, Share, ⋯ with its rows of today. A channel note's Edit window: the same with "Save" and Close, no foot.
- Data table: Back to "All tables", teal tile, name (the panel top bar's heading stays "Data"), the row count, the state in place of the toast; slot Table/Board and Filter; Share opens Shares; "New row"; ⋯.
- Artifact Canvas: rose tile, name, "W × H px", "Unsaved changes" once something changed; Undo and Redo; slot `Editor.topItems`; the split button; Help; Full screen; Close, which moves from the left end to the right.
- Word or Excel file: the kind's tile, name (the Name field moves up), size or sheet count, state; Undo and Redo leave the bar; "Save"; ⋯ with Open in a new tab and Download; Close.
- Quick Look and a channel's Preview: tile, name, meta; slot the window's tabs and "Present"; Download; the edit door or "Save"; ⋯; grow; Close.

## How does it look on the phone? (under 640 px)
- 48 px high: Back in place of Close, the name, the state's dot alone with its words as tooltip, the main button, ⋯. Undo and Redo are the first rows of ⋯; the slot, Share and Download follow them there.
