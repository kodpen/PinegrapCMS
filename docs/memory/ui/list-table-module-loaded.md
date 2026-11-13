---
name: list-table-module-loaded
description: A screen that calls the list table driver (pg_list_table_printed(), its cell helpers) must require includes/fn/list_table.php: My Campaign Profiles and Bookings answered HTTP 500 without it (#9091)
module: list_table
---

## Why must a screen load the driver itself?
- `functions.php` does not load `includes/fn/list_table.php`. A screen loads it with `require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php')`, or through a list module it requires that does (`page_list.php`, `user_list.php`, `contact_list.php`, `audit_list.php` and the others, some inside the function that builds the source).

## What was the symptom?
- My Campaign Profiles (`view_email_campaign_profiles.php`) answered HTTP 500 with only "Beklenmeyen bir hata oluştu." (#9010): `Call to undefined function pg_list_table_check_cell()`. Bookings (`view_bookings.php`) the same with `pg_list_table_printed_sort_columns()`. Both moved to printed lists (`pg_list_table_printed()`) without the require. Fixed in #9091.
- The fatal is in `data/temp/php_errors.log` (the Site Log's error view reads it).

## What guards it?
- `tests/list_table_module_loaded_test.php`: every root screen whose code (comments left out) calls a function of the driver contains the require, or requires an `includes/` module that does. `functions.php` must keep not loading it.
- A list screen that still answers 500 for another reason: `list-table-driver.md` (a loop that reuses the source's variable name, #9040).
