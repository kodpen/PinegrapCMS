---
name: workspace-personal-plan-export
description: A contact's data export lists the person's own plans naming them only when shared with the whole team (PC6b, owner 3361): title, days, owner, roadmap steps
---

## What does a contact's data export list of plans?
- Owner (2026-11-14, channel 3361): only the plans that name the contact and are shared with "The whole team", whoever runs the export. A plan kept "Only me" or for chosen team members is never listed.
- Section Workspace of `pg_privacy_export_collect()`, rows of record `plan`: id, title, starts and ends (the day for an all-day plan, else day and time; the last day, not the midnight after), owner (the user's contact name, else the username), steps ("Title (day); Title").
- Left out: the note, the reminder, the people it is shared with, a step's done mark and its task.

## Where is the rule kept?
- `ws_personal_plans_export()` in `includes/workspace/personal_plan_contact.php` asks `visibility = 'team'` in its query and again for every row; `ws_personal_plan_export_row()` is pure. The export loads the three plan files and reads plans through this function alone.
- `tests/workspace_personal_plan_contact_test.php` holds the rule and the row.

## And the plan's owner who left the team?
- The rule is the plan's sharing alone: a whole-team plan naming the contact is listed even when its owner is off the team, as the contact's data it holds is still on the site.
