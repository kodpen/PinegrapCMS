---
name: db-q-converted-tail
description: The installer, the session token check, the designer panel actions and the TinyMCE image and link pickers on db_q() (PR #2647): special cases, what stayed raw, guard test.
---

## Which files are converted, and how?
- `install/index.php` (22): the default language and host/path UPDATEs of `config`, the ten e-mail address UPDATEs (`config`, `custom_form_pages`, `page`, `products`), the administrator INSERT and its `selected_appmenu_items_array` UPDATE, `check_if_administrator_is_logged_in()`, and the system theme upgrade functions (three `system_theme_css_rules` counts, the rules read, the backup name check and the backup `files` INSERT). `(string)` everywhere the SQL quoted, also for the session id (`(string) (int)`), the theme id and `filesize()`. The MD5 password goes in as a bound string. Each call keeps its `or exit(mysqli_error(db::$con))` / `or output_error()`.
- `includes/authentication.php` (1): the `auth_tokens` selector lookup of the session token check. `db_q()` runs `@mysqli_query()`, so a missing table (the upgrade bridge) still returns `false` silently and the session is left alone.
- `includes/panel/designer.php` (2): the designer region INSERT (`cregion_user` bound `(int) USER_ID`, bare as before) and the design file lookup of the delete action (the name was `e()`, now bound once).
- `assets/lib/tiny_mce_3_5_10/plugins/advimage/add_file.php` and `advlink/add_file.php` (4 each): the name check, the two DELETEs of an overwrite and the `files` INSERT. These are Pinegrap's own upload handlers inside the TinyMCE folder, not library code. The DELETE ids were written unescaped before (read from the database).

## What stayed raw?
- The installer's language probe (line ~249) runs before `functions.php` is loaded, on its own connection; `db_q()` does not exist there yet. The `SET SESSION sql_mode`, `SHOW TABLES`, `SHOW COLUMNS … LIKE '…'` probes and the timestamp UPDATEs carry no value.
- `includes/authentication.php`'s column probe (`information_schema.COLUMNS`, line ~58) is the fallback for a request where `pg_schema_has()` is not loaded yet; it stays `mysqli_query()` with `mysqli_real_escape_string()`.
- Left for later (plan `refactor-candidates.md` item 4): the Flows calls (`includes/workspace/flows.php`, `flow_shares.php`, `flow_runs.php` ×2, `flow_anchors.php`), held by #2606 and the flows-API step; `init.php`, held by #2611; `custom_form.php`'s SHOW COLUMNS probe (item 5; #2601); `includes/fn/parasut.php`, the owner's local ERP area.
- `db()` / `db_item()` / `db_value()` calls with `escape()` in these files are helper calls, not raw queries, and were not touched.

## What guards it?
- `tests/db_q_tail_test.php`: per file the least number of `db_q()` calls and the most raw `mysqli_query()` calls, no escaped value in a `db_q()` SQL or a raw query (the authentication probe named as the exception), the installer's first `db_q()` after `functions.php` is loaded, one value per `?`, and the bound text of the token lookup, the region INSERT, the administrator check and the picker INSERT.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/plans/refactor-candidates.md
