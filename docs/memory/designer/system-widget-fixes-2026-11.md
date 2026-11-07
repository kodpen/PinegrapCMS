---
name: system-widget-fixes-2026-11
description: Row id suffix skips data-id/data-for, form list search cut by characters, custom form pattern slashes on the server, Check all tables counts a check that could not run
---

## Which attributes take a list row's id suffix?
- `pg_sw_uniquify_row_ids()` (`includes/fn/widgets.php`) matches `id="…"` and `for="…"` only after whitespace (`(?<=\s)`). With `\b` it also matched `data-id`, `data-product-id` and `data-for`, so a bound `data-id="{{submitted_form_id}}"` became `data-id="123_pgw5r123"` and page scripts read a wrong value. The id-referencing attributes (`data-bs-target`, `aria-*`, `href="#…"`, `data-bs-parent`) are unchanged. Test: `test_repeat_placement_row_ids_skip_data_attributes`.

## How is a search text cut?
- To 100 characters with `mb_substr(…, 'UTF-8')`, never `substr()`: the form list widget's query and search label (`_render_system_widget_form_list()`) and the catalog widget's search label (`widgets_catalog.php`). A byte cut inside a Turkish letter made `h()` print `''`: an empty search box and a broken `LIKE`.

## How does the server check a custom form field's pattern?
- `custom_form.php` builds the PCRE with `pg_cf_field_pattern_regex()` (`includes/fn/custom_form.php`): anchored `^(?:…)$`, `u`, only a bare `/` escaped, an escaped pair (`\/`, `\\`) kept. `str_replace('/', '\/')` turned an already escaped `\/` into `\\/`, the expression did not compile and the value passed.
- A pattern that still does not compile (an operator's typo) is ignored and the value passes (`$matched === 0` only marks the error): a typo in a pattern must not take the form down for everybody. Test: `custom_form_field_pattern_test.php`.

## What does "Check all tables" count as an issue?
- `pg_db_check_summary()` (`includes/fn/db_maintenance.php`) over the `check_and_repair_database_tables(true)` report: `error` and `check_failed` are issues, `repaired` a repair. `database_engine.php` counted only `error`, so a table whose CHECK TABLE could not run gave the green "0 issue(s)" notice while the findings list showed it as a danger. Test: `test_db_maintenance_check_summary`.
