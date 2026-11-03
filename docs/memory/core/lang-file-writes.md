---
name: lang-file-writes
description: Language file writes refuse text that is not UTF-8 instead of emptying a shard
module: lang_files
---

## What happens to a value that is not UTF-8?
- `pg_lang_file_encode()` returns false (json_encode() has no text for it) and `pg_lang_write_file()` writes nothing for a non-string, so `tr_add.php`, `tr_resolve.php` and the cache never write a broken file (#6071).
- `tools/tr_add.php` stops first with "Not UTF-8 text: <key>".

## What did it do before?
- json_encode() returned false and the encoder wrote `"\n"`: one Latin-1 value typed in a Windows console emptied its letter's shard, every key starting with that letter fell back to English.
