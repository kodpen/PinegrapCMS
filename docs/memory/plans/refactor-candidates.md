---
name: refactor-candidates
description: Refactor and infrastructure items from the 2026-10-08 code audit that were still open on 2026-10-16 (designer JS twin, add/edit pairs, big lists, db_q, schema probes, fragment cache, _f gates)
---

## Where does this come from and what is already done?
- A code audit of 2026-10-08 (old Turkish text: `git show 88e7ac6d:docs/_tespit_2026_10_08/refactor_altyapi_raporu.md`). Nothing here is a decision; check `workflow/decisions-made.md` before starting any item.
- Done since: dashboard widgets split (`includes/dashboard/widgets/`), panel action table `pg_panel_actions()` with token flags, `tools/build_js.php` + CI twin check, `tools/test.php`, error handlers (`includes/fn/errors.php`), `pg_db_run()` + `perf_stats.total_queries`, `pg_schema_has()`, `pg_list_state()`, InnoDB move (2026.4.8), `config` VARCHARs to TEXT (docs/memory/data/config-table-no-varchar.md), mail queue, campaign claim and retry, cron light/heavy lanes, backup zip + retention + remote copy, `List-Unsubscribe`, `check_copies.php`, `change_password.php` / `set_password.php` sign-in fixes.

## What is still open? (size S/M/L)
1. `assets/js/style_designer.js` (3.4 MB, one IIFE) is served raw: no `.src.js`/`.min.js` twin, not in `pg_js_bundles()`. Constraint: `pg_designer_i18n_keys()` (`includes/fn/designer.php`) scans the served file for `_sdT('…')` keys, so a twin must keep the scan on the source. Generated server rules (`includes/server_config.php`) have no compression or Expires blocks; whether hosts compress was never checked. M.
2. `add_*` / `edit_*` pairs: 34 of 51 pairs ≥60 % identical. Shared forms in `includes/forms/`: pages, e-mail campaign profile (#2356), contact (#2363), shipping method (#2366). Submitted form #2466 (~706 lines shared). Pattern: root files stay thin wrappers (never moved). M per pair. Done, each in ui/*-shared-form.md: calendar event (#2354, #2376, #2459), field (#2364, #2464), container (#2514), short link (#2491; 486 shared lines), user (#2507; ~432), product group (#2534; 378, add 539 / edit 1314). Next by shared lines: menu item (203, 45 %), zone (187, 55 %), menu (182, 53 %).
3. Big list screens print every row (client-side DataTables, no `LIMIT`): steps and what each screen needs in `big-list-paging.md`. L.
4. Placeholder query helper `db_q("… WHERE id = ? AND name = ?", [$id, $name])`. Mandatory in new code, old code switches when touched. ~2,100 raw `mysqli_query(` left; hottest files first, watch `mysqli_num_rows` / `MYSQLI_USE_RESULT` users. M. Helper PR #2138; converted: 31 PRs, #2159 to #2387, each named in its data/db-q-converted-*.md file; calendar, contact group and small function files #2371 (`includes/fn/custom_form.php` from that group still to do); next: the next hottest.
5. Done: the last raw `SHOW COLUMNS` probes (`widgets.php` order view, `security.save.php`) use `pg_schema_has()` (PR #2127); see release/schema-probe-rules.md. Kept on purpose: the router's `config` check, the wildcard listings in `api/auth.php` and `backup_restore.php`; `waf_table_has_column()` (239 callers) uses LIKE.
6. No fragment cache: with the query counter in `perf_stats`, cache the most expensive front-end widgets under `data/cache/`, keyed by content + `updated_at`; invalidation points first. M. Measured first (sandbox), fixed without a cache: classic menu 168 of a page's 257 queries (data/menu-tree-queries.md, PR #666), `<cregion>` tags 17 → 2 (data/common-region-queries.md, PR #801); home page now 75, measure designer pages before caching.

## Which questions wait for the owner?
- The six root `*_f.php` files (`duplicate_folder_f`, `duplicate_page_f`, `edit_offer_f`, `export_products_f`, `import_products_f`, `view_folder_and_files_f`) have no gate constant; they appear to define functions only. Add a gate, or leave them?

## What was never checked on a running site?
- Search results after the `search_items` move to InnoDB (8.14; engine differences are listed in the comment of `upgrade_2026_4_8_innodb_search()`), list screen times with large data, queries per page.
