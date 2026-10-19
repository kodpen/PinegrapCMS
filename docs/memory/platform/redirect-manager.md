---
name: redirect-manager
description: Redirect manager (2026.11.0, 11.0.12, PR #654): redirects table, matched only where a page request is about to 404, address rules, wildcards, refusals, screens, CSV, what is not built yet
---

## Where is a rule matched?
- `router.php` defines `PG_REDIRECT_LOOKUP` just before its last hand-over to `get_page.php` (files, robots.txt, the sitemap and short links never get there). The router cannot tell a missing page from a page, so the rule is asked where the 404 is decided: `output_error()` and `error()` with 404 (page not found, classic catalog item, form item view) and `pg_sw_item_missing()` (designer widget with no product or record for the address). All call `pg_redirect_not_found()` (`includes/fn/redirects.php`).
- A live page, file or short link therefore always wins; a rule for its address waits (the edit screen warns: `pg_redirect_address_taken()`).
- Only GET/HEAD, once per request, never while the SEO pass renders or after output started. Failed queries are logged, the 404 goes on.

## How are addresses compared?
- `pg_redirect_normalize_path()`: path below `PATH`, decoded (kept raw when not UTF-8), single slashes, no trailing slash, leading slash, no query. Only `http(s)://` makes a full address; `//a/b` is a path (a scanner's `//x/old` must not match `/old`).
- Case-insensitive: `from_hash` = SHA-1 of the lower-cased path, the unique key (a UNIQUE on a long utf8mb4 VARCHAR passes 767 bytes on older servers).
- The language prefix is part of the path (`/en/old` and `/old` are two rules), matched from `REQUEST_URL`.
- Exact rule first; then patterns (`*` = any text, slashes too), most literal characters first, then oldest. `$1`..`$9` take the stars, encoded one segment at a time. The visitor's query string goes on after the target's own, before its fragment. Location is built on `URL_SCHEME . HOSTNAME_SETTING . PATH` for site paths.

## What does saving refuse?
- `pg_redirect_rule_problem()` (codes in `tests/redirects_test.php`): empty or control characters, a `?` in the old address (matched without query), the home page `/` (it always answers), more than 9 stars, a target that is not `/path` or `http(s)://` (so `//x`, `/\x`, `javascript:` fail), `$n` beyond the stars, code not 301/302, a second rule for the address, the address to itself (a pattern whose sample target matches itself), a loop through other rules, a chain over 10 hops. The home page ends a chain (`/*` → `/` is allowed).
- Messages are plain text; screens `h()` them (liveform prints errors, notices and warnings raw).

## Which screens and files?
- `view_redirects.php` (list, Delete Selected, `?export=csv`), `edit_redirect.php` (create/edit/delete, `?from=` prefills), `import_redirects.php` (5 MB, 10,000 lines). Manager and above, like short links; Pages menu, command palette, help anchors `pages-cont#…`.
- CSV `from,to,code`: comma, or `;`/tab when the first line has no comma; BOM dropped, non-UTF-8 read as Windows-1254; first non-empty line skipped when it is not an address; code empty = 301. Import checks each line against the rules and the lines before it; an existing address is skipped unless "replace". Export writes RFC 4180 with a BOM.
- Hits and `last_hit` count redirects sent; changing the old address resets them.

## What is not built yet?
- `not_found_log` exists (same step) but nothing writes it: plan step 3. Slug-change rows (step 2) and `/redirects` API (step 6) are in docs/memory/plans/seo-toolkit-redirects.md.

## How was it verified?
- Sandbox (MariaDB, built-in server): the step twice (created, then "already exists"), the screens over HTTP and in headless Chromium, the redirects above (pattern, UTF-8, query carried, HEAD, POST not redirected, classic catalog miss, page wins), import of a Windows-1254 `;` file. `.html` addresses need the router script on PHP's built-in server.
