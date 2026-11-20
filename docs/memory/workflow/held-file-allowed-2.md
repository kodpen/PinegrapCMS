---
name: held-file-allowed-2
description: Held files, what the architect allowed from 2026-11-20 on: auth.php, image_editor.php and custom_form.php beside their holders; one call line in a root script's top-level code
---

## Where is the rule and the earlier list?
- `held-file-registry-rows.md`: a registry's own rows, or a function the holder's hunks are not in, after the architect's word in the agent channel. Its "What was allowed?" holds the cases up to 2026-11-17.

## What was added to the rule? (architect, 2026-11-21, channel messages 11218 and 11221)
- A root script with top-level code and no functions, such as `custom_form.php`: one call line of the step's own function, far from every holder's hunk, counts as a function the holder does not touch. More than that one line waits for the holder to land.
- A holder about to land (ready and named for the next train) is waited for, not passed: the line goes in after it has landed.
- A step that entered a held file without the word is told so; when its hunks are apart from the holder's the architect may allow it after the fact, and it goes ready only after the holder has landed.

## What was allowed?
- Beside SC5 #10868 on `includes/fn/auth.php` (channel message 11161): PP1b #10873 changes only `validate_user()`, which starts near line 2,483. #10868's hunks are in `pg_member_register()` near 653 and `pg_member_activate()` near 938. No second PR enters the file beside #10868.
- Beside CS4 #10858 and CS1 #10857 on `includes/fn/image_editor.php` (channel message 11181): DV2 #10892 changes only the rows of `pg_image_editor_strings_data()`. Its two actions go into the new `includes/fn/image_editor_data.php`, and nothing is appended at the file's end, where CS4 writes.
  - The holders' hunks: CS1 in `pg_image_editor_strings_keyframes()` and `pg_image_editor_strings_record()`; CS4 in `pg_image_editor_strings_core()`, `pg_image_editor_config()` and new functions at the end.
  - DV2 checks `git merge-tree` against CS4's newest head when it goes ready. The next step that needs the file waits until DV2 or CS4 has landed.
- On `custom_form.php` (channel messages 11218 and 11221), three PRs with hunks apart:
  - SC5 #10868 holds it, hunks near 1,128 and 1,742.
  - AR2 #10880 entered it without the word, hunks near 198 and 1,013 (the period check of the submit path): allowed after the fact. Its body names #10868 and it goes ready only after #10868 has landed.
  - LJ1b #10882 adds one line, `pg_lead_tie('form', …)`, after the `UPDATE forms SET contact_id` statement near 1,432, once #10868 has landed. Its body names #10880 and `git merge-tree` is clean with that head.
- On `includes/fn/contacts.php`: the rows of `pg_contact_holders()` are registry rows. LJ1b #10882 keeps its four rows after `ws_personal_plans`, apart from the row FD1b #10877 and RY1b #10878 each add at the list's end.

## What was refused?
- DX2 #10885 moving the New application sheet's inline rules out of `api_settings.php` while AA3 #10884 reworks that file (channel message 11221). DX2 writes the recipe into `backend.src.css` and DX3, after AA3, moves the sheet to it.

Related: docs/memory/workflow/held-file-registry-rows.md, docs/memory/workflow/held-file-bundled-sources.md
