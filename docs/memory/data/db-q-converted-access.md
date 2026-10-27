---
name: db-q-converted-access
description: Sign-in, access check, contact merge and designer asset files converted to db_q() (fn/auth, fn/contacts, fn/designer, the forms.php clauses): PR, special cases, what stayed raw, guard test.
---

## Which files are converted, and what stayed raw?
- `includes/fn/auth.php` (PR #2296): its 14 calls with values (device list and device limit of `auth_tokens`, `validate_username()`, `validate_user()`, the contact, contact group, calendar and folder access checks, `select_style()`, `no_acl_check()`, the developer PIN lookup), `(string)` where the SQL quoted, also for ints the old SQL quoted (`(string) (int) $_SESSION['sessionuserid']`, `(string) time()`, `(string) USER_ID`). The developer PIN lookup passed a `mysqli_real_escape_string()` value: the username now goes in bare, escaped once. Raw stay the four folder and style lists without values.
- `includes/fn/contacts.php` (PR #2296): all 17 calls of `merge_contacts()`. The OR lists (contacts to read, orphans to delete from `contacts`, from the xref and `opt_in`) hold `?` with a value array beside them, reset where the list is reset; the xref list's values serve both DELETEs. The remaining orphan's UPDATE writes its column names (`$key`, from the row) with `= ?` and passes `array_merge()` of their values and the fixed ones.
- `includes/fn/designer.php` (PR #2296): the three `files` queries of `pg_designer_sync_asset_files()`; the name and extension go in bare instead of `mysqli_real_escape_string()`. Errors still return false silently (`db_q()` runs `@mysqli_query()`). Raw stay the column probes and lists without values.
- `includes/fn/forms.php` clauses (PR #2296): `select_page()`, `get_ad_region_options()`, `get_number_of_contacts()` (`$where`) and `select_field_position()` (`$form_type_filter`) build `?` with a value list. Raw stay the clauses of PR #2272 (`$sql_where`, `$sql_collection`, `$sql_update`, the INSERT's `$values`) and the two product lists completed by `pg_catalog_bin_live_sql()`.

## What does the guard test check?
- `tests/db_q_auth_contacts_designer_test.php`: no escaped value in a `db_q()` SQL or a raw query, no escaped value in an assignment to `$where`, `$form_type_filter`, `*_where_statement` or `$sql_columns_to_update`, no `mysqli_real_escape_string` left in designer.php, one value per `?` in each literal list, and the bound text of sample queries.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/data/db-q-converted-commerce.md
