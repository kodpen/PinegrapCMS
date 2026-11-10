---
name: contact-delete-notice-groups
description: The contact list's Delete All Contacts notice names the groups through pg_contact_list_deleted_groups_label(): escaped, only the user's groups.
module: contact_list
---

## How does the mass delete name the groups it ran on?
- `view_contacts.php` (Delete All Contacts) names the groups with `pg_contact_list_deleted_groups_label($advanced, $picked, $group_filter, $groups)` (`includes/fn/contact_list.php`): the advanced filters' picked groups, or the picker's group, `[All]` and `[None]` as they are. The helper returns plain text.
- The Site Log line takes the names raw (Site Log stores plain text and escapes once on read); the notice gets `h($contact_group_list)` in its message call, since liveform notices are HTML and `lang()` does not escape its `{var:N}` values (#8549).
- Only groups of `pg_contact_list_groups($user)` are named; an id the user may not see, or of a group deleted meanwhile, is left out.

## What was wrong before?
- The screen read each name with its own query and put it raw into the notice: a group named `<img src=x onerror=…>` by one manager ran script in the browser of whoever deleted contacts of that group. A User (role 3) could also learn the name of any group by posting its id in the advanced filters, and a missing group gave a PHP warning on a null row.
- The log line got the escaped names too, so deleting contacts of "Çay & Kahve" showed `Çay &amp; Kahve` in Site Log, the dashboard Activity card and Edit User's activity list (#8549).
