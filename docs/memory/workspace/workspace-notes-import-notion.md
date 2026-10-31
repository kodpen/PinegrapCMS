---
name: workspace-notes-import-notion
description: Notion pages as notes (notes_notion.php, #3212, decided in #3180): the notion MCP alias's search and fetch tools with the person's own account, a page staged as a Markdown file, then the file import
---

## How does a Notion page become a note?
- Only through the site's Notion MCP server (the `notion` catalogue connector, found by its address with `ws_mcp_catalogue_added()`, else a server aliased `notion`); no Notion API code, no schema (#3180).
- The import window shows "From files" / "From Notion" when that server exists (`CFG.notes_notion`). From Notion asks `workspace_notes_import.php` step `notion_state`; anything but ready is one line with a link to Workspace settings › MCP servers (`#ws-mcp-<id>`, "Connect my account").
- `notion_search` calls the search tool, `notion_stage` fetches up to 25 picked pages (`WS_NOTE_NOTION_PICK_MAX`) and writes each as `files/<n>.txt` in a stage (provider `notion`), with front matter title, created, updated, `id` (the 32-hex page id) and tags (`ws_note_notion_file()` through `ws_note_enex_file()`). The answer is the upload's (`ws_notes_import_staged()`), plus `warnings` for pages not fetched; preview, duplicates (`ws_note_sources` by page id: same / update / new), limits, batches and finish are the file import's.

## Which account and tools?
- Only the person's own connection (`ws_mcp_connection(..., $user_id, true)`), never the site's: pages are private. A 401 renews the token once (`ws_mcp_reauthorize()`), errors read as `ws_mcp_error_text()`; a task answer is refused. Calls are not rows of `ws_mcp_calls` (its `via` ENUM has no import value); `last_used` is set.
- Tools come from the stored `tools/list`, as turned on (`ws_mcp_tools_on()`, approval-only tools left out): `notion-search` / `search` / `notion_search` / a name ending in it, same for fetch (`ws_note_notion_tool_pick()`). The argument name is read from the schema (`query`, else the first required string; `id`, `url`, `page_id`). Missing: state `no_tool`, "@notion offers no fetch tool, or it is turned off".

## How is a fetch answer read?
- `ws_note_notion_page()`: the JSON text item (`title`, `url`, `text`, `metadata.type`; not a page → refused), else the plain text. `<content>` is the body; `<properties>` JSON gives tags (Tags/Labels/Etiketler, list, JSON string or commas) and dates (`created_time`, "Created time", `date:X:start` read as x; last edited likewise). The page id comes from the address, else the id asked for. Titles lose the 32-hex id (`ws_note_notion_title()`; an address's dashes become spaces). No title: the first heading.
- `ws_note_notion_markdown()`: page/database mentions → `[title](url)`, a user mention → the name, a date mention → the date, `<empty-block/>` → an empty line, callout/toggle/columns/details → their text one tab less, other Notion tags dropped.
- Search: results of type page only, once each, at most 100 (`ws_note_notion_search_pages()`), also from `structuredContent`.

## Tests and what was not verified?
- tests/notes_notion_import_test.php (tool pick, argument names, ids and titles, search list, page to note and back through `ws_note_import_parse()`, states). The live search/fetch against mcp.notion.com was not run in the cloud session: it needs a Notion workspace connected on dev.
