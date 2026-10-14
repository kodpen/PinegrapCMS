---
name: db-helpers-and-error-contract
description: db()/db_items()/db_value()/e()/escape()/escape_like() helpers, singular table names, and why queries return false instead of throwing (mysqli_report OFF).
---

## Which DB helpers exist?
- `db('SQL')` runs a query. `db_items('SQL')` returns a result array. `db_value('SQL')` returns a single value.
- `e($val)` = SQL escape (`mysqli_real_escape_string`); `escape($val)` = same (old alias); `escape_like($val)` = escape for LIKE queries.
- Table names are SINGULAR: `page` (not pages!), `style`, `pregion`, `cregion`, `dregion`, `forms`, `form_data`.
- `products_images_xref` has NO `id` column: `ORDER BY id` is a fatal error.

## Does a failed query throw?
- No: a query error returns `false`, it does not throw. `mysqli_report(MYSQLI_REPORT_OFF)` is set in `install/index.php`, `router.php` and `init.php`, so front end, panel, `api.php` and scheduled jobs share one contract. THESE LINES MUST NOT BE REMOVED. To see an error read `mysqli_error(db::$con)`.
- Why: the codebase is written to this contract: 96 places use `mysqli_query(...) or exit(...)`, others `if (!$result)` or a leading `@`. Since PHP 8.1 the mysqli default reports errors as EXCEPTIONS; none of those checks sees an exception, so every SQL error became an uncaught fatal: an empty-body 500, cause only in error.log.
