---
name: unit-test-database-exit
description: A unit test statement that reaches db() ends the whole tools/test.php run with exit code 0 through output_error(); make it throw with pg_error_throws(true), or keep the tested part free of queries
---

## What happens when a unit test reaches the database?
- The db() family (`pg_db_run()`) finds no connection and calls `output_error()`, which prints its HTML page and calls `exit()`: the run stops inside that test, prints no summary and ends with exit code 0, so CI passes. A test of `pg_catalog_query()` with its API path switched off passed this way.

## How does a test call code that may reach it?
- Turn on `pg_error_throws(true)` for the call and restore the old value in `finally`: `output_error()` then throws a `RuntimeException` ("No database connection.") that the test asserts, or that the runner counts as an ERROR. `catalog_group_writer_test_query()` in `tests/catalog_group_writer_test.php` does this.
- Better, keep the tested part free of queries: hand it its reads as callables and collect its statements with a runner callable, as `pg_catalog_group_create_input()` and `pg_catalog_group_write()` take them (`commerce/catalog-group-writer.md`).
