---
name: widget-search-like-pattern
description: How the form data list search and the simple site search of the system widgets build their LIKE pattern, and the backslash fault #5160 fixed
module: widgets
---

## How do the widget searches match the typed text?
- The form data list search (`fd_q.data LIKE …` in `includes/fn/widgets.php`) and the simple site search (`$like`: regions, page titles, descriptions, keywords, page trees, comments) compare with `LIKE` on `e(pg_list_like_pattern($term))`: a typed backslash, `%` or `_` is a plain character.

## What did #5160 fix?
- Both built `'%' . e(escape_like($term)) . '%'`. `escape_like()` then escaped `%` and `_` but not the backslash (it does since #5935), so a backslash typed into the box escaped the next pattern character: `C:\temp` looked for `C:temp`, and a lone `\` found only values ending in `%`.
- `tests/widgets_search_like_test.php` keeps `escape_like(` out of the module and checks both searches use `pg_list_like_pattern()`.

## What does the form data list search read on a page in another language?
- A list with `translate_records` on also finds a record whose translated texts, in the fields the box searches, hold every typed word (TS1's words, ı and i as one letter): `pg_tr_search_list_records()` gives the ids, joined to the source `EXISTS` as `OR forms.id IN (…)` in `$search_where`, so the count and the paging read the same condition (#TS4PR).
- In the source language, and for a list that does not translate its records, nothing changes.
