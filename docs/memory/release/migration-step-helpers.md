---
name: migration-step-helpers
description: How to write an upgrade step with the runner.php install_* helpers: check-then-act, exact-name probes, re-runnable data statements, enum-narrowing MODIFY.
---

## How is an upgrade step written?
Every `ADD` / `CREATE` / `DROP` / `MODIFY` / `CHANGE` asks first, then acts. Writing `db("ALTER TABLE x ADD ...")` is forbidden. Use the `runner.php` helpers:
```php
install_add_column('config', 'waf_enabled', "TINYINT(1) NOT NULL DEFAULT 0");
install_add_index('visitors', 'idx_user_agent', "INDEX idx_user_agent (user_agent(64))");   // definition includes the index name
install_create_table('waf_rate', "CREATE TABLE waf_rate ( ... ) ENGINE=InnoDB ...");
install_drop_column($t, $c);  install_drop_index($t, $i);  install_drop_table($t);
install_modify_column($t, $c, $def, $required = true);   // error if column missing; $required = false after a rename
install_rename_column($t, $old, $new, $def);            // passes if old is missing and new exists
install_rename_table($old, $new);  install_set_engine($t, 'InnoDB');
install_table_exists / install_column_exists / install_index_exists / install_column_info($t, $c)
install_note('...');   // note shown on screen under that version
```

## What are the probe and data-statement rules?
- Probes match the exact name (`SHOW COLUMNS ... WHERE Field =`, `information_schema`); never `LIKE`, because `_` is a wildcard.
- Data statements (`UPDATE` / `DELETE`) are written re-runnable (`WHERE ... IS NULL`, `WHERE created_at = 0`).
- A `MODIFY` that narrows an enum always checks the previous shape with `install_column_info()` first (examples: 2026.1.14 / 2026.1.26).
- An unguarded `ADD` / `CREATE` is forbidden: every step is "exists -> skip", which is also the function's double-run test.

Related: docs/memory/release/upgrade-runner-behavior.md
