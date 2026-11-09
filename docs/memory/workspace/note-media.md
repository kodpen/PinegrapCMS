---
name: note-media
description: A note line linking only to a file of this site is drawn as the file in the read view (ws_render_body, note_media.php) and as a media card in the writing box (workspace_editor.js)
---

## Which lines are drawn as a file?
- In a note only (`ws_render_body()` with `$note = true`), a line that is nothing but `[label](url)` where the url is `http(s)://` + `HOSTNAME_SETTING . PATH` + one file name (no folder, query or fragment, has an extension, not hidden, no quote: the link rule stops at `'` and `"`) — what attaching a file writes.
- The name must be a row of `files` (`ws_note_media_files()`, one query per text, cached per request); otherwise, and for any other host, the line stays a link.
- Messages, export Markdown, import and the API keep the link: no format change, old notes get previews as they are.

## How is each kind drawn?
- `ws_file_kind()` decides. Picture: `<img loading="lazy">` inside a link, up to 20rem high. Video and sound: native players, `preload="metadata"`. PDF and any other file: a card (icon, name, size).
- Picture and card links carry `data-ws-media` (kind) and `data-ws-media-name`. A delegated click in `workspace.js` opens a picture in `viewImages()` with the other pictures of the same body, a PDF in `previewFile()`; other files follow the link. Inside a `contenteditable` (the writing box, M2) the click is left alone.
- Styles: `.ws-media*` in `backend.src.css`; the print page (`workspace_notes_export.php?print=1`) has its own short rules.

## How does the writing box show it (M2, PR after #8150)?
- `mediaLine()` in `workspace_editor.js` is the twin of `ws_note_media_line()` (and `fileKind()` of `ws_file_kind()`); the base is `CFG.urls.files` (`URL_SCHEME . HOSTNAME_SETTING . PATH`, `screen.php`). It cannot see `files`: a missing file's card shows the server's link.
- Only a box with `headings` and `media` options (the note box, via `richField()`) reads such a line: `parseBlocks(markup, headings, this.mediaOf)` gives a `media` block, drawn as a `.ws-rich-block-media` card (contentEditable false, `data-markup` = the line, so the text is kept as written).
- The card shows the server's drawing through `drawPreviews()` (`ws_calc`, the M1 HTML); a click calls `options.media.open()`: picture → `viewImages()`, PDF/video → `previewFile()`, else a new tab. A recording (`PGWsVoice.isVoice(label)`) gets `options.media.player()` → `PGWsVoice.player()` in `[data-ws-media-player]` instead (`drawMedia()`; clicks there are left to the player; an undo copy is rebuilt).
- `attach()`, `drawCanvas()` and recordings call `insertFile()`: a media card on a line of its own (else `insertLink()`).
- "Recordings in this note" (`voiceLinks()`) leaves out a recording on a line of its own: its player is in the card / the read view's sound player.

## How is a canvas in a note edited again (M3)?
- `options.media.action(media)` (a Promise of `{icon, label, run}` or null) adds one button to a media card (`[data-ws-media-action]`). The note box offers Edit the picture on a picture whose file has a canvas document: `noteCanvasDoc()` asks `workspace_file_save.php?note=<id>&file=<stored name>` `{action:'canvas'}` once per file.
- The endpoint reads only a file of the note's own folder (`files.folder` = `ws_note_folder_id($note, 0, false)`), else "The file could not be found."; a save writes a new file of the note (`ws_note_file_store_bytes()`, `ws_canvas_keep()`), answers `mode: 'replace'`, and `replaceFile()` links it in the old card's place. No schema: the old file stays in the folder, linked from the note's earlier version.

## How is it tested?
- `tests/workspace_note_media_test.php`: line matching (hosts, folders, queries, hidden names, encoded slash, words around), each kind's markup, escaping, only existing files. `ws_note_media_files($names, $seed)` takes a seed so tests skip the database. `tests/workspace_editor_media_test.php`: the JS twin against PHP, `parseBlocks()` with and without the reader, the notes screen wiring.
