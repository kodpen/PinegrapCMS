---
name: workflow-tables-later
description: What workflow tables leave for later (T1-T6 done): API/MCP import route, xlsx export, a "my tables" strip, CRM pipeline, tags in rows, table bin
---

## What is still missing?
- An import route in the API and MCP for a file or note table (T6a/T6b are panel only); it would follow the write-route rules: `dry_run` through a `*_check()`, `ws_wtable_import_rows()` quiet as on the panel.
- xlsx export of a table (`includes/fn/office.php`); a "my tables" strip on /workspace/my-day.
- A table bin or versions (a delete is final, `workflow-tables.md`); `ws_inbox` has no table column, so a share's bell line names no table (kind-only line, `workflow-tables-screen.md`).
- Tags in rows (`wtable_row` in `ws_refs`) wait for `plans/workspace-crm-seams.md` step 9 (`ws_refs.source_type` widening). The CRM pipeline is a shared table with a status and contact-link columns plus flows (same plan).

## Where did the rest go?
- Canvas, table automations and workflow mode: `plans/workspace-flows.md`. Scheduled-actions API: `workspace-scheduled-actions-api.md`, `workspace-scheduled-draft-proposals.md`.
