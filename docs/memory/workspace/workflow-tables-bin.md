---
name: workflow-tables-bin
description: The bin of workflow tables (11.0.178, TB1): a deleted row goes to ws_wtable_row_bin, a deleted table gets ws_wtables.deleted_at; restore, purge, privacy and contact readers
---

## Where does a deleted row or table go?
- A row: `ws_wtable_row_delete()` copies it to `ws_wtable_row_bin` (`ws_wtable_row_bin_put()`, with its `ws_refs` tags as `links` JSON `{"refs":[{type,id}]}`), then deletes it from `ws_wtable_rows`. The copy is written first so a stopped request never loses the row; a copy whose row this request did not remove is dropped again. The 13 statements that read rows stay unfiltered.
- A table: `ws_wtable_delete()` sets `deleted_at`/`deleted_by` (`ws_wtable_bin_put()`). Columns, rows, shares stay; its id stays taken, so flows naming it work again after a restore.
- Before step 11.0.178 has run (`ws_wtable_bin_ready()` false) a table delete is final and a row delete leaves no copy.
- The rollback of a half-made table (`ws_wtable_import_create()`, `ws_wtable_starter_create()`) calls `ws_wtable_delete_final()`, a real delete that also empties the table's bin.

## Who sees a binned table?
- Nobody. `ws_wtable()`, `ws_wtables_own()`, `ws_wtable_create_check()` (the 500 limit) and `ws_wtables_reached()` add `ws_wtable_live_sql()`; every screen, flow box and API call goes through `ws_wtable()`. Only `ws_wtable_binned()` / `ws_wtables_binned()` (owner's list, with `expires_at`) read them.

## How does a restore behave?
- Row (`ws_wtable_row_restore()`, write access): a full table refuses (`too_many_rows`). The bin entry is deleted first (claim), so two requests never both restore; the row comes back at the end with its id, cells, `revision + 1` and its `ws_refs`. An id taken meanwhile (an old database hands a deleted last id out again after a restart) gives a new id and `id_changed` true; the screen says its links were not restored. A failed write puts the entry back.
- Table (`ws_wtable_restore()`, owner only): refused at the table limit; it comes back whole.
- A binned row is deleted for good by whoever may edit the table (`ws_wtable_bin_row_delete()`).

## What do events say?
- Row delete still announces `workspace.wtable.row.deleted` (the catalogue says: into the bin). A restore announces `workspace.wtable.row.restored` to the webhooks of a table shared in a public channel, body as `created`. No flow box waits for it (not in `ws_events_own()` or the row anchors).

## When does the bin let go?
- After `config.recycle_retention_days` (30 when unset; `ws_wtable_bin_days()`). `ws_wtable_bin_purge()` in `workspace_recurring_job.php`: 200 rows and 5 tables a run, oldest first; a table goes with `ws_wtable_delete_final()`.

## Do the privacy and contact paths read the bin?
- Yes, the bin is data the site holds. `pg_privacy_wtable_rows(..., $store = 'bin')` reads `ws_wtable_row_bin` (its `id` the bin entry's, `row_id` the row's). Erasure entry `ws_wtable_bin_cells` (scope `wtable_bin_ids`) empties the same cells there. The export adds the bin's rows and gives each row `deleted_at`: the row's or its binned table's, 0 for neither.
- The contact merge holder `ws_wtable_bin_cells` re-points contact links in the bin too (`pg_contact_holders_move_wtable_cells(..., $table)`).

## What is left for TB2 and `tables-relational`?
- TB2: the screens and API routes (`plans/tables-bin.md`). Relational step 5 keeps the cells that linked to a binned row in `links` and puts them back on restore.
