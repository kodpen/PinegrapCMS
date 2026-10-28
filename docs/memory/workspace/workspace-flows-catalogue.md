---
name: workspace-flows-catalogue
description: The box catalogue GET /workspace/flow-kinds (settings read from the normalizers) and GET /workspace/flows/{id}/validate (the publish check without publishing), flow_catalogue.php
---

## Where is it?
- Engine `includes/workspace/flow_catalogue.php` (loaded after flow_versions.php); handlers in `api_flows.php`; tests `tests/workspace_flow_catalogue_test.php`. No schema, no panel use.

## What does the catalogue give?
- `ws_flow_kind_catalogue()`: every kind of `ws_flow_kinds()`, in its order: kind, label, starts, step, outcomes, outputs (key, type, label, mode), texts (a step kind's `texts`, the settings `{{node.…}}` fills), settings.
- Settings come from `ws_flow_config_normalize($kind, array())`, so a new key in a normalizer is in the API at once: type read from the empty value (text, integer, boolean, list, object); `ws_flow_setting_overrides()` sets what the empty value hides (an integer kept as '' for none: `blank`; `change.fields`, `condition.rule` objects; `report.metrics` a list of objects); `ws_flow_setting_values()` the fixed words.
- A new fixed-word setting goes into `ws_flow_setting_values()` with its normalizer's default first: the test sends every word and an unknown one through the normalizer.
- What a setting means stays in the `nodes` description of POST /workspace/flows; the catalogue has no per-setting texts (none to translate).

## What does validate do?
- `ws_flow_validate($viewer, $flow)`: edit access (403 else); `ws_flow_problems()` of the saved draft; for the owner (`ws_flow_share_manager()`) and no problem, `ws_flow_publish_check()` with their rights now, its first refusal as `refusal`. `publishable` only for the owner; `checked_rights` says whether the rights were looked at. Writes nothing.
- GET, not POST: the module counts every non-GET route a write that must rehearse (`test_ws_api_every_write_rehearses`).

## Who reaches them?
- `GET /workspace/flow-kinds` (`workspace.flow_kinds.list`): any `workspace:read` caller, one page via `api_ok_list()`; it names no record or person.
- `GET /workspace/flows/{id}/validate` (`workspace.flows.validate`, WorkspaceFlowValidation): `ws_api_flow_editable()`. Both MCP tools: an assistant drafts from the catalogue and checks the draft; only the person publishes.

Related: docs/memory/workspace/workspace-flows-outputs.md
