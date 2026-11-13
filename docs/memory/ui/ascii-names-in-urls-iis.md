---
name: ascii-names-in-urls-iis
description: IIS hands non-ASCII paths over in the ANSI code page: file names made ASCII, the request address read back as UTF-8 (pg_request_uri_utf8); no stored file name ends in a dot
---

## Why can a record with a Turkish character in its name not be found by URL?
- IIS decodes the percent-encoded UTF-8 path and passes it to PHP re-encoded in the server's ANSI code page. Measured: the browser sends `%C3%B6`, `REQUEST_URI` receives `f6`, the database value is `c3b6`. No `%` is left in `REQUEST_URI`, so `rawurldecode()` has nothing to do.
- Result: no record whose name has a Turkish character can be found from the URL. The file stays on disk and shows in the panel, but its address gives 404.
- The error is "page not found", NOT "file not found": when the router cannot find the file it goes on to look for a page.

## What fixes it?
- `prepare_file_name()` solves it with `pg_ascii_file_name()` (Turkish mapping first: the general accent stripper breaks `İ` and does not know `ş`/`ğ`).
- `prepare_catalog_item_address_name()` transliterates with `pg_transliterate_to_ascii()` and turns what is left outside `A-Za-z0-9[]_-` into `_`. It runs only when an address is built (new product, changed Catalog Name box), so an address stored earlier keeps its letters.

## Why does no stored file name end in a dot?
- Windows drops the dots at the end of a file name: `photo.jpg.` is written over `photo.jpg`, while the record keeps `photo.jpg.`, so the name check (`check_name_availability()`, records only) sees no clash. Two records then share one file, and a basic user could store the signing key as `dkim.key.` (`platform/dkim-key-in-file-manager.md`).
- `prepare_file_name()` drops them last, after the length limit (which can leave one) and after the upload rule (`shell.php.` is still `shell_php`). #620.

## Is the request address UTF-8?
- Yes. `pg_request_uri_from_server()` (`includes/fn/request_scheme.php`) gives it back through `pg_request_uri_utf8()`: path and query string read apart, a part that is not UTF-8 read from Windows-1254 (ISO-8859-9 without it). So `REQUEST_URL` and `get_request_uri()` are UTF-8, and `router.php` writes the same back into `$_SERVER['REQUEST_URI']` for the widgets that read it for a back address. `router_utf8_name()` is the same reading for one name.
- Symptom before (#8983): on `/pg-ÇĞİÖŞÜ` the edit mode switch, the toolbar frame and every `send_to` built with `urlencode(REQUEST_URL)` wrote `%C7%D0%DD…`; the browser read the bytes back as Latin-1 (`ÇÐÝ…`), a 404 and a 404 log line per press. `h()` of those bytes is empty, so Edit Page lost its `send_to`. `Ç Ö Ü` survived: the same byte in both code pages.
- A redirect carries the address percent-encoded (`pg_uri_ascii()`, through `pg_safe_redirect_path()` and `go()`): with the raw UTF-8 in `Location` the browser read it as Latin-1 and IIS answered 400 (#8983 reopened).

## What is the rule?
- When you generate a new name that enters a URL, reduce it to ASCII. Do not trust the server to behave correctly.
