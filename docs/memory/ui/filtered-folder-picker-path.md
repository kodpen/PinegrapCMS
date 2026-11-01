---
name: filtered-folder-picker-path
description: Folder pickers filtered by access type (private-access pickers) name a folder with the parents the filter left out
module: auth
---

## What was the symptom?
- Site Settings › E-Commerce "Grant Private Access", the product's private folder and the custom form's private folder list only private folders (`select_folder(..., 'private')`). The filter printed no option for a non-private parent and its children came up unindented and without a path: a site with several imported templates showed eight options that all read "private" (#4427, PR #PRNUM).

## What does the picker print now?
- `select_folder()` passes `$left_out_parents` down the tree: the names of the parents the access type filter skipped. The next printed folder is labelled `pg_folder_option_label($name, $left_out_parents)`, e.g. "pinegrap_cafe › private"; its own children start a fresh path and are indented under it.
- Only a folder skipped by the access type filter is named. A folder the user (role 3) has no access to is never named, and the root folder is left out of every path.
- The option value stays the folder id; an unfiltered picker is unchanged.

## Where are the tests?
- `tests/folder_picker_path_test.php`.
