---
name: workspace-notes-list-layout
description: My Notes list: a card beside the note card (widths, sticky, own scroll), its head, each note a .pg-list-row with a hairline, the open row's 3 px edge, bands that fold, the order
---

## How wide is the note list?
- Since the redraw (`notes-screen-drawing.md`) `startNotes()` in `assets/js/workspace.js` lays `.ws-nb` out as two cards: the list card 372 px from 1200 px up with 20 px between the cards, 300 px and 16 px from 1024 px; below 1024 px one card at a time (`.ws-nb-open`).

## How is the list card built?
- `section.pg-card.ws-nb-list-card`: the head `.ws-nb-list-head` (46 px, as the note card's head, so their lines meet) holds "Notes" (`notes_list_head`), the count of the notes listed, New group (`PGWsNoteGroups.addButton()`, only with groups) and the order's icon button (the same items as the bar's ⋯), then the body `.ws-nb-list`.
- From 1024 px the card is sticky and its body scrolls by itself; `keepListPlace()` keeps the scroll over a redraw and shows a note opened from elsewhere.
- The note card is the look's card too (`workspace-note-page.md`).

## How is a note's row drawn?
- Square in the card with a hairline under it, padded 12 px (16 px at its start); the card's border closes the last one. The rule is `.ws-nb-list .ws-nb-item`, to outweigh `.pg-list-row` (#12295).
- Since #12109 `drawItem(note)` draws a `.pg-list-row.ws-nb-item` (the look's row, `design_examples.php`): a `.pg-icon-tile` (amber for an own note, blue for a shared one), then `.pg-list-row-main`.
- The head line holds the title (`.pg-list-row-title`), the unread dot (`.pg-unread-dot`), the pin and lock marks and the ⋯ in `.pg-list-row-quick` (on hover or focus; always on the chosen row).
- Under it the two-line excerpt (`.pg-list-row-text`) and `.ws-nb-item-meta`: the time first (kept off the head so the title has it, #5194), then the faces or owner, the channel and the Claude tag. A read note is not dimmed; an unread one has the dot and a heavier title (`--pg-weight-title`), never a tint.

## How does the open note look in the list?
- It is the chosen row, `.is-chosen`: an accent tint of `--pg-tint-select` (7% light, 10% dark) and a 3 px accent edge, `inset 3px 0 0 var(--pg-accent)` (mirrored in RTL). No filled box or border.

## How do the sections and folders fold?
- My notes and Shared with me each stand under a band across the card (34 px, a line under it) holding the group label (#12109): `PGWsNoteList.section()` in `assets/js/workspace_note_list.js` draws an `h2` holding a `button.pg-group-label.ws-nb-fold` (chevron, name, `.pg-group-label-count`) over `.ws-nb-section-body`.
- A click or Enter folds the section (`aria-expanded`). Folded sections are kept in `localStorage` `pg_ws_notes_folded`, in try/catch; a search shows every section open.
- New group sits in the list card's head. The count is every note under the section: loose ones plus each folder's.
- Folders (`workspace_note_groups.js`, `workspace_note_group_shares.js`) use the same button and count on a band set in under the section's; their notes are set in by 14 px.

## How is the list ordered?
- The bar's ⋯ starts with Sort by (#12109): Last changed (the server's order), Name, Created (`PGWsNoteList.menuItems()`). The list card's head offers the same items under its sort icon. The pick is kept in `localStorage` `pg_ws_notes_order`; the ⋯ is always shown since it holds the order.
- `order()` sorts what the list has loaded: pinned first, then the newest change, the name (`localeCompare`, numbers as numbers) or the newest note; the higher id breaks a tie. The server's order stays the paging's.
- It sorts own notes in no folder and single shared notes; folders keep their hand-set order (pinned, then `group_sort`). Tests: `tests/workspace_note_list_test.php`.

## What does the note's place show with no note open?
- Never an empty pane: see `notes-screen-opening.md` (the old `placeholder()` and its second New note are gone).

Related: docs/memory/workspace/notes-screen-close-out.md
