---
name: short-link-names
description: Short link names (2026.11.0): the name rule, a pasted old address as a name, how a request finds its link (as it came, then in the stored form), IIS name recovery, how two names compare.
---

## What can a name be?
- `pg_short_link_name_problem()`: letters of any language, numbers, `. _ - / [ ] ~ ( ) + , ! = @ *`; at most 191 characters (100 before 11.0.12), at most 9 stars, not stars and slashes alone, no `.` or `..` part.
- `pg_short_link_name_from_address()`: a pasted full address keeps its path (decoded, base path cut), then `pg_short_link_path_name()`: spaces to `_`, runs of `/` to one, no `/` at the ends. Classic screens, the File Manager (`pg_short_link_name_read()`: the same error text), the API and the CSV import use both.

## How does a request find its link?
- `router.php` looks the decoded path up as it came, then, when it differs, in the stored form (`pg_short_link_path_name()`, the router loads the module with `router_short_link_module()`): `/blog/post/`, `/Old%20Page`, `//post` reach `blog/post`, `Old_Page`, `post`.
- Symptom before (#1006): the pasted old address itself answered 404 (a WordPress permalink ends in `/`; an address with a space or `//`), only the `_` spelling or the one without the slash worked, from every save path and the CSV import alike.
- Patterns at 404 (`pg_short_link_not_found()` -> `pg_short_link_match_in()`): each pattern in its turn, the path as it came first, then the stored form; `$n` takes what the matching form gave.
- The loop checks walk `pg_short_link_match_in()` too and compare the link's own name in both forms, so an address that reaches a link only through its stored form counts as reaching it.
- IIS hands a non-ASCII path re-encoded in the ANSI code page (`ui/ascii-names-in-urls-iis.md`); `router_utf8_name()` reads it back from Windows-1254 (ISO-8859-9 where mbstring lacks it) before the lookup.

## How do two names compare?
- As the router's `WHERE name = '…'` does: `pg_short_link_key()` is the database's weight string of the name under the column's collation (`pg_short_link_name_collation()`, `SHOW FULL COLUMNS`), a hundred names per query (`pg_short_link_keys()`), kept for the request. PHP does not imitate the collation.
- Under `utf8mb4_unicode_ci`: `ı`/`i`, `ø`/`o`, `ł`/`l`, `æ`/`ae` are two names; `ę`/`e`, `ș`/`s`, `ő`/`o`, `ß`/`ss`, case are one.
- Symptom before (#PR832): keys were `pg_transliterate_to_ascii()` + lower case, so `hakkimizda` -> `/hakkımızda` was refused as "its own address", the CSV import skipped or overwrote `kılıf` for `kilif`, and `święta` -> `/swieta` was saved as an endless self-redirect.
- Without a connection (the test runner) `pg_short_link_fold()`: NFKD without marks, lower case (intl), only lower case without intl.

Tests: `tests/short_link_redirects_test.php`. PRs: #699, #1006.
