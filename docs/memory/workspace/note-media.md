---
name: note-media
description: A note line holding only a link to a file of this site is drawn as the file (picture, video/sound player, file card) by ws_render_body(…, $note = true); includes/workspace/note_media.php
---

## Which lines are drawn as a file?
- In a note only (`ws_render_body()` with `$note = true`), a line that is nothing but `[label](url)` where the url is `http(s)://` + `HOSTNAME_SETTING . PATH` + one file name (no folder, query or fragment, has an extension, not hidden) — what attaching a file writes.
- The name must be a row of `files` (`ws_note_media_files()`, one query per text, cached per request); otherwise, and for any other host, the line stays a link.
- Messages, export Markdown, import and the API keep the link: no format change, old notes get previews as they are.

## How is each kind drawn?
- `ws_file_kind()` decides. Picture: `<img loading="lazy">` inside a link, up to 20rem high. Video and sound: native players, `preload="metadata"`. PDF and any other file: a card (icon, name, size).
- Picture and card links carry `data-ws-media` (kind) and `data-ws-media-name`. A delegated click in `workspace.js` opens a picture in `viewImages()` with the other pictures of the same body, a PDF in `previewFile()`; other files follow the link. Inside a `contenteditable` (the writing box, M2) the click is left alone.
- Styles: `.ws-media*` in `backend.src.css`; the print page (`workspace_notes_export.php?print=1`) has its own short rules.

## How is it tested?
- `tests/workspace_note_media_test.php`: line matching (hosts, folders, queries, hidden names, encoded slash, words around), each kind's markup, escaping, only existing files. `ws_note_media_files($names, $seed)` takes a seed so tests skip the database.
