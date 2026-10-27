---
name: db-q-converted-comments-search
description: Users list, user delete, account profile, comment add/edit, site search and sitemap files converted to db_q(): PR, special cases, what stayed raw and why, guard test.
---

## Which files are converted, and what stayed raw?
- PR #2318: `view_users.php` 5, `delete_users.php` 9, `my_account_profile.php` 4, `add_comment.php` 12, `edit_comment.php` 7, `get_search_results.php` 5, `get_sitemap_info.php` 4 calls with values, `(string)` where the SQL quoted. Many wrote values unescaped into quotes (`$id`, `$user_id`, `$contact_id`, `$file_id`, the comment counters, `$collection`, the visitor id, the sitemap page ids, `REGISTRATION_CONTACT_GROUP_ID` beside the contact id): bound as `(string)`, now escaped, same text for an id. SQL held in `$query2` (users list) or `$db_query` (search) is bound the same way.
- The search's two LIKE filters bind `'%' . escape_like($query) . '%'`: escaped once, as `escape(escape_like())` did.
- Pieces that hold only integers or fixed column text stay interpolated in a `db_q()` SQL, as they cannot hold a `?`: the comment INSERT's `$sql_review_*` / `$sql_parent_*` (`(int)` values), the sitemap's `$sql_*_noindex` column names, the search's `$featured_page_id_list` (`get_page_id_list_for_sql()`, integers).
- Raw stay: the users list counts and list (`$where`, `$order_by`, `$sql_from_table`, ...), the account profile contact UPDATE (`$sql_profile_set`, bare ids) and user UPDATE (`$sql_timezone`), the registration group check (constant only), the comment UPDATE (`$sql_file_id`, `$sql_seller_reply`), the search's page list with only the integer list, the sitemap's calendar events (`$sql_calendar_condition`) and the calls without values.
- `tests/db_q_users_comments_search_test.php` guards all seven: no escaped value in a `db_q()` SQL (also through `$query2` / `$db_query`), no value in a raw query outside the clauses above, one value per `?`.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/comments-table.md
