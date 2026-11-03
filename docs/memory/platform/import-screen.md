---
name: import-screen
description: import.php screen rules: menu entry, picker choices (no Recycle Bin or Workspace, path labels), field rows by type, dry run refusals, discard and 7-day sweep, undo answers
module: importers
---

## Where is the screen reached from?
- The Pages row's panel, below Search and audits: "Import from another platform" for roles 0-2 once `imports` exists (`pg_schema_has('imports')`, `includes/fn/output.php`); `import.php` highlights the Pages row. Before this it was linked from nowhere.

## What do the mapping pickers offer?
- Forms: not on a page in the Recycle Bin or under the Workspace's folder (`pg_folder_in_bin_chain()` over `pg_folder_access_tree()` and `pg_workspace_folder_id()`), labelled "form name (page name)" when the two differ.
- Folders and product groups: `pg_import_tree_choices()` (pure): tree order, each label its path ("Shop › private"), a lone top node (the root folder) left out of the paths. Folders leave out the Recycle Bin, the Workspace and archived folders, each with its subtree; product groups take the live ones (`pg_catalog_bin_live_sql()`).
- The choices are the save's whitelist (`pg_import_mapping_clean()`), so a refused place cannot be posted either.

## Which fields does a field row offer?
- A `form_field` row carries `field_types`, the types its writer fills (`includes/import/write_record.php`): title, body, summary text box/text area; date date, date and time, text box; featured image file upload; categories and tags check box, pick list, radio button, text box, text area. `pg_import_field_fits()` (pure) also refuses `information` fields. An empty list takes every type.
- The fields of the offered forms are printed once as JSON (form id → [id, label, type]); the script fills a field select when its form is chosen. Printing them as options in each row made the page about 1 MB on a site with 1,900 fields.

## Why can the dry run say "Will be skipped"?
- `pg_import_dry_run()` asks `pg_import_precheck($kind, $item)` for an item not imported before; a writer may define `pg_import_precheck_<kind>()` (contact: no name or address held; product: store off, row error, type, SKU held; user: address invalid or held). A refusal is tallied under `failed` in the dry-run counts, shown as "Will be skipped", and its reason fills the First items' Note column. The contact and user writers call their precheck themselves.

## What happens to an upload that is never started?
- The Checked page has "Discard the upload" (`action=discard`, `pg_import_discard()`): only `uploaded`/`dry_run` rows, which made nothing; the row, the source file and the cursor go. `pg_import_job_tick()` discards such rows older than 7 days (`PG_IMPORT_UNSTARTED_DAYS`, 50 a tick).

## What does an undo step answer?
- `pg_import_undo()` adds `closed` (missing, undone or past the 30 days; also `done`) and `busy` (the lock is held). The screen reloads on `closed`, asks again after 3 s on `busy`, and shows a kept count 4 s before reloading. Before, a stale tab polled every 200 ms for ever.
- A running import shows the Undo card as "Stop and undo the import"; once it is pressed the run polling stops. The question is `pgConfirm()`, `window.confirm` only without it.
