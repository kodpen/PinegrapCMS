---
name: workspace-personal-plan-sharing
description: Who can see a person's plan (PC4): Only me, chosen team members, the whole team; what the others see and may not do
---

## How is a plan shared?
- "Who can see" on the drawer: Only me (`visibility` `me`, the default), Chosen team members (`people`, rows in `ws_personal_plan_people`; the owner and anyone off the team are dropped on save) or The whole team (`team`). The note above the title says who sees it.
- The others read it on their My calendar and Today as "Plan of <owner>" ("admin kişisinin planı"), in a read-only drawer ("<owner> shared this plan with you. Only they can change it."), without the owner's reminder or the list of the others. Save and delete refuse them (only the owner may change it: `ws_personal_plan_can_edit()`).
- Only the owner is reminded, so taking a share away leaves no inbox line to remove.

## What keeps it closed?
- The gate `ws_personal_plan_can_see()` alone opens it (`workspace-personal-plans.md`); an unknown `visibility` is closed. A guest is never offered (the picker lists the team, `ws_team_members()`), and a chosen member who leaves the team reads it no more.

Test: `tests/workspace_personal_plans_test.php`.
