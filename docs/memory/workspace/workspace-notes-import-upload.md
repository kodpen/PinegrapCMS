---
name: workspace-notes-import-upload
description: The notes import's upload: picked list and its checks, XMLHttpRequest progress, cancel, reading step, footer gate, leave guard, request_max cap, per-file sentences, session let go before staging
---

## What does the person see from picking to the preview?
- Picking or dropping only lists the files (`drawPick()` in `openImport()`, workspace.js), as the file manager's upload window: name, size, "{var:1} dosya seçildi, {var:2}", rows that will not go dimmed with "burada izin verilmiyor" or their size in red. "Yükle" sends, "Temizle" empties.
- Sending: "Yükleniyor… %42", the shared `progressBar()`, "1,4 MB/6,0 MB", "Yüklemeyi iptal et". Last byte sent: "Dosyalar okunuyor…" with a moving bar, then the preview.
- A refused upload comes back to the same list with its message (`role="alert"`). Keys: `notes_import_*` in `ws_notes_js_strings()`, plus `uploading` and `percent`.

## Why XMLHttpRequest for the upload step only?
- fetch() reports no upload progress. The JSON steps keep `send()`; a network failure says "İstek başarısız oldu…", not "Failed to fetch".
- An HTML answer (nginx 413, IIS 404.13) says "Yükleme, bu sunucunun tek istekte kabul ettiğinden büyük."; other HTML (504) the generic sentence.
- Late upload events after a cancel or the answer are ignored (`phase === 'send'`).

## What does the browser check, and from which list?
- The kinds: `CFG.notes_import.types` = `ws_note_import_extensions()`, the stage's list; no copy in JS.
- A folder, another kind or a file over the cap is left out, the rest sent. A dropped folder is told apart by `webkitGetAsEntry()`: "Vault.md" passes by name and fails the request.
- Over the files that go, in the stage's order: none left, more than `limits.files`, over `limits.max`, a zip with others, .enex with others. One file is held to the cap by its size (upload_max_filesize counts the content); several add 1 KB a file of part headers, as request_max keeps only 64 KB.

## Why can a second tap not stop the upload?
- Cancel upload (focused), Close and the preview's Import take Upload's place, so a double tap or a second Enter stopped the upload or imported unseen. For 800 ms after Upload, footer clicks are ignored (capture listener); a repeated Enter/Space keydown always is.

## Why is the cap min(100 MB, file_max, request_max)?
- Loose files travel in one request. Before, a total between upload_max_filesize and post_max_size passed the screen and came back as a 413. One request, no batches: a stage is all-or-nothing (the exception to platform/upload-limits-and-empty-post.md).

## What do Cancel, X, Escape and leaving do?
- Escape never closes the dialog. Sending: Cancel aborts, the list comes back; X aborts and closes. Reading: Cancel or X closes; the stage is discarded when the answer comes. Writing: no closing.
- A `beforeunload` guard stands only while sending, reading or writing (a standing one blocks the back-forward cache).

## What does the server say about a file it did not get whole?
- `ws_note_import_upload_error($name, $error, $file_max)`: add_file.php's sentences naming the file. No Site Log line: a cancel reaches PHP as UPLOAD_ERR_PARTIAL. Codes 6-8 and a non-upload temp file go to `ws_note_import_log_failure()`. UPLOAD_ERR_NO_FILE (hand-made requests) is answered as an empty upload, not logged.

## Why is the session closed before staging?
- Staging lays out .enex and reads zips, past 30 s, and the session lock would hold the person's other requests. The endpoint makes the token, keeps it in the session, `session_write_close()`, raises the time limit (guarded), then `ws_note_import_stage($viewer, $files, $token)`. A failed stage leaves a name with no folder: later steps say "expired".
- Two uploads at once in one session: the newer removes the older's folder, so the older fails ("Dosya kaydedilemedi.", a mkdir line in the error log); if it got through, its later steps and discard say "expired" and its stage waits for the 24 h sweep.
