---
name: import-zip-url-resolution
description: How the Import ZIP File screen resolves relative references (url_to_absolute.php), the PCRE2 failure that left imported pages with broken images/CSS/JS, and the resolver's rules
---

## Which resolver does each importer use?
- `import_zip.php` (Design › Import ZIP File, and the folder screen's ZIP modal) gives every archive entry the URL `http://example.com/<entry name>` and keys `$imported_items` by it. `<base href>` and, through `update_imported_items.php` `resolve_url_for_update()`, every reference it rewrites (`img`, `link`, `script`, `source`, `embed`, `object`, `a`, CSS `url()` and `@import`) are resolved with `url_to_absolute()` (third-party RFC 3986 resolver, BSD, `url_to_absolute.php`).
- `import_design.php` (Import My Site) defines its own `resolve_url_for_scan()` (`parse_url()` based); `resolve_url_for_update()` prefers it when it exists, so that importer never reaches `url_to_absolute()`.
- The visual designer's HTML/ZIP import is a different path: `designer-import-validation-theme.md`.

## What was broken, and what was the symptom?
- On PHP 7.3+ (PCRE2) the `split_url()` pattern did not compile: `[a-zA-Z\d-.%]` reads `\d-.` as a range starting at an escape, which PCRE2 refuses ("preg_match(): Compilation failed: invalid range in character class at offset 119"). `url_to_absolute()` returned `false` for every URL, so after Import ZIP File no reference was rewritten to `{path}<name>`: the imported pages showed broken images, unstyled HTML, missing scripts and links to the old file names, and the log filled with that warning. On PHP 8, `url_remove_dot_segments()` also threw a TypeError (`mb_strrpos()` got `'UTF-8'` as its offset). Fixed in #548, guarded by `tests/url_to_absolute_test.php` (RFC 3986 5.4 tables).

## What are the resolver's rules now?
- A hyphen in a character class goes last (`[a-zA-Z\d.%-]`, scheme `[a-zA-Z\d+.-]`).
- Path, query and fragment accept a space and raw bytes 0x80-0xff, so `görseller/ürün 1.jpg` resolves to the same key as the archive entry; dot segments are split on the `/` byte (`explode()`, not a `/u` pattern), so a non-UTF-8 (Latin-1) name resolves too instead of a TypeError. Percent-encoded references are not decoded (`decode` defaults to false): `g%C3%B6rsel.png` does not match the entry `görsel.png`.
- An empty reference resolves to the base; a base without a path (`<base href="http://example.com">`) merges from the root; a path ending in `.` or `..` keeps its trailing slash (`./g/.` -> `.../g/`).
- `join_url()`'s `$encode` branch is unused and its IPv6 pattern is malformed; left alone.
