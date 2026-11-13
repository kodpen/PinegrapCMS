---
name: agent-page-card
description: The card of an agent's live page (#8851): Open, Full screen, View source and versions, Download, "Edited by" and Undo; runs only when opened, stops when the channel is left
---

## What does the card show?
- A message whose file is a live page (`ws_page_card()`, `includes/workspace/page_card.php`) carries `file.page` {version, by, edited_by, can_undo, versions, frame, sandbox}; `workspace.js` `filePreview()` draws `PGWsPageCard.card()` (`assets/js/workspace_page_card.js`, in `workspace.min.js`) instead of the file line.
- The card: the page's name, "Live page by @handle. It is the agent's work, not the panel's." (the first version's agent), Open, Full screen, View source, Download (the file's own address with `download`; `get_file.php` serves HTML without script).
- A guest's room page draws its own file line (a download link), with no Open.

## When does a page run?
- Only on Open: the frame (`PATH . SOFTWARE_DIRECTORY . '/live_page.php?message=<id>'`; until #8960 the folder was missing and a site framed its own 404 page, sandbox from `pg_guarded_frame_panel_sandbox('script')`, `allow-scripts` only) is made then. Close removes it; `app.open()` calls `PGWsPageCard.stopAll()`, so leaving the channel stops every page. A message drawn again while open (an edit, Undo) opens again with the newest version.
- The agent's line sits above the frame, outside it, in the card and in Full screen (the box with the line goes full screen, `requestFullscreen()`); nothing in the page can cover it.
- The panel posts the frame's size to the shell on load and resize; it takes a height only when `event.source` is a frame the card made, bounded to 120-640 px in the card.

## View source and versions?
- Panel action `ws_page_source` {message_id, version (0 newest)}: any reader of the channel; answers the version's HTML as the agent wrote it (without the site's libraries) and the versions (`ws_page_versions_read()`, created_at as Unix time). The drawer shows it as text with a version picker and Copy.

## How does Undo work?
- "Edited by @handle" shows while the newest version is an agent's (source `agent`) after the first (`ws_page_card_state()`, tested in `tests/workspace_agent_page_card_test.php`). Undo is offered to a member who may write in the channel (`$can_post`), unlike a canvas, whose Undo is its author's: a page's message is always an application's.
- Panel action `ws_page_undo` {message_id, version} (`ws_page_undo()`): 409 unless the newest is still that edit; the version before's bytes and document (maker, libraries) become a new version with source `restore` through `ws_page_write()`, so the edit stays among the versions. A message in an earlier version of the conversation is refused, as for `ws_canvas_undo`.
