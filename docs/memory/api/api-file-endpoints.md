---
name: api-file-endpoints
description: External API routes that send or take a file: api_send_file() and binary routes, Range/If-Range, multipart form_file uploads, $_FILES outside MCP, the Idempotency-Key fingerprint, picture copies
---

## How does a route send a file?
- With `api_send_file($path, $mime, $file_name, $inline)` (`includes/api/response.php`), as its own bytes rather than JSON. The route carries `'binary' => true` (no `returns`; the document writes the 200 as `application/octet-stream`) and `'mcp' => false`, since a tool result cannot carry the bytes. Example: `GET /workspace/messages/{id}/file`.
- `api_send_file()` itself answers the ETag/304, `Range` (206 with `Content-Range`, one range), 416 for a range past the end, `If-Range` (an ETag that no longer matches gets the whole file) and `Accept-Ranges`; the handler adds nothing. It drops every output buffer and streams the file in pieces.

## How does a route take a file?
- As a multipart part: the route declares `'form_file' => 'file'`, and the document then offers a `multipart/form-data` body with that part. Or as `content_base64` with `name` in a JSON body; both in one call is 422. Example: `POST /workspace/channels/{id}/files`.
- Read `$_FILES` only while `api_send_hook() === null`: a route the MCP endpoint runs in-process has no form of its own (`ws_api_upload_input()`, `includes/workspace/api_resources.php`).
- The `Idempotency-Key` fingerprint includes the part's name, size and SHA-256 (`api_idempotency_input()`), because a form part is not among the parsed fields: without it two different files sent with the same name and text would replay the first answer. It reads the part the route names in `form_file`; a route that takes a file in any other way adds it to the fingerprint there.

## Smaller copies of pictures?
- `ws_file_preview()` (`includes/workspace/files.php`) draws them with the image engine (`pg_process_image_file()`, `'write' => false`) at fixed edges (160, 320, 640, 1280, 1920), keeps them in `data/temp/workspace_previews` and sweeps them after 30 days. A rehearsed upload is checked in memory (`ws_upload_inspect()`) and never reaches the disk.
