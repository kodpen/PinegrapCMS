---
name: workspace-mcp-small-file
description: Posting a file into a channel over MCP (workspace_files_create, PR #1830): content_base64 and name only, at most 1 MB measured before decoding, larger files over REST, why
---

## Is posting a file an MCP tool?
- Yes since PR #1830: `workspace.files.create` (POST /workspace/channels/{id}/files) no longer carries `'mcp' => false`. Before, an AI app connected over MCP could not attach even a small picture or PDF to a channel.
- The download (`workspace.files.get`) stays no tool: a tool result cannot carry the bytes (`api-mcp-no-tool-routes.md`).

## What does the tool take?
- `content_base64` and `name` (plus `text`, `parent_id`, `dry_run`); never the multipart part, since `$_FILES` is read only while `api_send_hook() === null` (`api-file-endpoints.md`).
- At most `ws_mcp_upload_max_bytes()` (`includes/workspace/files.php`): 1 MB, or the channel limit `ws_upload_max_bytes()` when lower. Beyond it, 422 on `content_base64` asking for the REST route.
- The size is read from the text's length (`ws_upload_base64_bytes()`: data: prefix, white space and padding left out) before anything is decoded, in `ws_api_upload_input()`.
- Every other check is the REST route's: post rights, the person's message limit, `ws_upload_check()`, the dry run.

## Why 1 MB?
- The file travels base64 inside one JSON-RPC message that the client builds and its model often reads; the tool is for a screenshot, a small PDF or a voice note. A large file goes over REST as a form part (up to 20 MB).

## Where is it tested?
- `tests/workspace_api_test.php`: `test_ws_api_files_create_small_file_tool` (tool schema, the cap, base64 sizes, the check in `ws_api_upload_input()`), `test_ws_api_files_routes`.

Related: `api-mcp-server.md`, `api-file-endpoints.md`, `api-mcp-no-tool-routes.md`, `plans/workspace-mcp-capabilities.md`
