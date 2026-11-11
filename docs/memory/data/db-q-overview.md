---
name: db-q-overview
description: Start file for the db-q-* files: db_q() and pg_db_bind() (? placeholders, false on failure), the rule for new code, the converted areas grouped, what stays raw, how a file is converted
---

## What is it?
- `db_q($sql, $params)` runs a query whose values are `?` placeholders, filled by `pg_db_bind()` (`includes/fn/core.php`). It returns the mysqli result, or `false` like `mysqli_query()`, so a raw call switches without changing what it does on failure. How each `?` is filled, what is refused, the new-code rule: `db-q-placeholder-helper.md`.
- For developers only: no screen shows it. The other helpers, which end the request on failure: `db-helpers-and-error-contract.md`.

## Which decisions bind it?
- No owner decision. The rule is a working rule, the folder's first key rule (`data/README.md`): new code with values uses `db_q()`, old code switches when a file is touched for another reason.

## Which areas are converted?
- Each `db-q-converted-*` file names its files, the PR, the special cases, what stayed raw and the test that guards it.
- Content and storefront:
  - The page and content files: `db-q-converted-files.md` (also the rules below).
  - Front-end screens: `db-q-converted-front.md`, `db-q-converted-small-front.md`, `db-q-converted-order-front.md`.
  - Catalog, gallery and custom form: `db-q-converted-catalog-forms.md`; users, comments, site search and sitemap: `db-q-converted-comments-search.md`.
- Commerce:
  - Cart, checkout, order and product screens: `db-q-converted-commerce.md`, `db-q-converted-commerce-tail.md`.
  - Shipping, zones and product import: `db-q-converted-shipping-import.md`; tax zones, currencies and payment jobs: `db-q-converted-zones-currencies-jobs.md`.
  - Page type records and duplicate products: `db-q-converted-page-type-records.md`.
- Panel screens:
  - Users and pages: `db-q-converted-panel.md`; folders, page copy, image editor, theme designer: `db-q-converted-panel-content.md`.
  - Pages, regions, menus, themes: `db-q-converted-pages-regions-themes.md`, `db-q-converted-panel-menus-calendars.md`, `db-q-converted-panel-styles.md`; editors, explorer and firewall log: `db-q-converted-editors-explorer-waf.md`.
  - Contacts and forms: `db-q-converted-contacts-forms.md`; e-mail campaigns: `db-q-converted-campaign-screens.md`, `db-q-converted-campaigns-visitor-report.md`.
- Functions and system:
  - Sign-in, access, contact merge, designer assets: `db-q-converted-access.md`; small functions (queues, passkeys, consent log): `db-q-converted-calendars-small-functions.md`.
  - Logs, price history, backup restore: `db-q-converted-core-logs.md`; short links, audit trail, sessions: `db-q-converted-logs-short-links-system.md`.
  - Visitor statistics, output and settings: `db-q-converted-visitor-settings.md`; the dashboard: `db-q-converted-dashboard.md`; installer, pickers, Flows and the rest: `db-q-converted-tail.md`.

## What stays raw?
- A query without values, one completed by a clause built elsewhere with escaped values (it may hold a `?`), an integer-only IN list, an unbuffered read (`MYSQLI_USE_RESULT`): `db-q-converted-files.md`, `db-q-placeholder-helper.md`.
- Files loaded before `functions.php` (`router.php`), `get_file.php` with its own db copies, `includes/authentication.php` (`db-q-converted-tail.md`).

## How is a file converted?
- Values go in as `(string)` where the old SQL quoted them and `(int)` where it wrote a bare number, so the SQL sent stays the same; a value already escaped goes in unwrapped (`db-q-converted-files.md`).
- Keep the caller's failure check: `db_q()` answers `false`, it does not end the request.
- Add the file to a `tests/db_q_<area>_test.php` guard, which lists the converted files and the clause variables allowed to stay raw, and write what it kept raw into the matching `db-q-converted-*` file (or a new one, added here).
