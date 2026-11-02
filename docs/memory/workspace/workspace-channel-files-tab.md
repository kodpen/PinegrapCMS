---
name: workspace-channel-files-tab
description: A channel's Files tab (drawFiles() in workspace.js): the groups it draws (images, videos, voice messages and audio, documents) and the one-line file row
---

## Which groups does the Files tab draw?
- `drawFiles()` reads `ws_channel_files` and draws, each with its count: Images (a gallery), Videos (a reel), Voice messages and audio (`file.kind === 'audio'`, `t('voice_and_audio')`), Documents (everything else). The two lists share the local `fileRows(title, rows)`.
- Sounds used to fall into Documents, so voice messages (`.weba`) sat between spreadsheets (#5192).

## How is a file row laid out?
- Extension badge and name (`.ws-file-link`, at most 55 %), then the sender and time in `small.ws-file-meta`: one line, cut with an ellipsis, full text in its title. Then Preview or Play and Show in conversation.
- With the channel info open the meta used to wrap word by word, so rows had different heights (#5192).

## Tests
- `tests/workspace_files_tab_test.php`.
