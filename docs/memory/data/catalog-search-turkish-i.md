---
name: catalog-search-turkish-i
description: Product and product group search (site search, catalog search) folds I, İ, ı and i as one letter through pg_catalog_search_condition(); why, what it reuses, the issue it fixed.
---

## How does the product search compare a word?
- `get_catalog_search_results_for_field()` (`includes/fn/content.php`) builds each column's condition with `pg_catalog_search_condition($column, $operator, $term, $charset)` and runs it with `db_q()`.
- 'contains' is `pg_list_search_where()` with the one whole term: the plain `LIKE`, a `turkish_ci` LIKE for a word with I/ı, and the REPLACE fold of the column against `pg_list_search_fold()` of the word, the same SQL the panel lists use.
- 'is equal to' (exact name match, ranked first) is `col = ?` OR the folded column `= ?` folded word, when the word has an i letter.
- The charset is `pg_list_search_connection_charset()`, with no language check: folding only adds matches, and a visitor types capitals or without a Turkish keyboard on any site.

## Why?
- `utf8mb4_unicode_ci` folds I with i but keeps ı apart: "LAMBASI" and "lambasi" missed "Masa Lambası", "Işık" found nothing and "IŞIK" found "bisikleti" (issue #4454). Page results had been fixed in the fulltext path (`pg_search_i_variants()`, PR #4359), products had not.
- Pinned by `tests/catalog_search_fold_test.php`.
