---
name: office-edit-test-own-folder
description: Why the workbook save test (tests/office_edit_test.php) saves into a folder of its own run and not into the shared temporary folder (#12096)
---

## Where does `test_office_edit_save_and_read_back` save?
- Into a new folder under `sys_get_temp_dir()` named `pg_office_edit_test_<pid>_<random>`, made at its start and emptied and removed at its end; that folder is the save directory passed to `pg_office_xlsx_save()`.
- Its check that no `office_edit_*.tmp` or `office_edit_*.part` is left behind globs only that folder.

## Why not the shared temporary folder?
- A second test run on the same machine (the pre-push hook beside a manual run, two worktrees) left its own `.part` file there and the check counted it as this run's leftover, failing the test once (#12096).
- A save test that asserts on what is left in a directory gives itself a directory of its own.
