---
name: bug-hunt-areas-migration-steps-11
description: Bug hunt of the 2026.11.0 migration steps (area 13): run twice, cut off half-way, upgraded from a real 2026.4.10 install, tables of decisions-made-schema-46 to -49
---

## Which area was read?
- Area 13: the steps of `includes/migrations/2026.11.0.php` (11.0.1 to 11.0.276, called by `upgrade_to_2026_11_0()`), on a MariaDB 10.11 dev database.

## How was it run?
- A real 2026.4.10 site was installed from the `v2026.4.10` tag, then upgraded by `php pinegrap/install/index.php automated_upgrade` with today's files.
