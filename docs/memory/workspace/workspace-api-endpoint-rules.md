---
name: workspace-api-endpoint-rules
description: Writing a Workspace API endpoint: Site Log lines in the owner's name (ws_api_as_owner()), bell rows, search scopes per record type, the scope ceiling, files, events, where the other rules are
---

## Whose name goes on a Site Log line?
- A panel function that writes its own activity line with `$_SESSION['sessionusername']` (`ws_channel_create()`, `ws_group_save()` and others) is called from the API inside `ws_api_as_owner()`, which sets the application owner's name for the call; otherwise the line has no name. `test_ws_api_panel_log_lines_carry_the_owner` checks the calls in `includes/workspace/api*.php`.
- A bell row (`ws_bell_add()`) takes its name itself from `ws_bell_user()` (the panel session, else the application's owner); `ws_notify()` needs no wrapper.

## How does the search reach panel records?
- `GET /workspace/search` filters them by the scope that reads each type: `ws_api_search_record_scopes()` (`includes/workspace/api_day.php`). A record type added to `ws_record_type_keys()` (`refs.php`) goes there too, with its scope, or the API never finds it; user accounts have no scope and are not searched.

## Which other rules apply?
- A new scope also enters the owner ceiling `ws_owner_scopes()`, or the endpoint answers 403 (`workspace-module-decisions.md`).
- Every write is rehearsable: `workspace-api-dry-run.md`. Who may call which write: `workspace-api-callers.md`. Sync, inbox and hidden messages: `workspace-api-sync-inbox.md`.
- A file posted or fetched through the API: `api-file-endpoints.md`.
- A new `workspace.*` webhook event carries `channel_id` in its payload: `api-webhook-events.md`.
- General endpoint rules (presenters, lists, errors, partial updates): `api-endpoint-rules.md`.
