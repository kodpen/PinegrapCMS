---
name: contact-delete-notice-groups
description: The contact list's Delete All Contacts notice names the groups through pg_contact_list_deleted_groups_label(): escaped, only the user's groups.
module: contact_list
---

## How does the mass delete name the groups it ran on?
- `view_contacts.php` (Delete All Contacts) builds the notice and the log line with `pg_contact_list_deleted_groups_label($advanced, $picked, $group_filter, $groups)` (`includes/fn/contact_list.php`): the advanced filters' picked groups, or the picker's group, `[All]` and `[None]` as they are.
- A name is escaped with `h()`: liveform notices are printed as HTML and `lang()` does not escape its `{var:N}` values.
- Only groups of `pg_contact_list_groups($user)` are named; an id the user may not see, or of a group deleted meanwhile, is left out.

## What was wrong before?
- The screen read each name with its own query and put it raw into the notice: a group named `<img src=x onerror=…>` by one manager ran script in the browser of whoever deleted contacts of that group. A User (role 3) could also learn the name of any group by posting its id in the advanced filters, and a missing group gave a PHP warning on a null row.
