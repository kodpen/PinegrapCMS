---
name: list-table-quick-edit-menus
description: All Menus list quick edit (includes/fn/menu_list_edit.php): the fields, the classes as the chips post them, the menu screen's own check and save, who may change them
module: menu_list_edit
---

## Which cells change?
- Every value `edit_menu.php` changes (`pg_menu_quick_edit_columns()`): Name, Effect (Pop-up, Accordion, None = empty), First Expand Menu, Then Expand Menu (Top, Bottom, Left, Right), Classes, Active Item Classes. Text: Created, Last Modified. `tests/menu_list_edit_test.php` fails for a new column that is neither.
- The two positions apply to the Pop-up effect only; for another effect the cells stay fields but are muted (`fg-secondary`), and the effect's answer redraws them.
- Classes are one text field each: the typed list reaches the check as the screen's chips post it, one space between classes, each once (`pg_menu_quick_classes()`).

## Who may change them?
- The menu screen's rights (`validate_area_access()` 'designer'): role Administrator or Designer. `view_menus.php` answers `pg_list_edit_request()` before its own access check, so a refusal is JSON.

## How is a change checked and written?
- `pg_menu_quick_update()` fills a throwaway liveform (`menu_quick_edit`) with the stored menu plus the one change (`pg_menu_quick_form_values()`), runs the screen's `pg_menu_form_validate_name()` (required, 100 characters, the tag name rule with underscores only for a name that has one, unique) and `pg_menu_form_validate_classes()` (255 characters each), and writes with `pg_menu_form_save()`, the save the edit screen uses too. The first field error is the refusal's message; it logs "menu (…) was modified".
- A renamed menu is not renamed in the page styles' `<menu>` tags, as on the screen.
- The answer is the row read again (`pg_menu_list_sql($id)`, `pg_menu_list_cells()`): every cell, so the positions' muting and Last Modified follow.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-quick-edit.md
