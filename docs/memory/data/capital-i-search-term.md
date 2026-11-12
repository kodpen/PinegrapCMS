---
name: capital-i-search-term
description: Why older panel searches lowercase the typed term with pg_search_lower() (capital İ and U+0307), and why Find & Replace counts, highlights and replaces with one Unicode matcher.
module: text_search
---

## Why does a search term go through pg_search_lower()?
- `mb_strtolower('İ')` is "i" + U+0307 on PHP 7.3+. MySQL LIKE compares one character at a time and the mark matches no stored letter, so "İstanbul" or "İade" found nothing.
- `pg_search_lower()` (`includes/fn/text_search.php`) lowercases and drops U+0307. Used where the term meets `LOWER(column) LIKE`: Themes (`view_themes.php`), Comments (`view_comments.php`), Find & Replace's WHERE (`find_and_replace.php`).
- The server-paged lists bind the raw term (`pg_list_like_pattern()`) and never had the bug. `includes/fn/submitted_form_list.php` had the same bug and was left to its own PR.

## How does Find & Replace match in case-insensitive mode?
- One pattern, `pg_search_ci_pattern($find)` (the quoted term with `/iu`), counts (`pg_search_ci_count()`), places the highlighted snippet and replaces (`pg_search_ci_replace()`, replacement through `pg_regex_replacement()` so `$40` stays literal).
- Before, it counted with `substr_count(mb_strtolower())` and replaced with `str_ireplace()`, which folds ASCII only: "Çerez … çerez" with find "çerez" listed 2 and replaced 1; when every match differed in Turkish letter case, Replace said no matches were found.
- PCRE `/iu` pairs i with I only, so "istanbul" counted no match in an "İstanbul" row the database returned and the row was skipped (bug hunt fix). Each i-letter of the term now becomes a class with caseless mode off: `i`/`İ` → `[iIİ]`, `ı` → `[ıI]`, `I` → `[Iiıİ]`. A dotless ı never finds a dotted i ("kır" is not "kir"); a capital I is ambiguous and finds all four (#5881: "ISTANBUL" counted no "İstanbul").
- The row filter takes `pg_search_like_term()`: each capital I and dotless ı of the term is the one-letter wildcard `_`, the rest goes through `pg_search_lower()` and `escape_like()`. `LOWER()` never folds ı to i, so "IRMAK" (lowercased "irmak") fetched no "ırmak" row (#5881); the pattern then counts only real matches.
- Pinned by `tests/capital_i_search_test.php`.
