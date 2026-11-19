---
name: workspace-channel-files-tab
description: A channel's Files tab (drawFiles() in workspace.js): its groups, the gallery of 320 px copies 60 at a time, the server's pages of 200 files, the count on the tab, the file row
---

## Which groups does the Files tab draw?
- `drawFiles()` reads `ws_channel_files` and draws, each with its count: Images (a gallery), Videos (a reel), Voice messages and audio (`file.kind === 'audio'`, `t('voice_and_audio')`), Documents (everything else). The two lists share the local `fileRows(title, rows)`.
- Sounds used to fall into Documents, so voice messages (`.weba`) sat between spreadsheets (#5192).

## How is the gallery drawn?
- `FILE_PAGE` (60) tiles at a time: `drawPage()` adds the next slice and a quiet `pg-button` Show {n} more pictures (`files_more_pictures`) under the grid while pictures are left (TD1).
- The viewer (`viewImages(gallery, index)`) still steps through every picture, drawn or not. A tile keeps its 4 / 3 box before its picture arrives; the `img` is `loading="lazy"` and `decoding="async"`.
- A tile draws `file.thumb`, the picture's 320 px copy (`ws_file_thumb_url()` in `includes/workspace/files.php`: `?w=320` on JPEG, PNG and WebP; GIF and SVG keep their address); the viewer, Open in new tab and Download keep the original (TD2, #10415).
- `get_file.php` makes that copy for a workspace file after its Workspace checks: only 320 px in the picture's own format, whatever `w`/`f` ask, sent `private`. It is the derivative-cache copy `ws_file_preview()` already kept for the API (`api/api-file-endpoints.md`).
- Owner (2026-11-19): 298 pictures in the agent channel made the tab stutter.

## How are the files read from the server?
- A page at a time: `ws_channel_files` takes `before` and answers `messages`, `more` and `before` (`ws_channel_files_page()`, 200 files a page, `ws_channel_files_page_cut()` reads one row more to know whether older ones are left). The cursor is the last row read, not the last message drawn, so a left-out payload is never read twice.
- The tab keeps every page read and draws itself again with the next one, keeping its scroll. The gallery's Show more draws the next 60 read; when all are drawn and the server has more, it says Show older files (`files_older`) and asks the next page. The same button sits under the last group for the other kinds.
- There is no 300-file limit in the tab any more. `ws_channel_files()` (channel export, the poll's picture picker) and the API's Files list still read the newest 300 (`ws_channel_file_rows()` defaults).

## Does the tab show how many files there are?
- Yes: `ws_channel_tab_counts()` (channels.php) counts `files` (messages with `file_id > 0`, not deleted, no limit) and `drawTabMarks()` puts it beside Files with the title `tab_files_count` (TD1).
- The API's `WorkspaceSyncTabCounts` (`ws_api_sync_tab_counts_present()`) names its fields and does not carry `files`.

## How is a file row laid out?
- Extension badge and name (`.ws-file-link`, at most 55 %), then the sender and time in `small.ws-file-meta`: one line, cut with an ellipsis, full text in its title. Then Preview or Play and Show in conversation.
- With the channel info open the meta used to wrap word by word, so rows had different heights (#5192).

## Tests
- `tests/workspace_files_tab_test.php`.
