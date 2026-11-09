---
name: tables-bin
description: Plan (owner 2026-11-09): a deleted workflow table or row is kept in a bin for the Recycle Bin's days and can be restored; steps TB1-TB2
---

## What is it for?
- A deleted workflow table or row is gone for good today (`workspace/workflow-tables.md`), while people, agents and the API delete rows, and step 5 of `tables-relational` will clear the links to a deleted row.
- Decision: `workflow/decisions-made-workspace-10.md`; schema: `workflow/decisions-made-schema-14.md`.

## How does it behave?
- A delete moves the row or the table to the bin. It stays for `config.recycle_retention_days` (30 unless the site changed it; the panel Recycle Bin's setting), then the Workspace's cron deletes it for good, a bounded number per run as `pg_recycle_purge_if_due()` does.
- A row: whoever may edit the table sees the table's bin (who deleted, when, the first cells), restores a row or deletes it for good. A restored row comes back at the end of the table with its id, its cells and its links to site records. A full table (`ws_wtable_limits()`) refuses the restore.
- A table: its owner sees "Deleted tables" in the list and restores the table whole, with its columns, rows and shares. In the bin nobody reads it, no flow or API call reaches it and it does not count in the owner's limit.
- The bin is data the site still holds: the privacy export and erasure (`pg_privacy_wtable_rows()`) and the contact merge (`pg_contact_holders_move_wtable_cells()`) read it too.
- The rollback of a half-made table (`ws_wtable_import_create()`, `ws_wtable_starter_create()`) stays a real delete.

## Why this store?
- A deleted row leaves `ws_wtable_rows` for a table of its own, `ws_wtable_row_bin`. The 13 statements that read rows, two of them outside the Workspace (the contact merge and the privacy code), stay as they are, and so do the row readers `tables-relational` is adding.
- A deleted table only gets a mark, `ws_wtables.deleted_at`. Its id stays taken, so the flows that name it work again after a restore. Five readers learn the mark: `ws_wtable()`, `ws_wtables_own()`, `ws_wtable_create_check()`, `ws_wtables_reached()` and `pg_privacy_export_collect()`.
- An old database may give a deleted last id to a new row after a restart. A restored row whose id is taken gets a new one, and the screen says its links were not restored.

## Which steps are there?
- TB1 (M, schema) The store. **Done, #8412.** The columns and the table of `decisions-made-schema-14.md`. `ws_wtable_row_delete()` and `ws_wtable_delete()` move to the bin, and a final delete stays for the rollbacks and the purge. Restore functions, the purge, the privacy and contact readers, the shares' `deleted` event and a restore event. Tests for the pure parts; the step rerun twice on a dev DB. Memory: `workspace/workflow-tables-bin.md`. Claim as `[plan:tables-bin] TB1 — table and row bin`.

- TB2 (S, no schema) The screens and the routes. The table's bin and "Deleted tables" on `workspace_wtables.php`; the delete confirmations say how long the bin keeps. API routes list the bin and restore: `workspace.table_rows.restore` is an MCP tool as the row delete is, `workspace.tables.restore` is not; their schemas pass `tools/check_api_schema.php`. After TB1.

## What meets `tables-relational`?
- Its step 4: the row number counter never hands out a number a binned row holds.
- Its step 5: the links a delete clears are kept with the binned row and put back on a restore where the cell is still empty. Whichever of TB1 and that step lands second adds this.

## Done when?
- Both steps merged; the lasting points go to `workspace/` and this file is deleted.
