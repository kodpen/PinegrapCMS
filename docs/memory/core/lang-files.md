---
name: lang-files
description: Language files as sorted shards (includes/local/<code>/a.json … _.json), lang()'s folder-or-file read, tools/tr_add.php to add keys, tools/tr_resolve.php to settle conflicts, check_lang.php on shards
---

## Why shards?
- `includes/local/tr.json` is one object that every PR appends to, so any two PRs adding keys conflict (plan [[tr-json-split]]). Kept as shards sorted by key, a new key lands among its alphabetical neighbours: two PRs adding different keys touch different lines and git merges them.

## What does a language folder look like?
- `includes/local/<code>/`: `a.json` … `z.json` for keys starting with that letter (either case), `0.json` for a digit, `_.json` for anything else (`{var:1} …`, quotes, non-ASCII). `pg_lang_shard_of()` decides.
- Each shard is a JSON object, keys in `strcmp` order (capitals first), 4-space indent, raw Unicode, slashes and line separators, a final newline: `pg_lang_file_encode($map)` writes exactly that; `JSON_FORCE_OBJECT` keeps a numeric key ("10001") an object.

## How does lang() read a language?
- `pg_lang_read_folder(PG_FUNCTIONS_DIR . '/includes/local/<code>')` (in `core.php`, so it exists before any other module loads): every `*.json` there in name order, merged; a shard that does not decode is skipped. With no such folder (or an empty one) `lang()` reads `<code>.json` as before. Until the conversion (plan step 2) every language is still one file.

## How do I add a key?
- `php tools/tr_add.php "English" "Türkçe" ["English" "Türkçe" …]`, or `--from=<file.json>`; `--lang=<code>` for another language. With a folder the key goes into its shard at its sorted place; with the single file it goes at the end and every other line stays (the file is in `pg_lang_file_encode($map, false)` form, pinned by `test_lang_files_single_file_round_trip`). A key already there with the same value is skipped; with another value it is refused unless `--replace`. `--normalize` rewrites the language in its exact form.
- Files are written through a temporary file and `rename()` (`pg_lang_write_file()`).

## How do I settle a conflict in a language file?
- During the merge: `php tools/tr_resolve.php` (merging `development` into a branch) or `--development=ours` (merging a branch into `development` or a merge train). It takes git's three versions of each conflicted `includes/local/` language file, starts from development's and adds the branch's own edits (added keys, changed values, removed keys; the branch's value wins when both changed one key) with `pg_lang_merge3()`, writes it (a shard sorted; the single file in development's order, the branch's keys at the end) and stages it.
- After the conversion, `php tools/tr_resolve.php --legacy=<branch>` re-applies what a branch changed in the old single file onto the shards.

## What does check_lang.php check on shards?
- When `includes/local/tr/` exists: each shard against `pg_lang_shard_problems()` (a JSON object of strings, every key in its own shard, the exact form, so also no key twice), duplicates with lines (`pg_json_duplicate_keys()`), keys read through `pg_lang_read_folder()`. A folder and a `tr.json` side by side fail: `lang()` reads the folder, so the file would be stale.

Related: docs/memory/core/translation-rules.md, docs/memory/plans/tr-json-split.md
