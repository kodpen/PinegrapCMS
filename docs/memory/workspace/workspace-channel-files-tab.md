---
name: workspace-channel-files-tab
description: A channel's Files tab (drawFiles() in workspace.js): the groups it draws, the gallery drawn 60 pictures a page, the files count on the tab, and the one-line file row
---

## Which groups does the Files tab draw?
- `drawFiles()` reads `ws_channel_files` and draws, each with its count: Images (a gallery), Videos (a reel), Voice messages and audio (`file.kind === 'audio'`, `t('voice_and_audio')`), Documents (everything else). The two lists share the local `fileRows(title, rows)`.
- Sounds used to fall into Documents, so voice messages (`.weba`) sat between spreadsheets (#5192).

## How is the gallery drawn?
- `FILE_PAGE` (60) tiles at a time: `drawPage()` adds the next slice and a quiet `pg-button` Show {n} more pictures (`files_more_pictures`) under the grid while pictures are left (TD1).
- The viewer (`viewImages(gallery, index)`) still steps through every picture, drawn or not. A tile keeps its 4 / 3 box before its picture arrives; the `img` is `loading="lazy"` and `decoding="async"`.
- Each tile still loads the original file and `ws_channel_file_rows()` reads the newest 300 only: TD2 of `plans/channel-decisions-files-tabs.md` takes both.
- Owner (2026-11-19): 298 pictures in the agent channel made the tab stutter.

## Does the tab show how many files there are?
- Yes: `ws_channel_tab_counts()` (channels.php) counts `files` (messages with `file_id > 0`, not deleted, no limit) and `drawTabMarks()` puts it beside Files with the title `tab_files_count` (TD1).
- The API's `WorkspaceSyncTabCounts` (`ws_api_sync_tab_counts_present()`) names its fields and does not carry `files`.

## How is a file row laid out?
- Extension badge and name (`.ws-file-link`, at most 55 %), then the sender and time in `small.ws-file-meta`: one line, cut with an ellipsis, full text in its title. Then Preview or Play and Show in conversation.
- With the channel info open the meta used to wrap word by word, so rows had different heights (#5192).

## Tests
- `tests/workspace_files_tab_test.php`.
