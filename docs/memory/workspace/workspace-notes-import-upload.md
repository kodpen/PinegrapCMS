---
name: workspace-notes-import-upload
description: The notes import's upload: picked list and checks, XHR progress, cancel, reading step, footer gate, leave guard, request_max cap, per-file sentences, session let go before staging
---

## What does the person see from picking to the preview?
- Picking or dropping only lists the files (`drawPick()` in `openImport()`, workspace.js), as the file manager does: name, size, "{var:1} file(s) selected, {var:2}", rows that will not go dimmed with "not allowed here" or their size in red. "Upload" sends, "Clear" empties.
- Sending: "Uploading… 42%", the shared `progressBar()`, "1.4 MB of 6.0 MB", "Cancel upload". Last byte sent: "Reading the files…" with a striped, moving bar, then the preview. `.ws-progress` sets only `background-color`: the shorthand hid the stripes (a still, full bar).
- A refused upload returns to the list with its message (`role="alert"`). Keys: `notes_import_*` (`ws_notes_js_strings()`), `uploading`, `percent`.

## Why XMLHttpRequest for the upload step only?
- fetch() reports no upload progress. The JSON steps keep `send()`; a network failure says "The request failed. Please try again.", not "Failed to fetch".
- An HTML answer (nginx 413, IIS 404.13) says "The upload is larger than this server accepts in one request."; other HTML (504) the generic one.
- Late upload events after a cancel or the answer are ignored (`phase === 'send'`).

## What does the browser check, and from which list?
- The kinds: `CFG.notes_import.types` = `ws_note_import_extensions()`, the stage's list; no copy in JS.
- A folder, another kind or a file over the cap is left out, the rest sent. Dropped folders are found by `webkitGetAsEntry()` (a folder "Vault.md" passes by name).
- Over the files that go, in the stage's order: none left, more than `limits.files`, over `limits.max`, a zip with others, .enex with others. One file is held to the cap by its size (upload_max_filesize counts the content); several add 1 KB of part headers each (request_max keeps only 64 KB).

## Why can a second tap not stop the upload?
- Cancel upload (focused), then Close, then Import take Upload's place: a double tap or second Enter stopped the upload or imported unseen. Footer clicks are dropped for 800 ms after Upload (capture listener), repeated Enter/Space keydowns always.

## Why is the cap min(100 MB, file_max, request_max)?
- Loose files go in one request: before, a total between upload_max_filesize and post_max_size passed the screen and failed as a 413. No batches: a stage is all-or-nothing (the exception to platform/upload-limits-and-empty-post.md).

## What do Cancel, X, Escape and leaving do?
- Escape never closes it. Sending: Cancel aborts back to the list; X aborts and closes. Reading: Cancel or X closes, the stage dropped when the answer comes. Writing: no closing.
- A `beforeunload` guard stands only while sending, reading or writing (a standing one blocks the back-forward cache).

## What does the server say about a file it did not get whole?
- `ws_note_import_upload_error($name, $error, $file_max)`: add_file.php's sentences naming the file. No Site Log line, and PARTIAL (a cancel) or a size code is not logged; codes 6-8 and a non-upload temp file go to `ws_note_import_log_failure()` (a test guards the list). UPLOAD_ERR_NO_FILE (hand-made requests) reads as an empty upload, unlogged.

## Why is the session closed before staging?
- Staging .enex or a zip can pass 30 s; the session lock would hold the person's other requests. The endpoint makes the token, keeps it in the session, `session_write_close()`, raises the time limit (guarded), then stages with that token. A failed stage leaves a name with no folder: later steps say "The upload has expired. Start again."
- Two uploads at once in one session: the newer removes the older's folder. The older fails at a stage step ("The file could not be saved."; the error log names it: folder, move or stage.json) or, removed before its mkdir, stages, but its later steps say it expired and the stage waits for the 24 h sweep.
