---
name: tr-json-split
description: Plan (2026-10-25, step 1 done #1632; 2 conversion, 3 rules open): split includes/local/tr.json into sorted shards so PRs adding keys stop conflicting; tools, loader, readers, check_lang
---

## Why?
- `pinegrap/includes/local/tr.json` holds about 28,300 keys in one object and every PR appends at its end, so any two PRs that add keys conflict (the last key's comma changes; both insert at the same place). 94 commits touched it in three days (2026-10-22..25). A conflicted PR gets no CI and has to be merged again by hand.

## What was decided (2026-10-25)?
- Split the file. Design within that (2026-10-25): shard by the key's first character and keep each shard sorted. Sorting is what stops the conflicts: a new key lands among its alphabetical neighbours, so two PRs adding different keys touch different lines and git merges them. Shards keep each file small. Module shards were rejected: a key such as "Save" belongs to no module, and appending to a module file still conflicts.

## What is the target layout?
- `pinegrap/includes/local/tr/` with `a.json` … `z.json` (first character, case-folded), `0.json` (digits) and `_.json` (anything else: punctuation, braces, non-ASCII). Each one is a JSON object, keys in `strcmp` order, one key per line, 4-space indent, `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`, final newline. `en.json` stays one small file.

## What are the steps (one PR each, in order)?
1. Done (#1632; `core/lang-files.md`): tools and loader, no data moved. `includes/fn/lang_files.php`; `lang()` reads `includes/local/<lang>/*.json` when that folder exists, else `<lang>.json`; `tools/tr_add.php "English" "Türkçe"` (or `--from=<file.json>`, `--replace`); `tools/tr_resolve.php` (`--development=ours`, `--legacy=<ref>`); `check_lang.php` validates shards; tests in `tests/lang_files_test.php`.
2. Conversion, merged alone in an empty merge window: generate the shards from `tr.json`, delete `tr.json`, switch every reader (`git grep -n "includes/local/"`: `install/index.php` ~329 designer texts, `includes/fn/translate.php` ~620 language-file check, `test_secure_mode.php` ~67, `tools/check_lang.php`, and wherever the designer's `_sdT()` texts are read). The merging session then runs `php tools/tr_resolve.php --legacy=<branch>` for each open PR that added keys. The update must drop the old file or ignore it; the loader prefers the folder, so a leftover file is harmless (check the release and file-integrity memory for deleted-file lists).
3. Rules: `CLAUDE.md` fixed rule 3, `core/translation-rules.md` and `workflow/git-branches-and-shared-files.md` say "add keys with `php tools/tr_add.php`, never by hand", and take `tr.json` off the shared-files list.

## What is still open?
- Speed: reading ~28 files instead of one per request. Measure with the conversion (step 2); add a compiled PHP array cache in `data/temp/` only if it is measurably slower.
- Whether `en.json` and future language files follow the same layout (the loader handles both forms).

## Where to start?
- Step 2 (the conversion), merged alone in an empty merge window and agreed with the merging session first; then step 3. A draft PR titled `[plan:tr-json-split] 2 — convert tr.json to shards` claims it; read `core/lang-files.md`, then follow the working mode (`workflow/working-mode.md`).
