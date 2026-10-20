---
name: security-findings-2026-10-api
description: Security findings of the 2026-10-20 external API scan: the forms endpoints skip the folder rule for role 3, page and folder moves between private folders pass the widening check; one PR each
---

## Where does this come from?
- Bug scan of `development` @ `d9b1582` (2026-10-20, #706) over `includes/api/` core and resources, verified on a sandbox (PHP 8.3, MariaDB 10.11). Kept out of public issues (SECURITY.md) and recorded here until fixed. The public findings of the same scan: #855, #865, #867, #871, #874–#877. Check `pinegrap-verilmis-kararlar` first; neither item is on it.

## What is still open? (one PR each)
1. **Forms endpoints skip the folder rule for role 3** (medium, CWE-863). The panel opens a form and its submissions to a User (role 3) with the forms right only when `check_edit_access()` passes for the form page's folder (`view_submitted_forms.php` ~70, ~540; `edit_submitted_form.php` ~92, ~356). `api_owner_scopes()` grants that account `forms:read` and `forms:write` (`includes/api/scopes.php` ~305), but `api_forms_list()`, `api_forms_get()`, `api_form_submissions()`, `api_form_submission_get()` and `api_form_submission_update()` (`includes/api/resources/forms.php`) never look at the owner's folders: every form, every submission's values and the submission write are reachable. Reached with the account's own password too (`POST /auth/login`, a device session) or an OAuth connection, no other key needed. Fix: a folder filter like `contact_groups.php` ~28 / `campaigns.php` ~26 (`page.page_folder` in the owner's editable folders, `pg_folder_edit_access()` as `files.php` and `pages.php` already use); an unreachable form answers 404. Same pattern as the customers filter noted by the earlier scan.
2. **Moves between private folders pass the widening check** (low). `api_folder_access_widens()` (`includes/api/resources/folders.php` ~124) ranks only the access type, so `POST /pages/{id}` `folder_id` (`pages.php` ~652) and `POST /folders/{id}` `parent_id` (`folders.php` ~647) accept a private-to-private move although who may read a private folder is its own `aclfolder` list; a page from a narrow folder lands in one more people can open. The owner can make the same move on the page screen, so no escalation, but the endpoint's promise ("the API keeps or narrows access, never widens it") does not hold for private folders. Fix: when both effective types are private, accept only if the target's effective ACL is a subset of the source's; otherwise 409 `access_widening_refused`.

## What was verified and what was not?
- Both reproduced on the sandbox with a role-3 account (item 1: five forms and a submission's values read without any `aclfolder` row; item 2: a page moved between two private folders with different lists). The panel's own screens were read, not run.

## Where to start?
- Item 1 first: it is a read of personal data and a one-function fix. Item 2 after, or as an owner question: is the private-folder ACL part of the widening rule, or is private-to-private an accepted move?
