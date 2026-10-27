---
name: db-q-converted-editors-explorer-waf
description: Theme and file editors, custom style, explorer panel, page form and firewall log screen converted to db_q() (PR #2333): special cases, what stayed raw and why, guard test.
---

## Which files are converted, and what stayed raw?
- PR #2333: `edit_theme_file.php` 9, `add_theme_file.php` 5, `edit_file.php` 7, `edit_custom_style.php` 6, `includes/panel/explorer.php` 9, `includes/forms/page_form.php` 3, `view_waf_log.php` 5 calls with values. Values the old SQL quoted are passed `(string)` (also `$user['id']`, `$style_id`, `$theme_id`, the `(int)` file size and image sizes of the rotate update); the bare numbers of `view_waf_log.php` (`$start_timestamp`, `time()`) are passed `(int)`. The SQL text sent stays the same.
- `edit_file.php` kept the escaped `$file_id = escape($_POST['id'])` for its other users (audit trail, `db()` calls, the raw updates); its converted queries bind the posted id itself, `(string) ($_POST['id'] ?? '')`, so it is escaped once.
- The explorer folder size reads keep their own variables (`$parent_folder_query`, `$parent_folder_page_query`, `$parent_folder_file_query`); only the call changed.
- Raw stay the queries without values, the system theme CSS rule INSERTs of `add_theme_file.php` (bare insert id `$theme_id` in a long literal, and `$system_theme_properties_for_sql`), the file UPDATE and copy INSERT of `edit_file.php` (`$sql_design`, `$sql_size`), the style UPDATE of `edit_custom_style.php` (`$sql_social_networking_position`), and in `view_waf_log.php` the event and summary reads (`$where`, filter values escaped there) and the false-positive signs (`$in_list`, `$quoted_v4`).
- `view_waf_log.php` is a panel screen loaded through `init.php`; the firewall itself (`waf.php`) runs before `functions.php` and keeps its own queries.

## What guards it?
- `tests/db_q_contacts_forms_test.php` (shared with `db-q-converted-contacts-forms.md`) lists every file in `db_q_contacts_forms_test_files()` and the clause variables in `db_q_contacts_forms_test_built()`. It reads the last assignment of any `$query` or `$…_query` variable used as the SQL.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/db-q-converted-contacts-forms.md
