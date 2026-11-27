---
name: import-redirects
description: Content import redirects (2026.11.0): which items keep their old address as a 301 short link, destinations, refusals, report line, CSV download, undo
module: importers
---

## Which items get a redirect?
- After a made item `pg_import_run()` calls `pg_import_redirect_after()` (`includes/import/write_redirect.php`), which answers one line per redirect.
- The old addresses (`pg_import_redirect_old_addresses()`, pure): a WordPress or Squarespace post or page by its link (`data.link`; a `?p=` address has no path and gets none), a Shopify product at `/products/<handle>` (the handle from the source key), a Squarespace product at both its addresses (`data.addresses`, `import-squarespace.md`), a Webflow product at `/product/<handle>` (`data.addresses`, `import-webflow.md`), one name once.
- The WooCommerce products export carries no address, files and contacts have none; categories are names in the exports.
- The first redirect's `import_items` key is `redirect:<item key>`, a later one's ends in `:<n>` (`pg_import_redirect_source_key()`, cut to 191 characters with the number kept).
- The name is the old path through `pg_short_link_name_from_address($address, '/')`: the old host dropped, decoded, stored form, so a visitor's `/2020/01/post/` finds it (`short-link-names.md`).

## Where does it lead?
- Page: destination page (it follows a later rename; an imported page is a draft until it is published). Product: destination product on the site's first `catalog detail` page; none is skipped with a reason. Record: a URL to its address on the form's first record page (pretty name, else `?r=<code>`); an incomplete record gets no redirect.
- 301, link mode permanent, `created_user_id` the importing person.

## When is none added?
- A page made under its old address (a root WordPress page `/about/` becomes page `about`): `pg_import_redirect_is_own_address()` (pure, case ignored) returns no redirect and no line. Before, the report listed it as "in use on this site already".
- `pg_short_link_name_problem()`, a `*` in the path (it would be a pattern), a name any page, file or link holds (`check_name_availability()`: nothing is replaced), the loop checks (`pg_short_link_redirect_columns()`), before 11.0.12. Each is a report line of kind Redirects with the reason; the counts have a `redirect` row.

## Where are they listed and how do they go?
- The report card Redirects names the count and downloads them as `import.php?id=<n>&export=redirects` (`pg_short_link_csv()`, the short links screen's from,to,code format). They are ordinary short links on that screen too.
- Each is an `import_items` row kind `redirect` written right after its item, so undo (newest first) deletes the link before its item; a page or product delete removes its links itself as well. `pg_import_write_redirect()` takes a reader's own redirect items (`from`, `to`; Webflow's redirects file); one with `data.error` is skipped with that reason, which `pg_import_precheck_redirect()` gives the dry run.

Tests: `tests/import_redirect_test.php`.
