---
name: privacy-inventory
description: Personal data inventory (privacy_inventory.php): the catalogue beside the erase table list, live counts, transfers from the registry rows' country and data, the tests
module: privacy_inventory
---

## What is it?
- `pg_privacy_inventory()`: one line per table holding personal data: `categories`, `subjects` (customer, visitor, applicant, staff), `purpose` (English), suggested `ground`, `retention` (a key or a list), optional `person` (column, `int`|`text`) and `where`. Keys and English labels: `pg_privacy_inventory_categories()`, `_subjects()`, `_grounds()`, `_retentions()`. Pure; loaded by `functions.php`.
- Grounds and retentions are suggested defaults, not legal advice; the Privacy centre's screen (KV2) says so once.

## How is it kept live?
- `tests/privacy_inventory_test.php` fails when `pg_privacy_erase_tables()` reaches a table the catalogue lacks, when a line is not whole, and when a `person` column is not one the erasure finds or replaces in that table (so it exists wherever the erasure runs).
- A new personal table goes into both lists in the same PR.

## How are the counts read?
- `pg_privacy_inventory_counts()`: `COUNT(*) AS records` and, with a person column, `COUNT(DISTINCT NULLIF(col, 0 or ''))` as people; an `int` column counts only rows linked to somebody (`col > 0`). `files` counts only files of form submissions (`where`).
- A missing table or column (`pg_schema_has()`) or a failed query (`db_q()` answers false) gives `null`: not known, never 0.

## What are the transfers?
- Every registry row carries `country` (two letters, or `*` for a network with no one country) and `data` (category keys, or `array('none')`) from `pg_connections_transfer_fields()` in `includes/fn/connections_registry.php`; `pg_connections_row_problems()` names a row without them (`api/connections-registry.md`).
- `country` is the contracting company's seat as known when written; the shop checks it for its own notice.
- `pg_privacy_transfers($registry, $states, $site_country)`: rows connected, needing attention, or with no state (may be in use); `personal` unless data is `none`; `abroad` when personal and the country is not the site's (`*` is abroad). `pg_privacy_transfers_live()` reads the states as the Connections screen does (`pg_connections_screen_facts()`), calling no service.

Related: [[personal-data-erasure]], [[privacy-log-retention]]
