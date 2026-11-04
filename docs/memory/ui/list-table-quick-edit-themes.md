---
name: list-table-quick-edit-themes
description: All Themes list quick edit (includes/fn/theme_list_edit.php): the fields, the shared theme check and save, the answer that redraws the replaced theme
module: theme_list_edit
---

## Which cells change?
- Every one-line value `edit_theme_file.php` changes (`pg_theme_quick_edit_columns()`): File Name, Folder (the screen's `select_folder()` choices, coloured by access type), Activated for Desktop, Activated for Mobile (Yes/No). Text: Description (several lines), Last Modified. `tests/theme_list_edit_test.php` fails for a new column that is neither.
- A theme whose folder the picker does not offer (Recycle Bin) keeps its Folder cell as text.

## Who may change them?
- The theme screen's rights: role Administrator or Designer (`<= 1`). `view_themes.php` answers `pg_list_edit_request()` before its own access check, so a refusal is JSON; the folders are read only once a user is signed in (`select_folder()` reads the global user).

## How is a change checked and written?
- `includes/forms/theme_file_form.php`, shared with the edit screen: `pg_theme_file_form_validate()` (required, ends in ".css", not ".htaccess", name free) and `pg_theme_file_form_save()` (rename on the disk first, a failed rename marks the name and writes nothing; activation takes the other theme off that device type with its preview styles, moves the pages to this theme's preview styles; UPDATE, audit and log lines). The list fills a throwaway liveform (`theme_quick_edit`) with the stored theme plus the one change (`pg_theme_quick_form_values()`); the first field error is the refusal.
- The record must be a theme CSS file with a safe plain name (`pg_theme_list_row()`), as the screen's gate asks.

## How does the answer show the replaced theme?
- The save returns the ids it deactivated; the answer carries their cells under `rows` (id => cells), and `pg_list_table_edit.js` (`paintRow()`) redraws those rows on screen, so the old theme loses its mark without a reload. Any list can send `rows`.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-quick-edit.md, docs/memory/ui/theme-screens.md
