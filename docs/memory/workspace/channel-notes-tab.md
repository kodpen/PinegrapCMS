---
name: channel-notes-tab
description: A channel's Notes tab (PGWsChannelNotes.tab()), the panel action ws_channel_notes, GET /workspace/channels/{id}/notes, the notes count on the tabs, owner_channel_id on the note object
---

## Where is the Notes tab?
- Beside Files in the channel's tabs, once the channel has a note of its own (`tab_counts.notes`, `ws_note_channel_count()` in `ws_channel_tab_counts()`), or while it is the open tab. A first note written while the screen is open adds the tab in `drawTabMarks()`. Its mark counts the notes ("{var:1} notes"). PR #11226.
- Drawn by `tab()` in `assets/js/workspace_channel_notes.js`; `drawCenter()` in workspace.js passes `noteHooks()` with `current()` and `counted()`. Pure parts: `tabShown()`, `rowItems()`, `rowMeta()` (tests `tests/workspace_channel_notes_js_test.php`).

## What does it show?
- A bar (`pg-toolbar ws-pane-toolbar ws-cn-bar`): a search box over the titles ("Search the titles"), "Write a note" at its end for who may post.
- The Files tab's rows (`ws-file-list`, `ws-file-row`): the note icon and title, "Changed by <name> · <time>", a ⋯ menu; the row opens the note (`openNoteView`). Newest change first, 50 a page, "Older notes" for the next.
- Row menu: Open, Versions (the card's drawer), Copy to my notes (`channel-note-owned.md`), Export ("Download as Markdown", `workspace_notes_export.php?note=`), Delete (`can_delete`: the writer and the channel's managers, `ws_note_channel_may_delete()`).
- Empty: "No notes have been written here yet." / "No note has that in its title.", in the channel panes' dashed card.
- Spacing: the pane is a flex column with the Files tab's gap (`--ws-space-3`) between the bar and the list; the bar keeps the same gap between the search and "Write a note". Before PR #12261, the bar, the list and the button touched.

## Why is "Changed by" printed only once?
- The server's `changed_by` already carries its label: `ws_note_changed_by_words()` returns `lang('Changed by {var:1}')` with the name. `rowMeta()` prints it as it comes; wrapping it in a label again showed "Değiştiren: Değiştiren: @name" (and "Changed by Changed by" in English) until PR #12261. No `cn_changed_by` string is sent to the screen any more.

## What reads the list?
- `ws_note_channel_page_rows($channel_id, $options)`: `owner_channel_id` = the channel, title search, `updated_at DESC, id DESC`, the notes list's cursor position in group 0. `ws_note_channel_page()` presents them with the channel's access (`edit` for who may post, else `view`) and `can_delete`.
- Panel action `ws_channel_notes` (channel_id, q, after, after_id) → notes, total, after, after_id; it is in `pg_panel_actions()` (missing there, the tab answered "Invalid action." until PR #11272).
- API: `GET /workspace/channels/{id}/notes` (`workspace.channels.notes.list`, `api_channel_notes.php`): search, cursor, limit; 404 for a channel the owner cannot read. The note object carries `owner_channel_id` (null for a person's note).
- A person's own note shared in the channel is not listed: it stays theirs, with its card.

Related: docs/memory/workspace/channel-note-owned.md, docs/memory/workspace/channel-note-card.md
