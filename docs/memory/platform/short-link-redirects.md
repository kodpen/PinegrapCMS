---
name: short-link-redirects
description: Redirects are short link options (2026.11.0, 11.0.12): answer 0/301/302, old addresses and any letters as names, * patterns tried only at 404, loop checks on every entry point, IIS name recovery.
---

## Why are redirects short links and not a manager of their own?
- Owner decision 2026-10-19: a separate redirect table and screen (PR #654) was closed; the options moved onto short links, which already own the names at the root of the site (`check_name_availability()`).

## How does a short link answer?
- `short_links.redirect_code` 0: as always. A page, product group, product or file is shown at the short address; an address elsewhere is sent on (301 when permanent, 302 when timed or one-time, without the visitor's query string).
- 301 or 302: `Location` to the destination's own address (`pg_short_link_location()`, language prefix kept), the visitor's query string after the destination's own, the tracking code as `t=` first.
- A timed or one-time link answers 302 even when saved with 301 (`pg_short_link_status()`): a browser keeps a 301 for good.
- `router.php` answers an exact name itself (`router_short_link_redirect()`), loading `includes/fn/short_link_redirects.php` alone: the router has no `functions.php` (`router-no-functions-php.md`) and the address builders call no `lang()`.

## What can a name be?
- `pg_short_link_name_problem()`: letters of any language, numbers, `. _ - / [ ] ~ ( ) + , ! = @ *`; at most 191 characters (100 before the step), at most 9 stars, not stars and slashes alone.
- `pg_short_link_name_from_address()`: a pasted full address keeps its path (decoded, base path cut, spaces to `_`, no slash at the ends). Classic screens, the File Manager (`pg_short_link_clean_name()`) and the API use both.
- IIS hands a non-ASCII path re-encoded in the ANSI code page (`ui/ascii-names-in-urls-iis.md`); `router_utf8_name()` reads it back from Windows-1254 (ISO-8859-9 where mbstring lacks it) before the lookup.
- Two names compare like the database (`utf8mb4_unicode_ci`): `pg_short_link_key()`, transliterated and lower case.

## How do * patterns work?
- `is_pattern = 1` for a name with `*`; the router never takes it as an exact name. It is tried only when the page is about to answer 404: `output_error()` and `error()` with 404 and `pg_sw_item_missing()` call `pg_short_link_not_found()`, so a live page always wins. GET and HEAD only, not while the SEO pass renders, not after output started, at most 5000 patterns.
- Most characters outside the stars first, then the oldest. `$1` to `$9` in a URL destination take what each `*` matched, encoded per segment; in an exact name's URL `$n` is plain text.
- A pattern always redirects: 0 is saved as 301.

## What do the screens show?
- `view_short_links.php`: a badge with the answer visitors get (`pg_short_link_status()`) and Wildcard for a pattern; a link to a file shows File and the file name (both cells were empty before).
- Names go into links and QR codes percent-encoded (`encode_url_path()`); a pattern has no Visit link, no QR code, no address in the File Manager and is left out of the editor's link picker.

## What is refused on save?
- `pg_short_link_redirect_columns()` -> `pg_short_link_redirect_problem()` on every entry point: a `$n` beyond the stars (`to_references`), a `$n` in the host of a full address (`to_host`, an open redirect), an address that leads to the link itself (`to_self`), back to it through other links (`to_loop`) or on through more than 10 (`to_chain`). A page, file or the home page ends a chain.
- Before the step nothing is written or checked and names stay at 100 characters.

## What is left?
- The File Manager's short link window keeps the stored answer and offers no choice yet (`view_folders.php`).
- CSV import and export on the short link list (`plans/seo-toolkit-redirects.md`).

Tests: `tests/short_link_redirects_test.php`.
