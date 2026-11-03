---
name: decisions-made-schema-9
description: Decisions list, schema part 9 (2026-11-03 on): columns of the external API coverage steps
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the migration shape.

## Which columns were approved?
- Returns changed-since (#5927, architect, step X2b of `plans/external-api-coverage.md`): `order_returns.updated_at` INT UNSIGNED NOT NULL DEFAULT 0 with an index on (`updated_at`, `id`), in the next free 2026.11.0 sub-step at landing; backfilled once with GREATEST of the six status stamps; every writer of the row sets it through one helper; `GET /returns?modified_since` pages on it, oldest change first, like `GET /orders`. Reason: `api/api-endpoint-rules.md` allows a since filter only on a stamp every writer moves; a refused refund and a new return label move no stamp.
