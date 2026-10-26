---
name: get-file-byte-ranges
description: Range/206 in get_file.php and the external API from one copy (includes/http_range.php): what is answered 206, 416 or 200, If-Range by Last-Modified, which send paths take no range
---

## Why does get_file.php answer Range?
- Safari plays an `<audio>`/`<video>` only when its `Range` request is answered 206; workspace voice messages (`workspace/workspace-voice-recording.md`) stayed silent there. Every player also winds forward by asking for a part. Added in 2026.11.0.

## Where is the range code?
- `includes/http_range.php`, gate `PG_FUNCTIONS_DIR` or `PG_GET_FILE`, loaded by `functions.php` and by `get_file.php` (which never loads `functions.php`): `pg_http_byte_range()` (parse), `pg_http_range_answer()` (If-Range, then parse), `pg_http_content_range()`, `pg_http_send_file_part()` (streams in 1 MB pieces). Pure parts tested in `tests/http_byte_range_test.php`.
- The external API's `api_send_file()` uses the same functions (`api/api-file-endpoints.md`); there is no second copy.

## What does get_file.php answer?
- Only on its last path, PHP sending the file after every read rule has run: one `bytes=` range → 206 with `Content-Range` and that part; a range starting past the end (or an empty file) → 416, `Content-Range: bytes */size`, no body; no header, several ranges, another unit or nonsense → the whole file, 200. `Accept-Ranges: bytes` on all three.
- `If-Range` must equal the `Last-Modified` it sends (there is no ETag); anything else gets the whole file, never a 416.
- No range on the CSS/JS path (placeholders are replaced, the length changes) or on 304. With `XSENDFILE` the web server answers ranges itself.
- The read rules are untouched: a range is only a part of a file the request may already read; no `ws_` file reaches a site member.
