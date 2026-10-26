---
name: fn-module-map-additions-2
description: The includes/fn/ modules added after fn-module-map-additions filled up (2026.11.0 on) and the memory file that describes each.
---

## Which were added in 2026.11.0 after the first list?
- `designer_fx` (interactions of designer nodes: `pg_fx_normalize()`, `pg_fx_attrs()`, `pg_fx_inject()`): [[designer-interactions]].
- `collections` (a field of a record a record points at, in form list and detail widgets: `pg_coll_ref_bindings()`, `pg_coll_ref_fill()`, `pg_coll_ref_load()`): [[collection-references]].
- Not `includes/fn/`: groups of `view_folder_and_files_f.php` move to `includes/explorer/`, each required in place via `PG_FUNCTIONS_DIR`: `short_links` (#1956), `recycle_bin` (bin core, #1979), `recycle_pages_styles` (pages and styles binned from other screens, #1996), `recycle_names` (parked `pgbin_<id>_` names, the home flag, the daily purge, #PR).

## Where does a module added later go?
- Its line goes here under its version; earlier ones are in [[fn-module-map-additions]], the kinds of function each older module holds in [[fn-module-map]].
