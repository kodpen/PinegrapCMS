---
name: search-synonyms-promoted
description: Site search synonyms and promoted results: the two config_text rows, the box formats, pg_search_expand(), how the search widget merges and pins results.
module: site_search_tuning
---

## Where are they kept and edited?
- Two `config_text` rows of JSON, `search_synonyms` (list of word lists) and `search_promoted` (list of `{terms, url, title}`), not the `config` columns #5047 named: `config` takes no new column since 11.0.145 (`data/config-table-no-varchar.md`). No schema step.
- Site Settings › Features › Search Tuning (`includes/settings/features.php`, card `pgset-search-tuning`), saved only when its hidden `search_tuning_card` input was posted (`ui/settings-card-save-rules.md`); an empty box removes its row.
- Synonyms box: one group per line, words or phrases separated by commas. Promoted box: `terms => address` or `terms => address | title`, one per line.
- Every word and term is kept in the rollup's form (`pg_site_search_term()`, `visitors/site-search-rollup.md`): lower case, folded spaces, ≤ 100 characters. Limits: 200 groups of 20 words, 200 promoted lines of 20 terms.
- A promoted address is a path on this site without the site directory (a page name, a product or record address, a query allowed); a scheme, `//`, `..`, a backslash or a space drops the line.

## How does a search use them?
- `_pg_sw_search_collect()` is a wrapper: `_pg_sw_search_collect_one()` (the search itself, the old body) runs for the typed query, then `pg_search_tuning_apply()`.
- `pg_search_expand()` (pure): the typed query first, then the whole query swapped for each other word of its group, then each single word swapped; at most 4 searches. Each extra search runs `_pg_sw_search_collect_one()`; its results go after the typed ones in the same list (featured, catalog, pages), each address once; a cut extra search sets `limited`.
- `pg_search_promoted_for()`: entries one of whose terms equals the whole query. `pg_search_promoted_row()` makes a featured row labelled "Recommended" (`type_label`), title from the line, else the page title, else the address; a page the visitor may not view or left out of site search is no row. Pinned rows go first in `featured` and their addresses leave the other lists.
- A widget searching products only (`search_scope = products`) pins nothing. The legacy Search Results page uses neither.
- The rollup counts the result total after both (`pg_site_search_count()`), so a synonym that finds pages keeps a term out of zero results.
