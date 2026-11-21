---
name: channel-note-card
description: A note's card in a channel and its windows: look (plain card, full width), Show all, the foot, the card menu, Write a note, Edit, the Versions drawer (workspace_channel_notes.js)
---

## Where is the card drawn?
- `assets/js/workspace_channel_notes.js` (`window.PGWsChannelNotes`, in the workspace bundle before `workspace.js`). `noteCardNode()` in workspace.js only calls `card()` with `noteHooks()`: the screen's `setHtml`, `chipMenus`, `richField`, the context menu, `openNoteView`, `openNoteEdit` and `sync`.
- The card map `ws_note_cards_map()` also gives the share's `channel_id`, `channel_edit` (its `can_edit`), `via_group` and `versions` (kept versions, one grouped COUNT). The API's `note_card` picks its own fields and does not show these.

## How does the card look?
- Look A's plain card (`pg-card ws-cn-card`): thin line, surface, 16 px corners, no strip; as wide as the message column, on a phone too (no max-width). Its own `.ws-cn-*` rules in `backend.src.css` only place the parts.
- Head: the note icon and the title, the ⋯ menu at its end; no "Note" label (the `notes_card` string went with it).
- Inside the note: a heading of level 1 is 15 px (`--pg-text-status-title`), levels 2 and 3 are the text's 14 px in bold; paragraphs and lists close together (an empty line is a 6 px `.ws-cn-gap`, `tightGaps()`). A first line opening "Written up to" (`writtenUpTo()`, `metaLine()`) is drawn as `pg-text-meta`.
- The group card (`workspace_note_group_shares.js`) still draws the old `.ws-nb-msg-card` with its strip and label.
- Redrawn on the owner's request of 2026-11-21 (PR #10986): the coloured strip and the narrow card went.

## How does the fold work?
- The note is drawn whole; taller than 18rem it is folded (`.ws-cn-fold.is-folded`, a fade at the foot) with "Show all" / "Show less": a small quiet button (`pg-button-quiet pg-button-sm`) centred under the note in its row `.ws-cn-more`, hidden while the note fits. The inner scroll of the old card is gone.
- An opened card stays open while the screen is: kept by note and channel (`foldKey()`), so the redraw a save's touch causes keeps it. `FOLD_REM` in the script and the stylesheet's 18rem go together.

## What do the foot and the menu hold?
- Foot: one small muted line (`ws-cn-foot pg-text-meta`): "Belongs to <owner> · Changed by <name> · <when>" (`footLine()` joins `footWords()`; `changedWords()` reads the server's `changed_by`), cut with an ellipsis where too long, then a small ghost "Versions" button with its `pg-count` at the end when the note keeps any (icon and count only under 576 px).
- Menu (`pg-icon-button` ⋯, `menuItems()`): Read, Edit (owner or edit access), Versions, Open on the notes screen; for the owner on the channel's own share (not a note group's) the switch "People who may post here can edit" (`ws_note_channel_edit`).

## What do the windows do?
- Write a note: in the composer's attach (paperclip) menu for who may post, not in a thread or an archive era; title, the note's writing box (marks, lists, headings, table, code), the switch off; Send calls `ws_note_channel_write`, then the channel syncs.
- Edit: a dialog with the note read by `ws_note_get`; Save sends `revision` and `base`. A stale save shows the server's "<name> changed this note meanwhile." with "Load their version" and "Keep mine", as the notes screen does.
- Versions: a drawer (480 px from the end, a bottom sheet under 576 px) reading `ws_note_get` with `versions: 1` and `version: id`, the notes screen's own list, no second store. Show draws one; "Put this version back" (who may edit) saves its body with the card's revision; a stale one is refused with the same words.

## What comes next?
- The foot and the drawer's rows name the person; the server's `changed_by` words (an agent's handle) replace them in KD3b (`workspace-note-changed-by-app.md`).
- Tests: `tests/workspace_channel_notes_js_test.php` (fold state, foot words and line, the Written up to line, the card's look, menu items, hooks). Introduced by PR #10863; redrawn by PR #10986.
