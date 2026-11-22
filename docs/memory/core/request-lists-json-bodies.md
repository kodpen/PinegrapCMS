---
name: request-lists-json-bodies
description: JSON body keys read as one value (token, action, mode, ids, text) that hold a list or an object count as not given: image editor API, translations calls, Workspace canvas save, notes import
---

## Which endpoints leave out a list in a one-value key?
- `image_editor_api.php`: `pg_image_editor_api()` reads its request through `pg_image_editor_request_values()` (`includes/fn/image_editor.php`), which drops a list or an object from each key `pg_image_editor_single_keys()` names. The document, export, extend, redact, masks, pictures and motion stay as they came.
- `translations_action.php`, `workspace_file_save.php` and `workspace_notes_import.php`: a loop right after the body is decoded, over the keys each one reads as text or a number.
- Numbers and yes or no stay; only a list or an object is dropped, as `pg_request_row_values()` does for a posted row.
- A new key read as one value goes into the endpoint's list.

## Why?
- A sandbox probe posted each key as `[]`, `["x"]` and `{"a":1}` beside a valid token. `(string)` logged "Array to string conversion" for the image editor's token, action, mode, family, object_type, column and kind; the translations' action, language, scope, engine and text; the canvas save's token; the notes import's token, step, query, section and stage.
- `(int)` of a list is 1. The image editor opened, saved over or copied file 1 for a list in file_id, to or target, and version 1 for one in version_id; the translations saved string 1 or read job 1.
- The chat attachment check in `image_editor_api.php` (`pg_image_editor_request_files()`, `includes/authentication.php`) reads a list as no file, so it checked nothing while the action went on with file 1.
- PR #11102.

## Which test guards them?
- `tests/json_request_values_test.php`:
  - the image editor drops lists in its one-value keys and keeps the rest;
  - every key the editor, its data tables and the picture translations cast, or hand to an id or text helper, is in `pg_image_editor_single_keys()`;
  - the three root endpoints drop lists before their first read, for every key they cast.

## What read clean?
- `barcode_decrease_inventory.php`, `barcode_increase_inventory.php` and `booking_slots.php` with the same probe.
- The signed-out raw-body endpoints (webhooks, OAuth, MCP, ActivityPub inbox, CSP reports, payment callbacks, flow starts, the guest page) with an empty body, broken JSON, scalars, lists and odd key types: no 500, no PHP message.
- `api.php` checks each parameter against its schema (`api_validate_input()`, `includes/api/router.php`) before a handler reads it.

Related: docs/memory/core/request-parameter-arrays.md, docs/memory/core/json-endpoint-rules.md
