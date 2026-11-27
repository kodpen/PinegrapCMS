---
name: workspace-notes-list-layout
description: My Notes list: column widths, each note a .pg-list-row (time under the excerpt), the chosen row's tint and edge, group labels that fold, the order in the bar's ⋯, the New note button in the empty pane
---

## How wide is the note list?
- `startNotes()` in `assets/js/workspace.js` puts the list in `lg:col-5 xl:col-4 ws-nb-list-col` and the note in `lg:col-7 xl:col-8`. Until #5194 it was `lg:col-4 xl:col-3`: at a 1063 px window with the side menu open the list was 271 px and titles kept 92-114 px ("Launch bu…").

## How is a note's row drawn?
- `drawItem(note)` draws a `.pg-list-row.ws-nb-item` (the look's row, `design_examples.php`): a `.pg-icon-tile` (amber for an own note, blue for a shared one), then `.pg-list-row-main`.
- The head line holds the title (`.pg-list-row-title`), the unread dot (`.pg-unread-dot`), the pin and lock marks and the ⋯ in `.pg-list-row-quick` (shown on hover or focus, always on the chosen row).
- Under it come the two-line excerpt (`.pg-list-row-text`) and `.ws-nb-item-meta`: the time first (`.pg-list-row-time`, kept off the head so a narrow column leaves the head to the title, #5194), then the faces or owner, the channel and the Claude tag. A read note is not dimmed; the unread colour rules stay.

## How does the open note look in the list?
- It is the chosen row, `.is-chosen`: the background is `color-mix(in srgb, var(--pg-accent) var(--pg-tint-select), transparent)` (7% light, 10% dark) with an accent edge, `inset 2px 0 0 var(--pg-accent)` (mirrored in RTL). There is no filled box or border; the old `.active` rule is gone.

## How do the sections and folders fold?
- My notes and Shared with me each stand under a group label: `PGWsNoteList.section()` in `assets/js/workspace_note_list.js` draws an `h2` holding a `button.pg-group-label.ws-nb-fold` (chevron, name, `.pg-group-label-count`) over `.ws-nb-section-body`.
- A click or Enter folds the section (`aria-expanded`, `aria-controls`; the chevron turns -90deg). Folded sections are kept in `localStorage` `pg_ws_notes_folded`; reads and writes sit in try/catch, so without storage every section opens. A search shows every section open.
- New group sits in the My notes head beside the label. The count is every note under the section: loose ones plus each folder's.
- Folders (`workspace_note_groups.js`, `workspace_note_group_shares.js`) use the same `pg-group-label ws-nb-fold` button and count, and keep their own stores.

## How is the list ordered?
- The bar's ⋯ starts with Sort by: Last changed (the server's order), Name, Created (`PGWsNoteList.menuItems()`). The pick is kept in `localStorage` `pg_ws_notes_order`; the ⋯ is always shown since it holds the order.
- `order()` sorts what the list has loaded: pinned notes first, then the newest change, the name (`localeCompare` in the panel's locale, numbers read as numbers) or the newest note; the higher id breaks a tie. The server's order stays the paging's.
- It is applied to own notes in no folder and to single shared notes. Folders keep their hand-set order (pinned first, then `group_sort`). Tests: `tests/workspace_note_list_test.php`.

## What does the pane show before a note is picked?
- `placeholder()`: the icon, "pick a note" text and a New note button that calls `startNew()`, the same as the toolbar's.
