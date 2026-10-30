---
name: stored-choice-kept
description: Selects whose list leaves out the stored choice add it back selected and marked (Edit Product private folder, Workspace template department and assignees)
---

## What was the symptom?
- Edit Product: the "Set View Access to Folder" picker passed 0 to `select_folder()`, so it always opened on "-Select Folder-" and every Save wrote `products.private_folder` = 0; with Grant Private Access on, buyers got an access row for folder 0 (access to nothing).
- Workspace template editor (`workspace_template.php`): the Department select and the task "Assign to" lists offered only active departments and team members, so a template whose department was archived, or a task given to an archived department or a person who left, opened on "No department" / "creator" and was saved that way.

## What do the screens do now?
- `product_builder.php` passes the stored folder to `select_folder()`. That function still drops the selected folder when its access is no longer Private or it sits in the Recycle Bin, so `pg_pb_kept_option()` appends it selected with its name and "(no longer offered)".
- `workspace_template.php` reads `ws_departments(false, true)` and `ws_template_kept_choices()` (includes/workspace/templates.php): an archived department is listed, marked "(archived)", only when the template or a task's `department:ID` holds it; a person a task's `user:ID` names who is not in `ws_team_members()` is added to the people list marked "(no longer in the team)". After a refused Save it reads the posted task JSON.
- Stored rows are never rewritten; `select_folder()` and `workspace_templates.js` are unchanged.

## Where are the tests?
- `tests/stored_choice_kept_test.php`.
