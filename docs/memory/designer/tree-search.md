---
name: tree-search
description: Site search (simple mode) and Find & Replace read designer page trees and shared component trees: which texts, how a replace writes a tree, what is left out
module: tree_search
---

## Why?
- A page or common region converted to the visual designer keeps its words in a tree, not in `pregion`/`cregion` rows. Simple site search and Find & Replace read only those rows, so a converted site lost its search (open item of `classic-regions-in-designer.md`; plan step LD3, #6257).
- The advanced site search was not affected: `update_search_index.php` indexes the drawn page, shared components included.
- The panel's "where is this used" (file and page usage in the file manager) already scanned `page_tree_code`, `page_tree_json` and `shared_components.tree_json`.

## Which texts of a tree are read?
- `pg_tree_search_texts()` (`includes/fn/tree_search.php`): the node fields the site translation reads, `pg_tr_node_fields()` (content text, semantic text but in `pre`/`code`/script tags, component titles, `alt`, worded attributes such as `title`/`placeholder`), without the `_cond:` operands. Never classes, ids, bindings, bound samples, code nodes, the component name snapshot or a link's address.
- A `shared_ref` has no children in a stored tree; its component is read from its own row.

## What does site search count?
- `get_search_results.php`, simple mode: for each searchable page, beside its page region rows, the words of `pg_page_tree_json()` and of the components it places, nested ones once each (`pg_tree_search_plain_text()`: markup removed, entities decoded). Components are read once per request (`pg_tree_search_components()`, system widgets left out).
- The visual editor's Search Results widget (`_pg_sw_search_collect_one()` in `includes/fn/widgets.php`), simple mode, counts the same words: the tree of `pg_page_tree_sql_expr()` (its own, else its design's) and its components. Before, it read `page.page_tree_json` only, with its own text reader, so a page whose words sat in a placed component, or one still drawn from its design's tree, was not found there. Its classic pages query applies `pg_page_region_read_sql()` like the legacy page: stale region rows of a converted page are no hit and no excerpt (#7688).
- A candidate page is pre-selected with LIKE over its tree, or by placing a component whose words match (`pg_tree_search_matching_components()`, then `pg_tree_search_shared_ref_sql()`: `"sharedId":N` as a number or a string).

## What does Find & Replace do with trees?
- Two scope types: Designer Pages (`page_tree_json` not empty, Recycle Bin left out, link to the page) and Shared Components (`system_region_config IS NULL`, link `edit_system_style.php?component=`). Each matching text is one row of the results.
- Candidate rows: a plain term without `"`, `\` or control characters is pre-selected with LIKE over the JSON (`pg_tree_search_like_safe()`); otherwise, and for a regular expression, every tree is read and the PHP count decides.
- Replace: `pg_tree_search_replace()` changes only those texts; the tree is written whole. A page: `pg_tree_search_save_page()` draws the body again (`generate_style_code_from_tree()`, session messages set aside as in the rebuild), sets modified time and user, a stale SEO score, a revision (`publish`, seeded first when the page has none), `pg_tr_owner_saved()`. A page someone holds in the designer (`pg_collab_may_edit_page('', id)`) is not written and is named in a notice. A component: `tree_json`, `updated_at`, `pg_tr_owner_saved('shared')`.
- A link's address in a tree is not replaced (it is not a text); edit it in the designer.

## Tests?
- `tests/tree_search_test.php`: texts, replace keeps wiring, placed ids, plain text with components, matching components and their SQL, LIKE safety, source pins (the widget's too). The two writers need a database and were not run in a browser.
