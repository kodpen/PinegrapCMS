---
name: workspace-settings-card-saves
description: Workspace Settings card saves: list allow-list, refused form refilled with its error, rank rule for team rows, hours and holiday checks, mailbox fields
---

## Which posted lists does the screen accept?
- `workspace_settings.php` refuses any field posted as a list unless `pg_request_unexpected_lists()` is given its name (workdays, members, leads, primary, people, mcp_tool, route_match, route_channel). The mailbox routing rows (`route_match[]`, `route_channel[]`) came after the guard and were missing, so every mailbox save failed with "the value you entered for route_match, route_channel is not valid".
- `tests/workspace_settings_lists_test.php` reads every `name="x[` of the card sources against that list: a new card field drawn as a list goes into both.

## What does a refused card save show?
- The redirect keeps the card's anchor, but the error used to sit only at the top of the page and the form came back from the database. Now, when the liveform holds an error after the switch, `ws_settings_refused_fields($_POST)` (`includes/workspace/screen.php`, pure) is kept in `$_SESSION['software']['ws_settings_refused']` for one page: field name => value (`name[]` for a list, `name[key]` for a row), never the token, a password, a secret or a key.
- `ws_settings_refused_script()` finds the form by `ws_action` and `box_id` / `department_id` / `feed_id` (not a `d-inline` row form), fills it, opens its collapse and moves the `.software_error` alert into it (or into the card the anchor names) and scrolls to it. A mailbox password is typed again. Without JavaScript the error stays at the top.

## Where are the department and team member saves?
- In `includes/workspace/team_settings.php`, shared with the Site Settings dialog: [[workspace-team-settings]].

## Whose team row may a person change?
- `ws_settings_may_edit_person($viewer_role, $person_role)` (`includes/workspace/people.php`): only a row of the same or a lower rank (Administrator 0 above Designer 1 above Manager 2 above a basic user 3). A higher rank's row is drawn disabled and a posted one is skipped. Before, a Manager or a basic user with the settings right could tick Not in the team on an administrator, who then got Access denied on every Workspace screen, this one included.

## How are hours and holidays checked?
- `ws_settings_minutes()` (`includes/workspace/workdays.php`) returns 0 for empty or 0 (the default), null for text, a negative number or more than the ceiling; a positive value under the floor is raised to it. Defaults and Team members refuse null with "{field}: enter the hours as a number from 0 to N." and save nothing (before, -3 became 5 minutes and "abc" the default).
- `ws_holiday_add()` refuses a last day before the first ("The last day cannot be before the first day."), where it used to save one day.

## What do the department ticks mean?
- Team lead or Own department ticked makes the person a member on save (members ∪ leads ∪ primary, within the team); the page script ticks In the department with them and unticking it clears both.

## Which mailbox fields depend on others?
- Name, address and channel are `required`. Folder to move to shows only with After reading = Move; SMTP server and port only with Send through = the mailbox's sending server (`data-ws-mail-when="name=value"`, `d-none`, a script in `ws_mail_settings_card()`); the connected account is required with Send through = the connected account (its None option is now value "").

Tests: `tests/workspace_settings_input_test.php`, `tests/workspace_settings_lists_test.php`, `tests/workspace_mail_connection_test.php`.
