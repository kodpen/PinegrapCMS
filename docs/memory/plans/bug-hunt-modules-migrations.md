---
name: bug-hunt-modules-migrations
description: Module list of plans/bug-hunt.md, migrations part: the open version's migration file and its runner helpers, one line each with its state
---

## Which migration files are hunted?
- Taken: 2026.11.0 (`includes/migrations/2026.11.0.php`, with `runner.php`'s `install_heavy_tables()` entry) by the 2026.11.0 migration worker; found: the preflight list missed shipping_tracking_numbers, user and files.
