---
name: list-table-quick-edit-containers
description: All Containers list quick edit (includes/fn/container_list_edit.php): the fields, the units, the container screen's own check and save, who may change them
module: container_list_edit
---

## Which cells change?
- Every value `edit_container.php` changes (`pg_container_quick_edit_columns()`): Name, Enabled (Yes/No), Length, Width, Height, Weight and Cost. Text: Created, Last Modified. `tests/container_list_edit_test.php` fails for a new column that is neither.
- Sizes are typed in inches and the weight in pounds, the units stored; the screen's cm/kg switch is only on the screen. A field opens with the figure at up to four places, no thousands separator, in the site's decimal style (`pg_container_quick_value()`); a zero weight or cost opens blank, as on the screen.

## Who may change them?
- The container screen's rights (`validate_ecommerce_access()`): role below User, or a User with `manage_ecommerce`. `view_containers.php` answers `pg_list_edit_request()` before its own access check, so a refusal is JSON.

## How is a change checked and written?
- `pg_container_quick_update()` fills a throwaway liveform (`container_quick_edit`) with the stored container as the screen opens it plus the one change (`pg_container_quick_form_values()`; Enabled off is posted empty, like the unticked switch), runs the screen's `pg_container_form_validate()` and writes with `pg_container_form_save()`, the save the edit screen uses too (one parameterised UPDATE; a failed `db()` UPDATE used to reach `mysqli_num_rows(false)`). The first field error is the refusal's message; it logs "container (…) was modified".
- The answer is the row read again (`pg_container_list_sql($id)`, `pg_container_list_cells()`): every cell, so the name's enabled/disabled colour and Last Modified follow.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/ui/list-table-quick-edit.md
