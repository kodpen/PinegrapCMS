---
name: security-findings-2026-10-api
description: Security findings of the 2026-10-20 external API scan: the forms endpoints' role-3 folder rule done (PR #933); left: page and folder moves between private folders pass the widening check
---

## Where does this come from?
- Bug scan of `development` @ `d9b1582` (2026-10-20, #706) over `includes/api/` core and resources, verified on a sandbox (PHP 8.3, MariaDB 10.11). Kept out of public issues (SECURITY.md) and recorded here until fixed. The public findings of the same scan: #855, #865, #867, #871, #874–#877. Check `pinegrap-verilmis-kararlar` first; neither item is on it.

## What was fixed?
1. **Forms endpoints skipped the folder rule for role 3** (medium, CWE-863) — done, PR #933. A User (role 3) with the forms right got `forms:read`/`forms:write` and every form, every submission's values and the submission write, while the panel opens only forms on pages in folders it may edit. All five forms handlers now apply that rule (`api_form_owner_filter()`, `get_folders_that_user_has_access_to()`), a form outside answers 404. Rule and test: `core/external-api-authorization.md`.

## What is still open?
2. **Moves between private folders pass the widening check** (low). `api_folder_access_widens()` (`includes/api/resources/folders.php` ~124) ranks only the access type, so `POST /pages/{id}` `folder_id` (`pages.php` ~652) and `POST /folders/{id}` `parent_id` (`folders.php` ~647) accept a private-to-private move although who may read a private folder is its own `aclfolder` list; a page from a narrow folder lands in one more people can open. The owner can make the same move on the page screen, so no escalation, but the endpoint's promise ("the API keeps or narrows access, never widens it") does not hold for private folders. Fix: when both effective types are private, accept only if the target's effective ACL is a subset of the source's; otherwise 409 `access_widening_refused`.

## What was verified and what was not?
- Both reproduced on the sandbox with a role-3 account (item 1: five forms and a submission's values read without any `aclfolder` row; item 2: a page moved between two private folders with different lists). The panel's own screens were read, not run.
- Item 1's fix on the sandbox: a role-3 account with edit rights on one folder lists 3 of 18 forms; get, submissions, one submission and the write (dry run and real) answer 404 outside it; rights on a parent folder reach its subfolders; a view-only row and no row reach nothing; the panel's submitted-form screens show and refuse the same records; the administrator still lists all 18.

## Where to start?
- Item 2, or as an owner question first: is the private-folder ACL part of the widening rule, or is private-to-private an accepted move?
