---
name: refactor-candidates
description: Refactor and infrastructure items from the 2026-10-08 code audit that were still open on 2026-10-16 (designer JS twin, add/edit pairs, big lists, db_q, schema probes, fragment cache, _f gates)
---

## Where does this come from and what is already done?
- A code audit of 2026-10-08 (old Turkish text: `git show 88e7ac6d:docs/_tespit_2026_10_08/refactor_altyapi_raporu.md`). Nothing here is a decision; check `workflow/decisions-made.md` before starting any item.
- Done since: dashboard widgets split (`includes/dashboard/widgets/`), panel action table `pg_panel_actions()` with token flags, `tools/build_js.php` + CI twin check, `tools/test.php`, error handlers (`includes/fn/errors.php`), `pg_db_run()` + `perf_stats.total_queries`, `pg_schema_has()`, `pg_list_state()`, InnoDB move (2026.4.8), `config` VARCHARs to TEXT (docs/memory/data/config-table-no-varchar.md), mail queue, campaign claim and retry, cron light/heavy lanes, backup zip + retention + remote copy, `List-Unsubscribe`, `check_copies.php`, `change_password.php` / `set_password.php` sign-in fixes.

## What is still open? (size S/M/L)
1a. Done: `style_designer.min.js` built from `style_designer.src.js` (#3063, core/js-bundles.md).
1b. Done: compression and caching server rules (#2848).
2. Done: `add_*` / `edit_*` pairs share their forms; each pair has a `ui/*-shared-form.md` file naming its PR (`git log --grep='shared form'`), last ERP account (#3043). Not targets: offer (`add_offer.php` only redirects), product (`product_builder.php`), system_style (`includes/designer_screen.php`), erp_till / erp_quote / erp_purchase_order / erp_waybill (already share `includes/erp/till_form.php`, `invoice_form.php`, `waybill_form.php`), erp_expense / erp_invoice / comment / theme_file (8-12 % line overlap).
3. Done: big list screens paged on the server (users #2460, submitted forms #2509, contacts #2528, products #2655, commissions #2675, audit trail #2719, firewall log #2776; `ui/server-paged-lists.md`), sitemap.xml kept between requests (#2797, `visitors/sitemap-cache.md`).
4. Placeholder query helper `db_q("… WHERE id = ? AND name = ?", [$id, $name])`. Mandatory in new code, old code switches when touched. ~2,100 raw `mysqli_query(` left; hottest files first, watch `mysqli_num_rows` / `MYSQLI_USE_RESULT` users. M. Helper PR #2138; converted: 31 PRs, #2159 to #2387, each named in its data/db-q-converted-*.md file; calendar, contact group and small function files #2371 (`includes/fn/custom_form.php` from that group still to do); installer, designer, TinyMCE #2647; Flows, init.php #2819. Left: raw by rule (data/db-q-converted-files.md) or named in data/db-q-converted-tail.md.
5. Done (PR #2127, release/schema-probe-rules.md). Kept on purpose: the router's `config` check, the wildcard listings in `api/auth.php` and `backup_restore.php`; `waf_table_has_column()` (239 callers) uses LIKE.
6. No fragment cache: with the query counter in `perf_stats`, cache the most expensive front-end widgets under `data/cache/`, keyed by content + `updated_at`; invalidation points first. M. Measured first (sandbox), fixed without a cache: classic menu 168 of a page's 257 queries (data/menu-tree-queries.md, PR #666), `<cregion>` tags 17 → 2 (data/common-region-queries.md, PR #801); home page now 75, measure designer pages before caching.

## Which working rule applies?
- The six root `*_f.php` files (`duplicate_folder_f` … `view_folder_and_files_f`) are included, never URL-called, and define functions only: add the gate constant when one is next touched; moving them under `includes/` waits for that touch (Fixed rules 4).

## What was never checked on a running site?
- Search results after the `search_items` move to InnoDB (8.14; engine differences are listed in the comment of `upgrade_2026_4_8_innodb_search()`), list screen times with large data, queries per page.
