---
name: list-table-quick-edit-users
description: Users list quick edit (includes/fn/user_list_edit.php): which cells are fields for whom and in which view, how a username, e-mail, role, start page or right is checked and written
module: user_list_edit
---

## Which cells change?
- `pg_user_quick_edit_columns($editor_role, $filter, $pages)`, kept in `pg_user_list_view()` (`$view['quick_edit']`): username, e-mail, start page (views that show it; choices are `select_page()`'s, `pg_user_quick_page_choices()`), role (administrator and designer only: a manager's user screen posts role 3 hidden), and the User role's rights whose module is on (calendars, forms, visitors, contacts, campaigns, commerce).
- `pg_user_quick_cell_editable()`: a right only on a role-3 row (the other roles have every right; the mark is text) and only where the view shows it as the right itself: the default view, plus Send Campaigns in My Contact Managers. A role only where the editor may hand out the present one; a start page only while the stored one is among the choices.
- Text, as computed: Private User, Content Manager (folder rights), Member User (the contact), Site Manager/Designer (follow the role), the folder/calendar/form/group name lists, Send Campaigns Only (two rights), User's Contact, the times. `tests/user_list_edit_test.php` fails for another column that is not a field.

## How is a change checked and written?
- `view_users.php` answers `pg_list_edit_request()` before drawing; the edit URL carries the view (`view_users.php?filter=…`).
- `pg_user_quick_update()`: the editor reaches only a lower role (an administrator anyone), as `edit_user.php`. `pg_user_quick_change()` (pure) decides the one column: role through `pg_user_form_role()` + `pg_user_role_writable()` for the present and the new role, the last enabled administrator keeps the role; username at most 100 characters; a right `yes`/''. Username and e-mail pass `pg_user_write_problem()` with the stored other half.
- One `UPDATE user SET <column>`, stamped (`user_user`, `user_timestamp`), audit line, "user (x) was modified" in the site log; renaming one's own account renames `$_SESSION['sessionusername']`.
- A right switched off takes its grants as the screen's save does (`pg_user_quick_dropped_grants()`): contact groups once neither contacts nor campaigns are left, calendars with the calendar right while Calendars is on; a `notice` says so. Undo turns the right back on without them.
- The answer re-reads the row in its view and redraws every cell (a role change redraws Site Manager/Designer and the rights). Cells do not gain or lose their field until the list reloads: a right on a row just made a manager is refused with the reason.

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/core/user-write.md
