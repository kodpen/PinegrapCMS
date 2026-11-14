---
name: personal-calendar-census-privacy
description: PC1 census (2026-11-14): what personal data export and erasure, account deletion and leaving the team do today to a person's Workspace calendar data, and what each must do with private plans
---

## What do export and erasure read today?
- Export (`pg_privacy_export_collect()`, section `workspace`): a contact's customer channels, approvals asked by e-mail and workflow-table rows naming them. Nothing a staff member wrote for themselves (tasks, messages, own iCal) is exported.
- Erasure (`pg_privacy_erase_tables()`): a visit naming the contact keeps its row, `contact_id` set to 0. `pg_contact_holders()` lists `ws_events.contact_id` (merge move, erase find).
- Nobody exports a higher or equal account, and nobody erases their own account (`core/personal-data-export.md`, `core/personal-data-erasure.md`).

## What is proposed for plans?
- A person's own plans (title, days, note, steps, who it is shared with) go into their own export under `workspace` as `record` `personal_plan`: it is data about them that only they wrote. Erasing a user deletes them (see below).
- A plan naming a customer (PC6): exported to that customer by title and days only (the note is the staff member's), `contact_id` set to 0 on erasure as a visit is, and `ws_personal_plans.contact_id` added to `pg_contact_holders()` (merge move, erase find); its drift test fails until it is.
- Both are decisions about personal data: asked with the tables.

## What happens when an account is deleted?
- `pg_user_delete()` (`includes/fn/user_write.php`) deletes what is only the account's own (tokens, address book, `ws_saved_replies`...). PC3 adds its plans, their steps, their `ws_personal_plan_people` rows and the account's rows as a person a plan is shared with, and the inbox lines naming them.

## What happens when an account leaves the team?
- Leaving is losing the team role (`ws_is_team_member()`, `ws_team_ids()`); the account stays. Today: the own iCal rows are deleted on the next read (`ws_personal_ical_sync()`), the Google/Outlook writer's entry is deleted (`ws_calsync_tick()`), a disabled account's iCal subscription answers 404 (`core/user-disabled.md`).
- Proposed: plans are kept but read by nobody (the gate asks `ws_is_team_member()` for the owner and the reader); reminders are not sent; coming back brings them back. A plan shared with someone who left is no longer drawn for them. Deleting the account deletes them.
- No administrator takes over or reads a leaver's plans (plan rule: closed holds for an administrator too).

## What about a guest?
- A guest has no calendar: `ws_personal_plan_can_see()` refuses anyone outside the team, and a guest is never offered in "Chosen team members".
