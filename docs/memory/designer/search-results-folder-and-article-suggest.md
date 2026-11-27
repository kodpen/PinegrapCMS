---
name: search-results-folder-and-article-suggest
description: The Search Results widget's "Search only in" folder (search_folder_id) and the public article_suggest.php that a form field set to "Offer articles from" calls as the visitor types (help centre HC2)
module: site_search
---

## What does "Search only in" do?
- A setting of the Search Results widget, under "Search scope": cfg `search_folder_id`, read by `pg_search_folder_setting()` (`includes/fn/site_search.php`); 0 or missing is the whole site, as before.
- Set, `_pg_sw_search_collect_one()` (`includes/fn/widgets.php`) searches that folder and its sub-folders only, always from the search index (`search_items`, the advanced search) whatever Site Settings › Search says: a collection's records, such as help articles, are only there.
- No catalog and no promoted (pinned) results in a folder search; the site's synonyms still apply. Translated pages and records are kept only inside the folder's branch.
- Visitors (signed out or role 3) see only the folders they may view: `pg_search_narrow_folders($subtree, $visible)` intersects the branch with them; staff get the whole branch. Nothing left finds nothing.
- The designer's select lists the folders as a tree (`_fetchFolders()`, `_swFolderOptions()`, as the Photo Gallery's Folder); while one is chosen the product rows of the settings are hidden.

## What does article_suggest.php answer?
- Root, public, GET only (405): `?folder=<id>&q=<text>`; no folder is 400. JSON `{"results": [{"title", "url", "excerpt"}]}`, `Cache-Control: private, no-store`, `X-Robots-Tag: noindex`.
- The search is the widget's: `_pg_sw_search_collect($q, ['search_scope' => 'pages', 'search_folder_id' => $folder])`, so the same folder and visitor fences. `pg_article_suggest_rows()` keeps featured then other results, each address once, no files, at most `PG_ARTICLE_SUGGEST_ROWS` (5), excerpt cut to 160 characters.
- Text under three characters (`pg_article_suggest_query()`) gives an empty list without searching or counting.
- Rate: `pg_article_suggest_throttled()`, 120 per 10 minutes per `waf_ip_subject()` (`waf_rate` scope `article_suggest`), working with the firewall off; past it 429, logged as `rate-article-suggest` in `waf_log` and `data/firewall.log`, no offence.
- A question is not counted as a site search (no rollup, no visitor term): it is typing, not a search.

## How does a form field call it?
- A text box, search box or text area of a custom form that is not office-use-only has "Offer articles from" in its Form Field panel: `_cf.suggest_folder` (folder id; "— Off —" removes it).
- At render `pg_cf_mark_article_suggest()` (`includes/fn/custom_form.php`) gives such a control `data-pg-suggest="…/article_suggest.php?folder=<id>"` and `autocomplete="off"`; not in the editor's Preview, an e-mail or the SEO pass. `pg_cf_article_suggest_script()` is printed once inside the form.
- The script asks after a 350 ms pause and lists the answers under the control as a Bootstrap 5 `list-group` under "These articles may answer your question:", each link in a new tab so the form keeps what was typed; a late answer for older text is dropped; links not starting with `/` are skipped.
- HC3 (a form opens a support request) is meant to pair this with its subject field; the setting itself works on any form.

## Tests?
- `tests/help_article_search_test.php`: the setting, the narrowing, the query and rows, the marking, and the call sites read from the source.
