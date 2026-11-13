---
name: explorer-file-window-encoding
description: File Manager Edit File window on a text file that is not UTF-8 (Windows-1254): opens with no text and a note, saves no text; panel answers never go out as an empty JSON body (pg_panel_answer_json())
---

## What does the Edit File window do with text that is not UTF-8?
- `pg_explorer_handle_file_get()` (`includes/explorer/handle_file_window.php`) reads the text and checks it with `pg_explorer_text_is_utf8()` (`mb_check_encoding`, a byte order mark is fine). Text that fails is not sent: `content` stays `null` and `content_not_utf8` is `true`.
- The window shows `L.file_not_utf8` where the editor would be ("The text of this file is not saved as UTF-8, so it is not shown here and saving leaves it as it is. Its name, folder and description can still be changed.") and `saveFileEditor()` sends no `content`, so the bytes stay as they were.
- Not converted for display: the encoding is a guess (Windows-1254, 1252, ISO-8859-9 look alike), and a converted text saved back would change the file's bytes.
- Before (#8988): `json_encode()` refused the bytes, `respond()` printed an empty 200 body and the window never opened, so the file's description and design flag could not be changed either.

## What does a panel answer do with a value json_encode() refuses?
- `respond()` in `api.php` writes `pg_panel_answer_json()` (`includes/fn/core.php`): `encode_json()` as before when it works; otherwise `pg_json_encode_answer()` (broken bytes as U+FFFD, `api/api-response-json-encoding.md`); a value JSON cannot hold at all (INF, NAN, too deep) becomes `status: error` with "The request failed. Please try again.".
- The normal path is byte for byte what it was (slashes still escaped).

Related: docs/memory/api/api-response-json-encoding.md, tests/explorer_file_window_encoding_test.php
