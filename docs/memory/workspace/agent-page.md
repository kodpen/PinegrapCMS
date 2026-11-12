---
name: agent-page
description: Live pages an agent makes (#8815): what one is for and is not, the routes, the mark of a page without a column, versions (20 kept), 409, who may
---

## What is a live page?
- An HTML document an agent writes (styles and script inside), kept as an `.html` file of the channel, carried by a message of the application; shown only in the guarded frame (`platform/guarded-frame.md`, `live_page.php`). Owner decision in `workflow/decisions-made-workspace-16.md` (#8762).
- What a still picture cannot be: a chart that shows a value on hover, a filter, a small tool, a presentation. A picture stays a canvas (`agent-canvas-svg.md`).
- A page is treated as hostile whoever made it (the agent may have read hostile words first): the guard holds before it is shown anywhere (`platform/guarded-frame.md`).
- It runs only once a person opens it and stops when the channel is left, so its script costs no reader who did not ask; team accounts open it, a guest sees it without Open (`agent-page-card.md`).
- Code: `includes/workspace/page.php` (storage, versions, the mark), `api_page.php` (routes), `page_card.php` (the card), `page_ai.php` (the assistants' tools, `agent-page-assistants.md`). Built in #8815, #8830, #8851, #8852.

## What is not in it?
- Architect (2026-11-11): a page that reads the site's records or the channel, a page a person writes, a page on the public site: each its own question, asked when wanted.

## How is a message known to carry a page, with no column?
- Channels refuse an `.html` upload (`ws_upload_refused_extensions()`; tested in `tests/workspace_agent_page_test.php`), so only the page routes keep one, through `ws_store_file_bytes()` directly.
- Each version is a `file_versions` row with `format = 'html'`, `source = 'agent'`, the bytes under `data/file_versions/`, and `doc_json` `{"page":1,"by":{id,kind,handle}}`. `ws_page_is_page($file_id)` asks for such a row.
- `live_page.php` shows only a page (`ws_page_is_page()`): an `.html` file that reached a channel before uploads refused it is never run.

## What do the routes do?
- `POST /workspace/channels/{id}/pages` (`workspace.channels.pages.create`, 201): `html` (required), `text`, `name` (`ws_page_file_name()`: "Page" when empty, always `.html`), `parent_id`. The message checks run before anything is kept; dry run stops before the write.
- `GET /workspace/messages/{id}/page` (`workspace:read`, any reader): `html`, `size`, `version` (newest `version_no`), `versions` (version, source, user_id, agent_id, agent handle, size, created_at). 404 when the message carries no page.
- `PATCH /workspace/messages/{id}/page` (also POST): `version` and `html`, the whole document anew. `version` not the newest: 409 `conflict`. The file is rewritten in the file directory (`pg_file_write_whole()`), its size updated, a version added, then pruned; `ws_message_touch()`.
- MCP tools come from the rows (`workspace_channels_pages_create`, `workspace_messages_page_get`, `workspace_messages_page_edit`).

## Who may, and what are the limits?
- As for canvases: `ws_api_canvas_agent($channel, $refused)` (Claude where Claude may be asked, else an application with an allowed row in the channel's agents), in a channel the owner may post in; a person's device 403.
- HTML: not empty, UTF-8, at most 500 KB (`PG_GUARDED_FRAME_MAX_BYTES`, the frame's own limit), else 422 on `html`.
- Versions: the first and the newest 19, 20 in all (`ws_page_versions_keep()`, `ws_page_versions_pruned()`). Deleting the file takes them along (`pg_file_versions_delete_file()`, the daily sweep).

## How does a page use a library?
- A short list the site ships (Chart.js first), named in the call and put in by the site, never an address: `agent-page-libraries.md`.

## What is a known gap?
- Two edits at the same instant both pass the version check; the second's version row is refused by the unique key and it answers 503 after its bytes were written. Rare; the next read shows the bytes the file holds.
