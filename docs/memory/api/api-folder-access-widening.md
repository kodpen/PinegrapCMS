---
name: api-folder-access-widening
description: External API page and folder moves keep or narrow access: access type rank, and for private folders the aclfolder reader list (PR #1786); 409 access_widening_refused
---

## What does the API refuse when a page or folder moves?
- `POST /pages/{id}` `folder_id` and `POST /folders/{id}` `parent_id`/`access` answer 409 `access_widening_refused` for any change that serves something to more people. The panel's page and folder screens still allow these moves: opening part of the site up is a person's decision there.
- First test: the effective access type (`api_folder_access_widens()`, ranks public < guest < registration < membership < private). An unknown type counts as closed before and open after.
- Second test, for private folders (PR #1786): who reads a private folder is its `aclfolder` list, inherited from above. A page move between two private folders, or each folder of a moved branch that is private before and after, must keep or narrow its readers.

## How are a private folder's readers computed?
- `api_folder_readers_walk()` mirrors `check_private_access()`: for each user the nearest row on the way up decides; rights 2 or an unexpired rights-1 row let them in until the end date (`9999-12-31` for none), an expired row shuts them out without looking further up.
- `api_folder_acl()` loads only role-3 rows: managers and above read every private folder, so their rows never decide.
- `api_folder_readers_widen()` is true when the target has a reader the source lacks, or the same reader with a later end date.
- A folder that turns private from another type is narrowed and needs no list check.

## Which test guards it?
- `tests/api_folders_test.php` (walk, inheritance, expiry, the widening rule). Before PR #1786 a private-to-private move into a folder with a wider list passed the type check.
