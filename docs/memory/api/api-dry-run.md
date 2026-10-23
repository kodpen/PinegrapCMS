---
name: api-dry-run
description: Making an external API write rehearsable (X-Dry-Run or ?dry_run=true): the route flag, where api_dry_run_stop() goes, batch endpoints, the *_check() split, side effects a rehearsal skips
---

## What does a rehearsal promise?
- `X-Dry-Run: true` or `?dry_run=true` (never a body field) runs everything up to the first write: credentials, scope, body shape, required fields, whether the row exists. It answers `200 {"dry_run": true, "would", "resource", "detail"}`. A refusal is the real refusal (same status, code and field). Only what the write itself would reveal (a unique key taken meanwhile) is not foreseen.
- A write route without `'dry_run' => true` refuses a rehearsal with 400 `dry_run_unsupported` right after the scope check, before any parameter is validated (`api_run()`); a value other than true/false is 400 `invalid_dry_run`. A rehearsal takes no Idempotency-Key, so the real call sent afterwards with the same key still runs.
- The flag also adds the `X-Dry-Run`/`dry_run` parameters and the `DryRun` answer to the document, and the `dry_run` argument to the MCP tool.

## Where does api_dry_run_stop() go?
- Directly above the FIRST write, after every check (`includes/api/dry_run.php`). Everything above the call is what a rehearsal verifies, everything below what it skips; a stop placed early promises a validation that was never done. `$detail` carries what the handler already worked out (the id it would write to, the rows a batch would touch).
- `tools/check_api_schema.php` fails a `dry_run` route whose handler calls neither `api_dry_run_stop()` nor `api_dry_run_requested()`.

## And a batch endpoint?
- Ask `api_dry_run_requested()` once, run each row's checks, skip the writes and answer the per-row result at the end (`api_products_prices()`, `api_inventory_bulk()`).

## How is a panel function made rehearsable?
- When it checks inside its write, move the checks into a `*_check()` function and let the real function call it first; the endpoint calls the same check and stops. The panel and both API calls then meet one set of refusals. The Workspace's checks: `workspace-api-dry-run.md`.
- A side effect that closes an expired record on the way (a poll, an approval request) is skipped in a rehearsal: `if ($checked['expired'] && !api_dry_run_requested())`.
