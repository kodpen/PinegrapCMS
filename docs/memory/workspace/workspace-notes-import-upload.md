---
name: workspace-notes-import-upload
description: The notes import's upload: picked list and its checks, XMLHttpRequest progress, cancel, reading step, leave guard, request_max cap, per-file sentences, session let go before staging
---

## What does the person see from picking to the preview?
- Picking or dropping only lists the files (`drawPick()` in `openImport()`, workspace.js), as the file manager's upload window: name, size, "{var:1} dosya seçildi, {var:2}", rows that will not go dimmed with "burada izin verilmiyor" or their size in red. "Yükle" sends, "Temizle" empties. The drop area says the file count limit.
- Sending: "Yükleniyor… %42", the shared `progressBar()`, "1,4 MB/6,0 MB", "Yüklemeyi iptal et". Last byte sent: "Dosyalar okunuyor…" with a striped moving bar, then the preview.
- A failed upload comes back to the same list with its message. Keys: `notes_import_*` in `ws_notes_js_strings()`, plus `uploading` and `percent`.

## Why XMLHttpRequest for the upload step only?
- fetch() reports no upload progress. The JSON steps (`import`, `finish`, `discard`) keep `send()`; a network failure there says "İstek başarısız oldu…", not the browser's "Failed to fetch".
- An HTML answer (nginx 413, IIS 404.13) says "Yükleme, bu sunucunun tek istekte kabul ettiğinden büyük."; other HTML (504, 524) the generic sentence.
- Late upload events after a cancel or the answer are ignored (`phase === 'send'`).

## What does the browser check, and from which list?
- The kinds come from `CFG.notes_import.types` = `ws_note_import_extensions()`, the list `ws_note_import_stage()` reads: no copy in JS. The kind is the last extension, lower case, as the stage reads it.
- A folder, a file of another kind or one over the cap is left out and the rest is sent (the file manager's rule). A dropped folder is told apart by `webkitGetAsEntry()` and never sent: "Vault.md" passes by name, and a folder in the FormData fails the request.
- Over the files that go, in the stage's order: none left, more than `limits.files`, total plus 1 KB a file over `limits.max` (each part's headers; request_max keeps only 64 KB), a zip with others, .enex with others. The first holds the button back.

## Why is the cap min(100 MB, file_max, request_max)?
- Loose files travel in one request, so post_max_size less the envelope bounds the total. Before, a total between upload_max_filesize and post_max_size passed the screen and came back as a 413 (`ws_note_import_upload_max($limits)`).

## Why one request and no batches?
- A stage is all-or-nothing; appending to one would need a new step. The exception to platform/upload-limits-and-empty-post.md's batches.

## What do Cancel, X, Escape and leaving do?
- Escape never closes the dialog (`data-bs-keyboard="false"`).
- Sending: Cancel aborts, the list comes back; X aborts and closes. Nothing is staged.
- Reading: Cancel or X closes; the request finishes and its stage is discarded.
- Writing: the dialog cannot be closed.
- A `beforeunload` guard stands only while sending, reading or writing (a standing one keeps the page out of the back-forward cache).

## What does the server say about a file it did not get whole?
- `ws_note_import_upload_error($name, $error, $file_max)`: add_file.php's sentences, naming the file (too large, arrived in part, could not accept).
- No Site Log line: a cancel on Apache reaches the script as UPLOAD_ERR_PARTIAL. Codes 6-8 and a temporary file that is not an upload go to `ws_note_import_log_failure()`: "Notes import: receiving an uploaded file failed: PHP upload error 7".

## Why is the session closed before staging?
- Staging lays out Evernote exports and reads zips, past PHP's 30 s. The endpoint makes the stage token, keeps it in the session, calls `session_write_close()`, raises the time limit (guarded: hosts disable `set_time_limit`), then `ws_note_import_stage($viewer, $files, $token)`: a file session's lock would hold the person's other requests meanwhile. A failed stage leaves a name with no folder; later steps answer "expired".
