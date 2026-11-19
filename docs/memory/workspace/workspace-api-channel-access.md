---
name: workspace-api-channel-access
description: Channel kind, join, clear and version rename in the API (api_channel_access.php): person only, no MCP tool, the panel checks, who comes in and leaves
---

## Which routes, and who may call them?
- `POST /workspace/channels/{id}/kind` (kind public or private), `POST …/join`, `POST …/clear` (title optional), `POST …/eras/{number}` (title; empty takes the name off), all `workspace:write`, `dry_run`, `'mcp' => false`.
- Person only (`ws_api_person_only()`): an application key is refused 403 on each; a team devices session or an OAuth connection (acting for the person who approved it) passes, as Make private, Join and Clear are the person's own acts in the panel.
- The panel's checks: kind, clear and rename need staff in the channel (`ws_can_change_channel_kind()`, `ws_can_clear_channel()`), else 403; join only a public channel (403 for a private one: it is joined when a member adds you). A version number the channel does not have is 404; nothing written since the conversation began 422; versions table missing 503.

## What do they answer?
- kind: `WorkspaceChannelKindChange` (channel_id, kind, changed, gaining, losing). gaining and losing are the team members outside the channel who start or stop reading it (`ws_api_channel_kind_forecast()`, pure), in the answer and the dry run, as an access write must (`workspace-api-layout-writes.md`). Its members read it either way; staff may still open a private channel for inspection.
- join: the channel (`WorkspaceChannel`); already a member changes nothing.
- clear and rename: `WorkspaceChannelEraWrite` (channel_id, id, number, title, label, messages); a clear's dry run gives the number it would keep. The list is `GET …/eras` (CA12).

## How are the panel's writers reused?
- `ws_channel_make_public()` / `_private()`, `ws_channel_join()`, `ws_channel_clear()`, `ws_channel_era_rename()`; the logging ones inside `ws_api_as_owner()`.
- "Nothing to clear" is `ws_channel_clear_span()` (eras.php), shared by the panel's clear and the route, so the dry run refuses it too.
- Tests: `tests/workspace_api_channel_access_test.php`. Added in PR #9800 (CA14 of the channel API comparison, `api/api-panel-coverage-channels.md`).
