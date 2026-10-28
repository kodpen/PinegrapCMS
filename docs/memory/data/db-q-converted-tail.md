---
name: db-q-converted-tail
description: Installer, designer actions, TinyMCE pickers (#2647), Flows INSERTs and init.php's path UPDATE on db_q(); why includes/authentication.php keeps raw queries; what stayed raw, guard test.
---

## Which files are converted, and how?
- `install/index.php` (22): the default language and host/path UPDATEs of `config`, the ten e-mail address UPDATEs (`config`, `custom_form_pages`, `page`, `products`), the administrator INSERT and its `selected_appmenu_items_array` UPDATE, `check_if_administrator_is_logged_in()`, and the system theme upgrade functions (three `system_theme_css_rules` counts, the rules read, the backup name check and the backup `files` INSERT). `(string)` everywhere the SQL quoted, also for the session id (`(string) (int)`), the theme id and `filesize()`. The MD5 password goes in as a bound string. Each call keeps its `or exit(mysqli_error(db::$con))` / `or output_error()`.
- `includes/panel/designer.php` (2): the designer region INSERT (`cregion_user` bound `(int) USER_ID`, bare as before) and the design file lookup of the delete action (the name was `e()`, now bound once).
- `assets/lib/tiny_mce_3_5_10/plugins/advimage/add_file.php` and `advlink/add_file.php` (4 each): the name check, the two DELETEs of an overwrite and the `files` INSERT. These are Pinegrap's own upload handlers inside the TinyMCE folder, not library code. The DELETE ids were written unescaped before (read from the database).
- Flows (PR #NNNN): `ws_flow_versions` in `includes/workspace/flows.php`, `ws_flow_shares` in `flow_shares.php`, the three `ws_flow_runs` INSERT … SELECT of `flow_runs.php` (×2) and `flow_anchors.php`. Ids and times bound as ints (bare, quoted before), the graph, start key and context as strings; the flow id is bound twice in the run INSERTs. They stay off `db()` because a duplicate key (1062) is an answer: `db_q()` runs nothing after `@mysqli_query()`, so `mysqli_errno()`, `mysqli_affected_rows()` and `mysqli_insert_id()` still read that INSERT.
- `init.php`: the config `path` UPDATE (bound string, `or output_error()` kept). Its `SELECT * FROM config` carries no value and stays raw.

## What stayed raw?
- The installer's language probe (line ~249) runs before `functions.php` is loaded, on its own connection; `db_q()` does not exist there yet. The `SET SESSION sql_mode`, `SHOW TABLES`, `SHOW COLUMNS … LIKE '…'` probes and the timestamp UPDATEs carry no value.
- `includes/authentication.php` calls no `db_q()`: `get_file.php` loads it without `functions.php` and defines only `db()`, `db_value()`, `db_item()`, `db_items()` and `escape()` (the contract at the top of the file). The `auth_tokens` selector lookup of `pg_session_token_binding_broken()` runs for every signed-in download there, so a `db_q()` call ended each one with an undefined function; it stays `@mysqli_query()` with `escape()`. The column probe (`information_schema.COLUMNS`) stays raw too.
- Left for later: `custom_form.php`'s SHOW COLUMNS probe (with the designer collections work); `includes/fn/parasut.php`, the owner's local ERP area.
- `db()` / `db_item()` / `db_value()` calls with `escape()` in these files are helper calls, not raw queries, and were not touched.

## What guards it?
- `tests/db_q_tail_test.php`: per file the least number of `db_q()` calls and the most raw `mysqli_query()` calls, no escaped value in a `db_q()` SQL or a raw query, no `db_q()` call in `includes/authentication.php`, the installer's first `db_q()` after `functions.php` is loaded, one value per `?`, and the bound text of the region INSERT, the administrator check, the picker INSERT and a Flows run INSERT. `tests/workspace_flow_runs_test.php` checks the run INSERT keeps its `status = 'live'` guard.

Related: docs/memory/data/db-q-converted-files.md (conversion rules), docs/memory/plans/refactor-candidates.md
