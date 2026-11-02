---
name: user-list-folder-filters
description: Users list filters My Private Users and My Content Managers: one row per user, managers listed without folder rights, the fault the PR fixed
module: user_list
---

## How do the users list filters pick their rows?
- `pg_user_list_filter_rule($filter)` (`includes/fn/user_list.php`) gives each named filter's condition, heading and subheading; `pg_user_list_view()` reads every filter `FROM user`, one row per user.
- My Private Users and My Content Managers ask `(user.user_role < '3') OR EXISTS (SELECT 1 FROM aclfolder WHERE aclfolder_user = user.user_id AND aclfolder_rights = '1'|'2')`: an administrator, designer or manager (every folder, "All" in the row) is listed whether or not they have folder rows.

## What was wrong before?
- Those two filters listed `FROM aclfolder LEFT JOIN user` with `DISTINCT`: a manager with no aclfolder row of their own was missing from both lists, and one with an unrelated folder row (a private right) appeared in My Content Managers only by chance. Fixed in #5169; `tests/user_list_filter_rule_test.php`.
