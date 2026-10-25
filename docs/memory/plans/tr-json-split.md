---
name: tr-json-split
description: Plan (2026-10-25, not started): split includes/local/tr.json into sorted shard files so two PRs adding keys stop conflicting; tools to add and resolve keys; loader, readers and check_lang
---

## Why?
- `pinegrap/includes/local/tr.json` holds about 28,300 keys in one object and every PR appends at its end, so any two PRs that add keys conflict (the last key's comma changes; both insert at the same place). 94 commits touched it in three days (2026-10-22..25). A conflicted PR gets no CI and has to be merged again by hand.

## What did the owner decide (2026-10-25)?
- Split the file. Design within that (2026-10-25): shard by the key's first character and keep each shard sorted. Sorting is what stops the conflicts: a new key lands among its alphabetical neighbours, so two PRs adding different keys touch different lines and git merges them. Shards keep each file small. Module shards were rejected: a key such as "Save" belongs to no module, and appending to a module file still conflicts.

## What is the target layout?
- `pinegrap/includes/local/tr/` with `a.json` … `z.json` (first character, case-folded), `0.json` (digits) and `_.json` (anything else: punctuation, braces, non-ASCII). Each one is a JSON object, keys in `strcmp` order, one key per line, 4-space indent, `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`, final newline. `en.json` stays one small file.

## What are the steps (one PR each, in order)?
1. Tools and loader, no data moved. A new `includes/fn/lang_files.php` with pure functions: the shard of a key, sorted insert, the exact file format, the union of two sides. `lang()` (`includes/fn/core.php` ~4030) reads `includes/local/<lang>/*.json` in name order when that folder exists, else `<lang>.json` as today. New `tools/tr_add.php "English" "Türkçe"` (or `--from <file.json>`): writes each key into its shard at its sorted place, refuses a key that exists with another value unless `--replace`. New `tools/tr_resolve.php`: settles conflicted shards (keys of both sides; a key the PR changed from the merge base takes the PR's value; re-sorted), with `--legacy <ref>` re-applying the keys a branch added to the old `tr.json` onto the shards. `check_lang.php` validates shards when the folder exists: format, order, right shard, no key twice. Tests in `tests/lang_files_test.php`.
2. Conversion, merged alone in an empty merge window: generate the shards from `tr.json`, delete `tr.json`, switch every reader (`git grep -n "includes/local/"`: `install/index.php` ~329 designer texts, `includes/fn/translate.php` ~620 language-file check, `test_secure_mode.php` ~67, `tools/check_lang.php`, and wherever the designer's `_sdT()` texts are read). The merging session then runs `tools/tr_resolve.php --legacy` for each open PR that added keys. The update must drop the old file or ignore it; the loader prefers the folder, so a leftover file is harmless (check the release and file-integrity memory for deleted-file lists).
3. Rules: `CLAUDE.md` fixed rule 3, `core/translation-rules.md` and `workflow/git-branches-and-shared-files.md` say "add keys with `php tools/tr_add.php`, never by hand", and take `tr.json` off the shared-files list.

## What is still open?
- Speed: reading ~28 files instead of one per request. Measure in step 1; add a compiled PHP array cache in `data/temp/` only if it is measurably slower.
- Whether `en.json` and future language files follow the same layout (the loader handles both forms).

## Where to start?
- Step 1. Claim it as `[plan:tr-json-split] 1 — sharded language files: tools and loader`, then follow the working mode (`workflow/working-mode.md`): one PR, light checks.
