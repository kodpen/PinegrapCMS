---
name: lang-files
description: Turkish wording as sorted shards in includes/local/tr/: why, layout, pg_lang_map() the one reader, old tr.json on updated sites, tr_add.php, tr_resolve.php, check_lang.php, speed
---

## Why shards?
- The Turkish wording was one `includes/local/tr.json` (28,674 keys) that every PR appended to, so any two PRs adding keys conflicted: 94 commits in three days, each conflicted PR merged again by hand. Since the conversion (2026-10-25) it is 28 shards, each sorted by key: a new key lands among its alphabetical neighbours, so two PRs adding different keys touch different lines and git merges them.
- Not by module: a key such as "Save" belongs to no module, and appending to a module file conflicts all the same.

## What does a language folder look like?
- `includes/local/<code>/`: `a.json` … `z.json` for keys starting with that letter (either case), `0.json` for a digit, `_.json` for anything else (`{var:1} …`, quotes, non-ASCII). `pg_lang_shard_of()` decides.
- Each shard: a JSON object, keys in `strcmp` order (capitals first), 4-space indent, raw Unicode, slashes and line separators, a final newline, as `pg_lang_file_encode($map)` writes it; `JSON_FORCE_OBJECT` keeps a numeric key ("10001") an object.
- `en.json` (English overrides) stays one small file; a new language may take either form.

## Who reads a language?
- Only `pg_lang_map($code)` (`core.php`): `pg_lang_source()` takes the folder when it holds a `*.json`, else `<code>.json` (a code that is not a plain name builds no path); `pg_lang_read_folder()` merges the shards in name order and skips one that does not decode. Through it: `lang()`, `pg_tr_ui_has_file()` (a folder counts), the install screen's designer texts, the tests (`tests/helpers/language_map.php`). `test_secure_mode.php` loads no functions and repeats the order itself.
- An update writes the package's files and deletes none, so an updated site keeps its old `tr.json` beside the folder. It is harmless (the folder wins, so it is never read) and Cleanup (`clean_up.php`) keeps it: a pre-update backup holds only the files the package overwrote, so a rollback to a version before the shards would find no `tr.json` and speak English.

## How do I add a key?
- `php tools/tr_add.php "English" "Türkçe" [...]` or `--from=<file.json>`; `--lang=<code>`; never by hand. Each key goes into its shard at its sorted place. Same value already there: skipped; another value: refused unless `--replace`. `--normalize` rewrites a language in its exact form. Files are written through a temporary file and `rename()` (`pg_lang_write_file()`).

## How do I settle a conflict in a language file?
- `php tools/tr_resolve.php` (merging `development` into a branch) or `--development=ours` (a branch into `development` or a train): each conflicted shard becomes development's plus the branch's own edits (`pg_lang_merge3()`; the branch's value wins when both changed a key), written and staged.
- The old `tr.json` still edited by one side (modify/delete): the same command puts that side's edits since the merge base onto the shards, stages them and removes the file, never writing it again; `--legacy=<ref>` does so outside a merge. A shard one side deleted, or a git command that fails: SKIPPED, exit 1.
- Git runs through `proc_open()`, no cmd.exe, no redirection: Windows works too. `.gitattributes` keeps the shards LF on every checkout: check_lang compares bytes.

## What does check_lang.php check?
- Each shard against `pg_lang_shard_problems()` (an object of strings, each key in its own shard, the exact form, so no key twice), duplicates with lines (`pg_json_duplicate_keys()`), the keys through `pg_lang_read_folder()`. A folder and a `tr.json` side by side fail.

## Is reading 28 files slower?
- Measured at the conversion (PHP 8.4 64-bit, Windows, 100 runs): about 20 ms for the folder against 16 ms for the single file, mostly the 28 file opens. No cache was added for it; a compiled cache for either form is step 5 of [[merge-flow-tools]].

Related: docs/memory/core/translation-rules.md
