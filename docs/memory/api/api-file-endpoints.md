---
name: api-file-endpoints
description: External API routes that send or take a file: api_send_file() and binary routes, Range/If-Range, multipart form_file uploads, $_FILES outside MCP, the Idempotency-Key fingerprint, picture copies
---

## How does a route send a file?
- With `api_send_file($path, $mime, $file_name, $inline)` (`includes/api/response.php`), as its own bytes rather than JSON. The route carries `'binary' => true` (no `returns`; the document writes the 200 as `application/octet-stream`) and `'mcp' => false`, since a tool result cannot carry the bytes. Example: `GET /workspace/messages/{id}/file`.
- `api_send_file()` itself answers the ETag/304, `Range` (206 with `Content-Range`, one range), 416 for a range past the end, `If-Range` (an ETag that no longer matches gets the whole file) and `Accept-Ranges`; the handler adds nothing. It drops every output buffer and streams the file in pieces, with the helpers `get_file.php` shares (`includes/http_range.php`, `platform/get-file-byte-ranges.md`).

## How does a route take a file?
- As a multipart part: the route declares `'form_file' => 'file'`, and the document then offers a `multipart/form-data` body with that part. Or as `content_base64` with `name` in a JSON body; both in one call is 422. Example: `POST /workspace/channels/{id}/files`.
- Read `$_FILES` only while `api_send_hook() === null`: a route the MCP endpoint runs in-process has no form of its own (`ws_api_upload_input()`, `includes/workspace/api_resources.php`).
- The `Idempotency-Key` fingerprint includes the part's name, size and SHA-256 (`api_idempotency_input()`), because a form part is not among the parsed fields: without it two different files sent with the same name and text would replay the first answer. It reads the part the route names in `form_file`; a route that takes a file in any other way adds it to the fingerprint there.

## Smaller copies of pictures?
- `ws_file_preview($path, $wanted, $file_id)` (`includes/workspace/files.php`, #5402) answers from the image derivative cache: `pg_image_derivative()` with the file's `files` id, in its own format, at `ws_file_preview_width()` (edge 160/320 → 320, 640 → 960, 1280 → 1440, 1920 → 1920), so one copy serves Workspace and panel lists, with the derivatives' signature, encode slots and daily sweep (`platform/image-derivatives.md`). Null (the original) when no copy is made. The Workspace access checks stay in front in `ws_api_files_get()`. The old `data/temp/workspace_previews` folder is emptied by the general job. A rehearsed upload is checked in memory (`ws_upload_inspect()`) and never reaches the disk.

## Where does an upload go?
- `POST /files` files into the applications' folder (`api_upload_folder_id`), never a folder named in the request; with `form_id` into the form's public picture folder (`api-form-rich-text-in.md`).

## Which files does `GET /files` list?
- Only what any visitor may open: `api_files_public_clause()` (`includes/api/resources/files.php`) keeps files in a `public` folder and leaves out `attachment = 1` (submitted form and comment uploads, drawn signatures), which `get_file.php` serves only to people who may see the record. The list, its count and `GET /files/{id}` all use it. Before this an application with files read listed those uploads' names and sizes.
