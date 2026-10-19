---
name: ascii-names-in-urls-iis
description: IIS re-encodes non-ASCII URL paths to the ANSI code page so Turkish-named files 404 (pg_ascii_file_name); Windows drops a trailing dot, so no stored file name ends in one
---

## Why can a record with a Turkish character in its name not be found by URL?
- IIS decodes the percent-encoded UTF-8 path and passes it to PHP re-encoded in the server's ANSI code page. Measured: the browser sends `%C3%B6`, `REQUEST_URI` receives `f6`, the database value is `c3b6`. No `%` is left in `REQUEST_URI`, so `rawurldecode()` has nothing to do.
- Result: no record whose name has a Turkish character can be found from the URL. The file stays on disk and shows in the panel, but its address gives 404.
- The error is "page not found", NOT "file not found": when the router cannot find the file it goes on to look for a page.

## What fixes it and where is the open gap?
- `prepare_file_name()` solves it with `pg_ascii_file_name()` (Turkish mapping first: the general accent stripper breaks `İ` and does not know `ş`/`ğ`).
- The same gap remains in `prepare_catalog_item_address_name()`: product pages with Turkish addresses 404 the same way. Changing it would affect live links, so it is a separate decision.

## Why does no stored file name end in a dot?
- Windows drops the dots at the end of a file name: `photo.jpg.` is written over `photo.jpg`, while the record keeps `photo.jpg.`, so the name check (`check_name_availability()`, records only) sees no clash. Two records then share one file, and a basic user could store the signing key as `dkim.key.` (`platform/dkim-key-in-file-manager.md`).
- `prepare_file_name()` drops them last, after the length limit (which can leave one) and after the upload rule (`shell.php.` is still `shell_php`). #620.

## What is the rule?
- When you generate a new name that enters a URL, reduce it to ASCII. Do not trust the server to behave correctly.
