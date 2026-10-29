---
name: speculation-prefetch
description: Speculation Rules prefetch of menu links (2026.11.0, 11.0.127, #3184): what the block lists, what is left out, who gets none, the Features switch, counting and the page cache
module: speculation
---

## What does a page get?
- `includes/fn/speculation.php`. One `<script type="speculationrules">` before `</head>`: a `prefetch` rule, `source: list`, `eagerness: moderate` (a 200 ms hover or a pointer press), at most 20 URLs (`PG_SPECULATION_MAX_URLS`). No document rule and no prerender (decision #3184). Only Chromium browsers act on it.
- Spliced by `pg_speculation_rules_inject()` in `get_page_content.php` after the smart active pass, outside edit mode and e-mail. That one place serves designer and classic pages; `includes/fn/output.php` and `includes/fn/designer.php` are not touched.

## Where do the addresses come from?
- Menus note what they draw with `pg_speculation_note()`: `get_menu_content()` (classic `<menu>`, after its access check) and the Menu widget (`_render_system_widget_menu_region()`, the rows of `pg_sw_list_menu_rows()`). Navbar links of designer pages are read from the finished HTML: `<a>` with class `nav-link` and no `data-bs-toggle` (`pg_speculation_nav_hrefs()`). Other links of the page are never listed.
- `pg_speculation_take()` hands the noted ones over and forgets them, once per page.

## What is left out?
- `pg_speculation_path()`: anchors, other schemes, other hosts (allowed: `HOSTNAME`, `HOSTNAME_SETTING`), relative paths, any `?`, paths outside `PATH`, the software directory (panel, `get_file`, sign-out), a last segment with a file extension other than `.html`/`.htm`, dot segments.
- Then one query reads the named pages and the home page: only an existing page whose folder is `public` and whose type `pg_speculation_page_type_kept()` lists (standard, folder view, photo gallery, catalog, catalog detail, calendar view, calendar event view, form list/item view, form view directory, custom form). Sign-in, sign-out, registration, cart, order, checkout, account and password pages, search results and any type added later are left out. The page shown is left out.

## Who gets no block?
- `pg_speculation_rules_wanted()`: switch off, a signed-in member or staff (`USER_LOGGED_IN`, unknown counts as signed in), a page drawn in another language (`FRONTEND_LANGUAGE`: the language prefix is added to links only in `pg_tr_finalize()`, so the list would name the wrong address). A page already carrying `speculationrules` (operator code) gets no second one.

## Where is the switch?
- `config.speculation_prefetch` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 (`upgrade_2026_11_0_speculation_prefetch()`), `SPECULATION_PREFETCH` in `init.php` (0 while the column is missing). Settings › Features, Feature Options card, "Prefetch Menu Links", drawn and saved only once the column exists. Running sites get it on.

## How are prefetches counted and cached?
- Counted once as a page view and session, as any request (decision #3184, point 3): a followed prefetch is served from the browser's cache with no second request. The not-found log still skips prefetch requests (`not-found-log.md`). Signed-in pages never carry the block, so a cached anonymous page (`page-file-cache.md`) carries the same block for everyone.

Tests: `tests/speculation_rules_test.php`. The stock config row test (`tests/innodb_test.php`) counts the column.
