---
name: mobile-api-shelved-m8
description: Plan mobile-api-shelved, steps M8a to M8d (owner 2026-11-22, beside the other plans): sort=newest on the remaining lists, several reads in one request, a list of deleted records, a CORS allow-list
---

## What holds for all four?
- General capabilities for every client of the external API, as M1 to M7: no route for one app, texts name a team device. Each route is one row of `api_schema()` and keeps the area's key rules (`api/README.md`).
- No schema: a step that finds it needs a table or a column stops and asks the architect.
- A need the app's issue words differently wins over this file's shape where the two differ in detail; a difference in kind goes to the architect.

## Which steps? (side by side: each has its own files)
- M8a (S) `sort=newest` on the lists a device scope reads that lack it, by the walk of `api/api-list-newest-first.md` (`list_order.php`); the PR lists which lists got it and which cannot (no stable key).
- M8b (M) several reads in one request.
- M8c (M) the deleted records list.
- M8d (S) the CORS allow-list.

## How does one request carry several reads? (M8b)
- `POST /batch`, body `{"requests":[{"id","path","query","if_none_match"}]}`, at most 20. Reads only: each entry is a GET of the API, run in process the way the MCP server runs a handler (`api/api-mcp-server.md`), so input still comes through `api_request_input()`.
- Each entry is checked as its own call: its scope, its row rule, its 404. The answer lists `{id, status, etag, body}` in the order asked; an entry's error is its own and never fails the others. A matching `if_none_match` answers 304 with no body.
- Not taken as an entry: `/batch` itself, the token routes, binary and file routes (`api/api-file-endpoints.md`), any write. The rate limit counts every entry.
- The scope is none of its own: the call needs a valid caller, each entry its route's scope.

## How does a client learn what was deleted? (M8c)
- `GET /changes/deleted?since=<time>&types=<list>`: `{type, id, deleted_at}` rows from the audit trail's delete lines (`audit_log`, `core/audit-trail-stored-values.md`), oldest first, with the lists' cursor paging.
- `types` are API resource names; the step maps each to its table and gives only the types whose deletes the audit trail records on every path. The others are named in `GET /meta` as not covered, and to the architect.
- A row is given only to a caller whose scope reads that type; a user-role caller only where the type has no row rule narrower than the scope (else the type is left out for them).
- `since` older than the audit trail keeps: 410 `since_too_old`, and the client reads the lists whole again. Nothing of the deleted record but its id and time is answered.

## Which origins may call from a browser? (M8d)
- A list of origins kept as a `config_text` row (no column), empty at first: with it empty the API answers as today.
- For a listed origin the API answers the preflight and adds `Access-Control-Allow-Origin` with that origin and `Vary: Origin`; never `*`, never credentials: a caller signs with its bearer token only.
- The list is edited where Application Access keeps a site's API settings, by roles 0 and 1; an origin is `https://` with a host, or the fixed origins a native shell uses, named in the field's help.
- The token routes stay closed to a browser origin unless it is listed.

## How is a step checked?
- Tests of the pure parts first (the entry check of M8b, the type map and the since rule of M8c, the origin match of M8d); `php tools/check_api_schema.php`; the OpenAPI page and the MCP tool list read after the change. M8b and M8d are `'mcp' => false`.

Related: docs/memory/plans/mobile-api-shelved.md, docs/memory/api/api-device-sessions.md, docs/memory/api/api-list-newest-first.md
