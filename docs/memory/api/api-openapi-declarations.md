---
name: api-openapi-declarations
description: How a route and an object are declared for the OpenAPI document: returns, field schemas beside presenters, nested nullable objects, list item types, 201/204, the panel's loader, check_api_schema.php
---

## What does a route declare about its answer?
- `'returns'`: an object name (`'ErpInvoice'`), `array('list' => 'ErpInvoice')` for a page, and the envelope fields beside `list` when a listing carries its own (`array('list' => 'WorkspaceInboxItem', 'reload' => 'boolean')`, sent through `api_ok_list()`'s `$extra`).
- A create that answers 201 declares `'status' => 201`. An answer with no body (`api_send(204, ...)`) declares `'status' => 204`, so the document says 204 instead of 200, and its route id goes into `$without_body` in `tools/check_api_schema.php` (today `webhooks.delete`).
- A file answer: `'binary' => true` instead of `returns` (`api-file-endpoints.md`).

## Where are an object's fields declared?
- In a `*_schema()` function beside the presenter, one line per field, registered by name in `api_openapi_objects()` (`includes/api/openapi.php`) or in the module's `*_openapi_objects()`. Workspace and ERP pair each presenter with its schema through a `// What X() returns.` comment.
- Nullable fields (`'string?'`): `api-openapi-nullable-fields.md`.
- An inline nested object cannot be nullable: declare it as an object of its own and write the field `'Name?'` (`'Device?'`, `'ErpQuoteSignature?'`).

## What about parameters?
- A `'type' => 'list'` parameter names its items with `'of'`: `'integer'`, `'string'` or an inline object `array('hash' => 'string', ...)`. Without `'of'` the document calls it a list of objects.
- A body parameter's `description` is printed in the document and in the MCP tool a model reads: English, short, true.

## How does the panel build the document?
- `api_docs.php?openapi=1` loads every resource file through `api_openapi_load_declarations()`, outside the API entry: a new resource file's gate accepts `PG_API_ENTRY` OR `PG_API_PANEL`, and the file holds nothing but function definitions at top level. A declaration not loaded is skipped and every reference to it points at nothing.

## What does tools/check_api_schema.php check?
- Every route has `returns` (or `binary`, or a `$without_body` entry); every object named is registered and its function exists; a 201 is declared exactly when the handler answers it; a `dry_run` route has a stop; every non-409 code is in `api_error_catalogue()`. It reads `includes/api/` and the modules' `includes/*/api.php` and `api_*.php`.
- The field-by-field presenter check runs only for the pairs in its `$presenters` list. Many module objects are not in it (`ErpInvoice`, `ErpAccount`, `ErpReceipt`, `ErpWaybill`, `WorkspaceMessage`, `WorkspaceTask` and others): a field added to such a presenter is added to its `*_schema()` by hand, or the pair is added to the list.
