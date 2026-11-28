---
name: document-head-part
description: The shared document head (assets/js/pg_doc_head.js, pgDocHead.build(), DH1): its options in order, setters, the more menu's groups, the phone shape, its words, styles and example
module: doc_head
---

## What is the document head?
- One row at the top of a note, a Data table, the Artifact Canvas, a file window and the view windows (`plans/document-head.md`, look in `plans/document-head-screens.md`). DH1 built the part; no window uses it yet (DH2 to DH6).
- `assets/js/pg_doc_head.js`, served as written (no bundle): `window.pgDocHead`. It and `includes/fn/doc_head.php` are Pinegrap Artifacts files under `license-editors.txt` (proprietary header), not MIT. A page prints `pg_doc_head_includes()` (`includes/fn/doc_head.php`, once per page): the words as `window.pgDocHeadWords`, then the script.
- No Bootstrap script: the more menu is the head's own (`.pg-doc-head-menu`, closed by Escape, an outside press or a row). Styles: block "Look A: document head" at the end of `backend.src.css`, built from `pg-icon-button`, `pg-button-sm`, `pg-icon-tile`, `pg-initials`, `pg-seg`. A page without the panel stylesheet (the canvas on a site page) needs its own answer in DH4.

## What does pgDocHead.build(options) take?
- Each part is drawn only when given, left to right: `back`, `kind` {icon, tone, label} (the tile), `name` with `rename(name)` (renamable; a rejected promise or `false` puts the old name back), `meta`, `state` + `stateInfo` {time, ago, reason}, `undo` {onUndo, onRedo, canUndo, canRedo}, `slot` (an element), `faces`, `share`, `download`, `main`, `menu`, `grow` {icon, label}, `close`.
- `main`: {label, icon, style `fill` or `quiet` (a view window's edit door), `gate` (45 % strength and inert until a change), onClick}, or {node} for the canvas's split button as it is.
- It returns {root, setName, setMeta, setState, setUndo, setMain ({dirty, label, disabled}), isDirty, closeMenu, destroy}. Close calls `close.onClick(isDirty)`; the window asks in its confirm dialog.

## What are the name and the state?
- The name is a button when renamable: a click puts a field with the accent ring in its place; Enter or leaving saves, Esc restores. `pgDocHead.cleanName()` trims and joins spaces; empty keeps the old name. Cut with an ellipsis, whole in the tooltip.
- States `saving`, `saved`, `unsaved`, `failed` with the note's words "Saving…", "Saved", "Unsaved changes", "Could not save"; `failed` shows the reason as tooltip, `saved` the time; with `ago` it adds "· 2 min ago" ("Just now" under a minute, the clock time after an hour) and redraws every 30 s. No state, nothing drawn.

## What does the more menu hold?
- `menu` keys in this order, a group with no row left out, a line between: `open`, `fullscreen`; `copyLink`, `send`; `download`, `export`, `print`; `versions`; `own` (a list of {icon, label, onClick}); `help`, `delete` (rose). A key takes a function or {onClick, label, icon, count}. `pgDocHead.menuGroups()` is the pure order.
- A menu of one row is drawn as that row's icon button (`pgDocHead.singleRow()`); ⋯ is then drawn only for the phone.

## How does it look on a phone?
- Under 640 px (window media query, `pgDocHead.PHONE_QUERY`): 48 px; Back (Close becomes Back when no `back` is given), the name, the state's dot (words in the tooltip), the main button, ⋯. Undo and Redo are the menu's first rows, then the slot (moved into the menu while it is open), Share and Download.

## Where is it shown and tested?
- Design examples, pattern "Document head" (`pg_look_examples_doc_head_pattern()`, after "Copy button", also in the phone pane): six hosts `[data-pg-doc-head]` holding JSON options, built by `pgDocHead.mountAll()`.
- `tests/doc_head_test.php`: the pure parts in Node, every word the script asks for printed by `pg_doc_head_words()`, the pattern.
- The memory file is `document-head-part` because the plan holds the name `document-head`; DH7 may rename it.
