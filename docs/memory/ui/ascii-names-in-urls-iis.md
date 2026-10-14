---
name: ascii-names-in-urls-iis
description: IIS re-encodes non-ASCII URL paths to the ANSI code page so Turkish-named files 404; use pg_ascii_file_name, any name that enters a URL must be ASCII.
---

## Why can a record with a Turkish character in its name not be found by URL?
- IIS decodes the percent-encoded UTF-8 path and passes it to PHP re-encoded in the server's ANSI code page. Measured: the browser sends `%C3%B6`, `REQUEST_URI` receives `f6`, the database value is `c3b6`. No `%` is left in `REQUEST_URI`, so `rawurldecode()` has nothing to do.
- Result: no record whose name has a Turkish character can be found from the URL. The file stays on disk and shows in the panel, but its address gives 404.
- The error is "page not found", NOT "file not found": when the router cannot find the file it goes on to look for a page.

## What fixes it and where is the open gap?
- `prepare_file_name()` solves it with `pg_ascii_file_name()` (Turkish mapping first: the general accent stripper breaks `İ` and does not know `ş`/`ğ`).
- The same gap remains in `prepare_catalog_item_address_name()`: product pages with Turkish addresses 404 the same way. Changing it would affect live links, so it is a separate decision.

## What is the rule?
- When you generate a new name that enters a URL, reduce it to ASCII. Do not trust the server to behave correctly.
