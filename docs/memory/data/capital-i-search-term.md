---
name: capital-i-search-term
description: Why older panel searches lowercase the typed term with pg_search_lower() (capital İ and U+0307), and why Find & Replace counts, highlights and replaces with one Unicode matcher.
module: text_search
---

## Why does a search term go through pg_search_lower()?
- `mb_strtolower('İ')` is "i" + U+0307 on PHP 7.3+. MySQL LIKE compares one character at a time and the mark matches no stored letter, so "İstanbul" or "İade" found nothing.
- `pg_search_lower()` (`includes/fn/text_search.php`) lowercases and drops U+0307. Used where the term meets `LOWER(column) LIKE`: Themes (`view_themes.php`), Comments (`view_comments.php`), the image picker (`editor_select_image.php`), the link picker's pages, files and short links (`editor_select_page_or_file.php`), Find & Replace's WHERE (`find_and_replace.php`).
- The server-paged lists bind the raw term (`pg_list_like_pattern()`) and never had the bug. `includes/fn/submitted_form_list.php` had the same bug and was left to its own PR.

## How does Find & Replace match in case-insensitive mode?
- One pattern, `pg_search_ci_pattern($find)` = `'/' . preg_quote($find, '/') . '/iu'`, counts (`pg_search_ci_count()`), places the highlighted snippet and replaces (`pg_search_ci_replace()`, replacement through `pg_regex_replacement()` so `$40` stays literal).
- Before, it counted with `substr_count(mb_strtolower())` and replaced with `str_ireplace()`, which folds ASCII only: "Çerez … çerez" with find "çerez" listed 2 and replaced 1; when every match differed in Turkish letter case, Replace said no matches were found.
- PCRE `/iu` does not fold İ to i, so "istanbul" does not count an "İstanbul" row the database returned; the row is skipped, not half-replaced.
- Pinned by `tests/capital_i_search_test.php`.
