---
name: security-scan-retired-files
description: Security Scan retired release files - file_php_retired from the Cleanup tool's shared list, the list completed from the release tags, the check_pr.php rule
module: security_scan_files
---

## Why does the scan know retired files?
- The update writes the package over the files and deletes none, so a PHP file a later release dropped stays on an upgraded site. Before SC8b the scan reported it as `file_php_unknown` (high), found by the test round (#11633).
- Architect (2026-11-25): the update is not changed. Clean Up in System tools (a person's click) removes such files, and the scan reads the same list. Whether the update should remove them by itself is a product question for the owner, outside the security-scan plan.

## How is a retired file reported?
- A PHP file the scan would call unknown, at a place on the list, is `file_php_retired` (medium), #11672. Reason "A file of an earlier version that this version no longer uses."; "What to do" names Clean Up, and `pg_security_rule_link()` adds an "Open Clean Up" link; Quarantine works as for any file finding.
- It is reported, never passed over: a file left callable is a risk by itself, and a retired name can also be a planted file.
- Its content is still read for shell marks as a file outside the reference: a mark gives `file_shell_mark` (critical) beside it.
- Without a release reference root and `includes/` files are not judged, so a finished run does not mark it gone.

## Where is the list?
- `pg_clean_up_file_list()` in `includes/fn/files.php`, with its comment; `clean_up.php` and the scan both read it, never a second copy. `pg_clean_up_place_listed()` matches a listed place or a path inside a listed folder (`includes/phpexcel2/` is not inside `includes/phpexcel`).
- `tests/clean_up_list_test.php` asserts no listed place exists in the tree: a name in use again comes off the list (`platform/clean-up-tool-file-list.md`).
- Completed once from the release tags v2026.4.4 to v2026.4.10: every PHP file of the integrity scope a tag carried and the tree lacks is listed, a folder gone as a whole as the folder. The only one missing was `includes/templates/shipping_method_service.php`.

## What keeps it complete?
- `tools/check_pr.php` (`check_pr_retired_problems()`) refuses a branch that deletes, or renames away, a PHP file directly in `pinegrap/` or under `pinegrap/includes/` unless its old place is on the list at the branch's head. Merge trains, `development` and `main` skip it, as the other branch checks.
- No list of digests: the release reference holds this version only, and removing a file at a retired place is right whatever is in it.
