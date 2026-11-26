---
name: channel-memory-search
description: Memory search (search= on the two memory lists) and GET /workspace/memory/projects: word match, page, snippet, the measure that kept it without an index
---

## How does an agent search the memory?
- `search=<words>` on `GET /workspace/channels/{id}/memory` and `GET /workspace/memory/general` (`ws_api_memory_index()`, `includes/workspace/api_memory.php`). Every word must be in the title, the text or the tags; a "quoted phrase" is one word; ten words at most (`ws_memory_search_terms()` = `pg_list_search_terms()`).
- `tag`, `project` and `expired` narrow a search as they narrow the list; the channel list takes `project` too.
- A hit is the index entry as the list gives it plus `snippet`: 200 characters of the text around the first word, spaces folded, `…` where cut (`ws_memory_search_snippet()`). A word found only in the title or tags gives the text's start.
- 50 hits a page, pinned first then the newest; `next_cursor` carries the offset and the memory's channel id (0 general), a cursor of another memory answers 400 `invalid_cursor`.
- Without `search` both routes answer as before: every entry (500 at most), no `snippet`, no paging.

## How is a word matched?
- `ws_memory_search_where()` (pure, `includes/workspace/channel_memory_search.php`): each word a bound `LIKE` value with `%`, `_` and `\` escaped (`pg_list_like_pattern()`), OR over `m.title`, `m.text`, `m.tags`, the words ANDed.
- In a Turkish site (`pg_list_search_turkish_charset()`) a word holding I, İ, ı or i is matched folded on both sides only (`REPLACE(REPLACE(CONVERT(col USING cs), 'İ','i'), 'ı','i') LIKE` the folded word): under the case-insensitive collation it finds what the plain `LIKE` finds, so the plain pass and the `turkish_ci` pass of `pg_list_search_where()` are left out.

## Why is there no index?
- Measure (2026-11-26, MariaDB 10.11, one memory of 5000 records of 4000 characters, a word found nowhere so every row is read): plain word about 120 ms; a Turkish word with an I letter about 180 ms; the projects read 4 ms. Under the 300 ms the plan allowed, so no FULLTEXT index was asked for.
- With `pg_list_search_where()`'s three passes a Turkish I word took about 420 ms; that is why the memory search has its own builder. A new pass per column needs the measure again.

## How are a memory's projects listed?
- `workspace.memory.projects` (`GET /workspace/memory/projects`, `channel_id` for a channel the owner may read, none for the general memory, `expired`): `{project, count}` rows, alphabetical, '' (no project) last; a project in another case or spacing counts with the first one met (`ws_memory_project_counts()`, the Memory tab's heading fold). 403 / 503 for the general memory as its list. A list with `project` is that project's index.
- All are MCP tools (route rows); schemas `WorkspaceMemoryIndexEntry` (`snippet` string?) and `WorkspaceMemoryProject`.

Related: docs/memory/workspace/channel-memory.md, docs/memory/workspace/channel-memory-general.md, docs/memory/plans/agent-memory-search.md
