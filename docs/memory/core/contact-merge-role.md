---
name: contact-merge-role
description: Who may merge duplicate contacts: a role above user (3) on both merge paths; view_contacts.php checks it itself (#5124)
module: contact_list
---

## Who may merge contacts?
- Administrators, designers and managers (role below 3). `pg_contact_list_may_merge($user)` (`includes/fn/contact_list.php`) answers it.
- The bulk action in `edit_contacts.php` (`action=merge`) refuses role 3 with its own check.
- `view_contacts.php` "Merge Contacts" (a GET form with the token) asks `pg_contact_list_may_merge()` before reading any row.

## Why does view_contacts.php check the role itself?
- The merge runs `merge_contacts()` on whatever filter the request names. Role 3 is kept from the duplicates view and its button, but before #5124 a request with another filter (All My Contacts, a group) and a valid token merged that user's same-address contacts: contacts deleted and their records moved, which the bulk path refuses to role 3.

## Tests?
- `tests/contact_list_test.php`: `test_contact_list_merge_needs_a_role_above_user` (the roles, and the check placed before `merge_contacts(` in the screen's merge branch).
