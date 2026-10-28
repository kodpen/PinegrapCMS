---
name: page-cache-invalidation
description: Which writes make every page the file cache keeps old (PR #1402): the generation number, the request, panel, MCP and announce rules, what waits for the TTL
---

## How are stored pages made old?
- `pg_page_cache_mark_dirty()` registers one shutdown call that raises `config.page_cache_generation` (2026.11.0, 11.0.29) when the status is < 400, also while the page cache is off: the kept sitemap.xml follows the same number (`visitors/sitemap-cache.md`). The router reads the number from the config row it reads anyway and looks in `g<generation>/` only (`page-file-cache.md`).
- Raised directly: `pg_page_schedule_run()` when it moved a page; the settings save when the TTL changes; `pg_purge_caches()` (also sets the folder aside, "Page Cache").

## Which requests mark it?
- `init.php` after `waf_run('init')`, `pg_page_cache_request_dirties()`: a non-GET/HEAD/OPTIONS request of a signed-in user or an `integration.php` client. Not `page_view.php`, `api.php`, `chat.php`, `software_update_check.php`, `workspace*`, `mcp.php`, OAuth: they poll or answer reads with POST; nor `wishlist.php`: a heart changes no stored page, its state is filled in by script; nor `stock_notify.php`: no page shows a back-in-stock request.
- `api.php`, `pg_panel_dispatch()` → `pg_page_cache_panel_dirties($action, $entry, $request)`: write rows of content, designer, commerce, shared component and explorer, but `add_to_cart` and the shipping lookups. Not the bare `write` flag: dashboard and notification polls are write rows too.
- `designer`, `designer_file`, `shared_component` (`sub_action`) and `file_explorer` (`type`) carry reads, polls and writes on one row; they count only for a sub-action in the `dirty` list of `pg_page_cache_panel_sub_actions()`. The editor's presence heartbeat runs every 20 s, so the whole row would keep the cache empty while an editor is open.
- `tests/page_cache_test.php` reads every top-level `case` of those handlers and fails while one is in neither list: a new sub-action is sorted on purpose.
- `mcp.php`: every tool call is a POST. The send hook of `pg_mcp_route_run()` (behind tool calls, resource reads and prompts) asks `pg_page_cache_mcp_dirties()`: the route's method writes, not a dry run, answer 2xx. Most API resources (products, menus, forms, files) announce nothing or call `api_webhook_enqueue()` directly, so `pg_announce()` alone would miss them.
- `pg_announce()` of `page.*`, `product.*`, `product_group.*`, `file.*` (`pg_page_cache_announce_dirties()`): reaches the Workspace and any other announcer.
- `submit_order.php` when a product sells out; not on every stock decrement, which would empty the cache with each sale.

## What waits for the TTL?
- Stock counts after a sale, a Workspace stock change (only `inventory.changed`), a Workspace file save (`workspace_file_save.php`, the `pg_version_assets` stamp), anonymous comments or records, legacy GET writes with a token.

## What over-invalidates?
- Every POST of a signed-in member (cart, checkout steps, forms): costs hit ratio on a shop with many members, never correctness. A failed MCP write answers HTTP 200 but is not 2xx inside, so it does not count.
