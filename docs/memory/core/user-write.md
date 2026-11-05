---
name: user-write
description: Creating, saving and deleting a panel account goes through includes/fn/user_write.php, shared by add_user.php, edit_user.php, delete_users.php and the /users API writes
module: user_write
---

## Where is a panel account written?
- `includes/fn/user_write.php` (loaded with `require_once`, not by `functions.php`): `pg_user_create($fields, $editor_id)`, `pg_user_update($user_id, $fields, $editor, $save_access = true)` (the API passes false: it writes no access records), `pg_user_delete($user_id)`, `pg_user_new_account_mail($user_id, $fields)`. `add_user.php`, `edit_user.php` (save and delete) and `delete_users.php` call them; none of the three holds an `INSERT`, `UPDATE` or `DELETE` of the `user` row any more (PR #6484); the API routes on it: `api/api-users-write.md`.
- `$fields` is shaped as the user form posts it (`username`, `email`, `role`, `manage_contacts = 'yes'`, `view_card_data = '1'`, `set_page_type_<type>`, `edit_<folder>`, `menu_<id>`...); the module never reads `$_POST`. `pg_user_permission_ws_sql()` and `pg_user_erp_readonly_sql()` take the fields as a second argument for that reason (`$_POST` when left out, for `import_users.php`).

## Which checks stay with the caller?
- Before a write the caller checks the role with `pg_user_role_writable($editor_role, $role)` (an administrator any role; anyone else only below their own; no role at all only for an administrator) and the account fields with `pg_user_write_problem($username, $email, $user_id)` (`username_required`, `email_invalid`, `username_taken`, `email_taken`; a username and an address share one namespace), shown with `pg_user_write_problem_message()`.
- Whether the editor reaches the row, and the last administrator: the edit screen refuses to delete it with `pg_user_is_last_admin()` (no other enabled administrator); `delete_users.php` asks it per selected administrator too (it counted disabled administrators as others before, so the last enabled one could be deleted, #PR). `pg_user_update()` itself keeps the last administrator's role.
- The site log line (`log_activity()`) and the session username after a rename of one's own account stay in the screens.

## What does each write carry with it?
- Create: random password nobody is told, `user_created` stamp, notifications seeded as read, `user_password_changed_at`, access records, `create` audit line; the mail only when the caller asks (`pg_user_new_account_mail()`: the set password link, never a password).
- Update: a module's rights only while the module is on (`pg_user_form_module_columns()`), the "Account enabled" switch only with `account_enabled_drawn` and `pg_user_form_may_disable()` (tokens revoked on switching off), access records replaced, `update` audit line.
- Delete: tokens, second factor and passkeys, linked identities, favourites, back-in-stock requests, then the row (`delete` audit line with the row before), access records, address book, messages, saved replies, shared component grants.

Related: docs/memory/core/permission-columns.md, docs/memory/core/audit-trail-writers.md, docs/memory/api/api-users-read.md
