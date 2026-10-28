---
name: db-q-converted-panel
description: Panel screens converted to db_q() (user screens edit_user, add_user, import_users; page screens edit_pages, edit_page): PR, special cases, what stayed raw and why, guard test.
---

## Which panel screens are converted, and what stayed raw?
- User screens (PR #2297): `edit_user.php` 33, `add_user.php` 8, `import_users.php` 8 calls with values (duplicate username and e-mail checks, the role checks, the user delete and its grant tables, the folder, common region, menu, contact group, calendar and ad region grants), `(string)` where the SQL quoted. The folder grant INSERT, the contact group and calendar grants and the contact link wrote ids (`$folder_id`, `$rights`, `$user_id`, `$user['id']`, `$contact_id`) unescaped into quotes: bound as `(string)`, now escaped, same text for an id. The common region grant delete keeps `pg_common_region_not_converted_sql()` after its `?`: an integer list, no `?`.
- Raw stay the user UPDATE of `edit_user.php` (`$sql_role`, `$sql_offline_payment`, `$sql_set_page_types`, `$sql_ws_rights`, `$sql_erp_readonly`) and the user INSERT of `add_user.php` / `import_users.php` (`$sql_password`, `$sql_ws_values`, `$sql_set_page_type_values`, ...): those clauses carry escaped values and may hold a `?`.
- Page screens (PR #2297): `edit_pages.php` 30, `edit_page.php` 30 calls with values (page lookups, bulk folder/style/search/sitemap updates, the page delete and its form, calendar, comment, watcher and short link rows), `(string)` as above; `$attachment['id']` and `$user['id']` were unescaped in quotes. The page read of `edit_page.php` keeps its readiness ternaries (`pg_page_noindex_ready()` ...): column lists without values.
- Raw stay the page UPDATE of `edit_page.php` (`$sql_page_type`, `$sql_home_page`, `$sql_style_fields`, `$sql_noindex`, `$sql_seo_extras`, `$sql_ai_fields`, `$sql_seo_analysis_current`) and the page type table deletes named by `$page_type_table_name` (a bare table name, as #2285 kept bare column names raw).
- `tests/db_q_user_page_screens_test.php` guards all five: no escaped value in a `db_q()` SQL, no value in a raw query outside the clauses above, one value per `?`.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/core/permission-columns.md
