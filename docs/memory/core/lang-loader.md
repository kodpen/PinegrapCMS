---
name: lang-loader
description: Where lang() lives (includes/fn/lang.php), how get_file.php loads it without functions.php, the software language a request outside init.php speaks
module: lang
---

## Where is lang()?
- `includes/fn/lang.php`: `lang()`, the language file readers (`pg_lang_map()`, `pg_lang_source()`, `pg_lang_read_folder()`, the compiled cache read/write) and `pg_log_lang()`. They moved out of `core.php` and `forms.php` (#3661) so a script that skips `functions.php` can load them. The cache helpers they call stay in `lang_files.php`.
- `functions.php` loads it right after `core.php`.

## How does get_file.php get a language?
- The router dispatches `get_file.php` without `init.php` or `functions.php`, so before #3661 `lang()` did not exist there: `get_file_text()` fell back to English and the site log lines were bare English (issue #3332, "User viewed private file.").
- It now defines `PG_FUNCTIONS_DIR` when missing, loads `lang_files.php` and `lang.php`, then `pg_software_language_define()` with the router's `$router_config_row['software_language']`.

## In which order is the software language chosen?
- `pg_software_language_pick($enforcement, $stored, $default)`: config.php's `ENFORCEMENT_SOFTWARE_LANGUAGE`, else the stored `config.software_language` (`undefined`, which an old settings screen saved, counts as none), else `DEFAULT_SOFTWARE_LANGUAGE`; `''` when none, and `lang()` speaks English. The same order `init.php` uses. `pg_software_language_define()` leaves an already defined constant alone. Test: `tests/lang_loader_test.php`.

## And a page in another site language?
- `pg_log_lang()` holds `pg_tr_suspend()` where the translation module is loaded; where it is not (`get_file.php`) and `FRONTEND_LANGUAGE` is set, it names the software language in lang()'s `language` key instead.
