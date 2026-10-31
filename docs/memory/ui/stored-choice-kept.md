---
name: stored-choice-kept
description: Selects that drop the stored choice add it back selected and marked (product private folder, Workspace template, select_page(), calendar Next Page, disabled offers)
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

## How do the page and offer pickers keep a stored choice?
- `select_page()` (includes/fn/forms.php, about 50 pickers: User Start Page, Edit Page next and confirmation pages, commerce and security settings pages, offer upsell) left out every page in an archived folder, the Recycle Bin included, the selected one too, so the select opened blank and a plain Save wrote ''. It now lets only the selected page past the archived rule and marks it as `get_page_options()` does (`pg_page_option_status()`: " [ARCHIVED]", or the unparked name plus " [IN RECYCLE BIN]"). The role-3 folder filter still applies to it: a posted id on a refused Edit Page save would otherwise show page names the user cannot edit. Pages in folders sent to the bin stay listed, as `get_page_options()`'s default does.
- Edit Calendar Event passes the stored `next_page_id` (read from `calendar_events` on every draw, never from the post) to `pg_calendar_event_form_cards(..., $keep_next_page_id)`, which hands it to `get_page_options()`; before, a stored next page in an archived folder, or one a role-3 editor cannot edit, refused the save with "Next Page is required." while reservations were on.
- `select_offer()` (Edit Page Grant Offer, Affiliate Group Offer) marks an offer whose status is not 'enabled' " [DISABLED]": key codes and grants for it never apply.
- Tests: `tests/page_pickers_keep_test.php`.
