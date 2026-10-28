---
name: big-list-paging
description: Steps of refactor-candidates item 3: the big panel lists (users, submitted forms, contacts, products) paged, searched and counted on the server through a JSON action, one screen per step
---

## What does every step keep?
- `table.chart` and DataTables stay (`bootstrap-6-redesign.md`); only the rows come from an `api.php` action with the screen's own gate and the form token, one page at a time. Sort stays `pg_list_state()` heading links; the action reads the kept sort from the session.
- What a person can do stays: select all and the bulk buttons act on the rows on screen, as they did (DataTables kept only the drawn page in the DOM); exports and "all that match" actions keep reading the whole filtered list on the server.
- The search box finds what it found: every word in one of the columns the row shows as text (DataTables' smart search), labels matched through their stored values.
- Claim as `[plan:refactor-candidates] 3 — <screen> list paged on the server`; one step at a time.

## Which steps are there?
- 1. Users (`view_users.php`): no export, one bulk action (delete selected), no advanced filters; per-row queries (folder rights, sign-in lock) now run for one page only. Shared builder `includes/fn/user_list.php`, generic helpers `includes/fn/list_paging.php`, action `user_list_rows` (PR #2460, `ui/server-paged-lists.md`). Done.

- 2. Submitted forms (`view_submitted_forms.php`): rows are filtered in PHP with `check_edit_access()` per row and paged by 100 in PHP; the folder rule must become SQL before LIMIT. Search, advanced filters and the custom form choice already live in the session; Export Forms and Delete All Forms read the whole filtered list; delete selected posts `forms[]`. Fixes the forms part of issue #2038 (the search box searched only the page PHP sent). Done: PR #2509, `includes/fn/submitted_form_list.php`, action `submitted_form_list_rows`; the folder rule is SQL (`page.page_folder IN` the folders `check_edit_access()` passes).

- 3. Contacts (`view_contacts.php`): it never paged in PHP (`$number_of_screens` was never computed), so its search box already searched every row and #2038 does not apply to it; the gain is the page read. Advanced filters and the contact group choice in the session, role 3 limited to its groups (also re-checked by the action); the duplicates view reorders rows in PHP (children before their parent) and stays printed whole on the page through the same row builder; Export, Delete All and Merge read the whole filtered list; Organize Selected reads checked rows; rows carry `id` and lazy-loaded pictures. Done: PR #2528, `includes/fn/contact_list.php`, action `contact_list_rows`.

- 4. Products (`view_products.php`): many filters with their own sort columns, the impact score sort, inline quick edits posted to the same screen (`pg_pb_quick_update()`) on cells the rows carry, Export through `export_products_f.php`, Modify Selected reads checked rows; the variant sets mode is a separate screen.

- 5. Commissions (`view_commissions.php`, `view_recurring_commission_profiles.php`): paged by PHP like the forms list, so the search box searches one page (#2038); the PR that finishes them closes #2038 (`Fixes #2038`).
