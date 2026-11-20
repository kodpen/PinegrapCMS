---
name: api-workspace-highlights
description: Readers' highlights over the Workspace API (#10341): the WorkspaceMessage keys, who gets For me rows, the three person-only writes, what quote indexes, how changes reach devices
---

## What does WorkspaceMessage carry?
- `highlights` (`WorkspaceMessageHighlight[]`: id, quote, occurrence, scope, note, user_id, name, mine, can_remove, can_note), `highlight_text` and `can_highlight_all`, on every answer of `ws_api_message_present()`: channel and discussion lists, `/workspace/sync`, single-message answers.
- Built in `includes/workspace/api_highlights.php` (`ws_api_message_highlights_map()`, read once per page by `ws_api_messages_present()`) from the panel's own `ws_message_payloads()`, so the rows and the text match the panel.
- Empty for a deleted message, a system line, one the person deleted for themselves, and every message before the upgrade (`ws_highlights_ready()`).

## Who gets "For me" rows?
- Only the person's own team devices session (`ws_api_highlights_personal()`, i.e. `ws_api_device_writes_as_person()`). An application key and an OAuth connection get the `all` rows only. Another person's `me` row never comes.

## What do quote and occurrence index?
- `highlight_text`: the body as the panel draws it for this reader, white space folded (`ws_highlight_plain()` of the payload's `html`). Neither `text` (formulas, checkboxes, tags read differently) nor `body` folds to it, so the client draws and selects in `highlight_text`.

## Which writes are there?
- `POST /workspace/messages/{id}/highlights` {quote, occurrence, scope all|me, note?} → `{id, message}` through `ws_highlight_set()`; the same place again changes the scope.
- `POST /workspace/highlights/{id}/note` {note} and `POST /workspace/highlights/{id}/delete` → the message, through `ws_highlight_note()` / `ws_highlight_remove()`.
- All three: `ws_api_own_words_only()` (key and connection 403), `'mcp' => false`, dry run after every check, 503 before the upgrade, 404 for a message or highlight the person cannot see.
- Refusals from `ws_highlight_make_check()`: a field → 422 `validation_failed` with that field; a limit → 422 with its code `limit_message_mine`, `limit_message`, `limit_person` (in `api_error_catalogue()`).

## How do changes reach the person's other devices?
- An `all` change stamps the message (`ws_highlight_stamps()`), so `changed_since` and sync's `changed` bring it. A `me` change stamps nothing and was left so: its rows arrive on the next full read of the channel; the route descriptions say so.

Related: docs/memory/workspace/workspace-message-highlights.md, docs/memory/workspace/workspace-api-callers.md
