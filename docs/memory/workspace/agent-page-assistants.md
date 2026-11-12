---
name: agent-page-assistants
description: Pinegrap AI and model assistants make, read and edit live pages as ws_ai_step() tools (page_ai.php): make_live_page, read_live_page in parts, edit_live_page whole or by replacements; Claude through the MCP tools
---

## Which tools, and when are they offered?
- `includes/workspace/page_ai.php` (required at the end of `ai.php`): `make_live_page` (html, name, text, libraries), `read_live_page` (message_id, from), `edit_live_page` (message_id, version, html or replacements, libraries). `ws_ai_tools()` adds them, `ws_ai_tool_run()` sends them to `ws_page_ai_tool_run()` before the Visual Page Editor's `read_page`/`page_*` tools; their names are in `ws_ai_tool_names()`.
- The names never start with `page_` and are never `read_page`: those go to the designer's tools (`tests/workspace_page_ai_test.php` checks it).
- Offered where the canvas tools are (`ws_page_ai_offered()` is `ws_canvas_ai_offered()`, `agent-canvas-assistants.md`): Pinegrap AI and model assistants alike. Where they are offered `ws_ai_start()` sets `$state['live_page_tools']`, which gives the model 8000 tokens as for the designer.
- Claude has no tool of its own here: it makes and edits a page with the MCP tools of the routes (`agent-page.md`).

## What do they do?
- The same storage as the routes (`agent-page.md`): `ws_store_file_bytes()`, `ws_page_version_add()` with the maker, a message of the assistant's application under the request (`parent_id` the request's message); an edit is `ws_page_write()` (rewrite, version, prune, touch). Read and edit reach only pages of the request's own channel (`ws_page_ai_target()`).
- The maker in the version: `ws_canvas_ai_by()` (model assistant `{id, kind: model, handle}`, Pinegrap AI `{id: 0, kind: ai, handle: ai}`); the version's `user_id` is the writer `ws_assistant_writer()` finds.
- A stale `version` is refused with the newest one named; the model reads again.
- A page made or edited sets `$state['live_page_done']`, so "I made it" is not sent back as a claim of a change nobody proposed (`ws_ai_finish_answer()`).

## Why replacements, and how do they work?
- A page may be longer than the model writes in one answer, so `edit_live_page` takes either `html` (the whole document) or `replacements`, a JSON list (at most 20) of `{find, replace}` (`ws_page_ai_replacements()`). Applied in order (`ws_page_ai_apply()`), each find must stand exactly once in the text the ones before left, else nothing changes. The result is kept as the whole new document, as on the API.
- `libraries`: names with commas or a JSON list (`ws_page_ai_libraries()`); left out or empty on an edit keeps the version before's, `none` drops them.

## What does the model read?
- `ws_page_ai_present()`: message id, newest version, libraries, versions (maker without empty fields, UTC dates), `length`, and the HTML from `from` on, 10000 characters a part (`ws_page_ai_read_max()`, inside the 12000-character tool answer), with `next` where the following part starts or null.

## Tests?
- `tests/workspace_page_ai_test.php`: libraries and replacements as written, the replacements applied, the read in parts, the names and flat arguments. The tool runs need a database and a model; not run live. Built in LP3.
