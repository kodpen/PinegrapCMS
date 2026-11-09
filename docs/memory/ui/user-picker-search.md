---
name: user-picker-search
description: Searchable user pickers: staff and team lists as local data-pg-combobox, every-user fields on user_search_action.php (users list right, role scope, Turkish fold, 30 results), stored user kept
module: user_picker
---

## Which panel pickers choose a user, and how are they searched?
- Short lists, printed whole and searched in the browser (`data-pg-combobox`, a search box from 8 options): the live chat operator (Settings, Communication; staff, role <= 2), Add a staff member (Settings, Bookings; role < 3), the staff of `edit_booking.php` and the staff filter of `view_bookings.php` (booking staff), Share with and a person column's field in `workspace_wtables.php` (the Workspace team).
- A field that can name any account asks the server as the operator types: the Form Editor of `edit_submitted_form.php` (`data-pg-source="user_search_action.php?value=username"`, `data-pg-min-length="2"`, `data-pg-free-text`).
- Left alone: role pickers, site forms, the name filters of `erp_audit.php` and `view_audit_log.php` (names in the log, not accounts), the folder access panels' Add a user box (its own explorer action, role 3 only) and the Form Editor quick edit of the submitted forms list (a text field of `pg_list_table.js`).

## What does user_search_action.php answer, and to whom?
- `GET ?q=` (plus `role=staff` for roles below 3, `value=username` to store the username): `{results: [{id, text}]}`, the stored value and "First Last (username)" (`pg_user_picker_text()`), at most 30 enabled accounts by username. Nothing else is read back: no e-mail address, no role.
- Only a signed-in user with the users list's right, manager (2) or above (`pg_user_picker_may_search()`), and only the accounts that list shows them: an administrator every one, a designer or manager the roles below their own (`pg_panel_search_scope_sql('user_role', …)`, as the panel search). Checked on the `USER_LOGGED_IN` / `USER_ROLE` constants before anything, 403 with `{error}` in JSON (the combobox says it in its menu), never `validate_user()`'s HTML login page. Read only, so no form token.

## How does it match?
- Every word of the query in the username, the e-mail address, the first or the last name: `pg_list_search_where()`, the users list's own search, with the connection charset always given (`pg_list_search_connection_charset()`, as the Workspace search), so İ, I, ı and i are one letter ("yildiz" finds "Yıldız") and the collation folds ş/s, ç/c. In SQL, so the cap holds over any number of customers. `%` and `_` typed are plain.
- `pg_user_picker_search_where()` builds it and is pure: `tests/user_picker_search_test.php`.

## What happens to the stored user?
- It stays chosen and posted when it is outside the list: the screen prints it as the selected option (`pg_user_picker_label()`): the account's picker text, "#id (deleted)" for an id with no account, an unknown username as typed. The chat operator used to fall back to "Select" for an operator no longer staff, and the next save of the card blanked it.
- Save checks are unchanged: the form editor save still looks the username up and refuses an unknown one; with `data-pg-free-text` a manager can still type a username outside their scope, as with the old text box. A role-3 forms manager (no users list right) keeps the plain text box.

## Why do the booking forms ignore a change inside the menu?
- `view_bookings.php` sends its filter form and `edit_booking.php` reloads on every bubbling `change`; the combobox's search box has no name but sits inside the form, so typing then moving to an item fired `change` and left with the old staff. Both listeners return for `event.target.closest(".pg-combobox-menu")`.

## Where is the module loaded?
- `includes/fn/user_picker.php` is not in the `functions.php` manifest: `user_search_action.php`, `edit_submitted_form.php` and the settings prep load it with `require_once`.

Related: docs/memory/ui/pg-combobox.md, docs/memory/ui/pg-combobox-server-search.md, docs/memory/core/access-control-and-roles.md
