---
name: security-scan-retired-files
description: Decision (architect 2026-11-25): a PHP file an earlier release carried and this one dropped is told from an unknown file by the Clean Up tool's list; the update still deletes nothing; step SC8b
---

## What was found?
- The test round (SC8, #11633): the update writes the package over the files and deletes nothing, so a PHP file a later release dropped stays on an upgraded site and the file scan reports it as `file_php_unknown` (high).

## What is decided?
- Architect (2026-11-25): the update is not changed. The product already has its way of removing such files: the Clean Up tool in System tools, a person's click, working from `$file_list` in `clean_up.php` (`platform/clean-up-tool-file-list.md`).
- The scan reads the same list. A PHP file whose place is on it is reported as a kind of its own, `file_php_retired` (medium): reason "A file of an earlier version that this version no longer uses."; "What to do" names Clean Up with a link to it; Quarantine works as for any file finding.
- It is reported, never passed over: a file left callable is a risk by itself, and a retired name can also be a planted file. The shell-mark rule keeps reading it as a file outside the reference, so a mark in it is still `file_shell_mark` (high).
- One list: it moves out of `clean_up.php` into a function both read (`includes/fn/files.php`), with its comment; never a second copy.
- The list is made complete once: every PHP file in the integrity scope (the software folder itself and `includes/`) that a release tag carried and the tree no longer has is added, each by the list's own rule (nothing refers to the name; a name in use again never goes on it). A folder gone as a whole is listed as the folder.
- It stays complete: `tools/check_pr.php` refuses a PR that deletes a PHP file in the integrity scope, or renames it away, without its old place joining the list.

## What is it not?
- No list of digests: the release reference holds this version only, and removing a file at a retired place is right whatever is in it.
- Not a change of what the product does today, so nothing was asked of the owner. Whether the update should remove the list's files by itself is a product question for the owner, outside this plan.

## Which step builds it?
- SC8b (S), after #11633 has landed and before SC9: the function, the scan's kind with its texts, the list made complete, the `check_pr.php` rule. Tests: `tests/clean_up_list_test.php` still passes on the moved list; a listed place gives `file_php_retired`, an unlisted one `file_php_unknown`, a listed one with a shell mark `file_shell_mark`.
- Claim as `[plan:security-scan] SC8b — retired release files`; its PR adds the step's line to `plans/security-scan.md` and moves what stays true from here into `security-scan-file-scan.md`.
