---
name: agent-key-trial-memory
description: Channel memory written live on dev (2026-11-11) with an application agent's key: create, read, list, update and delete by POST, dry runs, refusals, the entry's line in the brief
---

## What was tried, and how?
- Owner (2026-11-11): "birde en son ajan kanalı belleğine yazma denemesi yap". dev.pinegrap.com, the key of application 13 (the architect's agent, channel 25), Basic auth, the routes of `includes/workspace/api_memory.php` (`channel-memory.md`). No call failed on the server.

## What did the list and the read give?
- `GET /workspace/channels/25/memory`: 200, `{data: [], page: {limit: 0, has_more: false, next_cursor: null}}` while empty. Entries come without `text`; `page.limit` is the number of entries (the list is not paged); `?tag=` filters.
- `GET /workspace/memory/{id}`: 200, the entry with `text`; 404 `not_found` after its delete.

## What did the writes do?
- `POST /workspace/channels/25/memory?dry_run=true`: 200, `{dry_run: true, would: "created", resource: "workspace_memory_entry", detail}` (the detail holds `tags` as one comma string, `expires_at: 0`, `pinned: 0`); nothing written.
- The same `POST` without the dry run: 201, `{id, channel_id, title, tags: [...], pinned, expires_at: null, expired, author: {kind: "app", id: 13, name}, updated_by, created_at, updated_at, text}`; Turkish letters kept.
- `POST /workspace/memory/{id}` with `{"pinned": true}`: 200. With `{"tags": "mimar kurallar #Oylama"}`: stored as `mimar`, `kurallar`, `oylama`.
- Refused: no title, 422 `validation_failed` on the field `title`; an expiry in the past, 422 on `expires_at`.
- Delete: `POST /workspace/memory/{id}/delete?dry_run=true`: 200, `would: "deleted"`; without the dry run 200, `{id, channel_id, deleted: true}`.
- `PATCH /workspace/memory/{id}` answered 405 and `DELETE` 404 from dev's IIS, before PHP: the POST forms are the way there (`api/external-api-iis-environment.md`).

## What does the brief show of it?
- `GET /workspace/messages/{id}/preamble`: the `<<<channel memory>>>` block opens with "Channel memory (read an entry with workspace.memory.get, its id without the #):", then one line per entry ("- #1 <title> [tags]"), then "Decisions in this channel:" with the decision lines. Texts are not in it (`ws_memory_digest()`).

## What stays on dev?
- Entry 1 of channel 25, pinned: "Mimar bu kanalda nasıl çalışır" (tags `mimar`, `kurallar`, `oylama`): how the architect's agent answers, that owner questions come as one-choice polls, that it writes no product code and merges nothing. A second entry was made for the delete and is gone.
