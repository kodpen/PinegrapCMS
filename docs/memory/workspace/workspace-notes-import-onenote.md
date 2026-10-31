---
name: workspace-notes-import-onenote
description: OneNote pages as notes (notes_onenote.php, #3291, decided in #3180): purpose notes per person, Graph Notes.Read, HTML import, pictures fetched on the server
---

## How does a OneNote page become a note?
- Through Microsoft Graph with the person's own connection: purpose `notes` (`includes/connect/purposes/notes.php`, `owner => person`, `Notes.Read` only: an import never writes). No schema (#3180).
- The import window shows "From OneNote" when the Microsoft app is ready and tokens can be sealed (`CFG.notes_onenote` = `ws_note_onenote_offered()`). Steps of `workspace_notes_import.php`: `onenote_state` (not connected → one line and a Connect button posting to `connect.php`, return `workspace_notes.php`), `onenote_sections` (`/me/onenote/sections` with `parentNotebook`, grouped by notebook), `onenote_pages` (one section, newest first, at most `WS_NOTE_ONENOTE_LIST_MAX`), `onenote_stage` (up to 25 pages, `WS_NOTE_ONENOTE_PICK_MAX`).
- Staging (`ws_note_onenote_stage()`): per page its metadata (title, created, last modified) and `/content` (HTML, at most `WS_NOTE_HTML_MAX`). The page's own pictures (`<img>` whose `data-fullres-src` or `src` is a Graph `/onenote/resources/<id>/$value`) are downloaded with the token, each within `ws_upload_max_bytes()`, all within `WS_NOTE_IMPORT_FILES_BYTES`, put inside the HTML as `data:` pictures, and `ws_note_html_read()` does the rest (signature check, dedupe, scripts and hidden parts dropped). A picture not downloaded is removed: no Graph address stays in a note.
- Each note is `files/<n>.txt` with front matter title, dates and `id` (the page id); its pictures `attachments/<k>/<name>` rows with the fifth value 1 (the body's `attachments/1/` is renumbered per note). Provider `onenote` in `ws_note_sources`: preview, same/update/new, limits, batches and finish are the file import's.

## How are calls made?
- `ws_note_onenote_get()` → `pg_connect_api()` (401 renews once; a 401 after that says connect again). Only `https://graph.microsoft.com/…` is called, `@odata.nextLink` included (`ws_note_onenote_graph_url()`).
- Throttling: a 429/503 reads Retry-After (seconds or a date, else 30 s); up to 5 s (`WS_NOTE_ONENOTE_WAIT_MAX`) it waits and repeats once, longer is told to the person. Staging stops at the first throttled or signed-out page; earlier pages stay staged, the rest are warnings.

## Owner-side setup and what was not verified?
- The Microsoft app (Settings › Connections) needs the delegated Graph permission `Notes.Read` in Entra; each person connects from the import window.
- Tests: tests/notes_onenote_import_test.php (states, ids, paging, notebooks/pages, page → note → file → `ws_note_import_parse()`, throttled and refused). The live run against Graph needs a Microsoft account on dev.
